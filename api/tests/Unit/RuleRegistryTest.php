<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Quotes\{CalculationState, Currency, ExchangeRates, InvalidRuleConfiguration, Money, PricingRule, QuoteCalculator, QuoteItem, RuleRegistry, RuleResult};
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RuleRegistryTest extends TestCase
{
    /** @dataProvider invalidConfigurations */
    public function test_invalid_configuration_has_a_local_field_path(array $parameters, string $field): void
    {
        try {
            RuleRegistry::standard()->create($parameters);
            self::fail('Expected invalid configuration.');
        } catch (InvalidRuleConfiguration $exception) {
            self::assertSame($field, $exception->field);
        }
    }

    public static function invalidConfigurations(): array
    {
        return [
            [[], 'type'], [['type' => 'unknown'], 'type'], [['type' => []], 'type'],
            [['type' => 'quantity_promo', 'category' => 'a', 'buy' => 0, 'pay' => 0], 'buy'],
            [['type' => 'quantity_promo', 'category' => 'a', 'buy' => '3', 'pay' => 2], 'buy'],
            [['type' => 'quantity_promo', 'category' => 'a', 'buy' => 3, 'pay' => 4], 'pay'],
            [['type' => 'quantity_promo', 'category' => 'a', 'buy' => 3, 'pay' => -1], 'pay'],
            [['type' => 'category_discount', 'category' => '', 'percent' => 10], 'category'],
            [['type' => 'category_discount', 'category' => 'a', 'percent' => -1], 'percent'],
            [['type' => 'percentage_discount', 'percent' => 101], 'percent'],
            [['type' => 'percentage_discount', 'percent' => true], 'percent'],
            [['type' => 'percentage_discount', 'percent' => 1.5], 'percent'],
            [['type' => 'percentage_discount', 'percent' => 'NaN'], 'percent'],
            [['type' => 'percentage_discount'], 'percent'],
            [['type' => 'fixed_discount', 'currency' => 'BRL', 'amount' => 1], 'currency'],
            [['type' => 'fixed_discount', 'currency' => 'ARS', 'amount' => -1], 'amount'],
            [['type' => 'fixed_discount', 'currency' => 'ARS', 'amount' => '0.001'], 'amount'],
            [['type' => 'discount_cap', 'max_percent' => -1], 'max_percent'],
            [['type' => 'discount_cap', 'max_percent' => 101], 'max_percent'],
            [['type' => 'tax', 'name' => '', 'percent' => 21], 'name'],
            [['type' => 'tax', 'name' => 'IVA', 'percent' => -1], 'percent'],
        ];
    }

    public function test_new_rule_requires_no_engine_or_existing_rule_changes(): void
    {
        $standard = RuleRegistry::standard();
        $registry = $standard->with('service_fee', fn (array $parameters) => new class implements PricingRule {
            public function type(): string
            {
                return 'service_fee';
            }

            public function apply(CalculationState $state, ExchangeRates $rates): RuleResult
            {
                return new RuleResult($state->addTax(Money::of(5, $state->subtotal->currency)), $state->total(), 'Servicio');
            }
        });
        $result = (new QuoteCalculator())->calculate(
            [new QuoteItem(1, 'Item', 'a', 1, Money::of(100, Currency::ARS))], Currency::ARS, new ExchangeRates([]),
            [$registry->create(['type' => 'service_fee']), $registry->create(['type' => 'percentage_discount', 'percent' => 10])],
        );
        self::assertSame('94.50', $result->state->total()->decimal());
        self::assertSame('service_fee', $result->breakdown[0]->type);
        $this->expectException(InvalidRuleConfiguration::class);
        $standard->create(['type' => 'service_fee']);
    }

    public function test_existing_rule_cannot_be_silently_overwritten(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RuleRegistry::standard()->with('tax', fn () => null);
    }

    public function test_invalid_factory_result_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RuleRegistry::standard()->with('bad_factory', fn () => null)->create(['type' => 'bad_factory']);
    }

    public function test_decimal_parameters_have_stable_labels_without_float_formatting(): void
    {
        $rule = RuleRegistry::standard()->create(['type' => 'percentage_discount', 'percent' => '12.5000']);
        $state = CalculationState::start([], Currency::ARS, new ExchangeRates([]));
        self::assertSame('12.5% general', $rule->apply($state, new ExchangeRates([]))->label);
    }
}
