<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

final readonly class RuleResult
{
    public function __construct(
        public CalculationState $state,
        public Money $base,
        public string $label,
    ) {
    }
}
