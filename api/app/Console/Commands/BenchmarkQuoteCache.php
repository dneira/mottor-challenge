<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Quotes\{PrepareQuote, QuoteReader, QuoteStore};
use App\Support\ExactJson;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, DB};

/** Local sequential benchmark; inserts and removes only its own synthetic quote. */
final class BenchmarkQuoteCache extends Command
{
    protected $signature = 'quotes:benchmark-cache {--iterations=200}';
    protected $description = 'Compare detail SQL reads with Redis misses/hits; clean up the synthetic record afterwards';

    public function handle(PrepareQuote $prepare, QuoteStore $store, QuoteReader $reader): int
    {
        $iterations = filter_var($this->option('iterations'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 10, 'max_range' => 10000]]);
        if ($iterations === false) {
            $this->error('iterations debe ser un entero entre 10 y 10000.');
            return 1;
        }
        $cache = Cache::store('quote_redis');
        $cache->get('quotes:benchmark:connection-check'); // Fail before writing data if Redis is unavailable.
        $input = (new ExactJson())->decode(file_get_contents(base_path('../fixtures/official.input.json')));
        $budgetId = 'BENCH-'.bin2hex(random_bytes(12));
        $input->budget->id = $budgetId;
        $quote = $store->create($prepare->prepare($input));
        $key = $reader->key($quote['id']);
        $queries = 0;
        DB::listen(function () use (&$queries) { $queries++; });
        $report = ['iterations_per_mode' => $iterations, 'database' => DB::connection()->getDriverName(),
            'snapshot_bytes' => strlen(json_encode($quote)), 'scope' => 'Sequential local snapshot reads; excludes HTTP and serialization', 'modes' => []];
        $oldEnabled = config('quotes.cache_enabled');
        $oldStore = config('quotes.cache_store');
        try {
            foreach (['sql', 'redis_miss', 'redis_hit'] as $mode) {
                config(['quotes.cache_enabled' => $mode !== 'sql', 'quotes.cache_store' => 'quote_redis']);
                for ($i = 0; $i < 10; $i++) {
                    $reader->find($quote['id']);
                }
                $samples = [];
                $queries = 0;
                for ($i = 0; $i < $iterations; $i++) {
                    if ($mode === 'redis_miss') {
                        $cache->forget($key); // Miss setup is excluded from the timed read.
                    }
                    $start = hrtime(true);
                    $actual = $reader->find($quote['id']);
                    $samples[] = (hrtime(true) - $start) / 1_000_000;
                    if ($actual !== $quote) {
                        throw new \RuntimeException('La lectura no conserva el snapshot original.');
                    }
                }
                sort($samples);
                $report['modes'][$mode] = [
                    'p50_ms' => round($samples[(int) ceil($iterations * .5) - 1], 4),
                    'p95_ms' => round($samples[(int) ceil($iterations * .95) - 1], 4),
                    'sql_queries' => $queries,
                ];
                $expectedQueries = $mode === 'redis_hit' ? 0 : $iterations;
                if ($queries !== $expectedQueries) {
                    throw new \RuntimeException('La cantidad de consultas no coincide con el modo; Redis pudo fallar.');
                }
            }
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } finally {
            config(['quotes.cache_enabled' => $oldEnabled, 'quotes.cache_store' => $oldStore]);
            try {
                $cache->forget($key);
            } finally {
                DB::table('quotes')->where('id', $quote['id'])->where('budget_id', $budgetId)->delete();
            }
        }

        return 0;
    }
}
