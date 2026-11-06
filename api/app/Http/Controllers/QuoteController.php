<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Quotes\{ImportQuotes, ListQuoteOptions, ListQuotes, PrepareQuote, QuoteReader, QuoteStore};
use App\Http\QuoteResponse;
use App\Support\ExactJson;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\Response;

final class QuoteController extends Controller
{
    public function store(Request $request, PrepareQuote $prepare, QuoteStore $store): Response
    {
        return QuoteResponse::detail($store->create($prepare->prepare($this->input($request))), 201);
    }

    public function batch(Request $request, ImportQuotes $import): Response
    {
        return QuoteResponse::detail($import->import($this->input($request)), 201);
    }

    public function index(Request $request, ListQuotes $list): Response
    {
        return QuoteResponse::detail($list->list(ListQuoteOptions::fromQuery($request->query())));
    }

    private function input(Request $request): mixed
    {
        try {
            return (new ExactJson())->decode($request->getContent());
        } catch (JsonException | InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['body' => ['JSON inválido o fuera de los límites admitidos.']]);
        }
    }

    public function show(string $id, QuoteReader $reader): Response
    {
        if (!ctype_digit($id) || strlen($id) > strlen((string) PHP_INT_MAX) || (strlen($id) === strlen((string) PHP_INT_MAX) && strcmp($id, (string) PHP_INT_MAX) > 0)) {
            abort(404);
        }
        $snapshot = $reader->find((int) $id);
        abort_if($snapshot === null, 404);

        return QuoteResponse::detail($snapshot);
    }
}
