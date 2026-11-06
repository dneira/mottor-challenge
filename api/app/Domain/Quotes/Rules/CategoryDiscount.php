<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Rules;

use App\Domain\Quotes\{CalculationState, ExchangeRates, PricingRule, RuleParameters, RuleResult};

final readonly class CategoryDiscount implements PricingRule
{
    public string $category;
    public string $percent;

    public function __construct(string $category, string|int $percent)
    {
        $this->category = RuleParameters::text($category, 'category');
        $this->percent = RuleParameters::decimal($percent, 'percent', 100);
    }

    public function type(): string
    {
        return 'category_discount';
    }

    public function apply(CalculationState $state, ExchangeRates $rates): RuleResult
    {
        $base = $state->commercialTotal($this->category);

        return new RuleResult(
            $state->discount($base->percentage($this->percent), $this->category),
            $base, "{$this->percent}% en {$this->category}",
        );
    }
}
