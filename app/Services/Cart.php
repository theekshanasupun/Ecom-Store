<?php
namespace App\Services;

use App\Core\Db;

final class Cart
{
    private static function &store(): array
    {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = ['lines' => [], 'coupon' => null];
        }
        return $_SESSION['cart'];
    }

    public static function key(int $pid, array $opts): string
    {
        ksort($opts);
        return $pid . ':' . substr(md5(json_encode($opts)), 0, 8);
    }

    public static function add(int $pid, int $qty, array $opts = []): array
    {
        $p = Catalog::byId($pid);
        if (!$p) {
            return ['ok' => false, 'error' => 'That product is no longer available.'];
        }
        // validate chosen options
        $clean = [];
        foreach ($p['options_list'] as $o) {
            $name = $o['name'] ?? '';
            $vals = $o['values'] ?? [];
            $pick = $opts[$name] ?? ($vals[0] ?? '');
            if ($vals && !in_array($pick, $vals, true)) {
                return ['ok' => false, 'error' => 'Please choose a valid ' . $name . '.'];
            }
            if ($name !== '') $clean[$name] = $pick;
        }
        $qty = max(1, min(99, $qty));
        $c = &self::store();
        $k = self::key($pid, $clean);
        $have = $c['lines'][$k]['qty'] ?? 0;
        $max = Catalog::available($p);
        if ($max <= 0) {
            return ['ok' => false, 'error' => $p['name'] . ' is out of stock.'];
        }
        $new = min($have + $qty, $max, 99);
        $c['lines'][$k] = ['pid' => $pid, 'qty' => $new, 'opts' => $clean];
        $msg = $new < $have + $qty ? 'Only ' . $max . ' available — cart updated.' : 'Added to cart';
        return ['ok' => true, 'message' => $msg, 'name' => $p['name']];
    }

    public static function setQty(string $key, int $qty): void
    {
        $c = &self::store();
        if (!isset($c['lines'][$key])) return;
        if ($qty <= 0) {
            unset($c['lines'][$key]);
            return;
        }
        $p = Catalog::byId((int) $c['lines'][$key]['pid']);
        $max = $p ? Catalog::available($p) : 0;
        $c['lines'][$key]['qty'] = max(1, min($qty, $max, 99));
    }

    public static function remove(string $key): void
    {
        $c = &self::store();
        unset($c['lines'][$key]);
    }

    public static function clear(): void
    {
        $_SESSION['cart'] = ['lines' => [], 'coupon' => null];
    }

    public static function count(): int
    {
        $n = 0;
        foreach (self::store()['lines'] as $l) $n += (int) $l['qty'];
        return $n;
    }

    public static function setCoupon(?string $code): void
    {
        $c = &self::store();
        $c['coupon'] = $code ? strtoupper(trim($code)) : null;
    }

    /** Hydrated cart + totals. */
    public static function summary(): array
    {
        $c = &self::store();
        $lines = [];
        $physical = $digital = 0;
        $hasPhysical = false;
        foreach ($c['lines'] as $key => $l) {
            $p = Catalog::byId((int) $l['pid']);
            if (!$p) {
                unset($c['lines'][$key]);
                continue;
            }
            $max = Catalog::available($p);
            $qty = min((int) $l['qty'], max($max, 0));
            if ($qty <= 0) {
                $lines[] = ['key' => $key, 'p' => $p, 'qty' => 0, 'opts' => $l['opts'], 'unit' => (int) $p['price'], 'total' => 0, 'max' => 0, 'physical' => $p['type'] === 'physical', 'sold_out' => true];
                continue;
            }
            $total = $qty * (int) $p['price'];
            $isPhys = $p['type'] === 'physical';
            if ($isPhys) {
                $physical += $total;
                $hasPhysical = true;
            } else {
                $digital += $total;
            }
            $lines[] = ['key' => $key, 'p' => $p, 'qty' => $qty, 'opts' => $l['opts'], 'unit' => (int) $p['price'], 'total' => $total, 'max' => $max, 'physical' => $isPhys, 'sold_out' => false];
        }
        $subtotal = $physical + $digital;

        $coupon = null;
        $couponError = null;
        $discount = 0;
        if (!empty($c['coupon'])) {
            [$coupon, $couponError] = Coupons::check($c['coupon'], $subtotal);
            if ($coupon) {
                $discount = Coupons::amount($coupon, $subtotal);
            } else {
                $c['coupon'] = null;
            }
        }

        $freeMin = (int) setting('free_shipping_min', 0);
        $flat = (int) setting('shipping_flat', 0);
        $shipping = 0;
        if ($hasPhysical) {
            $shipping = ($freeMin > 0 && $physical >= $freeMin) ? 0 : $flat;
        }
        $rate = (float) setting('tax_rate', 0);
        $tax = $rate > 0 ? (int) round(max(0, $subtotal - $discount) * $rate / 100) : 0;
        $total = max(0, $subtotal - $discount + $shipping + $tax);

        return [
            'lines' => $lines,
            'count' => array_sum(array_column($lines, 'qty')),
            'physical' => $physical,
            'digital' => $digital,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'tax' => $tax,
            'total' => $total,
            'has_physical' => $hasPhysical,
            'only_digital' => !$hasPhysical && $lines !== [],
            'coupon' => $coupon,
            'coupon_error' => $couponError,
            'free_min' => $freeMin,
            'free_pct' => ($hasPhysical && $freeMin > 0) ? min(100, (int) floor($physical / $freeMin * 100)) : 100,
            'free_left' => max(0, $freeMin - $physical),
            'has_sold_out' => (bool) array_filter($lines, fn($x) => $x['sold_out']),
        ];
    }

    public static function optLabel(array $opts): string
    {
        return implode(' · ', array_filter(array_map('strval', $opts)));
    }
}
