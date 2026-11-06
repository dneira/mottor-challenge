<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Rules;

use App\Domain\Quotes\{CalculationState, ExchangeRates, InvalidRuleConfiguration, Money, PricingRule, RuleResult};

final readonly class FixedDiscount implements PricingRule
{
    public function __construct(public Money $amount)
    {
        if ($amount->minor()->isNegative()) {
            throw new InvalidRuleConfiguration('amount', 'El descuento no puede ser negativo.');
        }
    }

    public function type(): string
    {
        return 'fixed_discount';
    }

    public function apply(CalculationState $state, ExchangeRates $rates): RuleResult
    {
        $amount = $rates->convert($this->amount, $state->subtotal->currency);
        [$whole, $fraction] = explode('.', $this->amount->decimal());
        // Group the exact string, without a float conversion through number_format().
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole);
        $label = $this->amount->currency->value.' '.$whole.($fraction === '00' ? '' : ','.$fraction);

        return new RuleResult($state->discount($amount), $state->total(), $label);
    }
}
