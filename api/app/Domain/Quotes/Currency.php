<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

enum Currency: string
{
    case ARS = 'ARS';
    case USD = 'USD';
    case EUR = 'EUR';
}
