<?php

namespace App\Services;

use App\Models\GiftCard;
use App\Models\GiftCardLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Mailer;
use App\Support\Triggers;
use Illuminate\Support\Facades\DB;

/** Gift cards: issue, validate, redeem, restore, email and scheduled delivery. */
final class GiftCards
{
    /** Card designs: key => [label, gradient start, gradient end, text colour]. */
    public const DESIGNS = [
        'classic' => ['Classic', '#c6ff3d', '#7ad100', '#0b0b0d'],
        'midnight' => ['Midnight', '#1d2b53', '#7e2553', '#ffffff'],
        'sunset' => ['Sunset', '#ff7a2f', '#ff3d81', '#ffffff'],
        'ocean' => ['Ocean', '#3b6cff', '#22d3ee', '#ffffff'],
        'party' => ['Party', '#8b7bff', '#ff5aa5', '#ffffff'],
        'mono' => ['Mono', '#2a2a30', '#0b0b0d', '#f3f1ec'],
    ];

    public static function enabled(): bool
    {
        return setting('gc_on', '1') === '1';
    }

    public static function amounts(): array
    {
        $a = array_values(array_unique(array_filter(array_map(fn ($x) => (int) round(((float) trim($x)) * 100), preg_split('/[\s,;]+/', (string) setting('gc_amounts', '25,50,100,200'))), fn ($c) => $c > 0)));
        sort($a);

        return $a;
    }

    public static function designs(): array
    {
        $on = array_filter(array_map('trim', explode(',', (string) setting('gc_designs', implode(',', array_keys(self::DESIGNS))))));
        $out = [];
        foreach (self::DESIGNS as $k => $d) if (in_array($k, $on, true)) $out[$k] = $d;

        return $out ?: self::DESIGNS;
    }

    public static function code(): string
    {
        $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $c = '';
            for ($i = 0; $i < 16; $i++) $c .= $chars[random_int(0, strlen($chars) - 1)].($i % 4 === 3 && $i < 15 ? '-' : '');
        } while (GiftCard::where('code', $c)->exists());

        return $c;
    }

    public static function normalise(string $code): string
    {
        $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));

        return strlen($c) === 16 ? implode('-', str_split($c, 4)) : strtoupper(trim($code));
    }

    public static function log(GiftCard $g, int $amount, string $reason, ?int $orderId = null, ?string $actor = null): void
    {
        GiftCardLog::create(['gift_card_id' => $g->id, 'amount' => $amount, 'balance' => $g->balance, 'reason' => mb_substr($reason, 0, 190), 'order_id' => $orderId, 'actor' => $actor, 'created_at' => now()]);
    }

    /** Create a card. $d: amount (cents), recipient_name/email, sender_name, message, design, deliver_at, expires_at, note, created_via, order_id, purchaser_user_id, send (bool) */
    public static function issue(array $d, ?string $actor = null): GiftCard
    {
        $months = (int) setting('gc_expiry_months', 0);
        $g = GiftCard::create([
            'code' => $d['code'] ?? self::code(), 'initial' => (int) $d['amount'], 'balance' => (int) $d['amount'], 'status' => 'active',
            'expires_at' => $d['expires_at'] ?? ($months > 0 ? now()->addMonths($months) : null),
            'design' => isset(self::DESIGNS[$d['design'] ?? '']) ? $d['design'] : 'classic',
            'recipient_name' => $d['recipient_name'] ?? null, 'recipient_email' => $d['recipient_email'] ?? null, 'sender_name' => $d['sender_name'] ?? null,
            'message' => isset($d['message']) ? mb_substr((string) $d['message'], 0, 500) : null, 'deliver_at' => $d['deliver_at'] ?? null,
            'purchaser_user_id' => $d['purchaser_user_id'] ?? null, 'order_id' => $d['order_id'] ?? null, 'note' => $d['note'] ?? null, 'created_via' => $d['created_via'] ?? 'admin',
        ]);
        self::log($g, (int) $d['amount'], ($d['created_via'] ?? 'admin') === 'purchase' ? 'Purchased' : 'Issued', $d['order_id'] ?? null, $actor);
        if (! empty($d['send']) && $g->recipient_email && (! $g->deliver_at || $g->deliver_at->isPast())) self::send($g);

        return $g;
    }

    /** One card per purchased unit, created when the order is paid (idempotent per order item + unit). */
    public static function issueForItem(Order $o, OrderItem $it): int
    {
        if ($it->type !== 'giftcard') return 0;
        $meta = json_decode((string) $it->meta, true) ?: [];
        $made = 0;
        for ($n = 1; $n <= $it->qty; $n++) {
            $tag = 'item:'.$it->id.':'.$n;
            if (GiftCard::where('order_id', $o->id)->where('note', $tag)->exists()) continue;
            $deliver = ! empty($meta['deliver_at']) ? \Illuminate\Support\Carbon::parse($meta['deliver_at']) : null;
            $g = self::issue([
                'amount' => $it->price, 'design' => $meta['design'] ?? 'classic', 'recipient_name' => $meta['to_name'] ?? null,
                'recipient_email' => ($meta['to_email'] ?? '') ?: $o->email, 'sender_name' => ($meta['from'] ?? '') ?: $o->name, 'message' => $meta['msg'] ?? null,
                'deliver_at' => $deliver, 'order_id' => $o->id, 'purchaser_user_id' => $o->user_id, 'note' => $tag, 'created_via' => 'purchase', 'send' => true,
            ], 'order '.$o->number);
            Orders::event($o, 'Gift card '.$g->masked().' ('.money($g->initial).') issued'.($g->deliver_at && $g->deliver_at->isFuture() ? ', scheduled for '.$g->deliver_at->format('Y-m-d') : ''));
            Triggers::fire('giftcard.issued', ['email' => $o->email, 'name' => $o->name, 'order' => $o, 'giftcard' => $g, 'total' => $g->initial, 'ref' => 'gc'.$g->id]);
            $made++;
        }

        return $made;
    }

    /** @return array{0:?GiftCard,1:?string} */
    public static function check(string $code): array
    {
        $g = GiftCard::where('code', self::normalise($code))->first();
        if (! $g) return [null, 'That gift card code was not found.'];
        if ($g->status === 'void') return [null, 'This gift card has been cancelled.'];
        if ($g->isExpired()) return [null, 'This gift card expired on '.$g->expires_at->format('M j, Y').'.'];
        if ($g->balance <= 0) return [null, 'This gift card has no balance left.'];
        if ($g->status !== 'active') return [null, 'This gift card is not active yet.'];

        return [$g, null];
    }

    /** Take $amount off the card inside the caller's transaction. Returns the amount actually taken. */
    public static function redeem(GiftCard $g, int $amount, ?Order $o = null, ?string $actor = null): int
    {
        return DB::transaction(function () use ($g, $amount, $o, $actor) {
            $row = GiftCard::whereKey($g->id)->lockForUpdate()->first();
            $take = max(0, min($amount, (int) $row->balance));
            if ($take <= 0) return 0;
            $row->balance -= $take;
            if ($row->balance <= 0) $row->status = 'used';
            $row->save();
            self::log($row, -$take, 'Used on order '.($o?->number ?? ''), $o?->id, $actor);
            $g->balance = $row->balance;

            return $take;
        });
    }

    public static function restore(string $code, int $amount, ?Order $o = null, string $reason = 'Restored'): void
    {
        $g = GiftCard::where('code', $code)->first();
        if (! $g || $amount <= 0) return;
        $g->balance += $amount;
        if ($g->status === 'used') $g->status = 'active';
        $g->save();
        self::log($g, $amount, $reason.($o ? ' (order '.$o->number.')' : ''), $o?->id, 'system');
    }

    /** Manual balance change by an admin (signed). */
    public static function adjust(GiftCard $g, int $delta, string $reason, string $actor): void
    {
        $g->balance = max(0, $g->balance + $delta);
        if ($g->balance > 0 && $g->status === 'used') $g->status = 'active';
        if ($g->balance <= 0 && $g->status === 'active') $g->status = 'used';
        $g->save();
        self::log($g, $delta, $reason, null, $actor);
    }

    public static function email(GiftCard $g): array
    {
        [$label, $c1, $c2, $fg] = self::DESIGNS[$g->design] ?? self::DESIGNS['classic'];
        $store = e((string) setting('store_name'));
        $from = $g->sender_name ? e($g->sender_name) : 'Someone';
        $greet = $g->recipient_name ? 'Hi '.e(explode(' ', $g->recipient_name)[0]).',' : 'Hello,';
        $msg = $g->message ? '<div style="background:#0b0b0d;border-left:3px solid '.e($c1).';border-radius:10px;padding:14px 16px;margin:16px 0;color:#f3f1ec;font-style:italic">“'.nl2br(e($g->message)).'”</div>' : '';
        $card = '<table width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0"><tr><td style="background:'.e($c1).';background-image:linear-gradient(135deg,'.e($c1).','.e($c2).');border-radius:18px;padding:26px;color:'.e($fg).'">'
            .'<div style="font:700 12px monospace;letter-spacing:3px;text-transform:uppercase;opacity:.8">'.$store.' gift card</div>'
            .'<div style="font:800 40px Arial,sans-serif;margin:10px 0">'.money($g->initial).'</div>'
            .'<div style="font:700 18px monospace;letter-spacing:2px;background:rgba(0,0,0,.18);border-radius:10px;padding:10px 12px;display:inline-block">'.e($g->code).'</div>'
            .($g->expires_at ? '<div style="font-size:12px;margin-top:10px;opacity:.85">Valid until '.e($g->expires_at->format('M j, Y')).'</div>' : '').'</td></tr></table>';
        $inner = '<p style="color:#cfcfd6;line-height:1.6">'.$greet.'<br>'.$from.' sent you a gift card for <b>'.$store.'</b>.</p>'.$msg.$card
            .'<p style="color:#cfcfd6;line-height:1.6">Enter the code at checkout to use your balance. You can check it any time at <a style="color:'.e(setting('accent', '#c6ff3d')).'" href="'.e(url('gift-cards/balance')).'">'.e(url('gift-cards/balance')).'</a>.</p>'
            .'<p style="margin-top:20px"><a style="display:inline-block;background:'.e(setting('accent', '#c6ff3d')).';color:#0b0b0d;padding:12px 22px;border-radius:99px;font-weight:700;text-decoration:none" href="'.e(url('/')).'">Start shopping</a></p>'
            .(setting('gc_terms') ? '<p style="color:#8a8a93;font-size:12px;margin-top:18px">'.nl2br(e((string) setting('gc_terms'))).'</p>' : '');
        $subject = str_replace(['{store}', '{amount}', '{from}'], [(string) setting('store_name'), money($g->initial), $g->sender_name ?: 'Someone'], (string) setting('gc_email_subject', 'You received a {store} gift card'));

        return [$subject, Mailer::wrap('Your gift card', $inner)];
    }

    public static function send(GiftCard $g): bool
    {
        if (! $g->recipient_email) return false;
        [$subject, $html] = self::email($g);
        $ok = Mailer::send($g->recipient_email, $subject, $html);
        if ($ok) $g->update(['sent_at' => now()]);

        return $ok;
    }

    /** Scheduled gift cards whose delivery date has arrived. */
    public static function sendDue(int $limit = 20): int
    {
        $n = 0;
        try {
            foreach (GiftCard::whereNull('sent_at')->whereNotNull('deliver_at')->where('deliver_at', '<=', now())->where('status', 'active')->whereNotNull('recipient_email')->limit($limit)->get() as $g) {
                if (self::send($g)) $n++;
            }
        } catch (\Throwable) {
        }

        return $n;
    }
}
