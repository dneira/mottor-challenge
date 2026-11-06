<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Quotes\Currency;
use App\Domain\Quotes\Money;
use App\Domain\Quotes\ProportionalAllocator;
use Brick\Math\BigRational;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ProportionalAllocatorTest extends TestCase
{
    /** @dataProvider allocations */
    public function test_exact_allocation(string $amount, array $weights, array $expected): void
    {
        $result = (new ProportionalAllocator())->allocate(
            Money::of($amount, Currency::ARS),
            array_map(fn ($weight) => BigRational::of($weight), $weights),
        );
        self::assertSame($expected, array_map(fn (Money $money) => $money->decimal(), $result));
        $sum = Money::of(0, Currency::ARS);
        foreach ($result as $part) {
            $sum = $sum->plus($part);
        }
        self::assertSame($amount, $sum->decimal());
    }

    public static function allocations(): array
    {
        return [
            'tie preserves original order' => ['0.02', [1, 1, 1], ['0.01', '0.01', '0.00']],
            'largest remainder wins' => ['0.01', [1, 3], ['0.00', '0.01']],
            'zero weight stays zero' => ['1.00', [0, 2, 1], ['0.00', '0.67', '0.33']],
            'rational weights' => ['0.05', ['1/3', '2/3'], ['0.02', '0.03']],
            'zero total and weights' => ['0.00', [0, 0], ['0.00', '0.00']],
            'empty zero allocation' => ['0.00', [], []],
            'large amount' => ['99999999999999999999.99', [1, 1], ['50000000000000000000.00', '49999999999999999999.99']],
        ];
    }

    public function test_conservation_and_balance_limits_across_small_cent_values(): void
    {
        $allocator = new ProportionalAllocator();
        $weights = array_map(fn ($value) => BigRational::of($value), [2, 3, 7]);
        for ($cents = 0; $cents <= 12; $cents++) {
            $amount = Money::rounded(BigRational::of($cents)->dividedBy(100), Currency::ARS);
            $parts = $allocator->allocate($amount, $weights);
            $sum = Money::of(0, Currency::ARS);
            foreach ($parts as $index => $part) {
                self::assertGreaterThanOrEqual(0, $part->minor()->toInt());
                self::assertLessThanOrEqual([2, 3, 7][$index], $part->minor()->toInt());
                $sum = $sum->plus($part);
            }
            self::assertSame($amount->decimal(), $sum->decimal());
        }
    }

    public function test_rounding_exact_shares_preserves_original_remainders(): void
    {
        $shares = array_map(fn ($value) => BigRational::of($value), ['0.0149', '0.0048', '0.0043']);
        $result = (new ProportionalAllocator())->roundShares($shares, Currency::ARS);
        self::assertSame(['0.02', '0.00', '0.00'], array_map(fn (Money $money) => $money->decimal(), $result));
    }

    public function test_aggregate_rounding_can_assign_one_cent_to_each_share(): void
    {
        $shares = [BigRational::of('0.009'), BigRational::of('0.009')];
        $result = (new ProportionalAllocator())->roundShares($shares, Currency::ARS);
        self::assertSame(['0.01', '0.01'], array_map(fn (Money $money) => $money->decimal(), $result));
    }

    /** @dataProvider invalidAllocations */
    public function test_invalid_allocation_is_rejected(string $amount, array $weights): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ProportionalAllocator())->allocate(
            Money::of($amount, Currency::ARS), array_map(fn ($value) => BigRational::of($value), $weights),
        );
    }

    public static function invalidAllocations(): array
    {
        return [['1.00', [0, 0]], ['1.00', []], ['-1.00', [1]], ['1.00', [-1, 2]]];
    }
}
