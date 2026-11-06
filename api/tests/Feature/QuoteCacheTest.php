<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Quotes\{PrepareQuote, QuoteReader, QuoteStore};
use App\Support\ExactJson;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\{Cache, DB};
use Tests\TestCase;

final class QuoteCacheTest extends TestCase
{
    use DatabaseMigrations;

    public function test_cache_miss_uses_one_query_and_hit_uses_none(): void
    {
        $quote = $this->quote();
        config(['quotes.cache_enabled' => true, 'quotes.cache_store' => 'array']);
        $reader = app(QuoteReader::class);
        DB::enableQueryLog();
        DB::flushQueryLog();
        self::assertSame($quote, $reader->find($quote['id']));
        self::assertCount(1, DB::getQueryLog());
        DB::flushQueryLog();
        self::assertSame($quote, $reader->find($quote['id']));
        self::assertCount(0, DB::getQueryLog());
    }

    public function test_disabled_cache_does_not_require_redis(): void
    {
        $quote = $this->quote();
        Cache::shouldReceive('store')->never();
        self::assertSame($quote, app(QuoteReader::class)->find($quote['id']));
    }

    public function test_expired_snapshot_is_reloaded_from_sql(): void
    {
        $quote = $this->quote();
        config(['quotes.cache_enabled' => true, 'quotes.cache_store' => 'array']);
        $reader = app(QuoteReader::class);
        $reader->find($quote['id']);
        \Illuminate\Support\Carbon::setTestNow(now()->addSeconds(3601));
        try {
            DB::enableQueryLog();
            DB::flushQueryLog();
            self::assertSame($quote, $reader->find($quote['id']));
            self::assertCount(1, DB::getQueryLog());
        } finally {
            \Illuminate\Support\Carbon::setTestNow();
        }
    }

    public function test_cache_read_failure_falls_back_to_sql(): void
    {
        $quote = $this->quote();
        config(['quotes.cache_enabled' => true, 'quotes.cache_store' => 'broken']);
        Cache::shouldReceive('store')->with('broken')->andThrow(new \RuntimeException('Redis unavailable'));
        self::assertSame($quote, app(QuoteReader::class)->find($quote['id']));
    }

    public function test_cache_write_failure_keeps_sql_result(): void
    {
        $quote = $this->quote();
        config(['quotes.cache_enabled' => true, 'quotes.cache_store' => 'broken']);
        $repository = \Mockery::mock(\Illuminate\Contracts\Cache\Repository::class);
        $repository->shouldReceive('get')->andReturn(null);
        $repository->shouldReceive('put')->andThrow(new \RuntimeException('Redis unavailable'));
        Cache::shouldReceive('store')->with('broken')->andReturn($repository);
        self::assertSame($quote, app(QuoteReader::class)->find($quote['id']));
    }

    public function test_not_found_is_not_cached(): void
    {
        config(['quotes.cache_enabled' => true, 'quotes.cache_store' => 'array']);
        $reader = app(QuoteReader::class);
        self::assertNull($reader->find(999));
        self::assertFalse(Cache::store('array')->has($reader->key(999)));
    }

    public function test_uncommitted_data_never_enters_cache(): void
    {
        config(['quotes.cache_enabled' => true, 'quotes.cache_store' => 'array']);
        DB::beginTransaction();
        try {
            $quote = $this->quote();
            $reader = app(QuoteReader::class);
            self::assertSame($quote, $reader->find($quote['id']));
            self::assertFalse(Cache::store('array')->has($reader->key($quote['id'])));
        } finally {
            DB::rollBack();
        }
        self::assertSame(0, DB::table('quotes')->count());
    }

    private function quote(): array
    {
        $input = (new ExactJson())->decode(file_get_contents(__DIR__.'/../../../fixtures/official.input.json'));

        return app(QuoteStore::class)->create(app(PrepareQuote::class)->prepare($input));
    }
}
