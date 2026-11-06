<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

use DomainException;

final class MissingExchangeRate extends DomainException
{
    public function __construct(public readonly Currency $from, public readonly Currency $to)
    {
        parent::__construct("Falta una tasa directa o inversa para {$from->value} a {$to->value}.");
    }
}
