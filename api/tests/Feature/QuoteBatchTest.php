<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\ExactJson;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class QuoteBatchTest extends TestCase
{
    use DatabaseMigrations;

    public function test_returns_summaries_in_input_order_and_persists_details(): void
    {
        $first = $this->input('B');
        $second = $this->input('A');
        $response = $this->batch([$first, $second])->assertCreated()->assertJsonPath('imported', 2);
        self::assertSame(['B', 'A'], array_column($response->json('quotes'), 'budget_id'));
        $summary = $response->json('quotes.0');
        self::assertEqualsCanonicalizing(['id', 'budget_id', 'currency', 'total'], array_keys($summary));
        self::assertStringContainsString('"total":4722388.00', $response->getContent());
        $this->get('/quotes/'.$summary['id'])->assertOk()->assertJsonPath('budget_id', 'B')->assertJsonCount(6, 'breakdown');
        self::assertSame(2, DB::table('quotes')->count());
    }

    public function test_collects_prefixed_errors_without_inserting_any_of_the_batch(): void
    {
        $this->batch([$this->input('PREVIOUS')])->assertCreated();
        $badRule = $this->input('INVALID');
        $badRule->rules[0]->type = 'unknown';
        $badClient = $this->input('INVALID2');
        $badClient->budget->client = '';
        $this->batch([$this->input('VALID'), $badRule, $badClient])->assertStatus(422)
            ->assertJsonValidationErrors(['quotes.1.rules.0.type', 'quotes.2.budget.client']);
        self::assertSame(['PREVIOUS'], DB::table('quotes')->pluck('budget_id')->all());
    }

    public function test_database_failure_rolls_back_earlier_inserts_in_the_same_batch(): void
    {
        $this->batch([$this->input('PREVIOUS')])->assertCreated();
        DB::unprepared("CREATE TRIGGER reject_quote BEFORE INSERT ON quotes WHEN NEW.budget_id = 'FAIL' BEGIN SELECT RAISE(ABORT, 'simulated storage failure'); END");
        $this->batch([$this->input('FIRST'), $this->input('FAIL')])->assertStatus(500)
            ->assertExactJson(['message' => 'Error interno del servidor.']);
        self::assertSame(['PREVIOUS'], DB::table('quotes')->pluck('budget_id')->all());
    }

    public function test_accepts_500_and_rejects_501_before_any_new_insert(): void
    {
        $input = $this->input('REPEAT');
        $input->budget->items = [];
        $input->rules = [];
        $this->batch(array_fill(0, 500, $input))->assertCreated()->assertJsonPath('imported', 500)->assertJsonCount(500, 'quotes');
        self::assertSame(500, DB::table('quotes')->count());
        $this->batch(array_fill(0, 501, $input))->assertStatus(422)->assertJsonValidationErrors('quotes');
        self::assertSame(500, DB::table('quotes')->count());
    }

    /** @dataProvider invalidBodies */
    public function test_invalid_batch_shape(string $body, string $path): void
    {
        $this->raw($body)->assertStatus(422)->assertJsonValidationErrors($path);
        self::assertSame(0, DB::table('quotes')->count());
    }

    public static function invalidBodies(): array
    {
        return [
            ['{}', 'quotes'], ['[]', 'quotes'], ['{"quotes":[]}', 'quotes'], ['{"quotes":{}}', 'quotes'],
            ['{"quotes":null}', 'quotes'], ['{"quotes":[null]}', 'quotes.0'], ['{"quotes":[true]}', 'quotes.0'], ['{"quotes":', 'body'],
        ];
    }

    private function input(string $id): \stdClass
    {
        $input = (new ExactJson())->decode(file_get_contents(__DIR__.'/../../../fixtures/official.input.json'));
        $input->budget->id = $id;
        return $input;
    }

    private function batch(array $quotes)
    {
        return $this->raw((new ExactJson())->encode(['quotes' => $quotes]));
    }

    private function raw(string $json)
    {
        return $this->call('POST', '/quotes/batch', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $json);
    }
}
