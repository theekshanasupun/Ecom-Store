<?php

namespace App\Support;

use App\Models\Automation;
use App\Models\EmailLog;
use App\Models\Order;
use App\Models\SavedCart;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\Cart;
use App\Services\Catalog;
use Illuminate\Support\Str;

/** Abandoned-cart capture, subscribers and email automations. */
final class Marketing
{
    public static function defaultAutomations(): array
    {
        $btn = "\n\n{cart_button}\n\nIf you have any questions just reply to this email.";

        return [
            ['key' => 'abandoned_1', 'name' => 'Abandoned cart — reminder 1', 'trigger_label' => 'Cart left behind, customer email known', 'enabled' => 0, 'delay_hours' => 1,
                'subject' => 'You left something in your cart', 'body' => "Hi {first_name},\n\nLooks like you didn't finish checking out. Your items are saved:\n\n{cart_items}{$btn}", 'coupon_code' => null],
            ['key' => 'abandoned_2', 'name' => 'Abandoned cart — reminder 2', 'trigger_label' => 'Still not purchased after reminder 1', 'enabled' => 0, 'delay_hours' => 24,
                'subject' => 'Still thinking it over? Your cart is waiting', 'body' => "Hi {first_name},\n\nWe're holding your cart, but popular items sell out fast.\n\n{cart_items}{$btn}", 'coupon_code' => null],
            ['key' => 'abandoned_3', 'name' => 'Abandoned cart — last chance (+coupon)', 'trigger_label' => 'Final nudge, optionally with a discount code', 'enabled' => 0, 'delay_hours' => 72,
                'subject' => 'Last chance: a little something for your cart', 'body' => "Hi {first_name},\n\nHere is a discount to help you finish your order. Use code {coupon} at checkout.\n\n{cart_items}{$btn}", 'coupon_code' => null],
            ['key' => 'welcome', 'name' => 'Welcome email', 'trigger_label' => 'New account or newsletter signup', 'enabled' => 0, 'delay_hours' => 0,
                'subject' => 'Welcome to {store}', 'body' => "Hi {first_name},\n\nThanks for joining {store}! Here's where to start:\n\n{shop_button}\n\nEnjoy your first order{coupon_line}.", 'coupon_code' => null],
            ['key' => 'post_purchase', 'name' => 'Post-purchase follow-up', 'trigger_label' => 'Some days after an order is paid', 'enabled' => 0, 'delay_hours' => 72,
                'subject' => 'How did we do, {first_name}?', 'body' => "Hi {first_name},\n\nWe hope you're enjoying your order {order_number}. If anything isn't right just reply to this email and we'll fix it.\n\nReady for more?\n\n{shop_button}", 'coupon_code' => null],
            ['key' => 'winback', 'name' => 'Win-back', 'trigger_label' => 'Customer has not ordered for a long time', 'enabled' => 0, 'delay_hours' => 1440,
                'subject' => 'We miss you, {first_name}', 'body' => "Hi {first_name},\n\nIt's been a while! Take a look at what's new at {store}{coupon_line}.\n\n{shop_button}", 'coupon_code' => null],
        ];
    }

    /* ------------------------------------------------------------ subscribers */

    public static function subscribe(string $email, ?string $name = null, string $source = 'popup'): ?Subscriber
    {
        $email = mb_strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        $s = Subscriber::where('email', $email)->first();
        if ($s) {
            if ($s->status === 'unsubscribed' && $source !== 'auto') $s->update(['status' => 'subscribed']);

            return $s;
        }

        return Subscriber::create(['email' => $email, 'name' => $name, 'source' => $source, 'status' => 'subscribed', 'token' => Str::random(32)]);
    }

    public static function optedOut(string $email): bool
    {
        return Subscriber::where('email', mb_strtolower($email))->where('status', 'unsubscribed')->exists();
    }

    public static function unsubscribeUrl(string $email): string
    {
        $s = self::subscribe($email, null, 'auto');

        return url('unsubscribe/'.($s?->token ?? 'x'));
    }

    /* ------------------------------------------------------------ cart capture */

    public static function rememberEmail(string $email): void
    {
        $email = mb_strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            session(['capture_email' => $email]);
            self::trackCart();
        }
    }

    public static function trackCart(): void
    {
        try {
            $user = auth()->user();
            $email = $user?->email ?: session('capture_email');
            if (! $email) return;
            $raw = Cart::raw();
            $tok = session('saved_cart');
            $row = $tok ? SavedCart::where('token', $tok)->where('status', 'open')->first() : null;
            $row ??= SavedCart::where('email', $email)->where('status', 'open')->first();
            if (! $raw['lines']) {
                if ($row && $row->reminders === 0) $row->delete();
                session()->forget('saved_cart');

                return;
            }
            $sum = Cart::summary();
            $data = ['email' => $email, 'name' => $user?->name, 'user_id' => $user?->id, 'data' => json_encode($raw), 'items' => $sum['count'], 'total' => $sum['total'], 'last_activity_at' => now(), 'session_id' => session()->getId()];
            if ($row) {
                $row->update($data + ($row->reminders > 0 ? ['reminders' => 0] : []));
            } else {
                $row = SavedCart::create($data + ['token' => Str::random(32), 'status' => 'open']);
            }
            session(['saved_cart' => $row->token]);
        } catch (\Throwable $e) {
            \Log::debug('trackCart: '.$e->getMessage());
        }
    }

    /** Called after an order is created: closes the open cart and credits automation revenue. */
    public static function orderPlaced(Order $o): void
    {
        try {
            $rows = SavedCart::where('status', 'open')->where(fn ($q) => $q->where('email', $o->email)->when($o->user_id, fn ($x) => $x->orWhere('user_id', $o->user_id))->when(session('saved_cart'), fn ($x) => $x->orWhere('token', session('saved_cart'))))->get();
            foreach ($rows as $r) {
                $rec = $r->reminders > 0;
                $r->update(['status' => $rec ? 'recovered' : 'ordered', 'order_id' => $o->id, 'recovered_at' => $rec ? now() : null]);
                if ($rec && ($a = Automation::where('key', 'abandoned_'.min(3, $r->reminders))->first())) {
                    $a->increment('recovered');
                    $a->increment('revenue', $o->total);
                }
            }
            session()->forget('saved_cart');
        } catch (\Throwable) {
        }
    }

    /* ------------------------------------------------------------ rendering */

    public static function render(string $tpl, array $vars): string
    {
        $map = [];
        foreach ($vars as $k => $v) $map['{'.$k.'}'] = $v;

        return strtr($tpl, $map);
    }

    private static function button(string $label, string $href): string
    {
        return '<a href="'.e($href).'" style="display:inline-block;background:'.e(setting('accent', '#c6ff3d')).';color:#0b0b0d;text-decoration:none;font-weight:700;padding:13px 26px;border-radius:99px">'.e($label).'</a>';
    }

    private static function cartItems(array $lines): string
    {
        $h = '<table width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0">';
        foreach ($lines as $l) {
            $p = Catalog::byId((int) ($l['pid'] ?? 0));
            if (! $p) continue;
            $img = $p->image ? '<img src="'.e(upload_url($p->image)).'" width="56" height="56" style="border-radius:10px;object-fit:cover;display:block" alt="">' : '';
            $h .= '<tr><td width="68" style="padding:8px 0">'.$img.'</td><td style="padding:8px 8px;color:#f3f1ec"><b>'.e($p->name).'</b><br><span style="color:#8a8a93;font-size:12px">Qty '.(int) $l['qty'].'</span></td><td align="right" style="padding:8px 0;color:#f3f1ec">'.money($p->price * (int) $l['qty']).'</td></tr>';
        }

        return $h.'</table>';
    }

    /** Subject + wrapped HTML for an automation. */
    public static function compose(Automation $a, array $ctx): array
    {
        $email = (string) ($ctx['email'] ?? '');
        $name = (string) ($ctx['name'] ?? '');
        $first = trim(explode(' ', $name)[0]) ?: 'there';
        $code = trim((string) $a->coupon_code);
        $vars = [
            'name' => e($name ?: 'there'), 'first_name' => e($first), 'store' => e((string) setting('store_name')), 'coupon' => e($code),
            'coupon_line' => $code ? ' — use code '.e($code).' for a discount' : '', 'order_number' => e((string) ($ctx['order_number'] ?? '')),
            'cart_total' => money((int) ($ctx['total'] ?? 0)), 'shop_url' => url('/'), 'cart_url' => (string) ($ctx['cart_url'] ?? url('cart')),
            'cart_items' => '', 'cart_button' => self::button('Return to your cart', (string) ($ctx['cart_url'] ?? url('cart'))), 'shop_button' => self::button('Visit the store', url('/')),
            'unsubscribe_url' => $email ? self::unsubscribeUrl($email) : url('/'),
        ];
        if (isset($ctx['lines'])) $vars['cart_items'] = self::cartItems($ctx['lines']);
        $subject = strip_tags(self::render($a->subject, array_map(fn ($v) => html_entity_decode(strip_tags((string) $v)), $vars)));
        $paras = [];
        foreach (preg_split("/\n{2,}/", trim(str_replace("\r", '', (string) $a->body))) as $block) {
            $b = self::render(e($block), []);
            $b = strtr($b, ['{cart_items}' => $vars['cart_items'], '{cart_button}' => $vars['cart_button'], '{shop_button}' => $vars['shop_button']]);
            $b = self::render($b, array_diff_key($vars, array_flip(['cart_items', 'cart_button', 'shop_button'])));
            $paras[] = (str_contains($b, '<table') || str_contains($b, '<a href') ? '<div style="margin:0 0 16px">'.nl2br($b).'</div>' : '<p style="margin:0 0 16px;color:#cfcfd6;line-height:1.6">'.nl2br($b).'</p>');
        }
        $foot = '<p style="margin:24px 0 0;font-size:11px;color:#8a8a93">You are receiving this because you shopped at '.e((string) setting('store_name')).'. <a style="color:#8a8a93" href="'.e($vars['unsubscribe_url']).'">Unsubscribe</a></p>';

        return [$subject, Mailer::wrap($subject, implode('', $paras).$foot)];
    }

    /** Send once per (automation, email, ref). */
    private static function dispatch(Automation $a, string $email, string $ref, array $ctx): bool
    {
        $email = mb_strtolower($email);
        if (self::optedOut($email) || EmailLog::where(['automation' => $a->key, 'email' => $email, 'ref' => $ref])->exists()) return false;
        [$subject, $html] = self::compose($a, $ctx + ['email' => $email]);
        $ok = Mailer::send($email, $subject, $html);
        try {
            EmailLog::create(['automation' => $a->key, 'email' => $email, 'ref' => $ref, 'subject' => Str::limit($subject, 180, ''), 'ok' => $ok, 'created_at' => now()]);
        } catch (\Throwable) {
        }
        if ($ok) $a->increment('sent');

        return $ok;
    }

    /** Run every due automation. Returns number of emails sent. */
    public static function runDue(int $limit = 25): int
    {
        $sent = 0;
        $auto = Automation::where('enabled', true)->get()->keyBy('key');
        $left = fn () => $limit - $sent > 0;

        foreach ([1, 2, 3] as $stage) {
            $a = $auto->get('abandoned_'.$stage);
            if (! $a) continue;
            $rows = SavedCart::where('status', 'open')->whereNotNull('email')->where('reminders', $stage - 1)->where('items', '>', 0)
                ->where('last_activity_at', '<=', now()->subHours((int) $a->delay_hours))->where('last_activity_at', '>=', now()->subDays(14))->orderBy('id')->limit(50)->get();
            foreach ($rows as $r) {
                if (! $left()) break;
                $d = $r->lines();
                $url = url('cart/recover/'.$r->token).($a->coupon_code ? '?c='.urlencode($a->coupon_code) : '');
                $ok = self::dispatch($a, $r->email, 'c'.$r->id.'-'.$stage, ['name' => $r->name, 'lines' => $d['lines'] ?? [], 'total' => $r->total, 'cart_url' => $url]);
                $r->update(['reminders' => $stage, 'last_reminder_at' => now()]);
                if ($ok) $sent++;
            }
        }

        if (($a = $auto->get('welcome')) && $left()) {
            $cut = now()->subHours((int) $a->delay_hours);
            $people = [];
            foreach (User::where('role', 'customer')->where('created_at', '<=', $cut)->where('created_at', '>=', now()->subDays(7))->limit(30)->get(['email', 'name']) as $u) $people[$u->email] = $u->name;
            foreach (Subscriber::where('status', 'subscribed')->where('created_at', '<=', $cut)->where('created_at', '>=', now()->subDays(7))->limit(30)->get(['email', 'name']) as $s) $people[$s->email] ??= $s->name;
            foreach ($people as $email => $name) {
                if (! $left()) break;
                if (self::dispatch($a, $email, 'welcome', ['name' => $name])) $sent++;
            }
        }

        if (($a = $auto->get('post_purchase')) && $left()) {
            foreach (Order::where('payment_status', 'paid')->where('paid_at', '<=', now()->subHours((int) $a->delay_hours))->where('paid_at', '>=', now()->subDays(30))->orderBy('id')->limit(30)->get() as $o) {
                if (! $left()) break;
                if (self::dispatch($a, $o->email, 'o'.$o->id, ['name' => $o->name, 'order_number' => $o->number])) $sent++;
            }
        }

        if (($a = $auto->get('winback')) && $left()) {
            $cut = now()->subHours((int) $a->delay_hours);
            $last = Order::where('payment_status', 'paid')->selectRaw('email, max(id) as oid, max(paid_at) as last_paid, max(name) as nm')->groupBy('email')->havingRaw('max(paid_at) <= ?', [$cut->toDateTimeString()])->limit(30)->get();
            foreach ($last as $o) {
                if (! $left()) break;
                if (self::dispatch($a, $o->email, 'w'.$o->oid, ['name' => $o->nm])) $sent++;
            }
        }
        Settings::set('automations_last_run', now()->toDateTimeString());

        return $sent;
    }

    /** Immediately send a test of an automation to an address. */
    public static function test(Automation $a, string $to): bool
    {
        $lines = [];
        if ($p = \App\Models\Product::where('status', 'active')->first()) $lines = [['pid' => $p->id, 'qty' => 1, 'opts' => []]];
        [$subject, $html] = self::compose($a, ['email' => $to, 'name' => 'Alex Example', 'lines' => $lines, 'total' => $p->price ?? 0, 'order_number' => 'K000000-TEST']);

        return Mailer::send($to, '[Test] '.$subject, $html);
    }
}
