<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductKey;
use App\Support\Mailer;
use App\Support\Triggers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Orders
{
    public const STATUSES = [
        'pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped',
        'completed' => 'Completed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded',
    ];

    public static function number(): string
    {
        do {
            $n = 'K'.date('ymd').'-'.strtoupper(Str::random(5));
        } while (Order::where('number', $n)->exists());

        return $n;
    }

    /** Create an order from the cart summary (transactional, re-checks stock). */
    public static function create(array $cust, array $sum, string $method): Order
    {
        $order = DB::transaction(function () use ($cust, $sum, $method) {
            foreach ($sum['lines'] as $l) {
                if (! empty($l['gc'])) continue;
                $fresh = Catalog::byId($l['p']->id);
                if (! $fresh || $fresh->available($l['opts']) < $l['qty'] || $l['qty'] < 1) {
                    throw new \RuntimeException('"'.$l['p']->name.'" just sold out or changed. Please review your cart.');
                }
            }
            $o = Order::create([
                'number' => self::number(), 'token' => Str::random(40), 'user_id' => $cust['user_id'] ?? null,
                'email' => mb_strtolower($cust['email']), 'name' => $cust['name'], 'phone' => $cust['phone'] ?? '',
                'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => $method,
                'subtotal' => $sum['subtotal'], 'shipping' => $sum['shipping'], 'tax' => $sum['tax'], 'discount' => $sum['discount'] + ($sum['offer_discount'] ?? 0), 'total' => $sum['total'],
                'savings' => $sum['savings'] ?? 0, 'tier_discount' => $sum['tier_discount'] ?? 0, 'ship_method' => $sum['ship_label'] ?? null,
                'currency' => currency_code(), 'coupon_code' => $sum['coupon']->code ?? null, 'has_physical' => $sum['has_physical'],
                'ship_name' => $cust['ship_name'] ?? '', 'ship_address' => $cust['ship_address'] ?? '', 'ship_address2' => $cust['ship_address2'] ?? '',
                'ship_city' => $cust['ship_city'] ?? '', 'ship_state' => $cust['ship_state'] ?? '', 'ship_zip' => $cust['ship_zip'] ?? '', 'ship_country' => $cust['ship_country'] ?? '',
                'notes' => $cust['notes'] ?? '', 'ip' => request()->ip(), 'source' => 'web',
            ]);
            foreach ($sum['lines'] as $l) {
                /** @var Product $p */
                $p = $l['p'];
                if (! empty($l['gc'])) {
                    $o->items()->create([
                        'product_id' => 0, 'name' => 'Gift card', 'type' => 'giftcard', 'options' => trim(($l['gc']['to_name'] ?: '').' '.($l['gc']['to_email'] ?: '')) ?: 'For yourself',
                        'qty' => $l['qty'], 'price' => $l['unit'], 'total' => $l['total'], 'art' => 'gift', 'art_color' => $p->art_color, 'color' => $p->art_color, 'category' => 'Gift cards',
                        'meta' => json_encode($l['gc'], JSON_UNESCAPED_UNICODE), 'orig_price' => $l['unit'],
                    ]);
                    continue;
                }
                $v = $l['variant'] ?? null;
                $o->items()->create([
                    'product_id' => $p->id, 'name' => $p->name, 'type' => $p->type, 'options' => Cart::optLabel($l['opts']), 'variant_key' => $v['key'] ?? null,
                    'qty' => $l['qty'], 'price' => $l['unit'], 'total' => $l['total'], 'orig_price' => $l['orig'] ?? $l['unit'], 'art' => $p->art, 'art_color' => $p->art_color,
                    'image' => ($l['img'] ?? '') ?: $p->image, 'color' => $p->color, 'category' => $p->cat?->name, 'file_path' => $p->file_path,
                ]);
                if ($p->type === 'physical' && ! $p->unlimited) {
                    if (! $p->adjustVariantStock($l['opts'], -$l['qty'])) Product::where('id', $p->id)->decrement('stock', $l['qty']);
                }
            }
            if (! empty($sum['coupon'])) {
                Coupon::where('id', $sum['coupon']->id)->increment('used');
                LoyaltyRewards::couponUsed($sum['coupon']->code);
            }
            self::event($o, 'Order placed ('.$method.')', 'customer');
            foreach ($sum['offer_rows'] ?? [] as $r) self::event($o, 'Offer applied: '.$r['label'].' (−'.money($r['amount']).')');

            // gift card / store credit tenders
            $giftUsed = $creditUsed = 0;
            if (($sum['gift'] ?? 0) > 0 && ! empty($sum['gift_card'])) {
                $giftUsed = GiftCards::redeem($sum['gift_card'], (int) $sum['gift'], $o, 'customer');
                if ($giftUsed > 0) self::event($o, 'Paid '.money($giftUsed).' with gift card '.$sum['gift_card']->masked());
            }
            if (($sum['credit'] ?? 0) > 0 && ! empty($cust['user_id'])) {
                $u = \App\Models\User::find($cust['user_id']);
                if ($u) {
                    $creditUsed = StoreCredit::take($u, (int) $sum['credit'], 'Order '.$o->number, $o->id);
                    if ($creditUsed > 0) self::event($o, 'Paid '.money($creditUsed).' with store credit');
                }
            }
            // loyalty tenders: points + cashback (separate wallets from store credit)
            if (($sum['lpoints'] ?? 0) > 0 || ($sum['lcash'] ?? 0) > 0) {
                $lu = ! empty($cust['user_id']) ? \App\Models\User::find($cust['user_id']) : null;
                if ($lu) {
                    [$pu, $pv, $cu2] = Loyalty::spend($lu, $o, (int) ($sum['lpoints'] ?? 0), (int) ($sum['lpoints_value'] ?? 0), (int) ($sum['lcash'] ?? 0));
                    if ($pu) self::event($o, 'Paid '.money($pv).' with '.number_format($pu).' loyalty points');
                    if ($cu2) self::event($o, 'Paid '.money($cu2).' with cashback');
                    $o->points_used = $pu; $o->points_value = $pv; $o->cashback_used = $cu2;
                }
            }
            if ($giftUsed || $creditUsed) {
                $o->update(['giftcard_used' => $giftUsed, 'credit_used' => $creditUsed, 'giftcard_code' => $giftUsed ? $sum['gift_card']->code : null]);
            }

            return $o;
        });
        Triggers::fire('order.created', ['email' => $order->email, 'name' => $order->name, 'order' => $order, 'user' => $order->user, 'total' => $order->total, 'ref' => 'o'.$order->id]);

        return $order;
    }

    /** Return gift card balance / store credit that was spent on an order that is cancelled or refunded. */
    public static function restoreTenders(Order $o, string $why = 'Order cancelled'): void
    {
        Loyalty::restoreTenders($o, $why);
        $claimed = Order::whereKey($o->id)->where(fn ($q) => $q->where('giftcard_used', '>', 0)->orWhere('credit_used', '>', 0))->first();
        if (! $claimed) return;
        $gu = (int) $claimed->giftcard_used;
        $cu = (int) $claimed->credit_used;
        // claim first (zero the columns) so a double click cannot restore twice
        if (Order::whereKey($o->id)->where('giftcard_used', $gu)->where('credit_used', $cu)->update(['giftcard_used' => 0, 'credit_used' => 0]) !== 1) return;
        $o->refresh();
        $o->giftcard_used = $gu;
        $o->credit_used = $cu;
        if ($o->giftcard_used > 0 && $o->giftcard_code) {
            GiftCards::restore($o->giftcard_code, (int) $o->giftcard_used, $o, $why);
            self::event($o, 'Gift card balance restored: '.money($o->giftcard_used));
        }
        if ($o->credit_used > 0 && $o->user) {
            StoreCredit::add($o->user, (int) $o->credit_used, $why.' — '.$o->number, $o->id, 'system:restore');
            self::event($o, 'Store credit restored: '.money($o->credit_used));
        }
    }

    public static function event(Order $o, string $msg, string $actor = 'system'): void
    {
        OrderEvent::create(['order_id' => $o->id, 'message' => Str::limit($msg, 490, ''), 'actor' => $actor]);
    }

    /** Payment confirmed → deliver digital goods and notify. Idempotent. */
    public static function markPaid(Order $o, string $ref = '', string $actor = 'system'): void
    {
        $o->refresh();
        if ($o->isPaid()) return;
        $o->update([
            'payment_status' => 'paid', 'payment_ref' => $ref ?: $o->payment_ref, 'paid_at' => now(),
            'status' => $o->status === 'pending' ? 'processing' : $o->status,
        ]);
        self::event($o, 'Payment received'.($ref ? " ($ref)" : ''), $actor);
        foreach ($o->items as $it) {
            Product::where('id', $it->product_id)->increment('sales', $it->qty);
        }
        self::deliver($o);
        self::refreshStatus($o);
        $o->refresh();
        self::sendReceipt($o, true);
        Loyalty::onOrderPaid($o);
        Triggers::fire('order.paid', ['email' => $o->email, 'name' => $o->name, 'order' => $o, 'user' => $o->user, 'total' => $o->total, 'ref' => 'o'.$o->id]);
        if (setting('notify_admin') === '1' && setting('store_email')) {
            Mailer::send((string) setting('store_email'), 'New paid order '.$o->number.' · '.money($o->total),
                Mailer::wrap('New paid order', '<p>'.e($o->name).' paid <b>'.money($o->total).'</b> for order '.e($o->number).'.</p><p><a style="color:#c6ff3d" href="'.e(url('admin/orders/'.$o->id)).'">Open in admin</a></p>'));
        }
    }

    /** Allocate pooled keys to key/account items. Returns number of missing units. */
    public static function deliver(Order $o): int
    {
        $missing = 0;
        foreach ($o->items()->where('type', 'giftcard')->get() as $it) GiftCards::issueForItem($o, $it);
        foreach ($o->items()->whereIn('type', ['key', 'account'])->get() as $it) {
            $need = $it->qty - ProductKey::where('order_item_id', $it->id)->count();
            if ($need <= 0) continue;
            $got = 0;
            $ids = ProductKey::where('product_id', $it->product_id)->where('status', 'available')->orderBy('id')->limit($need)->pluck('id');
            foreach ($ids as $kid) {
                $got += ProductKey::where('id', $kid)->where('status', 'available')->update(['status' => 'sold', 'order_item_id' => $it->id, 'sold_at' => now()]);
            }
            if ($got < $need) {
                $missing += $need - $got;
                self::event($o, 'Awaiting stock: '.($need - $got).' × '.$it->name);
            } else {
                self::event($o, 'Delivered '.$got.' × '.$it->name);
            }
        }

        return $missing;
    }

    public static function refreshStatus(Order $o): void
    {
        $o->refresh();
        if (in_array($o->status, ['cancelled', 'refunded', 'shipped', 'completed'], true) || ! $o->isPaid()) return;
        $pending = 0;
        foreach ($o->items()->whereIn('type', ['key', 'account'])->get() as $it) {
            $pending += max(0, $it->qty - ProductKey::where('order_item_id', $it->id)->count());
        }
        if (! $o->has_physical && $pending === 0) {
            $o->update(['status' => 'completed']);
        }
    }

    public static function setStatus(Order $o, string $status, string $actor = 'admin', string $tracking = ''): void
    {
        if (! isset(self::STATUSES[$status]) || $o->status === $status) return;
        $old = $o->status;
        $upd = ['status' => $status];
        if ($tracking !== '') $upd['tracking'] = $tracking;
        if ($status === 'refunded') $upd['payment_status'] = 'refunded';
        $o->update($upd);
        self::event($o, 'Status: '.self::STATUSES[$old].' → '.self::STATUSES[$status].($tracking ? " (tracking $tracking)" : ''), $actor);
        if (in_array($status, ['cancelled', 'refunded'], true) && ! in_array($old, ['cancelled', 'refunded'], true)) {
            foreach ($o->items()->where('type', 'physical')->get() as $it) {
                $p = Product::withoutEvents(fn () => Product::find($it->product_id));
                if ($p && $it->variant_key && $p->variantList()) {
                    $v = collect($p->variantList())->firstWhere('key', $it->variant_key);
                    if ($v && $p->adjustVariantStock($v['opts'], $it->qty)) continue;
                }
                Product::where('id', $it->product_id)->where('unlimited', false)->increment('stock', $it->qty);
            }
            self::restoreTenders($o, $status === 'refunded' ? 'Order refunded' : 'Order cancelled');
        }
        if (in_array($status, ['cancelled', 'refunded'], true)) Loyalty::onOrderRefund($o, max(0, (int) $o->total - (int) $o->loyalty_refunded), 'admin');
        else Loyalty::onOrderStatus($o);
        if (in_array($status, ['shipped', 'completed'], true)) {
            $msg = $status === 'shipped' ? 'Your order is on its way' : 'Your order is complete';
            $inner = '<p style="color:#cfcfd6;line-height:1.6">Order <b>'.e($o->number).'</b> is now <b>'.e(self::STATUSES[$status]).'</b>.'
                .($o->tracking ? '<br>Tracking: <b>'.e($o->tracking).'</b>' : '').'</p>'.self::button($o->publicUrl(), 'View order');
            Mailer::send($o->email, $msg.' · '.$o->number, Mailer::wrap($msg, $inner));
        }
    }

    private static function button(string $href, string $label): string
    {
        return '<p style="margin-top:20px"><a style="display:inline-block;background:#c6ff3d;color:#0b0b0d;padding:12px 22px;border-radius:99px;font-weight:700;text-decoration:none" href="'.e($href).'">'.e($label).'</a></p>';
    }

    public static function sendReceipt(Order $o, bool $withDelivery = true): void
    {
        $rows = '';
        foreach ($o->items()->get() as $it) {
            $rows .= '<tr><td style="padding:10px 0;border-bottom:1px solid #26262d;color:#f3f1ec">'.e($it->name).($it->options ? '<br><span style="color:#8a8a93;font-size:12px">'.e($it->options).'</span>' : '')
                .'</td><td align="center" style="color:#8a8a93;border-bottom:1px solid #26262d">×'.$it->qty.'</td><td align="right" style="color:#f3f1ec;border-bottom:1px solid #26262d">'.money($it->total).'</td></tr>';
            if ($withDelivery && $it->delivered) {
                $rows .= '<tr><td colspan="3" style="padding:8px 0 12px"><div style="background:#0b0b0d;border:1px dashed #c6ff3d;border-radius:12px;padding:12px;font-family:monospace;font-size:14px;color:#c6ff3d;word-break:break-all">'
                    .implode('<br>', array_map('e', $it->delivered)).'</div></td></tr>';
            }
        }
        $inner = '<p style="color:#cfcfd6;line-height:1.6">Thanks '.e(explode(' ', $o->name)[0]).'! Here is your receipt for order <b>'.e($o->number).'</b>.</p>'
            .'<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin:12px 0">'.$rows.'</table>'
            .($o->giftcard_used || $o->credit_used ? '<p style="color:#8a8a93;font-size:13px;margin:0 0 6px">'.($o->giftcard_used ? 'Gift card: −'.money($o->giftcard_used).' ' : '').($o->credit_used ? 'Store credit: −'.money($o->credit_used) : '').'</p>' : '')
            .'<p style="color:#f3f1ec;font-size:16px">Order total: <b style="color:#c6ff3d">'.money($o->total).'</b></p>'.self::button($o->publicUrl(), 'View your order');
        Mailer::send($o->email, 'Your order '.$o->number, Mailer::wrap($withDelivery ? 'Payment received' : 'Order received', $inner));
    }

    public static function sendPending(Order $o): void
    {
        $extra = $o->payment_method === 'bank'
            ? '<p style="color:#cfcfd6">Please pay <b>'.money($o->due()).'</b> by bank transfer and use <b>'.e($o->number).'</b> as the reference:</p><pre style="background:#0b0b0d;color:#c6ff3d;padding:12px;border-radius:12px;white-space:pre-wrap">'.e(setting('pay_bank_info')).'</pre>' : '';
        $inner = '<p style="color:#cfcfd6;line-height:1.6">We received your order <b>'.e($o->number).'</b> for <b>'.money($o->total).'</b>'.($o->due() < $o->total ? ' (<b>'.money($o->due()).'</b> still to pay after gift card / store credit)' : '').'.</p>'.$extra.self::button($o->publicUrl(), 'View order');
        Mailer::send($o->email, 'Order received '.$o->number, Mailer::wrap('We got your order', $inner));
        if (setting('notify_admin') === '1' && setting('store_email')) {
            Mailer::send((string) setting('store_email'), 'New order '.$o->number, Mailer::wrap('New order ('.$o->payment_method.')', '<p>'.e($o->name).' · '.money($o->total).'</p><p><a style="color:#c6ff3d" href="'.e(url('admin/orders/'.$o->id)).'">Open in admin</a></p>'));
        }
    }
}
