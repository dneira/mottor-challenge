<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

interface PricingRule
{
    public function type(): string;

    public function apply(CalculationState $state, ExchangeRates $rates): RuleResult;
}
