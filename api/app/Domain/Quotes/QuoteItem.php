<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

use InvalidArgumentException;

final readonly class QuoteItem
{
    public function __construct(
        public int $id,
        public string $description,
        public string $category,
        public int $quantity,
        public Money $unitPrice,
    ) {
        if ($quantity < 0 || $unitPrice->minor()->isNegative() || trim($category) === '') {
            throw new InvalidArgumentException('Cantidad/precio no negativos y categoría no vacía requeridos.');
        }
    }
}
