<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/** A JSON number token, distinguished from a string and never converted to float. */
final readonly class JsonNumber
{
    public function __construct(public string $value)
    {
        if (!preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?$/D', $value)) {
            throw new InvalidArgumentException('Número JSON inválido.');
        }
    }

    public static function fromInput(string $value): self
    {
        if (strlen($value) > 64 || (preg_match('/[eE]([+-]?\d+)$/D', $value, $match) && abs((int) $match[1]) > 100)) {
            throw new InvalidArgumentException('Número JSON inválido o fuera del límite de precisión.');
        }

        return new self($value);
    }
}
