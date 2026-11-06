<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Quotes\Currency;
use App\Domain\Quotes\Money;
use Brick\Math\BigRational;
use Brick\Math\Exception\RoundingNecessaryException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function test_decimal_addition_and_immutability(): void
    {
        $original = Money::of('0.10', Currency::ARS);
        $result = $original->plus(Money::of('0.20', Currency::ARS));
        self::assertSame('0.30', $result->decimal());
        self::assertSame('0.10', $original->decimal());
        self::assertSame('30', (string) $result->minor());
        self::assertSame('0.30', Money::fromMinor($result->minor(), Currency::ARS)->decimal());
    }

    /** @dataProvider roundingCases */
    public function test_half_up_rounding(string $input, string $expected): void
    {
        self::assertSame($expected, Money::rounded(BigRational::of($input), Currency::ARS)->decimal());
    }

    public static function roundingCases(): array
    {
        return [['0.005', '0.01'], ['-0.005', '-0.01'], ['1.0049', '1.00'], ['1.005', '1.01']];
    }

    public function test_input_with_fractional_cents_is_rejected(): void
    {
        $this->expectException(RoundingNecessaryException::class);
        Money::of('1.001', Currency::ARS);
    }

    public function test_currency_mismatch_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::of(1, Currency::ARS)->plus(Money::of(1, Currency::USD));
    }

    public function test_negative_quantity_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::of(1, Currency::ARS)->times(-1);
    }

    public function test_large_amounts_do_not_overflow_native_integers(): void
    {
        $money = Money::of('99999999999999999999.99', Currency::ARS);
        self::assertSame('100000000000000000000.00', $money->plus(Money::of('0.01', Currency::ARS))->decimal());
    }

    public function test_second_case_general_discount_is_rounded_at_rule_boundary(): void
    {
        self::assertSame('927028.13', Money::of('7416225.00', Currency::ARS)->percentage('12.5')->decimal());
    }
}
