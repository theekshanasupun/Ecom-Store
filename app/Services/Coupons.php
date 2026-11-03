<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;

/**
 * Coupon engine. Scopes: whole cart, specific products, specific departments.
 * Extras: sale-item exclusion, min spend / qty, usage + per-customer limits, first-order only,
 * max discount cap, free-shipping codes and automatic (no code needed) discounts.
 */
final class Coupons
{
    /** @return array{0:?Coupon,1:?string} */
    public static function check(string $code, int $subtotal, array $lines = [], ?string $email = null, ?int $userId = null): array
    {
        $c = Coupon::where('code', strtoupper(trim($code)))->first();
        if (! $c || $c->status !== 'active') return [null, 'That code is not valid.'];

        return self::validate($c, $subtotal, $lines, $email, $userId);
    }

    /** @return array{0:?Coupon,1:?string} */
    public static function validate(Coupon $c, int $subtotal, array $lines = [], ?string $email = null, ?int $userId = null): array
    {
        if ($c->status !== 'active') return [null, 'That code is not valid.'];
        if ($c->starts_at && $c->starts_at->isFuture()) return [null, 'That code is not active yet.'];
        if ($c->ends_at && $c->ends_at->isPast()) return [null, 'That code has expired.'];
        if ($c->max_uses > 0 && $c->used >= $c->max_uses) return [null, 'That code has been fully used.'];
        if ($c->min_total > $subtotal) return [null, 'Spend '.money($c->min_total).' or more to use this code.'];
        if ($lines) {
            $elig = self::eligible($c, $lines);
            if (! $elig) return [null, $c->scope === 'cart' ? 'No items in your cart qualify for this code.' : 'This code does not apply to the items in your cart.'];
            if ($c->min_qty > 0 && array_sum(array_column($elig, 'qty')) < $c->min_qty) return [null, 'Add at least '.$c->min_qty.' qualifying items to use this code.'];
        }
        if ($email || $userId) {
            $past = Order::where('status', '<>', 'cancelled')->where(fn ($q) => $q->when($userId, fn ($x) => $x->orWhere('user_id', $userId))->when($email, fn ($x) => $x->orWhere('email', mb_strtolower($email))));
            if ($c->first_order_only && (clone $past)->exists()) return [null, 'This code is for first orders only.'];
            if ($c->per_user_limit > 0 && (clone $past)->where('coupon_code', $c->code)->count() >= $c->per_user_limit) return [null, 'You have already used this code the maximum number of times.'];
        }

        return [$c, null];
    }

    /** Cart lines this coupon applies to. */
    public static function eligible(Coupon $c, array $lines): array
    {
        $out = [];
        $pids = $c->productIds();
        $cids = $c->categoryIds();
        foreach ($lines as $l) {
            if (($l['qty'] ?? 0) <= 0) continue;
            $p = $l['p'];
            if ($c->exclude_sale && $p->compare_price > $p->price) continue;
            if ($c->scope === 'products' && ! in_array((int) $p->id, $pids, true)) continue;
            if ($c->scope === 'categories' && ! in_array((int) $p->category_id, $cids, true)) continue;
            $out[] = $l;
        }

        return $out;
    }

    public static function amount(Coupon $c, int $subtotal, array $lines = []): int
    {
        $base = $lines ? array_sum(array_column(self::eligible($c, $lines), 'total')) : $subtotal;
        $d = $c->type === 'percent' ? (int) round($base * min(100, $c->value) / 100) : (int) $c->value;
        if ($c->max_discount > 0) $d = min($d, (int) $c->max_discount);

        return max(0, min($d, $base));
    }

    /** Best automatic discount for this cart, if any. */
    public static function auto(int $subtotal, array $lines, ?string $email = null, ?int $userId = null): ?Coupon
    {
        $best = null;
        $bestAmt = -1;
        foreach (Coupon::where('auto_apply', true)->where('status', 'active')->get() as $c) {
            [$ok] = self::validate($c, $subtotal, $lines, $email, $userId);
            if (! $ok) continue;
            $a = self::amount($c, $subtotal, $lines) + ($c->free_shipping ? 1 : 0);
            if ($a > $bestAmt) { $best = $c; $bestAmt = $a; }
        }

        return $best;
    }
}
