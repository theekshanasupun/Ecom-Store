<?php

namespace App\Services;

use App\Models\Product;

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
        if ($p->hasVariants()) {
            $v = $p->variantFor($clean);
            if (! $v || ! $v['active']) return ['ok' => false, 'error' => 'That combination is not available.'];
        }
        $qty = max(1, min(99, $qty));
        $c = self::data();
        $k = self::key($pid, $clean);
        $have = $c['lines'][$k]['qty'] ?? 0;
        $max = $p->available($clean);
        if ($max <= 0) return ['ok' => false, 'error' => $p->name.' is out of stock.'];
        $new = min($have + $qty, $max, 99);
        $c['lines'][$k] = ['pid' => $pid, 'qty' => $new, 'opts' => $clean];
        self::save($c);

        return ['ok' => true, 'message' => $new < $have + $qty ? 'Only '.$max.' available — cart updated.' : 'Added to cart', 'name' => $p->name];
    }

    /** A gift card purchase line: amount in cents + recipient details. */
    public static function addGiftCard(array $d): array
    {
        if (! GiftCards::enabled()) return ['ok' => false, 'error' => 'Gift cards are not available right now.'];
        $amount = (int) $d['amount'];
        $min = max(100, (int) round((float) setting('gc_min', 10) * 100));
        $max = max($min, (int) round((float) setting('gc_max', 500) * 100));
        $fixed = GiftCards::amounts();
        $custom = setting('gc_custom', '1') === '1';
        if ($amount < $min || $amount > $max || (! $custom && ! in_array($amount, $fixed, true))) {
            return ['ok' => false, 'error' => 'Choose an amount between '.money($min).' and '.money($max).'.'];
        }
        $to = mb_strtolower(trim((string) ($d['to_email'] ?? '')));
        if ($to !== '' && ! filter_var($to, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Enter a valid recipient email or leave it empty to receive the card yourself.'];
        $deliver = null;
        if (! empty($d['deliver_at'])) {
            $ts = strtotime((string) $d['deliver_at']);
            if ($ts && $ts > time() + 3600) $deliver = date('Y-m-d 08:00:00', $ts);
        }
        $gc = [
            'amount' => $amount, 'design' => isset(GiftCards::DESIGNS[$d['design'] ?? '']) ? $d['design'] : 'classic',
            'to_name' => mb_substr(trim((string) ($d['to_name'] ?? '')), 0, 100), 'to_email' => $to, 'from' => mb_substr(trim((string) ($d['from'] ?? '')), 0, 100),
            'msg' => mb_substr(trim((string) ($d['msg'] ?? '')), 0, 500), 'deliver_at' => $deliver,
        ];
        $c = self::data();
        $c['lines']['gc:'.substr(md5(json_encode($gc).microtime()), 0, 10)] = ['pid' => 0, 'qty' => 1, 'opts' => [], 'gc' => $gc];
        self::save($c);

        return ['ok' => true, 'message' => 'Gift card added to your cart', 'name' => 'Gift card '.money($amount)];
    }

    /** In-memory product used to render a gift card like any other cart line. */
    private static function giftProduct(array $gc): Product
    {
        $p = new Product;
        $dsg = GiftCards::DESIGNS[$gc['design'] ?? 'classic'] ?? GiftCards::DESIGNS['classic'];
        $p->forceFill(['id' => 0, 'category_id' => 0, 'name' => 'Gift card', 'slug' => '', 'tagline' => $gc['to_name'] ? 'For '.$gc['to_name'] : 'Digital gift card', 'type' => 'giftcard', 'price' => (int) $gc['amount'],
            'compare_price' => 0, 'art' => 'gift', 'art_color' => $dsg[1], 'image' => '', 'badge' => '', 'status' => 'active', 'sku' => '']);
        $p->setRelation('cat', null);

        return $p;
    }

    public static function setQty(string $key, int $qty): void
    {
        $c = self::data();
        if (! isset($c['lines'][$key])) return;
        if ($qty <= 0) {
            unset($c['lines'][$key]);
        } else {
            if (! empty($c['lines'][$key]['gc'])) {
                $c['lines'][$key]['qty'] = max(1, min($qty, 10));
            } else {
                $p = Catalog::byId((int) $c['lines'][$key]['pid']);
                $c['lines'][$key]['qty'] = max(1, min($qty, $p ? $p->available((array) $c['lines'][$key]['opts']) : 1, 99));
            }
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
        self::save(['lines' => [], 'coupon' => null, 'gift' => null, 'credit' => false, 'ship' => null]);
    }

    public static function setGiftCard(?string $code): void
    {
        $c = self::data();
        $c['gift'] = $code ? GiftCards::normalise($code) : null;
        self::save($c);
    }

    public static function setCredit(bool $on): void
    {
        $c = self::data();
        $c['credit'] = $on;
        self::save($c);
    }

    public static function setShip(?string $id): void
    {
        $c = self::data();
        $c['ship'] = $id;
        self::save($c);
    }

    /** Delivery choices for the physical part of the cart. */
    public static function shippingOptions(int $physical, bool $freeShip): array
    {
        $freeMin = (int) setting('free_shipping_min', 0);
        $std = (($freeShip || ($freeMin > 0 && $physical >= $freeMin)) ? 0 : (int) setting('shipping_flat', 0));
        $opts = ['standard' => ['id' => 'standard', 'label' => (string) setting('ship_std_label', 'Standard delivery'), 'price' => $std, 'eta' => (string) setting('shipping_note', '')]];
        foreach (preg_split('/\R/', (string) setting('shipping_methods', '')) as $line) {
            $part = array_map('trim', explode('|', $line));
            if (count($part) < 2 || $part[0] === '') continue;
            $id = \Illuminate\Support\Str::slug($part[0]) ?: 'method'.count($opts);
            $opts[$id] = ['id' => $id, 'label' => $part[0], 'price' => max(0, to_cents($part[1])), 'eta' => $part[2] ?? ''];
        }

        return $opts;
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
        $physical = $digital = $giftLines = $savings = 0;
        $hasPhysical = false;
        foreach ($c['lines'] as $key => $l) {
            if (! empty($l['gc'])) {
                $p = self::giftProduct($l['gc']);
                $qty = max(1, min(10, (int) $l['qty']));
                $total = $qty * (int) $l['gc']['amount'];
                $digital += $total;
                $giftLines += $total;
                $lines[] = ['key' => $key, 'p' => $p, 'qty' => $qty, 'opts' => [], 'unit' => (int) $l['gc']['amount'], 'orig' => (int) $l['gc']['amount'], 'total' => $total, 'max' => 10, 'physical' => false, 'sold_out' => false, 'gc' => $l['gc'], 'variant' => null];
                continue;
            }
            $p = Catalog::byId((int) $l['pid']);
            if (! $p) {
                unset($c['lines'][$key]);
                continue;
            }
            $opts = (array) $l['opts'];
            $max = $p->available($opts);
            $qty = min((int) $l['qty'], max($max, 0));
            $isPhys = $p->type === 'physical';
            $info = $p->priceInfo($opts, max(1, $qty));
            if ($qty <= 0) {
                $lines[] = ['key' => $key, 'p' => $p, 'qty' => 0, 'opts' => $opts, 'unit' => $info['price'], 'orig' => $info['compare'] ?: $info['price'], 'total' => 0, 'max' => 0, 'physical' => $isPhys, 'sold_out' => true, 'variant' => $info['variant']];
                continue;
            }
            $total = $qty * $info['price'];
            $savings += max(0, ($info['compare'] ?: $info['price']) - $info['price']) * $qty;
            if ($isPhys) {
                $physical += $total;
                $hasPhysical = true;
            } else {
                $digital += $total;
            }
            $lines[] = ['key' => $key, 'p' => $p, 'qty' => $qty, 'opts' => $opts, 'unit' => $info['price'], 'orig' => $info['compare'] ?: $info['price'], 'total' => $total, 'max' => $max, 'physical' => $isPhys, 'sold_out' => false, 'variant' => $info['variant'], 'img' => $info['image'], 'label' => $info['label']];
        }
        $subtotal = $physical + $digital;

        $coupon = null;
        $couponError = null;
        $discount = 0;
        $auto = false;
        $uid = auth()->id();
        $mail = auth()->user()?->email;
        $live = array_values(array_filter($lines, fn ($x) => ! $x['sold_out']));
        if (! empty($c['coupon'])) {
            [$coupon, $couponError] = Coupons::check($c['coupon'], $subtotal, $live, $mail, $uid);
            if (! $coupon) $c['coupon'] = null;
        }
        if (! $coupon && $live) {
            $coupon = Coupons::auto($subtotal, $live, $mail, $uid);
            $auto = (bool) $coupon;
        }
        if ($coupon) $discount = Coupons::amount($coupon, $subtotal, $live);

        $offers = Offers::cartDiscounts($live);
        $offerDiscount = max(0, min((int) $offers['total'], $subtotal - $giftLines - $discount));

        $freeMin = (int) setting('free_shipping_min', 0);
        $freeShip = $coupon && $coupon->free_shipping;
        $shipOpts = $hasPhysical ? self::shippingOptions($physical, (bool) $freeShip) : [];
        $shipId = $shipOpts ? (isset($shipOpts[$c['ship'] ?? '']) ? $c['ship'] : array_key_first($shipOpts)) : null;
        $shipping = $shipId ? (int) $shipOpts[$shipId]['price'] : 0;
        $rate = (float) setting('tax_rate', 0);
        $taxBase = max(0, $subtotal - $giftLines - $discount - $offerDiscount);
        $tax = $rate > 0 ? (int) round($taxBase * $rate / 100) : 0;
        $total = max(0, $subtotal - $discount - $offerDiscount + $shipping + $tax);

        // ---- tenders: gift card + store credit (cannot pay for gift cards themselves) ----
        $tenderable = max(0, $total - $giftLines);
        $gift = 0;
        $giftCard = null;
        $giftError = null;
        if (! empty($c['gift'])) {
            [$giftCard, $giftError] = GiftCards::check($c['gift']);
            if ($giftCard) $gift = min((int) $giftCard->balance, $tenderable);
            else $c['gift'] = null;
        }
        $creditBal = (auth()->check() && StoreCredit::enabled()) ? StoreCredit::balance(auth()->user()) : 0;
        $useCredit = $creditBal > 0 && ! empty($c['credit']);
        $credit = $useCredit ? min($creditBal, $tenderable - $gift) : 0;
        self::save($c);

        return [
            'lines' => $lines, 'count' => array_sum(array_column($lines, 'qty')),
            'physical' => $physical, 'digital' => $digital, 'subtotal' => $subtotal, 'discount' => $discount, 'offer_discount' => $offerDiscount, 'offer_rows' => $offers['rows'], 'savings' => $savings,
            'shipping' => $shipping, 'tax' => $tax, 'total' => $total,
            'gift' => $gift, 'gift_card' => $giftCard, 'gift_error' => $giftError, 'credit' => $credit, 'credit_balance' => $creditBal, 'use_credit' => $useCredit, 'due' => max(0, $total - $gift - $credit),
            'has_physical' => $hasPhysical, 'only_digital' => ! $hasPhysical && $lines !== [], 'has_gift' => $giftLines > 0, 'gift_lines_total' => $giftLines,
            'ship_options' => $shipOpts, 'ship_id' => $shipId, 'ship_label' => $shipId ? $shipOpts[$shipId]['label'] : '',
            'coupon' => $coupon, 'coupon_error' => $couponError, 'coupon_auto' => $auto, 'free_ship_coupon' => (bool) $freeShip,
            'free_min' => $freeMin,
            'free_pct' => ($hasPhysical && $freeMin > 0) ? min(100, (int) floor($physical / $freeMin * 100)) : 100,
            'free_left' => max(0, $freeMin - $physical),
            'has_sold_out' => (bool) array_filter($lines, fn ($x) => $x['sold_out']),
        ];
    }

    /** Replace the whole cart (used by abandoned-cart recovery links). */
    public static function restore(array $lines, ?string $coupon = null): void
    {
        $out = [];
        foreach ($lines as $k => $l) {
            if (! empty($l['gc'])) { $out[$k] = $l; continue; }
            $p = Catalog::byId((int) ($l['pid'] ?? 0));
            if (! $p || $p->available((array) ($l['opts'] ?? [])) <= 0) continue;
            $o = (array) ($l['opts'] ?? []);
            $out[$k] = ['pid' => $p->id, 'qty' => max(1, min((int) $l['qty'], $p->available($o), 99)), 'opts' => $o];
        }
        self::save(['lines' => $out, 'coupon' => $coupon ? strtoupper($coupon) : null]);
    }

    public static function raw(): array
    {
        return self::data();
    }

    /** Free-delivery progress for the sitewide bar. */
    public static function freeBar(): array
    {
        $min = (int) setting('free_shipping_min', 0);
        $sum = self::summary();
        $left = max(0, $min - $sum['physical']);

        return ['min' => $min, 'left' => $left, 'pct' => $min > 0 ? min(100, (int) floor($sum['physical'] / $min * 100)) : 0, 'unlocked' => $min > 0 && $sum['physical'] >= $min, 'empty' => $sum['physical'] === 0, 'left_txt' => money($left), 'min_txt' => money($min)];
    }

    public static function optLabel(array $opts): string
    {
        return implode(' · ', array_filter(array_map('strval', $opts)));
    }
}
