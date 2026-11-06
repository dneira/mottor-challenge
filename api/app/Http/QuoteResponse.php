<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\{ExactJson, JsonNumber};
use Symfony\Component\HttpFoundation\Response;

final class QuoteResponse
{
    public static function detail(array $snapshot, int $status = 200): Response
    {
        $data = self::numbers($snapshot);

        return new Response((new ExactJson())->encode($data), $status, ['Content-Type' => 'application/json']);
    }

    private static function numbers(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($key === 'exchange_rates') {
                $rates = new \stdClass();
                foreach ($value as $pair => $rate) {
                    $rates->{$pair} = new JsonNumber($rate);
                }
                $data[$key] = $rates;
            } elseif (is_string($value) && in_array($key, ['subtotal', 'total', 'original_total', 'unit_price', 'line_total', 'base', 'amount', 'running_total'], true)) {
                $data[$key] = new JsonNumber($value);
            } elseif (is_array($value)) {
                $data[$key] = self::numbers($value);
            }
        }

        return $data;
    }
}
