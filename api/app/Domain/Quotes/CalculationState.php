<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

use Brick\Math\BigRational;
use InvalidArgumentException;

final readonly class CalculationState
{
    /** @param list<QuoteItem> $items @param list<Money> $lineTotals @param list<Money> $balances */
    private function __construct(
        public array $items,
        public array $lineTotals,
        public array $balances,
        public Money $subtotal,
        public Money $taxes,
    ) {
    }

    /** @param list<QuoteItem> $items */
    public static function start(array $items, Currency $currency, ExchangeRates $rates): self
    {
        if (!array_is_list($items)) {
            throw new InvalidArgumentException('Los ítems deben ser una lista.');
        }
        $totals = [];
        $ids = [];
        $subtotal = Money::of(0, $currency);
        foreach ($items as $item) {
            if (!$item instanceof QuoteItem || isset($ids[$item->id])) {
                throw new InvalidArgumentException('Se requieren ítems válidos con IDs únicos.');
            }
            $ids[$item->id] = true;
            $total = $rates->convert($item->unitPrice->times($item->quantity), $currency);
            $totals[] = $total;
            $subtotal = $subtotal->plus($total);
        }

        return new self($items, $totals, $totals, $subtotal, Money::of(0, $currency));
    }

    public function commercialTotal(?string $category = null): Money
    {
        $total = Money::of(0, $this->subtotal->currency);
        foreach ($this->balances as $index => $balance) {
            if ($category === null || $this->items[$index]->category === $category) {
                $total = $total->plus($balance);
            }
        }

        return $total;
    }

    public function total(): Money
    {
        return $this->commercialTotal()->plus($this->taxes);
    }

    /** @return list<BigRational> */
    public function balanceWeights(?string $category = null): array
    {
        return array_map(
            fn (QuoteItem $item, Money $balance) => $category === null || $item->category === $category
                ? $balance->rational() : BigRational::zero(),
            $this->items, $this->balances,
        );
    }

    public function discount(Money $requested, ?string $category = null): self
    {
        $available = $this->commercialTotal($category);
        $amount = $requested->compareTo($available) > 0 ? $available : $requested;
        $shares = (new ProportionalAllocator())->allocate($amount, $this->balanceWeights($category));

        return $this->discountShares($shares);
    }

    /** @param list<Money> $shares */
    public function discountShares(array $shares): self
    {
        return $this->adjustBalances($shares, false);
    }

    public function restoreDiscount(Money $amount): self
    {
        $weights = array_map(fn (Money $original, Money $balance) => $original->minus($balance)->rational(), $this->lineTotals, $this->balances);
        $shares = (new ProportionalAllocator())->allocate($amount, $weights);

        return $this->adjustBalances($shares, true);
    }

    public function addTax(Money $amount): self
    {
        if ($amount->minor()->isNegative()) {
            throw new InvalidArgumentException('El impuesto no puede ser negativo.');
        }

        return new self($this->items, $this->lineTotals, $this->balances, $this->subtotal, $this->taxes->plus($amount));
    }

    /** @param list<Money> $shares */
    private function adjustBalances(array $shares, bool $restore): self
    {
        if (!array_is_list($shares) || count($shares) !== count($this->balances)) {
            throw new InvalidArgumentException('Debe existir una asignación por ítem.');
        }
        $balances = [];
        foreach ($shares as $index => $share) {
            if (!$share instanceof Money || $share->minor()->isNegative()) {
                throw new InvalidArgumentException('Las asignaciones deben ser importes no negativos.');
            }
            $balance = $restore ? $this->balances[$index]->plus($share) : $this->balances[$index]->minus($share);
            if ($balance->minor()->isNegative() || $balance->compareTo($this->lineTotals[$index]) > 0) {
                throw new InvalidArgumentException('Un saldo debe permanecer entre cero y su importe original.');
            }
            $balances[] = $balance;
        }

        return new self($this->items, $this->lineTotals, $balances, $this->subtotal, $this->taxes);
    }
}
