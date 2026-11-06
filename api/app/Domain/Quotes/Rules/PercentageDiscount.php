<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Rules;

use App\Domain\Quotes\{CalculationState, ExchangeRates, PricingRule, RuleParameters, RuleResult};

final readonly class PercentageDiscount implements PricingRule
{
    public string $percent;

    public function __construct(string|int $percent)
    {
        $this->percent = RuleParameters::decimal($percent, 'percent', 100);
    }

    public function type(): string
    {
        return 'percentage_discount';
    }

    public function apply(CalculationState $state, ExchangeRates $rates): RuleResult
    {
        $base = $state->total();

        return new RuleResult($state->discount($base->percentage($this->percent)), $base, "{$this->percent}% general");
    }
}
