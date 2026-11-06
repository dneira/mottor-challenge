<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

final readonly class BreakdownStep
{
    public function __construct(
        public int $step,
        public string $type,
        public string $label,
        public Money $base,
        public Money $amount,
        public Money $runningTotal,
    ) {
    }

    /** Exact decimal strings for internal snapshots; not an HTTP serializer. */
    public function snapshot(): array
    {
        return [
            'step' => $this->step, 'type' => $this->type, 'label' => $this->label,
            'base' => $this->base->decimal(), 'amount' => $this->amount->decimal(),
            'running_total' => $this->runningTotal->decimal(),
        ];
    }
}
