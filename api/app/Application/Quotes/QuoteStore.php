<?php

declare(strict_types=1);

namespace App\Application\Quotes;

use Illuminate\Support\Facades\DB;
use App\Domain\Quotes\{Currency, Money};

final class QuoteStore
{
    public function create(PreparedQuote $quote): array
    {
        return DB::transaction(fn () => $this->insert($quote));
    }

    /** @param list<PreparedQuote> $quotes */
    public function createBatch(array $quotes): array
    {
        return DB::transaction(function () use ($quotes) {
            $summaries = [];
            foreach ($quotes as $quote) {
                $snapshot = $this->insert($quote);
                $summaries[] = array_intersect_key($snapshot, array_flip(['id', 'budget_id', 'currency', 'total']));
            }

            return $summaries;
        });
    }

    private function insert(PreparedQuote $quote): array
    {
        $s = $quote->snapshot;
        $id = DB::table('quotes')->insertGetId([
            'budget_id' => $s['budget_id'], 'client' => $s['client'], 'client_search' => mb_strtolower($s['client']),
            'event' => $s['event'], 'event_lines' => json_encode($s['event_lines'], JSON_THROW_ON_ERROR),
            'currency' => $s['currency'], 'subtotal' => $s['subtotal'], 'total' => $s['total'],
            'total_minor' => (string) Money::of($s['total'], Currency::from($s['currency']))->minor(),
            'exchange_rates' => json_encode((object) $s['exchange_rates'], JSON_THROW_ON_ERROR),
            'input_json' => $quote->inputJson, 'result_json' => json_encode($s, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'engine_version' => 'v1', 'created_at' => now(),
        ]);

        return ['id' => (int) $id] + $s;
    }

    public function find(int $id): ?array
    {
        $row = DB::table('quotes')->select('id', 'result_json')->where('id', $id)->first();

        return $row === null ? null : ['id' => (int) $row->id] + json_decode($row->result_json, true, 64, JSON_THROW_ON_ERROR);
    }
}
