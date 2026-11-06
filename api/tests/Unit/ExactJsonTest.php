<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\{ExactJson, JsonNumber};
use PHPUnit\Framework\TestCase;

final class ExactJsonTest extends TestCase
{
    public function test_roundtrip_preserves_number_lexemes_strings_and_empty_container_types(): void
    {
        $input = '{"price":9007199254740993.01,"rate":1.235e-3,"integer":12,"text":"12.50 and \\\"9\\\"","empty":{},"list":[],"bool":true,"nil":null}';
        // Use a separately encoded text to exercise escaped quotes without confusing PHP escaping.
        $input = str_replace('"text":"12.50 and \\\"9\\\""', '"text":'.json_encode('12.50 and "9"'), $input);
        $codec = new ExactJson();
        $value = $codec->decode($input);
        self::assertInstanceOf(JsonNumber::class, $value->price);
        self::assertSame('9007199254740993.01', $value->price->value);
        self::assertSame('1.235e-3', $value->rate->value);
        self::assertSame('12.50 and "9"', $value->text);
        self::assertInstanceOf(\stdClass::class, $value->empty);
        self::assertSame([], $value->list);
        self::assertSame($input, $codec->encode($value));
    }

    /** @dataProvider invalidJson */
    public function test_invalid_or_ambiguous_json_is_rejected(string $json): void
    {
        $this->expectException(\Exception::class);
        (new ExactJson())->decode($json);
    }

    public static function invalidJson(): array
    {
        return [['{"a":01}'], ['{"a":1,}'], ['{"a":1,"a":2}'], ['{"a":1e999}'], ['{"a":NaN}'], ['{"a":"unterminated}']];
    }

    public function test_encoder_rejects_float_instead_of_silently_losing_precision(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ExactJson())->encode(['total' => 0.1]);
    }

    public function test_money_token_keeps_two_decimal_places(): void
    {
        self::assertSame('{"total":10.00}', (new ExactJson())->encode(['total' => new JsonNumber('10.00')]));
    }
}
