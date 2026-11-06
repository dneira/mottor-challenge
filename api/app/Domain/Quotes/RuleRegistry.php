<?php

declare(strict_types=1);

namespace App\Domain\Quotes;

use App\Domain\Quotes\Rules\{CategoryDiscount, DiscountCap, FixedDiscount, PercentageDiscount, QuantityPromo, Tax};
use InvalidArgumentException;

final readonly class RuleRegistry
{
    /** @param array<string, callable(array): PricingRule> $factories */
    public function __construct(private array $factories)
    {
    }

    public static function standard(): self
    {
        return new self([
            'quantity_promo' => fn (array $p) => new QuantityPromo(
                RuleParameters::text($p['category'] ?? null, 'category'),
                RuleParameters::integer($p['buy'] ?? null, 'buy', 1),
                RuleParameters::integer($p['pay'] ?? null, 'pay'),
            ),
            'category_discount' => fn (array $p) => new CategoryDiscount(
                RuleParameters::text($p['category'] ?? null, 'category'),
                RuleParameters::decimal($p['percent'] ?? null, 'percent', 100),
            ),
            'percentage_discount' => fn (array $p) => new PercentageDiscount(RuleParameters::decimal($p['percent'] ?? null, 'percent', 100)),
            'fixed_discount' => fn (array $p) => new FixedDiscount(RuleParameters::money($p['amount'] ?? null, $p['currency'] ?? null)),
            'discount_cap' => fn (array $p) => new DiscountCap(RuleParameters::decimal($p['max_percent'] ?? null, 'max_percent', 100)),
            'tax' => fn (array $p) => new Tax(
                RuleParameters::text($p['name'] ?? null, 'name'),
                RuleParameters::decimal($p['percent'] ?? null, 'percent'),
            ),
        ]);
    }

    public function with(string $type, callable $factory): self
    {
        RuleParameters::text($type, 'type');
        if (isset($this->factories[$type])) {
            throw new InvalidArgumentException('El tipo ya está registrado.');
        }

        return new self([...$this->factories, $type => $factory]);
    }

    public function create(array $parameters): PricingRule
    {
        $type = RuleParameters::text($parameters['type'] ?? null, 'type');
        $factory = $this->factories[$type] ?? null;
        if ($factory === null) {
            throw new InvalidRuleConfiguration('type', "Tipo de regla desconocido: {$type}.");
        }
        $rule = $factory($parameters);
        if (!$rule instanceof PricingRule || $rule->type() !== $type) {
            throw new InvalidArgumentException('La fábrica debe devolver una regla del tipo registrado.');
        }

        return $rule;
    }
}
