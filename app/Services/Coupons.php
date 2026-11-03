<?php

namespace App\Services;

use App\Models\Coupon;

final class Coupons
{
    /** @return array{0:?Coupon,1:?string} */
    public static function check(string $code, int $subtotal): array
    {
        $c = Coupon::where('code', strtoupper(trim($code)))->first();
        if (! $c || $c->status !== 'active') return [null, 'That code is not valid.'];
        if ($c->starts_at && $c->starts_at->isFuture()) return [null, 'That code is not active yet.'];
        if ($c->ends_at && $c->ends_at->isPast()) return [null, 'That code has expired.'];
        if ($c->max_uses > 0 && $c->used >= $c->max_uses) return [null, 'That code has been fully used.'];
        if ($c->min_total > $subtotal) return [null, 'Spend '.money($c->min_total).' or more to use this code.'];

        return [$c, null];
    }

    public static function amount(Coupon $c, int $subtotal): int
    {
        $d = $c->type === 'percent' ? (int) round($subtotal * min(100, $c->value) / 100) : $c->value;

        return max(0, min($d, $subtotal));
    }
}
