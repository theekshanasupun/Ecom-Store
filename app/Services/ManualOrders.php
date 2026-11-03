<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Orders created by staff in the admin (phone / in-store / invoice orders). */
final class ManualOrders
{
    public const PAY_METHODS = ['cash' => 'Cash', 'pos' => 'Card (in person)', 'bank' => 'Bank transfer', 'other' => 'Other', 'stripe' => 'Card online (Stripe)', 'paypal' => 'PayPal'];

    /** Clean + price the posted lines. Returns [lines, subtotal]. Throws RuntimeException with a friendly message. */
    private static function resolveLines(array $raw): array
    {
        $lines = [];
        foreach ($raw as $l) {
            $qty = max(1, min(9999, (int) ($l['qty'] ?? 1)));
            $pid = (int) ($l['product_id'] ?? 0);
            $override = isset($l['price']) && $l['price'] !== '' && $l['price'] !== null ? to_cents($l['price']) : null;
            if ($pid > 0) {
                $p = Product::with('cat')->find($pid);
                if (! $p) throw new \RuntimeException('A product on this order no longer exists.');
                $opts = [];
                foreach ($p->options_list as $o) {
                    $name = $o['name'] ?? '';
                    $vals = $o['values'] ?? [];
                    $pick = $l['opts'][$name] ?? ($vals[0] ?? '');
                    if ($vals && ! in_array($pick, $vals, true)) throw new \RuntimeException('Choose a valid '.$name.' for '.$p->name.'.');
                    if ($name !== '') $opts[$name] = $pick;
                }
                $info = method_exists($p, 'priceInfo') ? $p->priceInfo($opts, $qty) : ['price' => (int) $p->price, 'compare' => (int) $p->compare_price];
                $unit = max(0, $override ?? (int) $info['price']);
                $orig = max((int) ($info['compare'] ?? 0), $unit);
                $avail = method_exists($p, 'available') ? $p->available($opts) : $p->available();
                if ($avail < $qty) throw new \RuntimeException('"'.$p->name.'" has only '.($avail === PHP_INT_MAX ? 'unlimited' : $avail).' in stock for that selection.');
                $v = method_exists($p, 'variantFor') ? $p->variantFor($opts) : null;
                $lines[] = ['p' => $p, 'qty' => $qty, 'unit' => $unit, 'orig' => $orig, 'total' => $unit * $qty, 'opts' => $opts, 'variant_key' => $v['key'] ?? null, 'name' => $p->name];
            } else {
                $name = trim((string) ($l['name'] ?? ''));
                if ($name === '') throw new \RuntimeException('Give every custom item a name.');
                $unit = $override ?? 0;
                $lines[] = ['p' => null, 'qty' => $qty, 'unit' => $unit, 'orig' => $unit, 'total' => $unit * $qty, 'opts' => [], 'variant_key' => null, 'name' => mb_substr($name, 0, 190)];
            }
        }
        if (! $lines) throw new \RuntimeException('Add at least one item to the order.');

        return [$lines, array_sum(array_column($lines, 'total'))];
    }

    /** Totals preview/calc shared by the form (JSON) and create(). */
    public static function totals(array $d): array
    {
        [$lines, $sub] = self::resolveLines((array) ($d['lines'] ?? []));
        $disc = 0;
        $dv = (float) ($d['discount_value'] ?? 0);
        if ($dv > 0) $disc = ($d['discount_type'] ?? 'fixed') === 'percent' ? (int) round($sub * min(100, $dv) / 100) : to_cents($dv);
        $coupon = null;
        $couponAmt = 0;
        $freeShip = false;
        $code = strtoupper(trim((string) ($d['coupon'] ?? '')));
        if ($code !== '') {
            $cl = [];
            foreach ($lines as $l) if ($l['p']) $cl[] = ['p' => $l['p'], 'qty' => $l['qty'], 'total' => $l['total']];
            [$coupon, $err] = Coupons::check($code, $sub, $cl, mb_strtolower((string) ($d['email'] ?? '')) ?: null, ! empty($d['user_id']) ? (int) $d['user_id'] : null);
            if (! $coupon) throw new \RuntimeException('Coupon: '.$err);
            $couponAmt = Coupons::amount($coupon, $sub, $cl);
            $freeShip = (bool) $coupon->free_shipping;
        }
        $disc = min($sub, $disc + $couponAmt);
        $ship = $freeShip ? 0 : max(0, to_cents($d['shipping'] ?? 0));
        $rate = (float) setting('tax_rate', 0);
        $tax = $rate > 0 ? (int) round(max(0, $sub - $disc) * $rate / 100) : 0;

        return ['lines' => $lines, 'subtotal' => $sub, 'discount' => $disc, 'coupon' => $coupon, 'shipping' => $ship, 'tax' => $tax, 'total' => max(0, $sub - $disc + $ship + $tax)];
    }

    public static function create(array $d, string $actor = 'admin'): Order
    {
        $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
        $name = trim((string) ($d['name'] ?? ''));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Enter a valid customer email.');
        if (mb_strlen($name) < 2) throw new \RuntimeException('Enter the customer name.');

        $user = ! empty($d['user_id']) ? User::find((int) $d['user_id']) : null;
        $user ??= User::where('email', $email)->first();
        $d['user_id'] = $user?->id;
        $d['email'] = $email;
        $t = self::totals($d);
        $hasPhysical = (bool) array_filter($t['lines'], fn ($l) => $l['p'] && $l['p']->type === 'physical');
        $s = fn ($k, $n = 190) => mb_substr(trim((string) ($d[$k] ?? '')), 0, $n);
        if ($hasPhysical && ($s('ship_address') === '' || $s('ship_city') === '' || $s('ship_country') === '')) {
            throw new \RuntimeException('Physical items need a shipping address (street, city and country).');
        }

        $newPass = null;
        $o = DB::transaction(function () use ($d, $t, $user, $email, $name, $hasPhysical, $s, &$newPass, $actor) {
            if (! $user && ! empty($d['create_account'])) {
                $newPass = Str::password(12, true, true, false);
                $user = User::create(['name' => $name, 'email' => $email, 'password' => $newPass, 'phone' => $s('phone', 40), 'role' => 'customer', 'status' => 'active']);
            }
            $o = Order::create([
                'number' => Orders::number(), 'token' => Str::random(40), 'user_id' => $user?->id, 'email' => $email, 'name' => mb_substr($name, 0, 150), 'phone' => $s('phone', 40),
                'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => isset(self::PAY_METHODS[$d['pay_method'] ?? '']) ? $d['pay_method'] : 'other',
                'subtotal' => $t['subtotal'], 'shipping' => $t['shipping'], 'tax' => $t['tax'], 'discount' => $t['discount'], 'total' => $t['total'],
                'currency' => currency_code(), 'coupon_code' => $t['coupon']->code ?? null, 'has_physical' => $hasPhysical,
                'ship_name' => $s('ship_name', 150) ?: $name, 'ship_address' => $s('ship_address', 255), 'ship_address2' => $s('ship_address2', 255), 'ship_city' => $s('ship_city', 100),
                'ship_state' => $s('ship_state', 100), 'ship_zip' => $s('ship_zip', 20), 'ship_country' => $s('ship_country', 100), 'ship_method' => $s('ship_method', 80) ?: null,
                'notes' => $s('notes', 1000), 'ip' => null, 'source' => 'admin',
                'savings' => max(0, array_sum(array_map(fn ($l) => max(0, $l['orig'] - $l['unit']) * $l['qty'], $t['lines'])) + $t['discount']),
            ]);
            foreach ($t['lines'] as $l) {
                /** @var ?Product $p */
                $p = $l['p'];
                $o->items()->create([
                    'product_id' => $p?->id ?? 0, 'name' => $l['name'], 'type' => $p?->type ?? 'service', 'options' => Cart::optLabel($l['opts']),
                    'qty' => $l['qty'], 'price' => $l['unit'], 'total' => $l['total'], 'orig_price' => $l['orig'], 'variant_key' => $l['variant_key'],
                    'art' => $p?->art, 'art_color' => $p?->art_color, 'image' => $p?->image, 'color' => $p?->color, 'category' => $p?->cat?->name, 'file_path' => $p?->file_path,
                ]);
                if ($p && $p->type === 'physical' && ! $p->unlimited) {
                    $touched = $l['opts'] && method_exists($p, 'adjustVariantStock') ? $p->adjustVariantStock($l['opts'], -$l['qty']) : false;
                    if (! $touched) Product::where('id', $p->id)->decrement('stock', $l['qty']);
                }
            }
            if ($t['coupon']) Coupon::where('id', $t['coupon']->id)->increment('used');
            Orders::event($o, 'Order created by staff', $actor);

            return $o;
        });
        if ($newPass !== null && ! empty($d['notify'])) {
            $inner = '<p style="color:#cfcfd6">An account was created for you at <b>'.e(setting('store_name')).'</b>.</p><p style="color:#cfcfd6">Email: <b>'.e($email).'</b><br>Temporary password: <b>'.e($newPass).'</b></p><p><a style="color:'.e(setting('accent')).'" href="'.e(url('login')).'">Sign in</a> and change your password from your account page.</p>';
            \App\Support\Mailer::send($email, 'Your '.setting('store_name').' account', \App\Support\Mailer::wrap('Your account is ready', $inner));
        }
        $notify = ! empty($d['notify']);
        $paid = ($d['pay_status'] ?? 'unpaid') === 'paid';
        $ref = mb_substr(trim((string) ($d['pay_ref'] ?? '')), 0, 190);
        if ($paid) {
            if ($notify) {
                Orders::markPaid($o, $ref ?: 'manual', $actor);
            } else {
                // same as Orders::markPaid, minus the customer email
                $o->update(['payment_status' => 'paid', 'payment_ref' => $ref ?: 'manual', 'paid_at' => now(), 'status' => 'processing']);
                Orders::event($o, 'Payment recorded ('.(self::PAY_METHODS[$o->payment_method] ?? $o->payment_method).($ref ? ", $ref" : '').')', $actor);
                foreach ($o->items as $it) if ($it->product_id) Product::where('id', $it->product_id)->increment('sales', $it->qty);
                Orders::deliver($o);
                Orders::refreshStatus($o);
                \App\Support\Triggers::fire('order.paid', ['email' => $o->email, 'name' => $o->name, 'order' => $o, 'total' => $o->total, 'ref' => 'o'.$o->id]);
            }
        } elseif ($notify) {
            Orders::sendPending($o);
        }
        $o->refresh();
        $want = (string) ($d['status'] ?? '');
        if (isset(Orders::STATUSES[$want]) && $want !== $o->status) {
            if ($notify) {
                Orders::setStatus($o, $want, $actor);
            } else {
                $old = $o->status;
                $o->update(['status' => $want] + ($want === 'shipped' ? ['shipped_at' => now()] : []));
                Orders::event($o, 'Status: '.(Orders::STATUSES[$old] ?? $old).' → '.Orders::STATUSES[$want], $actor);
            }
            $o->refresh();
        }
        if ($o->status === 'shipped' && ! $o->shipped_at) $o->update(['shipped_at' => now()]);
        \App\Support\Triggers::fire('order.created', ['email' => $o->email, 'name' => $o->name, 'order' => $o, 'total' => $o->total, 'ref' => 'o'.$o->id]);

        return $o;
    }
}
