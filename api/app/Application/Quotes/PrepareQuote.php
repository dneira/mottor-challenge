<?php

declare(strict_types=1);

namespace App\Application\Quotes;

use App\Domain\Quotes\{Currency, ExchangeRates, InvalidRuleConfiguration, MissingExchangeRate, Money, QuoteCalculator, QuoteItem, RuleRegistry};
use App\Support\{ExactJson, JsonNumber};
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Illuminate\Validation\ValidationException;
use stdClass;

final class PrepareQuote
{
    public function prepare(mixed $input): PreparedQuote
    {
        $root = $this->object($input, 'body');
        $budget = $this->object($root->budget ?? null, 'budget');
        $id = $this->text($budget->id ?? null, 'budget.id', true, 255);
        $client = $this->text($budget->client ?? null, 'budget.client', true, 255);
        $event = $this->text($budget->event ?? null, 'budget.event');
        $currency = $this->currency($root->currency ?? null, 'currency');
        $rateObject = $this->object($root->exchange_rates ?? null, 'exchange_rates');
        $rates = [];
        foreach ($rateObject as $pair => $value) {
            $path = 'exchange_rates.'.$pair;
            if (!preg_match('/^(ARS|USD|EUR)_(ARS|USD|EUR)$/D', $pair, $matches) || $matches[1] === $matches[2]) {
                $this->fail($path, 'Par de monedas inválido.');
            }
            $rate = $this->number($value, $path);
            if (BigDecimal::of($rate)->compareTo(0) <= 0) {
                $this->fail($path, 'La tasa debe ser mayor que cero.');
            }
            $rates[$pair] = $rate;
        }
        $exchange = new ExchangeRates($rates);
        $items = [];
        $ids = [];
        foreach ($this->list($budget->items ?? null, 'budget.items') as $index => $data) {
            $path = 'budget.items.'.$index;
            $item = $this->object($data, $path);
            $itemId = $this->integer($item->id ?? null, $path.'.id');
            if (isset($ids[$itemId])) {
                $this->fail($path.'.id', 'El ID de ítem está duplicado.');
            }
            $ids[$itemId] = true;
            $quantity = $this->integer($item->quantity ?? null, $path.'.quantity');
            if ($quantity < 0) {
                $this->fail($path.'.quantity', 'La cantidad no puede ser negativa.');
            }
            $unit = $this->money($item->unit_price ?? null, $this->currency($item->currency ?? null, $path.'.currency'), $path.'.unit_price');
            $items[] = new QuoteItem($itemId, $this->text($item->description ?? null, $path.'.description'), $this->text($item->category ?? null, $path.'.category', true), $quantity, $unit);
            try {
                $exchange->convert($unit->times($quantity), $currency);
            } catch (MissingExchangeRate $exception) {
                $this->fail('exchange_rates', $exception->getMessage());
            }
        }
        $rules = [];
        foreach ($this->list($root->rules ?? null, 'rules') as $index => $data) {
            $path = 'rules.'.$index;
            $parameters = (array) $this->object($data, $path);
            foreach (['percent', 'max_percent', 'amount'] as $key) {
                if (array_key_exists($key, $parameters)) {
                    $parameters[$key] = $this->number($parameters[$key], $path.'.'.$key);
                }
            }
            foreach (['buy', 'pay'] as $key) {
                if (array_key_exists($key, $parameters)) {
                    $parameters[$key] = $this->integer($parameters[$key], $path.'.'.$key);
                }
            }
            try {
                $rule = RuleRegistry::standard()->create($parameters);
                if ($rule instanceof \App\Domain\Quotes\Rules\FixedDiscount) {
                    $exchange->convert($rule->amount, $currency);
                }
                $rules[] = $rule;
            } catch (InvalidRuleConfiguration $exception) {
                $this->fail($path.'.'.$exception->field, $exception->getMessage());
            } catch (MissingExchangeRate $exception) {
                $this->fail('exchange_rates', $exception->getMessage());
            }
        }
        $result = (new QuoteCalculator())->calculate($items, $currency, $exchange, $rules)->snapshot();
        foreach (['subtotal', 'total'] as $field) {
            if (BigDecimal::of($result[$field])->compareTo('9999999999999999999999999999.99') > 0) {
                $this->fail($field, 'El importe supera la capacidad de almacenamiento.');
            }
        }
        $snapshot = ['budget_id' => $id, 'client' => $client, 'event' => $event,
            'event_lines' => (new \App\Domain\Quotes\TicketLines())->wrap($event), 'exchange_rates' => $rates] + $result;

        return new PreparedQuote($snapshot, (new ExactJson())->encode($root));
    }

    private function object(mixed $value, string $path): stdClass
    {
        if (!$value instanceof stdClass) {
            $this->fail($path, 'Se requiere un objeto JSON.');
        }
        return $value;
    }

    private function list(mixed $value, string $path): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            $this->fail($path, 'Se requiere una lista JSON.');
        }
        return $value;
    }

    private function text(mixed $value, string $path, bool $required = false, ?int $max = null): string
    {
        if (!is_string($value) || ($required && trim($value) === '') || ($max !== null && mb_strlen($value) > $max)) {
            $this->fail($path, 'Texto inválido.');
        }
        return $value;
    }

    private function number(mixed $value, string $path): string
    {
        if (!$value instanceof JsonNumber) {
            $this->fail($path, 'Se requiere un número JSON, no texto.');
        }
        return $value->value;
    }

    private function integer(mixed $value, string $path): int
    {
        $number = $this->number($value, $path);
        try {
            return BigDecimal::of($number)->toBigInteger()->toInt();
        } catch (MathException $exception) {
            $this->fail($path, 'Se requiere un entero dentro del rango admitido.');
        }
    }

    private function currency(mixed $value, string $path): Currency
    {
        $currency = is_string($value) ? Currency::tryFrom($value) : null;
        if ($currency === null) {
            $this->fail($path, 'Moneda no admitida.');
        }
        return $currency;
    }

    private function money(mixed $value, Currency $currency, string $path): Money
    {
        $number = $this->number($value, $path);
        try {
            $money = Money::of($number, $currency);
        } catch (MathException $exception) {
            $this->fail($path, 'El importe debe poder expresarse en centavos.');
        }
        if ($money->minor()->isNegative()) {
            $this->fail($path, 'El importe no puede ser negativo.');
        }
        if ($money->rational()->compareTo('9999999999999999999999999999.99') > 0) {
            $this->fail($path, 'El precio unitario supera el rango admitido.');
        }
        return $money;
    }

    private function fail(string $path, string $message): never
    {
        throw ValidationException::withMessages([$path => [$message]]);
    }
}
