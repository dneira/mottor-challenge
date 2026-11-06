<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Quotes\{CalculationResult, Currency, ExchangeRates, Money, QuoteCalculator, QuoteItem, RuleRegistry};

/** Test-only reader for known valid fixtures. The HTTP numeric adapter belongs to I3. */
final class QuoteFixture
{
    public static function read(string $name): array
    {
        $json = file_get_contents(__DIR__.'/../../../fixtures/'.$name.'.json');
        // Keep number lexemes exact; quoted strings (including digits in descriptions) are skipped.
        $pattern = '/"(?:[^"\\\\]|\\\\.)*"(*SKIP)(*F)|-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?/';
        $json = preg_replace_callback($pattern, fn (array $match) => '"'.$match[0].'"', $json);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public static function calculate(string $name): CalculationResult
    {
        $input = self::read($name.'.input');
        $items = array_map(fn (array $item) => new QuoteItem(
            (int) $item['id'], $item['description'], $item['category'], (int) $item['quantity'],
            Money::of($item['unit_price'], Currency::from($item['currency'])),
        ), $input['budget']['items']);
        $registry = RuleRegistry::standard();
        $rules = array_map(function (array $parameters) use ($registry) {
            foreach (['buy', 'pay'] as $key) {
                if (isset($parameters[$key])) {
                    $parameters[$key] = (int) $parameters[$key];
                }
            }

            return $registry->create($parameters);
        }, $input['rules']);

        return (new QuoteCalculator())->calculate($items, Currency::from($input['currency']), new ExchangeRates($input['exchange_rates']), $rules);
    }
}
