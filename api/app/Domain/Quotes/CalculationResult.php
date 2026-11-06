<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

final readonly class CalculationResult
{
    /** @param list<BreakdownStep> $breakdown */
    public function __construct(public CalculationState $state, public array $breakdown)
    {
    }

    /** Calculation fields only. Application layer adds budget metadata and persisted ID. */
    public function snapshot(): array
    {
        $ticket = new TicketLines();
        $items = [];
        foreach ($this->state->items as $index => $item) {
            $items[] = [
                'id' => $item->id, 'description' => $item->description,
                'description_lines' => $ticket->wrap($item->description), 'category' => $item->category,
                'quantity' => $item->quantity, 'unit_price' => $item->unitPrice->decimal(),
                'currency' => $item->unitPrice->currency->value,
                'line_total' => $this->state->lineTotals[$index]->decimal(),
            ];
        }

        return [
            'currency' => $this->state->subtotal->currency->value,
            'subtotal' => $this->state->subtotal->decimal(), 'total' => $this->state->total()->decimal(),
            'items' => $items,
            'breakdown' => array_map(fn (BreakdownStep $step) => $step->snapshot(), $this->breakdown),
        ];
    }
}
