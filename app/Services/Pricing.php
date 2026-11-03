<?php

namespace App\Services;

use App\Models\DiscountRule;
use App\Models\Product;

/**
 * Automatic price rules (v2.6): sitewide / product / department discounts, audience targeting,
 * schedules and quantity tiers. Applied on the storefront only — admin screens always see raw prices.
 */
final class Pricing
{
    private static ?array $rules = null;

    public static function forget(): void
    {
        self::$rules = null;
    }

    /** Storefront requests only (admin edits must never persist a discounted price). */
    public static function active(): bool
    {
        try {
            return ! request()->is('admin', 'admin/*') || request()->is('admin/builder/preview', 'admin/email/preview');
        } catch (\Throwable) {
            return true;
        }
    }

    /** @return DiscountRule[] */
    public static function rules(): array
    {
        if (self::$rules === null) {
            try {
                self::$rules = DiscountRule::where('status', 'active')
                    ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                    ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                    ->orderByDesc('priority')->orderBy('id')->get()->all();
            } catch (\Throwable) {
                self::$rules = [];
            }
        }

        return self::$rules;
    }

    private static function applies(DiscountRule $r, Product $p, int $price, int $compare, int $qty): bool
    {
        if ($qty < max(1, (int) $r->min_qty)) return false;
        if ($r->audience === 'guests' && auth()->check()) return false;
        if ($r->audience === 'customers' && ! auth()->check()) return false;
        if ($r->skip_sale && $compare > $price) return false;
        if ($r->scope === 'products') return in_array((int) $p->id, $r->productIds(), true);
        if ($r->scope === 'categories') return in_array((int) $p->category_id, $r->categoryIds(), true);

        return true;
    }

    /**
     * Best (lowest) price for a product at a given quantity.
     * @return array{0:int,1:?string,2:?int} [price, badge label, rule id]
     */
    public static function discounted(Product $p, int $price, int $compare = 0, int $qty = 1): array
    {
        if ($price <= 0 || ! $p->exists || ! self::active()) return [$price, null, null];
        $best = [$price, null, null];
        foreach (self::rules() as $r) {
            if (! self::applies($r, $p, $price, $compare, $qty)) continue;
            $new = $r->type === 'percent' ? (int) round($price * (100 - min(100, (int) $r->value)) / 100) : max(0, $price - (int) $r->value);
            if ($new < $best[0]) $best = [$new, $r->label ?: ($r->type === 'percent' ? (int) $r->value.'% off' : null), (int) $r->id];
        }

        return $best;
    }

    /** Quantity-tier rules that could apply to this product (for "buy 3, save 10%" hints). */
    public static function tiers(Product $p): array
    {
        $out = [];
        foreach (self::rules() as $r) {
            if ((int) $r->min_qty <= 1) continue;
            if ($r->audience === 'guests' && auth()->check()) continue;
            if ($r->audience === 'customers' && ! auth()->check()) continue;
            if ($r->scope === 'products' && ! in_array((int) $p->id, $r->productIds(), true)) continue;
            if ($r->scope === 'categories' && ! in_array((int) $p->category_id, $r->categoryIds(), true)) continue;
            $out[] = ['qty' => (int) $r->min_qty, 'type' => $r->type, 'value' => (int) $r->value, 'label' => $r->label];
        }
        usort($out, fn ($a, $b) => $a['qty'] <=> $b['qty']);

        return $out;
    }

    /** Called when a product is loaded on the storefront: bakes the 1-unit discount into price / compare_price. */
    public static function apply(Product $p): void
    {
        if (! self::active() || ! self::rules()) return;
        $price = (int) $p->getRawOriginal('price');
        $compare = (int) $p->getRawOriginal('compare_price');
        [$new, $label, $rule] = self::discounted($p, $price, $compare, 1);
        $p->disc = ['orig' => $price, 'orig_compare' => $compare, 'label' => $label, 'rule' => $rule, 'on' => $new < $price];
        if ($new < $price) {
            $p->setAttribute('price', $new);
            $p->setAttribute('compare_price', max($compare, $price));
            if (! $p->badge) $p->setAttribute('badge', $label ?: '');
        }
    }
}
