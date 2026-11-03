<?php
namespace App\Services;

use App\Core\Crypto;
use App\Core\Db;
use App\Core\Mailer;

final class Orders
{
    public const STATUSES = [
        'pending' => 'Pending', 'processing' => 'Processing', 'shipped' => 'Shipped',
        'completed' => 'Completed', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded',
    ];

    public static function number(): string
    {
        do {
            $n = 'K' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        } while (Db::val('SELECT COUNT(*) FROM orders WHERE number = ?', [$n]));
        return $n;
    }

    /** Create an order from the current cart summary. */
    public static function create(array $cust, array $sum, string $method): array
    {
        return Db::tx(function () use ($cust, $sum, $method) {
            foreach ($sum['lines'] as $l) {
                $fresh = Catalog::byId((int) $l['p']['id']);
                if (!$fresh || Catalog::available($fresh) < $l['qty'] || $l['qty'] < 1) {
                    throw new \RuntimeException('"' . ($l['p']['name']) . '" just sold out or changed. Please review your cart.');
                }
            }
            $id = Db::insert('orders', [
                'number' => self::number(),
                'token' => bin2hex(random_bytes(20)),
                'user_id' => $cust['user_id'] ?: null,
                'email' => mb_strtolower($cust['email']),
                'name' => $cust['name'],
                'phone' => $cust['phone'] ?? '',
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => $method,
                'subtotal' => $sum['subtotal'],
                'shipping' => $sum['shipping'],
                'tax' => $sum['tax'],
                'discount' => $sum['discount'],
                'total' => $sum['total'],
                'currency' => currency_code(),
                'coupon_code' => $sum['coupon']['code'] ?? null,
                'has_physical' => $sum['has_physical'] ? 1 : 0,
                'ship_name' => $cust['ship_name'] ?? '',
                'ship_address' => $cust['ship_address'] ?? '',
                'ship_address2' => $cust['ship_address2'] ?? '',
                'ship_city' => $cust['ship_city'] ?? '',
                'ship_state' => $cust['ship_state'] ?? '',
                'ship_zip' => $cust['ship_zip'] ?? '',
                'ship_country' => $cust['ship_country'] ?? '',
                'notes' => $cust['notes'] ?? '',
                'ip' => client_ip(),
                'created_at' => now(),
            ]);
            foreach ($sum['lines'] as $l) {
                $p = $l['p'];
                Db::insert('order_items', [
                    'order_id' => $id, 'product_id' => $p['id'], 'name' => $p['name'], 'type' => $p['type'],
                    'options' => Cart::optLabel($l['opts']), 'qty' => $l['qty'], 'price' => $l['unit'], 'total' => $l['total'],
                    'art' => $p['art'], 'art_color' => $p['art_color'], 'image' => $p['image'], 'color' => $p['color'],
                    'category' => $p['cat']['name'] ?? '', 'file_path' => $p['file_path'],
                ]);
                if ($p['type'] === 'physical' && !$p['unlimited']) {
                    Db::exec('UPDATE products SET stock = stock - ? WHERE id = ?', [$l['qty'], $p['id']]);
                }
            }
            if (!empty($sum['coupon'])) {
                Db::exec('UPDATE coupons SET used = used + 1 WHERE id = ?', [$sum['coupon']['id']]);
            }
            self::event($id, 'Order placed (' . $method . ')', 'customer');
            return self::find($id);
        });
    }

    public static function find(int $id): ?array
    {
        return Db::one('SELECT * FROM orders WHERE id = ?', [$id]);
    }

    public static function byNumber(string $n): ?array
    {
        return Db::one('SELECT * FROM orders WHERE number = ?', [$n]);
    }

    public static function items(int $orderId): array
    {
        $items = Db::all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
        foreach ($items as &$it) {
            $it['delivered'] = [];
            if (in_array($it['type'], ['key', 'account'], true)) {
                foreach (Db::all('SELECT payload FROM product_keys WHERE order_item_id = ? ORDER BY id', [$it['id']]) as $k) {
                    $it['delivered'][] = Crypto::decrypt($k['payload']);
                }
            }
        }
        return $items;
    }

    public static function event(int $orderId, string $msg, string $actor = 'system'): void
    {
        Db::insert('order_events', ['order_id' => $orderId, 'message' => mb_substr($msg, 0, 500), 'actor' => $actor, 'created_at' => now()]);
    }

    public static function events(int $orderId): array
    {
        return Db::all('SELECT * FROM order_events WHERE order_id = ? ORDER BY id DESC', [$orderId]);
    }

    /** Payment confirmed – deliver digital goods, notify. Idempotent. */
    public static function markPaid(int $id, string $ref = '', string $actor = 'system'): void
    {
        $o = self::find($id);
        if (!$o || $o['payment_status'] === 'paid') {
            return;
        }
        Db::update('orders', ['payment_status' => 'paid', 'payment_ref' => $ref ?: $o['payment_ref'], 'paid_at' => now(), 'status' => $o['status'] === 'pending' ? 'processing' : $o['status']], 'id = ?', [$id]);
        self::event($id, 'Payment received' . ($ref ? " ($ref)" : ''), $actor);
        foreach (Db::all('SELECT product_id, qty FROM order_items WHERE order_id = ?', [$id]) as $it) {
            Db::exec('UPDATE products SET sales = sales + ? WHERE id = ?', [$it['qty'], $it['product_id']]);
        }
        $missing = self::deliver($id);
        self::refreshStatus($id);
        $o = self::find($id);
        self::sendReceipt($o, true);
        if (setting('notify_admin') === '1' && setting('store_email')) {
            Mailer::send((string) setting('store_email'), 'New paid order ' . $o['number'] . ' · ' . money($o['total']),
                Mailer::wrap('New paid order', '<p>' . e($o['name']) . ' paid <b>' . money($o['total']) . '</b> for order ' . e($o['number']) . '.</p><p><a style="color:#c6ff3d" href="' . e(abs_url('admin/orders/' . $o['id'])) . '">Open in admin</a></p>'));
        }
    }

    /** Allocate pool keys to key/account items. Returns count of missing units. */
    public static function deliver(int $orderId): int
    {
        $missing = 0;
        $items = Db::all("SELECT * FROM order_items WHERE order_id = ? AND type IN ('key','account')", [$orderId]);
        foreach ($items as $it) {
            $have = (int) Db::val('SELECT COUNT(*) FROM product_keys WHERE order_item_id = ?', [$it['id']]);
            $need = (int) $it['qty'] - $have;
            if ($need <= 0) continue;
            $ids = Db::col("SELECT id FROM product_keys WHERE product_id = ? AND status = 'available' ORDER BY id LIMIT " . $need, [$it['product_id']]);
            $got = 0;
            foreach ($ids as $kid) {
                $n = Db::exec("UPDATE product_keys SET status = 'sold', order_item_id = ?, sold_at = ? WHERE id = ? AND status = 'available'", [$it['id'], now(), $kid]);
                $got += $n;
            }
            if ($got < $need) {
                $missing += $need - $got;
                self::event($orderId, 'Awaiting stock: ' . ($need - $got) . ' × ' . $it['name'], 'system');
            } else {
                self::event($orderId, 'Delivered ' . $got . ' × ' . $it['name'], 'system');
            }
        }
        return $missing;
    }

    public static function refreshStatus(int $id): void
    {
        $o = self::find($id);
        if (!$o || in_array($o['status'], ['cancelled', 'refunded', 'shipped', 'completed'], true) || $o['payment_status'] !== 'paid') {
            return;
        }
        $pending = 0;
        foreach (Db::all("SELECT id, qty FROM order_items WHERE order_id = ? AND type IN ('key','account')", [$id]) as $it) {
            $pending += max(0, (int) $it['qty'] - (int) Db::val('SELECT COUNT(*) FROM product_keys WHERE order_item_id = ?', [$it['id']]));
        }
        if (!$o['has_physical'] && $pending === 0) {
            Db::update('orders', ['status' => 'completed'], 'id = ?', [$id]);
        }
    }

    public static function setStatus(int $id, string $status, string $actor = 'admin', string $tracking = ''): void
    {
        $o = self::find($id);
        if (!$o || !isset(self::STATUSES[$status]) || $o['status'] === $status) {
            return;
        }
        $upd = ['status' => $status];
        if ($tracking !== '') $upd['tracking'] = $tracking;
        if ($status === 'refunded') $upd['payment_status'] = 'refunded';
        Db::update('orders', $upd, 'id = ?', [$id]);
        self::event($id, 'Status: ' . self::STATUSES[$o['status']] . ' → ' . self::STATUSES[$status] . ($tracking ? " (tracking $tracking)" : ''), $actor);
        if (in_array($status, ['cancelled', 'refunded'], true) && !in_array($o['status'], ['cancelled', 'refunded'], true)) {
            foreach (Db::all("SELECT product_id, qty FROM order_items WHERE order_id = ? AND type = 'physical'", [$id]) as $it) {
                Db::exec('UPDATE products SET stock = stock + ? WHERE id = ? AND unlimited = 0', [$it['qty'], $it['product_id']]);
            }
        }
        if ($status === 'shipped' || $status === 'completed') {
            $o = self::find($id);
            $msg = $status === 'shipped' ? 'Your order is on its way' : 'Your order is complete';
            $inner = '<p style="color:#cfcfd6;line-height:1.6">Order <b>' . e($o['number']) . '</b> is now <b>' . e(self::STATUSES[$status]) . '</b>.'
                . ($o['tracking'] ? '<br>Tracking: <b>' . e($o['tracking']) . '</b>' : '') . '</p>'
                . '<p><a style="display:inline-block;background:#c6ff3d;color:#0b0b0d;padding:12px 22px;border-radius:99px;font-weight:700;text-decoration:none" href="' . e(self::publicUrl($o)) . '">View order</a></p>';
            Mailer::send($o['email'], $msg . ' · ' . $o['number'], Mailer::wrap($msg, $inner));
        }
    }

    public static function publicUrl(array $o): string
    {
        return abs_url('order/' . $o['number'] . '?t=' . $o['token']);
    }

    public static function sendReceipt(array $o, bool $withDelivery = true): void
    {
        $items = self::items((int) $o['id']);
        $rows = '';
        foreach ($items as $it) {
            $rows .= '<tr><td style="padding:10px 0;border-bottom:1px solid #26262d;color:#f3f1ec">' . e($it['name']) . ($it['options'] ? '<br><span style="color:#8a8a93;font-size:12px">' . e($it['options']) . '</span>' : '')
                . '</td><td align="center" style="color:#8a8a93;border-bottom:1px solid #26262d">×' . (int) $it['qty'] . '</td><td align="right" style="color:#f3f1ec;border-bottom:1px solid #26262d">' . money($it['total']) . '</td></tr>';
            if ($withDelivery && $it['delivered']) {
                $rows .= '<tr><td colspan="3" style="padding:8px 0 12px"><div style="background:#0b0b0d;border:1px dashed #c6ff3d;border-radius:12px;padding:12px;font-family:monospace;font-size:14px;color:#c6ff3d;word-break:break-all">'
                    . implode('<br>', array_map('e', $it['delivered'])) . '</div></td></tr>';
            }
        }
        $inner = '<p style="color:#cfcfd6;line-height:1.6">Thanks ' . e(explode(' ', $o['name'])[0]) . '! Here is your receipt for order <b>' . e($o['number']) . '</b>.</p>'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;margin:12px 0">' . $rows . '</table>'
            . '<p style="color:#f3f1ec;font-size:16px">Total paid: <b style="color:#c6ff3d">' . money($o['total']) . '</b></p>'
            . '<p style="margin-top:20px"><a style="display:inline-block;background:#c6ff3d;color:#0b0b0d;padding:12px 22px;border-radius:99px;font-weight:700;text-decoration:none" href="' . e(self::publicUrl($o)) . '">View your order</a></p>';
        Mailer::send($o['email'], 'Your order ' . $o['number'], Mailer::wrap($withDelivery ? 'Payment received' : 'Order received', $inner));
    }

    public static function sendPending(array $o): void
    {
        $extra = '';
        if ($o['payment_method'] === 'bank') {
            $extra = '<p style="color:#cfcfd6">Please pay by bank transfer and use <b>' . e($o['number']) . '</b> as the reference:</p><pre style="background:#0b0b0d;color:#c6ff3d;padding:12px;border-radius:12px;white-space:pre-wrap">' . e(setting('pay_bank_info')) . '</pre>';
        }
        $inner = '<p style="color:#cfcfd6;line-height:1.6">We received your order <b>' . e($o['number']) . '</b> for <b>' . money($o['total']) . '</b>.</p>' . $extra
            . '<p><a style="display:inline-block;background:#c6ff3d;color:#0b0b0d;padding:12px 22px;border-radius:99px;font-weight:700;text-decoration:none" href="' . e(self::publicUrl($o)) . '">View order</a></p>';
        Mailer::send($o['email'], 'Order received ' . $o['number'], Mailer::wrap('We got your order', $inner));
        if (setting('notify_admin') === '1' && setting('store_email')) {
            Mailer::send((string) setting('store_email'), 'New order ' . $o['number'], Mailer::wrap('New order (' . $o['payment_method'] . ')', '<p>' . e($o['name']) . ' · ' . money($o['total']) . '</p><p><a style="color:#c6ff3d" href="' . e(abs_url('admin/orders/' . $o['id'])) . '">Open in admin</a></p>'));
        }
    }
}
