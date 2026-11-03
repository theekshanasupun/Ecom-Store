<?php

namespace App\Services;

final class Cart
{
    private static function data(): array
    {
        return session('cart', ['lines' => [], 'coupon' => null]);
    }

    private static function save(array $c): void
    {
        session(['cart' => $c]);
    }

    public static function key(int $pid, array $opts): string
    {
        ksort($opts);

        return $pid.':'.substr(md5(json_encode($opts)), 0, 8);
    }

    public static function add(int $pid, int $qty, array $opts = []): array
    {
        $p = Catalog::byId($pid);
        if (! $p) return ['ok' => false, 'error' => 'That product is no longer available.'];

        $clean = [];
        foreach ($p->options_list as $o) {
            $name = $o['name'] ?? '';
            $vals = $o['values'] ?? [];
            $pick = $opts[$name] ?? ($vals[0] ?? '');
            if ($vals && ! in_array($pick, $vals, true)) {
                return ['ok' => false, 'error' => 'Please choose a valid '.$name.'.'];
            }
            if ($name !== '') $clean[$name] = $pick;
        }
        $qty = max(1, min(99, $qty));
        $c = self::data();
        $k = self::key($pid, $clean);
        $have = $c['lines'][$k]['qty'] ?? 0;
        $max = $p->available();
        if ($max <= 0) return ['ok' => false, 'error' => $p->name.' is out of stock.'];
        $new = min($have + $qty, $max, 99);
        $c['lines'][$k] = ['pid' => $pid, 'qty' => $new, 'opts' => $clean];
        self::save($c);

        return ['ok' => true, 'message' => $new < $have + $qty ? 'Only '.$max.' available — cart updated.' : 'Added to cart', 'name' => $p->name];
    }

    public static function setQty(string $key, int $qty): void
    {
        $c = self::data();
        if (! isset($c['lines'][$key])) return;
        if ($qty <= 0) {
            unset($c['lines'][$key]);
        } else {
            $p = Catalog::byId((int) $c['lines'][$key]['pid']);
            $c['lines'][$key]['qty'] = max(1, min($qty, $p ? $p->available() : 1, 99));
        }
        self::save($c);
    }

    public static function remove(string $key): void
    {
        $c = self::data();
        unset($c['lines'][$key]);
        self::save($c);
    }

    public static function clear(): void
    {
        self::save(['lines' => [], 'coupon' => null]);
    }

    public static function count(): int
    {
        return array_sum(array_column(self::data()['lines'], 'qty'));
    }

    public static function setCoupon(?string $code): void
    {
        $c = self::data();
        $c['coupon'] = $code ? strtoupper(trim($code)) : null;
        self::save($c);
    }

    /** Hydrated cart + totals. */
    public static function summary(): array
    {
        $c = self::data();
        $lines = [];
        $physical = $digital = 0;
        $hasPhysical = false;
        foreach ($c['lines'] as $key => $l) {
            $p = Catalog::byId((int) $l['pid']);
            if (! $p) {
                unset($c['lines'][$key]);
                continue;
            }
            $max = $p->available();
            $qty = min((int) $l['qty'], max($max, 0));
            $isPhys = $p->type === 'physical';
            if ($qty <= 0) {
                $lines[] = ['key' => $key, 'p' => $p, 'qty' => 0, 'opts' => $l['opts'], 'unit' => $p->price, 'total' => 0, 'max' => 0, 'physical' => $isPhys, 'sold_out' => true];
                continue;
            }
            $total = $qty * $p->price;
            if ($isPhys) {
                $physical += $total;
                $hasPhysical = true;
            } else {
                $digital += $total;
            }
            $lines[] = ['key' => $key, 'p' => $p, 'qty' => $qty, 'opts' => $l['opts'], 'unit' => $p->price, 'total' => $total, 'max' => $max, 'physical' => $isPhys, 'sold_out' => false];
        }
        $subtotal = $physical + $digital;

        $coupon = null;
        $couponError = null;
        $discount = 0;
        if (! empty($c['coupon'])) {
            [$coupon, $couponError] = Coupons::check($c['coupon'], $subtotal);
            if ($coupon) {
                $discount = Coupons::amount($coupon, $subtotal);
            } else {
                $c['coupon'] = null;
            }
        }
        self::save($c);

        $freeMin = (int) setting('free_shipping_min', 0);
        $shipping = $hasPhysical ? (($freeMin > 0 && $physical >= $freeMin) ? 0 : (int) setting('shipping_flat', 0)) : 0;
        $rate = (float) setting('tax_rate', 0);
        $tax = $rate > 0 ? (int) round(max(0, $subtotal - $discount) * $rate / 100) : 0;

        return [
            'lines' => $lines, 'count' => array_sum(array_column($lines, 'qty')),
            'physical' => $physical, 'digital' => $digital, 'subtotal' => $subtotal, 'discount' => $discount,
            'shipping' => $shipping, 'tax' => $tax, 'total' => max(0, $subtotal - $discount + $shipping + $tax),
            'has_physical' => $hasPhysical, 'only_digital' => ! $hasPhysical && $lines !== [],
            'coupon' => $coupon, 'coupon_error' => $couponError,
            'free_min' => $freeMin,
            'free_pct' => ($hasPhysical && $freeMin > 0) ? min(100, (int) floor($physical / $freeMin * 100)) : 100,
            'free_left' => max(0, $freeMin - $physical),
            'has_sold_out' => (bool) array_filter($lines, fn ($x) => $x['sold_out']),
        ];
    }

    public static function optLabel(array $opts): string
    {
        return implode(' · ', array_filter(array_map('strval', $opts)));
    }
}
