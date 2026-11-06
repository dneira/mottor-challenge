<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Rules;

use App\Domain\Quotes\{CalculationState, ExchangeRates, PricingRule, RuleParameters, RuleResult};

final readonly class Tax implements PricingRule
{
    public string $name;
    public string $percent;

    public function __construct(string $name, string|int $percent)
    {
        $this->name = RuleParameters::text($name, 'name');
        $this->percent = RuleParameters::decimal($percent, 'percent');
    }

    public function type(): string
    {
        return 'tax';
    }

    public function apply(CalculationState $state, ExchangeRates $rates): RuleResult
    {
        $base = $state->total();

        return new RuleResult($state->addTax($base->percentage($this->percent)), $base, "{$this->name} {$this->percent}%");
    }
}
