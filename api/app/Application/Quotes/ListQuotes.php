<?php

declare(strict_types=1);

namespace App\Application\Quotes;

use App\Domain\Quotes\{Currency, ExchangeRates, MissingExchangeRate, Money};
use Brick\Math\BigInteger;
use Illuminate\Support\Facades\DB;

final class ListQuotes
{
    public function list(ListQuoteOptions $options): array
    {
        $query = DB::table('quotes');
        if ($options->client !== '') {
            $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($options->client));
            $query->whereRaw("client_search LIKE ? ESCAPE '!'", ['%'.$literal.'%']);
        }
        $count = (clone $query)->count();
        $rows = $query->select('id', 'budget_id', 'client', 'event', 'event_lines', 'currency', 'total_minor', 'exchange_rates')
            ->orderBy('id')->offset($options->offset())->limit($options->perPage)->get();
        $data = [];
        foreach ($rows as $row) {
            $original = Money::fromMinor(BigInteger::of($row->total_minor), Currency::from($row->currency));
            $rates = new ExchangeRates(json_decode($row->exchange_rates, true, 64, JSON_THROW_ON_ERROR));
            try {
                $converted = $rates->convert($original, $options->currency)->decimal();
            } catch (MissingExchangeRate $exception) {
                $converted = null;
            }
            $data[] = [
                'id' => (int) $row->id, 'budget_id' => $row->budget_id, 'client' => $row->client,
                'event' => $row->event, 'event_lines' => json_decode($row->event_lines, true, 64, JSON_THROW_ON_ERROR),
                'original_currency' => $original->currency->value, 'original_total' => $original->decimal(),
                'currency' => $options->currency->value, 'total' => $converted,
            ];
        }

        return ['data' => $data, 'meta' => [
            'page' => $options->page, 'per_page' => $options->perPage,
            'total' => $count, 'last_page' => max(1, intdiv($count, $options->perPage) + ($count % $options->perPage === 0 ? 0 : 1)),
        ]];
    }
}
