<?php

namespace App\Support;

use App\Models\Automation;
use App\Models\AutomationQueue;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Event bus for custom email automations (v2.6). Core code calls Triggers::fire() and never needs to
 * know what is listening. Matching enabled custom automations get one queue row per step; Marketing::runDue()
 * sends them when due.
 *
 * ctx keys (all optional except email): email, name, order (App\Models\Order), user (App\Models\User),
 * giftcard (App\Models\GiftCard), product_ids (int[]), total (cents), ref (unique string for de-duplication),
 * gift_code, gift_amount (cents), credit_amount (cents), coupon.
 */
final class Triggers
{
    /** event key => label (the automation UI lists these). */
    public const EVENTS = [
        'order.created' => 'Order placed',
        'order.paid' => 'Order paid',
        'order.shipped' => 'Order shipped',
        'order.completed' => 'Order completed',
        'order.cancelled' => 'Order cancelled',
        'order.refunded' => 'Order refunded',
        'customer.registered' => 'Customer registered',
        'subscriber.added' => 'Newsletter signup',
        'giftcard.issued' => 'Gift card issued',
        'credit.added' => 'Store credit added',
    ];

    public static function fire(string $event, array $ctx): void
    {
        try {
            if (! isset(self::EVENTS[$event]) || ! installed()) return;
            $email = mb_strtolower(trim((string) ($ctx['email'] ?? ($ctx['order']->email ?? ($ctx['user']->email ?? '')))));
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) return;
            foreach (Automation::where('enabled', true)->where('type', 'custom')->where('trigger_event', $event)->get() as $a) {
                if (! self::matches($a, $ctx)) continue;
                self::enqueue($a, $event, $email, $ctx);
            }
        } catch (\Throwable $e) {
            \Log::debug('Triggers::fire '.$event.': '.$e->getMessage());
        }
    }

    /** Conditions are only checked when the event carries an order. */
    public static function matches(Automation $a, array $ctx): bool
    {
        $c = $a->conditionList();
        $o = $ctx['order'] ?? null;
        if (! $o instanceof Order) return true;
        $total = (int) $o->total;
        if (! empty($c['min_total']) && $total < (int) $c['min_total']) return false;
        if (! empty($c['max_total']) && $total > (int) $c['max_total']) return false;
        if (! empty($c['payment_method']) && $o->payment_method !== $c['payment_method']) return false;
        if (! empty($c['country']) && stripos((string) $o->ship_country, (string) $c['country']) === false) return false;
        if (! empty($c['first_order']) && Order::where('email', $o->email)->where('id', '<>', $o->id)->where('status', '<>', 'cancelled')->exists()) return false;
        $pids = array_values(array_filter(array_map('intval', (array) ($c['product_ids'] ?? []))));
        $cids = array_values(array_filter(array_map('intval', (array) ($c['category_ids'] ?? []))));
        if ($pids || $cids) {
            $in = $o->items()->pluck('product_id')->all();
            $hit = $pids && array_intersect($pids, $in);
            if (! $hit && $cids) $hit = Product::whereIn('id', $in)->whereIn('category_id', $cids)->exists();
            if (! $hit) return false;
        }

        return true;
    }

    private static function enqueue(Automation $a, string $event, string $email, array $ctx): void
    {
        $o = $ctx['order'] ?? null;
        $user = $ctx['user'] ?? User::where('email', $email)->first();
        $name = (string) ($ctx['name'] ?? ($o->name ?? ($user->name ?? '')));
        $base = (string) ($ctx['ref'] ?? ($o instanceof Order ? 'o'.$o->id : $event.':'.substr(md5($email.date('Y-m-d')), 0, 10)));
        $payload = [
            'order_number' => $o->number ?? ($ctx['order_number'] ?? ''), 'order_total' => $o->total ?? ($ctx['total'] ?? null),
            'order_url' => $o instanceof Order ? $o->publicUrl() : '', 'tracking' => $o->tracking ?? '', 'tracking_url' => $o->tracking_url ?? '',
            'gift_code' => $ctx['gift_code'] ?? ($ctx['giftcard']->code ?? ''), 'gift_amount' => $ctx['gift_amount'] ?? ($ctx['giftcard']->initial ?? null),
            'credit_amount' => $ctx['credit_amount'] ?? null, 'coupon' => $ctx['coupon'] ?? '',
        ];
        $steps = $a->steps();
        $first = true;
        foreach ($steps as $i => $s) {
            $run = now()->addMinutes(max(0, (int) ($s['delay_minutes'] ?? 0)));
            try {
                AutomationQueue::create(['automation_id' => $a->id, 'email' => $email, 'name' => $name, 'ref' => Str::limit($base.':s'.$i, 78, ''), 'step' => $i,
                    'payload' => json_encode($payload), 'run_at' => $run, 'status' => 'pending']);
            } catch (\Throwable) {
                $first = false; // duplicate → already queued for this event
                continue;
            }
        }
        // optional "also add store credit" action, once per event
        $credit = (int) ($a->conditionList()['credit_cents'] ?? 0);
        if ($first && $steps && $credit > 0 && $user && class_exists(\App\Services\StoreCredit::class)) {
            try {
                \App\Services\StoreCredit::add($user, $credit, 'Automation: '.$a->name);
            } catch (\Throwable $e) {
                \Log::debug('automation credit: '.$e->getMessage());
            }
        }
    }
}
