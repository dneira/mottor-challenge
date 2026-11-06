<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/** Largest remainder allocation, stable ties by input position. */
final class ProportionalAllocator
{
    /**
     * Distribute a nonnegative amount using exact, nonnegative weights.
     * Weights need not be monetary values; callers choose the eligible balances.
     * @param list<BigRational> $weights
     * @return list<Money>
     */
    public function allocate(Money $amount, array $weights): array
    {
        if (!array_is_list($weights) || $amount->minor()->isNegative()) {
            throw new InvalidArgumentException('Se requiere un monto no negativo y una lista de pesos.');
        }
        $totalWeight = BigRational::zero();
        foreach ($weights as $weight) {
            if (!$weight instanceof BigRational || $weight->compareTo(0) < 0) {
                throw new InvalidArgumentException('Los pesos deben ser racionales no negativos.');
            }
            $totalWeight = $totalWeight->plus($weight);
        }
        if ($totalWeight->isZero()) {
            if (!$amount->minor()->isZero()) {
                throw new InvalidArgumentException('No se puede distribuir un monto positivo entre pesos cero.');
            }

            return array_map(fn () => Money::of(0, $amount->currency), $weights);
        }

        $ideals = array_map(fn (BigRational $weight) => $weight->multipliedBy($amount->minor())->dividedBy($totalWeight), $weights);

        return $this->distribute($amount, $ideals);
    }

    /**
     * Round the sum of exact monetary shares, preserving their original fractional remainders.
     * Used for promotions: normalizing weights to the rounded total would change who receives a cent.
     * @param list<BigRational> $shares
     * @return list<Money>
     */
    public function roundShares(array $shares, Currency $currency): array
    {
        if (!array_is_list($shares)) {
            throw new InvalidArgumentException('Las participaciones deben ser una lista.');
        }
        $sum = BigRational::zero();
        foreach ($shares as $share) {
            if (!$share instanceof BigRational || $share->isNegative()) {
                throw new InvalidArgumentException('Las participaciones deben ser racionales no negativos.');
            }
            $sum = $sum->plus($share);
        }

        return $this->distribute(
            Money::rounded($sum, $currency),
            array_map(fn (BigRational $share) => $share->multipliedBy(100), $shares),
        );
    }

    /** @param list<BigRational> $idealCents @return list<Money> */
    private function distribute(Money $amount, array $idealCents): array
    {
        $cents = [];
        $remainders = [];
        $remaining = $amount->minor();
        foreach ($idealCents as $index => $ideal) {
            $cents[$index] = $ideal->toScale(0, RoundingMode::Down)->toBigInteger();
            $remainders[$index] = $ideal->minus($cents[$index]);
            $remaining = $remaining->minus($cents[$index]);
        }

        $order = array_keys($idealCents);
        usort($order, fn (int $a, int $b) => $remainders[$b]->compareTo($remainders[$a]) ?: $a <=> $b);
        // At most one residual cent per positive share.
        for ($i = 0, $count = $remaining->toInt(); $i < $count; $i++) {
            $index = $order[$i];
            $cents[$index] = $cents[$index]->plus(1);
        }

        return array_map(fn ($value) => Money::fromMinor($value, $amount->currency), $cents);
    }
}
