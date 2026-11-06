<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Rules;

use App\Domain\Quotes\{CalculationState, ExchangeRates, Money, PricingRule, RuleParameters, RuleResult};

final readonly class DiscountCap implements PricingRule
{
    public string $maxPercent;

    public function __construct(string|int $maxPercent)
    {
        $this->maxPercent = RuleParameters::decimal($maxPercent, 'max_percent', 100);
    }

    public function type(): string
    {
        return 'discount_cap';
    }

    public function apply(CalculationState $state, ExchangeRates $rates): RuleResult
    {
        $netDiscount = $state->subtotal->minus($state->commercialTotal());
        $excess = $netDiscount->minus($state->subtotal->percentage($this->maxPercent));
        if ($excess->minor()->isNegative()) {
            $excess = Money::of(0, $state->subtotal->currency);
        }

        return new RuleResult($state->restoreDiscount($excess), $state->subtotal, "Tope {$this->maxPercent}%");
    }
}
