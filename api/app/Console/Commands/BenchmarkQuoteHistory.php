<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Quotes\{ImportQuotes, ListQuoteOptions, ListQuotes};
use App\Support\ExactJson;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class BenchmarkQuoteHistory extends Command
{
    protected $signature = 'quotes:benchmark-history {--quotes=10000} {--samples=25}';
    protected $description = 'Import synthetic history in batches, measure two-query listing, then remove only generated records';

    public function handle(ImportQuotes $import, ListQuotes $list): int
    {
        $count = filter_var($this->option('quotes'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 100, 'max_range' => 10000]]);
        $samples = filter_var($this->option('samples'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 5, 'max_range' => 100]]);
        if ($count === false || $samples === false) {
            $this->error('quotes: 100..10000; samples: 5..100.');
            return 1;
        }
        $template = (new ExactJson())->decode(file_get_contents(base_path('../fixtures/official.input.json')));
        $marker = 'HISTORY-'.bin2hex(random_bytes(12));
        $report = ['synthetic' => true, 'quotes' => $count, 'batch_size' => 500, 'samples_per_listing' => $samples,
            'database' => DB::connection()->getDriverName(), 'scope' => 'Application services with real SQL; excludes HTTP/network and browser', 'listings' => []];
        $started = hrtime(true);
        try {
            for ($offset = 0; $offset < $count; $offset += 500) {
                $quotes = [];
                for ($i = $offset; $i < min($count, $offset + 500); $i++) {
                    $quote = clone $template;
                    $quote->budget = clone $template->budget;
                    $quote->budget->id = $marker.'-'.$i;
                    $quote->budget->client = $marker.($i % 2 === 0 ? ' Norte' : ' Sur');
                    $quotes[] = $quote;
                }
                $result = $import->import((object) ['quotes' => $quotes]);
                if ($result['imported'] !== count($quotes)) {
                    throw new \RuntimeException('Importación incompleta.');
                }
                $this->comment('Importados '.min($count, $offset + 500).'/'.$count);
            }
            $report['import_seconds'] = round((hrtime(true) - $started) / 1_000_000_000, 3);
            $queryCount = 0;
            DB::listen(function () use (&$queryCount) { $queryCount++; });
            $cases = [
                'first_page_ARS' => ['client' => $marker, 'per_page' => 50],
                'last_page_USD' => ['client' => $marker, 'currency' => 'USD', 'per_page' => 50, 'page' => (int) ceil($count / 50)],
                'filtered_USD' => ['client' => $marker.' Norte', 'currency' => 'USD', 'per_page' => 50],
            ];
            foreach ($cases as $name => $parameters) {
                $options = ListQuoteOptions::fromQuery($parameters);
                $list->list($options); // warm-up excluded
                $times = [];
                $queryCount = 0;
                for ($i = 0; $i < $samples; $i++) {
                    $start = hrtime(true);
                    $page = $list->list($options);
                    $times[] = (hrtime(true) - $start) / 1_000_000;
                    $expectedTotal = $name === 'filtered_USD' ? (int) ceil($count / 2) : $count;
                    if ($page['meta']['total'] !== $expectedTotal || count($page['data']) > 50) {
                        throw new \RuntimeException('Resumen de listado inconsistente.');
                    }
                }
                if ($queryCount !== 2 * $samples) {
                    throw new \RuntimeException('El listado no usó exactamente dos consultas por petición.');
                }
                sort($times);
                $report['listings'][$name] = [
                    'p50_ms' => round($times[(int) ceil($samples * .5) - 1], 3),
                    'p95_ms' => round($times[(int) ceil($samples * .95) - 1], 3),
                    'sql_queries_per_request' => 2,
                ];
            }
            $report['peak_php_memory_mb'] = round(memory_get_peak_usage(true) / 1048576, 2);
        } finally {
            // Marker is random per run; never remove pre-existing user budgets.
            DB::table('quotes')->where('budget_id', 'like', $marker.'-%')->delete();
        }
        $report['remaining_generated_rows'] = DB::table('quotes')->where('budget_id', 'like', $marker.'-%')->count();
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return 0;
    }
}
