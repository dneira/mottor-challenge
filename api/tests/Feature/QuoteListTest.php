<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Quotes\{PrepareQuote, QuoteStore};
use App\Support\{ExactJson, JsonNumber};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{Cache, DB};
use Tests\TestCase;

final class QuoteListTest extends TestCase
{
    use DatabaseMigrations;

    public function test_defaults_and_empty_result_have_integer_metadata(): void
    {
        $this->get('/quotes')->assertOk()->assertExactJson(['data' => [], 'meta' => ['page' => 1, 'per_page' => 50, 'total' => 0, 'last_page' => 1]]);
    }

    public function test_official_summary_converts_using_stored_inverse_rate(): void
    {
        $quote = $this->create();
        $response = $this->get('/quotes?currency=USD')->assertOk();
        $response->assertExactJson(['data' => [[
            'id' => $quote['id'], 'budget_id' => 'PRE-2026-0418', 'client' => 'Agencia Norte',
            'event' => $quote['event'], 'event_lines' => $quote['event_lines'],
            'original_currency' => 'ARS', 'original_total' => 4722388.00, 'currency' => 'USD', 'total' => 3256.82,
        ]], 'meta' => ['page' => 1, 'per_page' => 50, 'total' => 1, 'last_page' => 1]]);
        self::assertStringContainsString('"original_total":4722388.00', $response->getContent());
        self::assertStringContainsString('"total":3256.82', $response->getContent());
        self::assertIsInt($response->json('meta.total'));
    }

    public function test_missing_rate_returns_null_without_hiding_original_total(): void
    {
        $this->create();
        $this->get('/quotes?currency=EUR')->assertOk()->assertJsonPath('data.0.total', null)
            ->assertJsonPath('data.0.original_total', 4722388.00)->assertJsonPath('data.0.currency', 'EUR');
    }

    public function test_uses_each_quotes_rate_and_prefers_direct_when_both_exist(): void
    {
        $first = $this->input();
        $second = $this->input();
        $second->exchange_rates->ARS_USD = new JsonNumber('0.001');
        $this->save($first);
        $this->save($second);
        $response = $this->get('/quotes?currency=USD')->assertOk();
        self::assertSame([3256.82, 4722.39], array_column($response->json('data'), 'total'));
    }

    public function test_same_currency_needs_no_rates(): void
    {
        $input = $this->input();
        $input->budget->items = [];
        $input->rules = [];
        $input->exchange_rates = new \stdClass();
        $this->save($input);
        $this->get('/quotes')->assertOk()->assertJsonPath('data.0.total', 0.00);
    }

    public function test_pagination_and_literal_case_insensitive_client_filter(): void
    {
        $ids = [];
        foreach (['Agencia NORTE', 'Sur', 'norte eventos', 'Otro', 'Norte'] as $client) {
            $ids[] = $this->create($client)['id'];
        }
        $response = $this->get('/quotes?client=NoRtE&per_page=2&page=2')->assertOk();
        self::assertSame([$ids[4]], array_column($response->json('data'), 'id'));
        self::assertSame(['page' => 2, 'per_page' => 2, 'total' => 3, 'last_page' => 2], $response->json('meta'));
        $this->get('/quotes?client=norte&per_page=2&page=9')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 3);
    }

    public function test_wildcards_are_literal_and_unicode_case_is_normalized(): void
    {
        $target = $this->create('ÁGORA 100%_!')['id'];
        $this->create('Agora 100Xother');
        foreach (['ágora', '%_!', '100%', '_'] as $filter) {
            $response = $this->get('/quotes?client='.rawurlencode($filter))->assertOk();
            self::assertSame([$target], array_column($response->json('data'), 'id'));
        }
        $this->get('/quotes?client='.rawurlencode("' OR 1=1 --"))->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_list_uses_two_queries_and_does_not_load_snapshots_or_use_redis(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->create();
        }
        config(['quotes.cache_enabled' => true]);
        Cache::shouldReceive('store')->never();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get('/quotes?client=Norte&per_page=2')->assertOk()->assertJsonCount(2, 'data');
        $queries = DB::getQueryLog();
        self::assertCount(2, $queries);
        self::assertStringContainsString('count(*)', $queries[0]['query']);
        self::assertStringContainsString('total_minor', $queries[1]['query']);
        foreach ($queries as $query) {
            self::assertStringNotContainsString('result_json', $query['query']);
            self::assertStringNotContainsString('input_json', $query['query']);
        }
        self::assertSame(['%norte%'], $queries[1]['bindings']);
    }

    public function test_listing_preserves_cents_beyond_float_precision(): void
    {
        $input = $this->input();
        $input->budget->items = [$input->budget->items[0]];
        $input->budget->items[0]->quantity = new JsonNumber('1');
        $input->budget->items[0]->unit_price = new JsonNumber('9007199254740993.01');
        $input->rules = [];
        $this->save($input);
        $raw = $this->get('/quotes')->assertOk()->getContent();
        self::assertStringContainsString('"total":9007199254740993.01', $raw);
        self::assertStringContainsString('"original_total":9007199254740993.01', $raw);
    }

    public function test_large_converted_output_is_not_rejected_by_input_token_limits(): void
    {
        $input = $this->input();
        $input->budget->items = [$input->budget->items[0]];
        $input->budget->items[0]->quantity = new JsonNumber('1');
        $input->budget->items[0]->unit_price = new JsonNumber('1.00');
        $input->rules = [];
        $input->exchange_rates->USD_ARS = new JsonNumber('1e-100');
        $this->save($input);
        $raw = $this->get('/quotes?currency=USD')->assertOk()->getContent();
        self::assertStringContainsString('"total":1'.str_repeat('0', 100).'.00', $raw);
    }

    public function test_existing_quotes_are_backfilled_from_exact_snapshot(): void
    {
        $input = $this->input();
        $input->budget->items = [$input->budget->items[0]];
        $input->budget->items[0]->quantity = new JsonNumber('1');
        $input->budget->items[0]->unit_price = new JsonNumber('9007199254740993.01');
        $input->rules = [];
        $quote = $this->save($input);
        $migration = new \AddExactListingTotalToQuotes();
        $migration->down();
        $migration->up();
        self::assertSame('900719925474099301', DB::table('quotes')->where('id', $quote['id'])->value('total_minor'));
        self::assertStringContainsString('"total":9007199254740993.01', $this->get('/quotes')->assertOk()->getContent());
    }

    /** @dataProvider invalidQueries */
    public function test_invalid_query_parameters(string $query, string $path): void
    {
        $this->get('/quotes?'.$query)->assertStatus(422)->assertJsonValidationErrors($path);
    }

    public static function invalidQueries(): array
    {
        return [
            ['currency=BRL', 'currency'], ['currency[]=ARS', 'currency'], ['currency=', 'currency'],
            ['page=0', 'page'], ['page=-1', 'page'], ['page=1.5', 'page'], ['page=1e2', 'page'], ['page[]=1', 'page'],
            ['page=999999999999999999999999', 'page'], ['page=9223372036854775807&per_page=200', 'page'],
            ['per_page=0', 'per_page'], ['per_page=201', 'per_page'], ['per_page=abc', 'per_page'], ['client[]=x', 'client'],
        ];
    }

    public function test_maximum_page_size_is_accepted(): void
    {
        $this->get('/quotes?per_page=200')->assertOk()->assertJsonPath('meta.per_page', 200);
    }

    private function create(string $client = 'Agencia Norte'): array
    {
        $input = $this->input();
        $input->budget->client = $client;
        return $this->save($input);
    }

    private function save(\stdClass $input): array
    {
        return app(QuoteStore::class)->create(app(PrepareQuote::class)->prepare($input));
    }

    private function input(): \stdClass
    {
        return (new ExactJson())->decode(file_get_contents(__DIR__.'/../../../fixtures/official.input.json'));
    }
}
