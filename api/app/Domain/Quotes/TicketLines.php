<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

use InvalidArgumentException;

final class TicketLines
{
    private const WIDTH = 28;
    private const LIMIT = 4;

    /** @return list<string> */
    public function wrap(string $text): array
    {
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false) {
            throw new InvalidArgumentException('El texto debe ser UTF-8 válido.');
        }
        $lines = [];
        $current = '';
        foreach ($words as $word) {
            if ($current !== '' && grapheme_strlen($current) + 1 + grapheme_strlen($word) <= self::WIDTH) {
                $current .= ' '.$word;
                continue;
            }
            if ($current !== '') {
                $lines[] = $current;
                $current = '';
            }
            while (grapheme_strlen($word) > self::WIDTH) {
                $lines[] = grapheme_substr($word, 0, self::WIDTH);
                $word = grapheme_substr($word, self::WIDTH);
            }
            $current = $word;
        }
        if ($current !== '') {
            $lines[] = $current;
        }
        if (count($lines) > self::LIMIT) {
            $lines = array_slice($lines, 0, self::LIMIT);
            $lines[3] = rtrim(grapheme_substr($lines[3], 0, self::WIDTH - 1)).'…';
        }

        return $lines;
    }
}
