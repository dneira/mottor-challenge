<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Quotes\Currency;
use App\Domain\Quotes\ExchangeRates;
use App\Domain\Quotes\MissingExchangeRate;
use App\Domain\Quotes\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ExchangeRatesTest extends TestCase
{
    public function test_same_currency_needs_no_rate(): void
    {
        $money = Money::of('12.34', Currency::ARS);
        self::assertSame($money, (new ExchangeRates([]))->convert($money, Currency::ARS));
    }

    public function test_official_direct_conversion(): void
    {
        $rates = new ExchangeRates(['USD_ARS' => '1450.00']);
        self::assertSame('1740000.00', $rates->convert(Money::of(1200, Currency::USD), Currency::ARS)->decimal());
    }

    public function test_official_listing_uses_inverse(): void
    {
        $rates = new ExchangeRates(['USD_ARS' => '1450.00']);
        self::assertSame('3256.82', $rates->convert(Money::of('4722388.00', Currency::ARS), Currency::USD)->decimal());
    }

    public function test_inverse_is_not_rounded_before_multiplication(): void
    {
        $rates = new ExchangeRates(['USD_ARS' => '3']);
        self::assertSame('0.67', $rates->convert(Money::of(2, Currency::ARS), Currency::USD)->decimal());
    }

    public function test_direct_rate_wins_when_both_exist(): void
    {
        $rates = new ExchangeRates(['USD_ARS' => '3', 'ARS_USD' => '0.5']);
        self::assertSame('1.00', $rates->convert(Money::of(2, Currency::ARS), Currency::USD)->decimal());
    }

    public function test_line_is_multiplied_before_conversion_rounding(): void
    {
        $rates = new ExchangeRates(['USD_ARS' => '0.3333']);
        $line = Money::of('0.01', Currency::USD)->times(3);
        self::assertSame('0.01', $rates->convert($line, Currency::ARS)->decimal());
        self::assertSame('0.00', $rates->convert(Money::of('0.01', Currency::USD), Currency::ARS)->times(3)->decimal());
    }

    public function test_no_triangulation_even_when_a_path_exists(): void
    {
        $this->expectException(MissingExchangeRate::class);
        (new ExchangeRates(['USD_ARS' => '1450', 'EUR_ARS' => '1562.10']))
            ->convert(Money::of(10, Currency::EUR), Currency::USD);
    }

    /** @dataProvider invalidRates */
    public function test_invalid_rates_are_rejected(array $rates): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ExchangeRates($rates);
    }

    public static function invalidRates(): array
    {
        return [
            [['USD_ARS' => '0']], [['USD_ARS' => '-1']], [['USD_BRL' => '2']],
            [['USD_USD' => '1']], [['USD_ARS' => 1.5]],
        ];
    }
}
