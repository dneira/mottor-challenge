<?php

declare(strict_types=1);

namespace App\Application\Quotes;

final readonly class PreparedQuote
{
    public function __construct(public array $snapshot, public string $inputJson)
    {
    }
}
