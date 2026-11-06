<?php

declare(strict_types=1);

namespace App\Application\Quotes;

use Illuminate\Validation\ValidationException;
use stdClass;

final class ImportQuotes
{
    public function __construct(private PrepareQuote $prepare, private QuoteStore $store)
    {
    }

    public function import(mixed $input): array
    {
        if (!$input instanceof stdClass || !isset($input->quotes) || !is_array($input->quotes)
            || !array_is_list($input->quotes) || count($input->quotes) < 1 || count($input->quotes) > 500) {
            throw ValidationException::withMessages(['quotes' => ['Se requiere una lista de entre 1 y 500 presupuestos.']]);
        }
        $prepared = [];
        $errors = [];
        foreach ($input->quotes as $index => $quote) {
            try {
                $prepared[] = $this->prepare->prepare($quote);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $path => $messages) {
                    $errors['quotes.'.$index.($path === 'body' ? '' : '.'.$path)] = $messages;
                }
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return ['imported' => count($prepared), 'quotes' => $this->store->createBatch($prepared)];
    }
}
