<?php

declare(strict_types=1);

namespace App\Application\Quotes;

use App\Domain\Quotes\Currency;
use Brick\Math\BigInteger;
use Brick\Math\Exception\MathException;
use Illuminate\Validation\ValidationException;

final readonly class ListQuoteOptions
{
    private function __construct(public Currency $currency, public int $page, public int $perPage, public string $client)
    {
    }

    public static function fromQuery(array $query): self
    {
        $code = array_key_exists('currency', $query) ? $query['currency'] : 'ARS';
        $currency = is_string($code) ? Currency::tryFrom($code) : null;
        if ($currency === null) {
            throw ValidationException::withMessages(['currency' => ['Moneda no admitida.']]);
        }
        $page = self::integer($query, 'page', 1);
        $perPage = self::integer($query, 'per_page', 50);
        if ($perPage > 200) {
            throw ValidationException::withMessages(['per_page' => ['El máximo es 200.']]);
        }
        if ($page - 1 > intdiv(PHP_INT_MAX, $perPage)) {
            throw ValidationException::withMessages(['page' => ['La página supera el rango admitido.']]);
        }
        $client = $query['client'] ?? '';
        if (!is_string($client)) {
            throw ValidationException::withMessages(['client' => ['Se requiere texto.']]);
        }

        return new self($currency, $page, $perPage, $client);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    private static function integer(array $query, string $key, int $default): int
    {
        if (!array_key_exists($key, $query)) {
            return $default;
        }
        $value = $query[$key];
        if ((is_string($value) || is_int($value)) && preg_match('/^[0-9]+$/D', (string) $value)) {
            try {
                $number = BigInteger::of($value)->toInt();
                if ($number >= 1) {
                    return $number;
                }
            } catch (MathException $exception) {
                // Convert overflow to a field validation error, not a server error.
            }
        }
        throw ValidationException::withMessages([$key => ['Se requiere un entero mayor o igual a 1.']]);
    }
}
