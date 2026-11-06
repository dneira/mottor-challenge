<?php

declare(strict_types=1);

namespace App\Domain\Quotes\Rules;

use App\Domain\Quotes\{CalculationState, ExchangeRates, InvalidRuleConfiguration, PricingRule, ProportionalAllocator, RuleParameters, RuleResult};
use Brick\Math\BigRational;

final readonly class QuantityPromo implements PricingRule
{
    public string $category;

    public function __construct(string $category, public int $buy, public int $pay)
    {
        $this->category = RuleParameters::text($category, 'category');
        RuleParameters::integer($buy, 'buy', 1);
        RuleParameters::integer($pay, 'pay');
        if ($pay > $buy) {
            throw new InvalidRuleConfiguration('pay', 'pay no puede superar buy.');
        }
    }

    public function type(): string
    {
        return 'quantity_promo';
    }

    public function apply(CalculationState $state, ExchangeRates $rates): RuleResult
    {
        $ideals = [];
        foreach ($state->items as $index => $item) {
            if ($item->category !== $this->category || $item->quantity === 0) {
                $ideals[] = BigRational::zero();
                continue;
            }
            // Free units cannot exceed quantity, so this product also fits in an integer.
            $free = intdiv($item->quantity, $this->buy) * ($this->buy - $this->pay);
            $ideals[] = $state->balances[$index]->rational()->multipliedBy($free)->dividedBy($item->quantity);
        }
        $shares = (new ProportionalAllocator())->roundShares($ideals, $state->subtotal->currency);

        return new RuleResult(
            $state->discountShares($shares), $state->commercialTotal($this->category),
            "{$this->buy}x{$this->pay} en {$this->category}",
        );
    }
}
