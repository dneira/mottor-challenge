<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Quotes\{CalculationResult, CalculationState, Currency, ExchangeRates, Money, QuoteCalculator, QuoteItem, TicketLines};
use App\Domain\Quotes\Rules\{CategoryDiscount, DiscountCap, FixedDiscount, PercentageDiscount, QuantityPromo, Tax};
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\QuoteFixture;

final class QuoteCalculatorTest extends TestCase
{
    public function test_official_case_matches_every_calculation_field_and_label(): void
    {
        $expected = QuoteFixture::read('official.expected');
        foreach (['subtotal', 'total'] as $field) {
            $expected[$field] = Money::of($expected[$field], Currency::ARS)->decimal();
        }
        foreach ($expected['items'] as &$item) {
            $item['id'] = (int) $item['id'];
            $item['quantity'] = (int) $item['quantity'];
            foreach (['unit_price', 'line_total'] as $field) {
                $item[$field] = Money::of($item[$field], Currency::ARS)->decimal();
            }
        }
        unset($item);
        foreach ($expected['breakdown'] as &$step) {
            $step['step'] = (int) $step['step'];
            foreach (['base', 'amount', 'running_total'] as $field) {
                $step[$field] = Money::of($step[$field], Currency::ARS)->decimal();
            }
        }
        unset($step);

        $result = QuoteFixture::calculate('official');
        $actual = $result->snapshot();
        $expected = array_intersect_key($expected, $actual);
        // Key order in a JSON object is irrelevant, field values and ordered arrays are exact.
        foreach ($actual as $field => $value) {
            self::assertSame($expected[$field], $value, $field);
        }
        $published = QuoteFixture::read('official.expected');
        self::assertSame($published['event_lines'], (new TicketLines())->wrap($published['event']));
        $this->assertInvariants($result);
    }

    public function test_second_case_matches_independent_decimal_calculation(): void
    {
        $result = QuoteFixture::calculate('second');
        self::assertSame('7927225.00', $result->state->subtotal->decimal());
        self::assertSame('7832871.49', $result->state->total()->decimal());
        self::assertSame(
            ['-119500.00', '-391500.00', '-927028.13', '-143735.00', '1332546.99', '154862.63'],
            array_map(fn ($step) => $step->amount->decimal(), $result->breakdown),
        );
        $this->assertInvariants($result);
    }

    public function test_category_after_fixed_discount_uses_allocated_current_balance(): void
    {
        $items = [$this->item(1, 'a', 1, '100'), $this->item(2, 'b', 1, '100')];
        $fixed = new FixedDiscount(Money::of(20, Currency::ARS));
        $category = new CategoryDiscount('a', 50);
        $first = $this->calculate($items, [$fixed, $category]);
        $second = $this->calculate($items, [$category, $fixed]);
        self::assertSame('90.00', $first->breakdown[1]->base->decimal());
        self::assertSame('135.00', $first->state->total()->decimal());
        self::assertSame('130.00', $second->state->total()->decimal());
        self::assertSame('100.00', $first->snapshot()['items'][0]['line_total']);
        $this->assertInvariants($first);
        $this->assertInvariants($second);
    }

    public function test_tax_and_cap_order_is_observable_without_retroactive_recalculation(): void
    {
        $items = [$this->item()];
        $discount = new PercentageDiscount(50);
        $tax = new Tax('IVA', 20);
        $cap = new DiscountCap(20);
        $taxFirst = $this->calculate($items, [$discount, $tax, $cap]);
        $capFirst = $this->calculate($items, [$discount, $cap, $tax]);
        self::assertSame('90.00', $taxFirst->state->total()->decimal());
        self::assertSame('10.00', $taxFirst->state->taxes->decimal());
        self::assertSame('96.00', $capFirst->state->total()->decimal());
        self::assertSame('16.00', $capFirst->state->taxes->decimal());
        $this->assertInvariants($taxFirst);
        $this->assertInvariants($capFirst);
    }

    public function test_cap_is_idempotent_but_does_not_limit_future_discounts(): void
    {
        $result = $this->calculate([$this->item()], [new PercentageDiscount(50), new DiscountCap(20), new DiscountCap(20), new PercentageDiscount(50)]);
        self::assertSame('0.00', $result->breakdown[2]->amount->decimal());
        self::assertSame('40.00', $result->state->total()->decimal());
        $this->assertInvariants($result);
    }

    public function test_cap_refunds_only_discounted_items_before_next_category_rule(): void
    {
        $result = $this->calculate([$this->item(1, 'a'), $this->item(2, 'b')], [new CategoryDiscount('a', 100), new DiscountCap(25), new CategoryDiscount('b', 10)]);
        self::assertSame('50.00', $result->state->balances[0]->decimal());
        self::assertSame('90.00', $result->state->balances[1]->decimal());
        self::assertSame('100.00', $result->breakdown[2]->base->decimal());
        $this->assertInvariants($result);
    }

    public function test_percentage_after_tax_uses_total_but_cannot_consume_tax_balance(): void
    {
        $result = $this->calculate([$this->item()], [new Tax('IVA', 20), new PercentageDiscount(100)]);
        self::assertSame('120.00', $result->breakdown[1]->base->decimal());
        self::assertSame('-100.00', $result->breakdown[1]->amount->decimal());
        self::assertSame('20.00', $result->state->total()->decimal());
        $this->assertInvariants($result);
    }

    public function test_fixed_discount_clamps_to_balance_and_preserves_tax(): void
    {
        $result = $this->calculate([$this->item()], [new Tax('IVA', 21), new FixedDiscount(Money::of(1000, Currency::ARS))]);
        self::assertSame('21.00', $result->state->total()->decimal());
        $this->assertInvariants($result);
    }

    public function test_zero_effect_rules_still_appear_in_original_order(): void
    {
        $result = $this->calculate([$this->item()], [new QuantityPromo('absent', 3, 2), new CategoryDiscount('absent', 15), new DiscountCap(0), new Tax('IVA', 0)]);
        self::assertSame(['quantity_promo', 'category_discount', 'discount_cap', 'tax'], array_map(fn ($step) => $step->type, $result->breakdown));
        self::assertSame(['0.00', '0.00', '0.00', '0.00'], array_map(fn ($step) => $step->amount->decimal(), $result->breakdown));
        self::assertSame([1, 2, 3, 4], array_map(fn ($step) => $step->step, $result->breakdown));
    }

    public function test_promo_does_not_pool_items_or_discount_incomplete_groups(): void
    {
        $result = $this->calculate([$this->item(1, 'a', 2, '10'), $this->item(2, 'a', 4, '10')], [new QuantityPromo('a', 3, 2)]);
        self::assertSame('-10.00', $result->breakdown[0]->amount->decimal());
        self::assertSame('50.00', $result->state->total()->decimal());
    }

    public function test_promo_uses_current_balance_and_can_repeat(): void
    {
        $result = $this->calculate([$this->item(1, 'a', 3, '10')], [new PercentageDiscount(50), new QuantityPromo('a', 3, 2), new QuantityPromo('a', 3, 2)]);
        self::assertSame('15.00', $result->breakdown[1]->base->decimal());
        self::assertSame('-5.00', $result->breakdown[1]->amount->decimal());
        self::assertSame('-3.33', $result->breakdown[2]->amount->decimal());
        self::assertSame('6.67', $result->state->total()->decimal());
        $this->assertInvariants($result);
    }

    public function test_promo_rounds_aggregate_not_each_item_and_tie_break_is_stable(): void
    {
        $items = [$this->item(9, 'a', 2, '0.01'), $this->item(1, 'a', 2, '0.01')];
        $result = $this->calculate($items, [new PercentageDiscount(50), new QuantityPromo('a', 2, 1)]);
        self::assertSame('-0.01', $result->breakdown[1]->amount->decimal());
        self::assertSame('0.00', $result->state->balances[0]->decimal());
        self::assertSame('0.01', $result->state->balances[1]->decimal());
        $this->assertInvariants($result);
    }

    public function test_zero_quantity_and_full_free_promo(): void
    {
        $result = $this->calculate([$this->item(1, 'a', 0), $this->item(2, 'a', 3)], [new QuantityPromo('a', 3, 0)]);
        self::assertSame('0.00', $result->state->total()->decimal());
        $this->assertInvariants($result);
    }

    public function test_no_rules_and_no_items_produce_zero_without_steps(): void
    {
        $result = $this->calculate([], []);
        self::assertSame('0.00', $result->state->total()->decimal());
        self::assertSame([], $result->breakdown);
        $this->assertInvariants($result);
    }

    public function test_rules_are_reusable_and_do_not_mutate_input_state(): void
    {
        $rates = new ExchangeRates([]);
        $state = CalculationState::start([$this->item()], Currency::ARS, $rates);
        $rule = new PercentageDiscount(10);
        $after = $rule->apply($state, $rates);
        self::assertSame('100.00', $state->total()->decimal());
        self::assertSame('90.00', $after->state->total()->decimal());
        self::assertSame($after->state->total()->decimal(), $rule->apply($state, $rates)->state->total()->decimal());
    }

    public function test_duplicate_item_ids_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculate([$this->item(), $this->item()], []);
    }

    public function test_inverse_conversion_of_fixed_discount(): void
    {
        $result = (new QuoteCalculator())->calculate(
            [new QuoteItem(1, '', 'a', 1, Money::of(10, Currency::USD))], Currency::USD,
            new ExchangeRates(['USD_ARS' => '3']), [new FixedDiscount(Money::of(2, Currency::ARS))],
        );
        self::assertSame('9.33', $result->state->total()->decimal());
        self::assertSame('ARS 2', $result->breakdown[0]->label);
    }

    public function test_all_six_rule_orders_preserve_money_and_nonnegative_balances(): void
    {
        $items = [$this->item(1, 'a', 3, '0.07'), $this->item(2, 'b', 4, '0.11')];
        $rules = [
            new QuantityPromo('a', 3, 2), new CategoryDiscount('a', '12.5'),
            new PercentageDiscount('33.33'), new FixedDiscount(Money::of('0.03', Currency::ARS)),
            new DiscountCap(20), new Tax('IVA', 21),
        ];
        $orders = 0;
        foreach ($this->permutations($rules) as $order) {
            $result = $this->calculate($items, $order);
            self::assertSame(array_map(fn ($rule) => $rule->type(), $order), array_map(fn ($step) => $step->type, $result->breakdown));
            $this->assertInvariants($result);
            $orders++;
        }
        self::assertSame(720, $orders);
    }

    private function permutations(array $values): iterable
    {
        if ($values === []) {
            yield [];

            return;
        }
        foreach ($values as $index => $value) {
            $rest = $values;
            array_splice($rest, $index, 1);
            foreach ($this->permutations($rest) as $permutation) {
                yield [$value, ...$permutation];
            }
        }
    }

    private function item(int $id = 1, string $category = 'a', int $quantity = 1, string $price = '100'): QuoteItem
    {
        return new QuoteItem($id, 'Item '.$id, $category, $quantity, Money::of($price, Currency::ARS));
    }

    private function calculate(array $items, array $rules): CalculationResult
    {
        return (new QuoteCalculator())->calculate($items, Currency::ARS, new ExchangeRates([]), $rules);
    }

    private function assertInvariants(CalculationResult $result): void
    {
        $running = $result->state->subtotal;
        foreach ($result->breakdown as $step) {
            $running = $running->plus($step->amount);
            self::assertSame($running->decimal(), $step->runningTotal->decimal());
        }
        self::assertSame($running->decimal(), $result->state->total()->decimal());
        $subtotal = Money::of(0, $running->currency);
        foreach ($result->state->lineTotals as $index => $line) {
            $subtotal = $subtotal->plus($line);
            self::assertFalse($result->state->balances[$index]->minor()->isNegative());
            self::assertLessThanOrEqual(0, $result->state->balances[$index]->compareTo($line));
        }
        self::assertSame($subtotal->decimal(), $result->state->subtotal->decimal());
    }
}
