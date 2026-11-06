<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/** Exact, immutable money. Decimal strings are internal, not the HTTP representation. */
final readonly class Money
{
    private function __construct(
        private BigDecimal $amount,
        public Currency $currency,
    ) {
    }

    /** Input amounts must already be representable in cents; never silently round input. */
    public static function of(string|int $amount, Currency $currency): self
    {
        return new self(BigDecimal::of($amount)->toScale(2), $currency);
    }

    /** One explicit rounding boundary for a calculated line or rule adjustment. */
    public static function rounded(BigRational $amount, Currency $currency): self
    {
        return new self($amount->toScale(2, RoundingMode::HalfUp), $currency);
    }

    public static function fromMinor(BigInteger $cents, Currency $currency): self
    {
        return self::rounded($cents->toBigRational()->dividedBy(100), $currency);
    }

    public function decimal(): string
    {
        return (string) $this->amount;
    }

    public function minor(): BigInteger
    {
        return $this->amount->multipliedBy(100)->toBigInteger();
    }

    public function rational(): BigRational
    {
        return $this->amount->toBigRational();
    }

    public function plus(self $other): self
    {
        $this->assertCurrency($other);

        return new self($this->amount->plus($other->amount), $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertCurrency($other);

        return new self($this->amount->minus($other->amount), $this->currency);
    }

    public function times(int $quantity): self
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('La cantidad no puede ser negativa.');
        }

        return new self($this->amount->multipliedBy($quantity), $this->currency);
    }

    public function percentage(string|int $percent): self
    {
        return self::rounded($this->rational()->multipliedBy($percent)->dividedBy(100), $this->currency);
    }

    public function compareTo(self $other): int
    {
        $this->assertCurrency($other);

        return $this->amount->compareTo($other->amount);
    }

    private function assertCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('No se pueden combinar importes de distintas monedas.');
        }
    }
}
