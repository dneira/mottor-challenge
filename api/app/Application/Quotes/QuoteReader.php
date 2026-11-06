<?php

declare(strict_types=1);

namespace App\Application\Quotes;

use Illuminate\Support\Facades\{Cache, DB, Log};
use Throwable;

final class QuoteReader
{
    public function __construct(private QuoteStore $store)
    {
    }

    public function find(int $id): ?array
    {
        if (!config('quotes.cache_enabled') || DB::transactionLevel() > 0) {
            return $this->store->find($id);
        }
        try {
            $cache = Cache::store(config('quotes.cache_store'));
            $snapshot = $cache->get($this->key($id));
            if (is_array($snapshot)) {
                return $snapshot;
            }
        } catch (Throwable $exception) {
            Log::warning('Quote cache read unavailable; using SQL.');
            return $this->store->find($id);
        }
        $snapshot = $this->store->find($id);
        if ($snapshot !== null) {
            try {
                $cache->put($this->key($id), $snapshot, config('quotes.cache_ttl'));
            } catch (Throwable $exception) {
                Log::warning('Quote cache write unavailable; SQL result retained.');
            }
        }

        return $snapshot;
    }

    public function key(int $id): string
    {
        $connection = config('database.default');
        $identity = json_encode([base_path(), $connection, config("database.connections.{$connection}.host"), config("database.connections.{$connection}.port"), config("database.connections.{$connection}.database")]);

        return 'quotes:detail:v1:'.hash('sha256', $identity).':'.$id;
    }
}
