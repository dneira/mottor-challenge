<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

use Brick\Math\BigDecimal;
use Brick\Math\BigRational;
use InvalidArgumentException;

final readonly class ExchangeRates
{
    /** @var array<string, BigRational> */
    private array $rates;

    /** @param array<string, string|int> $rates Exact decimal values from the input adapter. */
    public function __construct(array $rates)
    {
        $parsed = [];
        foreach ($rates as $pair => $value) {
            if (!is_string($pair) || !preg_match('/^(ARS|USD|EUR)_(ARS|USD|EUR)$/D', $pair, $matches)
                || $matches[1] === $matches[2]) {
                throw new InvalidArgumentException('Par de monedas inválido.');
            }
            if (!is_string($value) && !is_int($value)) {
                throw new InvalidArgumentException('Las tasas del dominio deben ser decimales exactos.');
            }
            $rate = BigDecimal::of($value)->toBigRational();
            if ($rate->compareTo(0) <= 0) {
                throw new InvalidArgumentException('Las tasas deben ser mayores que cero.');
            }
            $parsed[$pair] = $rate;
        }
        $this->rates = $parsed;
    }

    public function convert(Money $amount, Currency $target): Money
    {
        if ($amount->currency === $target) {
            return $amount;
        }
        $direct = $amount->currency->value.'_'.$target->value;
        $inverse = $target->value.'_'.$amount->currency->value;

        if (isset($this->rates[$direct])) {
            $result = $amount->rational()->multipliedBy($this->rates[$direct]);
        } elseif (isset($this->rates[$inverse])) {
            $result = $amount->rational()->dividedBy($this->rates[$inverse]);
        } else {
            throw new MissingExchangeRate($amount->currency, $target);
        }

        return Money::rounded($result, $target);
    }
}
