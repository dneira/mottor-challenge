<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

use InvalidArgumentException;

final class QuoteCalculator
{
    /** @param list<QuoteItem> $items @param list<PricingRule> $rules */
    public function calculate(array $items, Currency $currency, ExchangeRates $rates, array $rules): CalculationResult
    {
        if (!array_is_list($rules)) {
            throw new InvalidArgumentException('Las reglas deben ser una lista ordenada.');
        }
        $state = CalculationState::start($items, $currency, $rates);
        $steps = [];
        foreach ($rules as $index => $rule) {
            if (!$rule instanceof PricingRule) {
                throw new InvalidArgumentException('Regla inválida.');
            }
            $before = $state->total();
            $result = $rule->apply($state, $rates);
            $state = $result->state;
            // Derive the recorded adjustment from the real transition, never a second calculation.
            $steps[] = new BreakdownStep(
                $index + 1, $rule->type(), $result->label, $result->base,
                $state->total()->minus($before), $state->total(),
            );
        }

        return new CalculationResult($state, $steps);
    }
}
