<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use stdClass;

final class ExactJson
{
    public function decode(string $json): mixed
    {
        // Native decoder validates syntax/UTF-8/depth; its numeric values are never used.
        json_decode($json, false, 64, JSON_THROW_ON_ERROR);
        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"|-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?|true|false|null|[{}\[\]:,]/u', $json, $matches);
        $position = 0;

        return $this->read($matches[0], $position);
    }

    private function read(array $tokens, int &$position): mixed
    {
        $token = $tokens[$position++];
        if ($token === '{') {
            $object = new stdClass();
            if ($tokens[$position] === '}') {
                $position++;
                return $object;
            }
            do {
                $key = json_decode($tokens[$position++], true, 64, JSON_THROW_ON_ERROR);
                if (property_exists($object, $key)) {
                    throw new InvalidArgumentException('No se admiten claves JSON duplicadas.');
                }
                $position++; // colon, already syntax-checked
                $object->{$key} = $this->read($tokens, $position);
            } while ($tokens[$position++] === ',');

            return $object;
        }
        if ($token === '[') {
            $array = [];
            if ($tokens[$position] === ']') {
                $position++;
                return $array;
            }
            do {
                $array[] = $this->read($tokens, $position);
            } while ($tokens[$position++] === ',');

            return $array;
        }
        if ($token[0] === '"' || in_array($token, ['true', 'false', 'null'], true)) {
            return json_decode($token, true, 64, JSON_THROW_ON_ERROR);
        }

        return JsonNumber::fromInput($token);
    }

    public function encode(mixed $value): string
    {
        if ($value instanceof JsonNumber) {
            return $value->value;
        }
        if (is_array($value) || $value instanceof stdClass) {
            $list = is_array($value) && array_is_list($value);
            $parts = [];
            foreach ($value as $key => $item) {
                $parts[] = ($list ? '' : json_encode((string) $key, JSON_THROW_ON_ERROR).':').$this->encode($item);
            }

            return ($list ? '[' : '{').implode(',', $parts).($list ? ']' : '}');
        }
        if (is_float($value)) {
            throw new InvalidArgumentException('No se serializan floats en el contrato monetario.');
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
