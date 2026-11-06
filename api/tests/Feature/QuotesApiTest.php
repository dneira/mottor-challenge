<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\{ExactJson, JsonNumber};
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class QuotesApiTest extends TestCase
{
    use DatabaseMigrations;

    public function test_health_has_no_database_dependency(): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get('/health')->assertOk()->assertExactJson(['status' => 'ok']);
        self::assertSame([], DB::getQueryLog());
    }

    public function test_create_and_read_match_published_case_with_numeric_two_decimal_tokens(): void
    {
        $response = $this->send($this->fixture());
        $response->assertCreated();
        $expected = json_decode(file_get_contents(__DIR__.'/../../../fixtures/official.expected.json'), true);
        $expected['id'] = $response->json('id');
        $response->assertExactJson($expected);
        self::assertStringContainsString('"total":4722388.00', $response->getContent());
        self::assertStringContainsString('"unit_price":18000.00', $response->getContent());
        $stored = DB::table('quotes')->where('id', $expected['id'])->first();
        self::assertSame('v1', $stored->engine_version);
        self::assertStringContainsString('"total":"4722388.00"', $stored->result_json);
        self::assertSame($response->getContent(), $this->get('/quotes/'.$expected['id'])->assertOk()->getContent());
    }

    public function test_second_case_and_duplicate_budget_ids_are_allowed(): void
    {
        $raw = file_get_contents(__DIR__.'/../../../fixtures/second.input.json');
        $first = $this->send($raw)->assertCreated()->assertJsonPath('total', 7832871.49);
        $second = $this->send($raw)->assertCreated();
        self::assertNotSame($first->json('id'), $second->json('id'));
        self::assertSame(2, DB::table('quotes')->count());
    }

    public function test_large_price_preserves_exact_cents_across_database_roundtrip(): void
    {
        $raw = '{"budget":{"id":"LARGE","client":"A","event":"","items":[{"id":1,"description":"","category":"a","quantity":1,"unit_price":9007199254740993.01,"currency":"ARS"}]},"currency":"ARS","exchange_rates":{},"rules":[]}';
        $response = $this->send($raw)->assertCreated();
        self::assertStringContainsString('"total":9007199254740993.01', $response->getContent());
        self::assertStringContainsString('"exchange_rates":{}', $response->getContent());
        self::assertSame($response->getContent(), $this->get('/quotes/'.$response->json('id'))->assertOk()->getContent());
    }

    public function test_text_is_preserved_and_only_ticket_lines_are_normalized(): void
    {
        $input = (new ExactJson())->decode($this->fixture());
        $input->budget->event = '   Fiesta   anual  ';
        $input->budget->items[0]->description = '';
        $response = $this->send((new ExactJson())->encode($input))->assertCreated();
        $response->assertJsonPath('event', '   Fiesta   anual  ')->assertJsonPath('event_lines', ['Fiesta anual'])->assertJsonPath('items.0.description', '')->assertJsonPath('items.0.description_lines', []);
    }

    /** @dataProvider invalidInputs */
    public function test_validation_errors_have_paths_and_do_not_persist(string $path, mixed $value): void
    {
        $input = (new ExactJson())->decode($this->fixture());
        $parts = explode('.', $path);
        $target = &$input;
        foreach ($parts as $part) {
            if (is_array($target)) {
                $target = &$target[(int) $part];
            } else {
                $target = &$target->{$part};
            }
        }
        $target = $value;
        $this->send((new ExactJson())->encode($input))->assertStatus(422)->assertJsonValidationErrors($path);
        self::assertSame(0, DB::table('quotes')->count());
    }

    public static function invalidInputs(): array
    {
        return [
            ['currency', 'BRL'], ['budget.client', ''], ['budget.event', null],
            ['budget.items', new \stdClass()], ['exchange_rates', []], ['rules', new \stdClass()],
            ['budget.items.0.unit_price', '18.00'], ['budget.items.0.unit_price', new JsonNumber('-1')],
            ['budget.items.0.unit_price', new JsonNumber('0.001')], ['budget.items.0.quantity', new JsonNumber('1.5')],
            ['budget.items.0.quantity', true], ['budget.items.1.id', new JsonNumber('1')],
            ['rules.0.type', 'unknown'], ['rules.0.buy', new JsonNumber('0')], ['rules.2.percent', '10'],
            ['exchange_rates.USD_ARS', new JsonNumber('0')],
        ];
    }

    public function test_missing_rate_is_a_validation_error(): void
    {
        $input = (new ExactJson())->decode($this->fixture());
        $input->exchange_rates = new \stdClass();
        $this->send((new ExactJson())->encode($input))->assertStatus(422)->assertJsonValidationErrors('exchange_rates');
        self::assertSame(0, DB::table('quotes')->count());
    }

    public function test_overflow_is_rejected_before_database_insert(): void
    {
        $input = (new ExactJson())->decode($this->fixture());
        $input->budget->items[0]->unit_price = new JsonNumber('1e27');
        $this->send((new ExactJson())->encode($input))->assertStatus(422)->assertJsonValidationErrors('subtotal');
    }

    public function test_large_unit_price_is_rejected_even_when_quantity_is_zero(): void
    {
        $input = (new ExactJson())->decode($this->fixture());
        $input->budget->items[0]->quantity = new JsonNumber('0');
        $input->budget->items[0]->unit_price = new JsonNumber('1e100');
        $this->send((new ExactJson())->encode($input))->assertStatus(422)->assertJsonValidationErrors('budget.items.0.unit_price');
        self::assertSame(0, DB::table('quotes')->count());
    }

    public function test_malformed_json_is_422_even_without_accept_header(): void
    {
        $this->call('POST', '/quotes', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"budget":')->assertStatus(422)->assertJsonValidationErrors('body');
    }

    public function test_missing_id_returns_json_without_trace(): void
    {
        $this->get('/quotes/999')->assertNotFound()->assertExactJson(['message' => 'Presupuesto no encontrado.']);
        $this->get('/quotes/9999999999999999999999999999')->assertNotFound();
    }

    public function test_unexpected_database_failure_is_sanitized_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        $database = DB::getFacadeRoot();
        try {
            DB::shouldReceive('transactionLevel')->andReturn(0);
            DB::shouldReceive('table')->with('quotes')->andThrow(new \RuntimeException('private database credential'));
            $this->get('/quotes/1')->assertStatus(500)->assertExactJson(['message' => 'Error interno del servidor.']);
        } finally {
            DB::swap($database);
        }
    }

    public function test_cors_preflight_and_create_response(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Access-Control-Request-Method' => 'POST', 'Access-Control-Request-Headers' => 'Content-Type'])
            ->options('/quotes')->assertSuccessful()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
        $this->withHeaders(['Origin' => 'http://localhost:5173']);
        $this->send($this->fixture())->assertCreated()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    private function fixture(): string
    {
        return file_get_contents(__DIR__.'/../../../fixtures/official.input.json');
    }

    private function send(string $json)
    {
        return $this->call('POST', '/quotes', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $json);
    }
}
