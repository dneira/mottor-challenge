<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;

/** Local rule parameter errors; HTTP adapters may prefix field with rules.N. */
final class RuleParameters
{
    public static function text(mixed $value, string $field): string
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidRuleConfiguration($field, 'Se requiere texto no vacío.');
        }

        return $value;
    }

    public static function integer(mixed $value, string $field, int $minimum = 0): int
    {
        if (!is_int($value) || $value < $minimum) {
            throw new InvalidRuleConfiguration($field, "Se requiere un entero mayor o igual a {$minimum}.");
        }

        return $value;
    }

    public static function decimal(mixed $value, string $field, ?int $maximum = null): string
    {
        if (!is_int($value) && !is_string($value)) {
            throw new InvalidRuleConfiguration($field, 'Se requiere un decimal exacto.');
        }
        try {
            $decimal = BigDecimal::of($value);
        } catch (MathException $exception) {
            throw new InvalidRuleConfiguration($field, 'Decimal inválido.');
        }
        if ($decimal->isNegative() || ($maximum !== null && $decimal->compareTo($maximum) > 0)) {
            throw new InvalidRuleConfiguration($field, 'Decimal fuera del rango permitido.');
        }

        return (string) $decimal->strippedOfTrailingZeros();
    }

    public static function money(mixed $amount, mixed $currency): Money
    {
        $currency = is_string($currency) ? Currency::tryFrom($currency) : null;
        if ($currency === null) {
            throw new InvalidRuleConfiguration('currency', 'Moneda no admitida.');
        }
        $decimal = self::decimal($amount, 'amount');
        try {
            return Money::of($decimal, $currency);
        } catch (MathException $exception) {
            throw new InvalidRuleConfiguration('amount', 'El importe debe poder expresarse en centavos.');
        }
    }
}
