<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Quotes\TicketLines;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TicketLinesTest extends TestCase
{
    /** @dataProvider fixtures */
    public function test_shared_fixture(string $input, array $expected): void
    {
        $actual = (new TicketLines())->wrap($input);
        self::assertSame($expected, $actual);
        self::assertLessThanOrEqual(4, count($actual));
        foreach ($actual as $line) {
            self::assertLessThanOrEqual(28, grapheme_strlen($line));
            self::assertSame(trim($line), $line);
        }
    }

    public static function fixtures(): iterable
    {
        $fixtures = json_decode(file_get_contents(__DIR__.'/../../../fixtures/ticket.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($fixtures as $fixture) {
            yield $fixture['name'] => [$fixture['input'], $fixture['expected']];
        }
    }

    public function test_invalid_utf8_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new TicketLines())->wrap("\xff");
    }
}
