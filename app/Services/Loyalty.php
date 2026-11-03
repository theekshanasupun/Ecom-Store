<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Support\Mailer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Loyalty core (v2.7): settings, the permanent ledger, earning, reversals and checkout tenders.
 *
 * WALLETS — three separate balances:
 *   points    -> loyalty_ledger (wallet = points)    cached in users.points
 *   cashback  -> loyalty_ledger (wallet = cashback)  cached in users.cashback (cents)
 *   credit    -> credit_log / users.credit           (StoreCredit service, unchanged)
 * Balances are never written directly: every change is a ledger row; the cached column is recomputed from the ledger inside the
 * same DB transaction. A unique `reference` makes every award idempotent. Positive rows are "lots" with a `remaining` amount so that
 * expiry and spending work first-expiring-first-out.
 */
final class Loyalty
{
    public const WALLETS = ['points' => 'Loyalty points', 'cashback' => 'Cashback'];

    public const TYPES = ['earn' => 'Earned', 'bonus' => 'Bonus', 'redeem' => 'Redeemed', 'expire' => 'Expired', 'reverse' => 'Reversed', 'adjust' => 'Adjustment', 'refund' => 'Returned'];

    /** Order status ladder used for "earn after status". */
    private const RANK = ['pending' => 0, 'processing' => 1, 'shipped' => 2, 'completed' => 3];

    public static function defaults(): array
    {
        return [
            'loyalty_on' => '1', 'loyalty_name' => 'Rewards', 'loyalty_desc' => 'Earn points on every order, unlock tiers and spend them on rewards.', 'loyalty_color' => '#c6ff3d', 'loyalty_logo' => '',
            'loyalty_earn_rate' => '1', 'loyalty_round' => 'floor', 'loyalty_redeem_rate' => '100', 'loyalty_min_redeem' => '100', 'loyalty_max_redeem' => '0', 'loyalty_max_redeem_pct' => '100',
            'loyalty_expiry_months' => '12', 'loyalty_expiry_remind_days' => '30', 'loyalty_earn_status' => 'completed', 'loyalty_hold_days' => '0',
            'loyalty_guest_earn' => '0', 'loyalty_exclude_staff' => '1', 'loyalty_stack_coupons' => '1', 'loyalty_excl_products' => '', 'loyalty_excl_categories' => '',
            'loyalty_max_points_day' => '5000', 'loyalty_redeem_approval' => '0', 'loyalty_staff_adjust_limit' => '1000',
            'loyalty_tier_period' => '12', 'loyalty_tier_auto_up' => '1', 'loyalty_tier_auto_down' => '1',
            'cashback_on' => '1', 'cashback_pct' => '0', 'cashback_expiry_months' => '0', 'cashback_min_redeem' => '0', 'cashback_max_pct' => '100',
            'ref_on' => '1', 'ref_referrer_points' => '500', 'ref_referrer_cashback' => '0', 'ref_referee_points' => '100', 'ref_referee_pct' => '10', 'ref_min_order' => '0',
            'ref_max_per_user' => '50', 'ref_approve' => '0', 'ref_start' => '', 'ref_end' => '', 'ref_ambassador_pct' => '5',
            'pts_welcome' => '100', 'pts_first_purchase' => '100', 'pts_birthday' => '200', 'pts_anniversary' => '100', 'pts_review' => '50', 'pts_photo_review' => '100',
            'pts_video_review' => '150', 'pts_qa' => '20', 'pts_comeback' => '150', 'pts_social' => '25', 'comeback_days' => '90',
            'checkin_on' => '1', 'checkin_points' => '5', 'checkin_streak_every' => '7', 'checkin_streak_bonus' => '50',
            'spin_on' => '0', 'spin_per_day' => '1', 'spin_prizes' => "10 points|points|10|50\n25 points|points|25|30\n50 points|points|50|15\nBetter luck next time|nothing|0|20\n200 points|points|200|2",
            'scratch_on' => '0', 'scratch_per_day' => '1', 'scratch_prizes' => "5 points|points|5|50\nCashback 1.00|cashback|100|20\n20 points|points|20|20\nTry again tomorrow|nothing|0|30\nMystery 100 points|points|100|3",
            'loyalty_terms' => "Points are earned on eligible purchases once an order reaches the qualifying status. Points have no cash value and may be changed or withdrawn if abused.",
            'loyalty_expiry_policy' => 'Points expire after the period shown in your wallet. You will be reminded before they expire.',
            'loyalty_redeem_policy' => 'Rewards and vouchers are single-use unless stated. Redeemed rewards can be cancelled while unused.',
            'loyalty_webhook_url' => '', 'loyalty_webhook_secret' => '', 'loyalty_api_token' => '',
            'loyalty_notify_email' => '1', 'loyalty_last_tick' => '',
        ];
    }

    // ------------------------------------------------------------------ config helpers
    public static function on(): bool
    {
        return setting('loyalty_on', '1') === '1';
    }

    public static function name(): string
    {
        return (string) setting('loyalty_name', 'Rewards') ?: 'Rewards';
    }

    public static function cashbackOn(): bool
    {
        return setting('cashback_on', '1') === '1';
    }

    public static function cfgInt(string $k): int
    {
        return (int) setting($k, 0);
    }

    public static function ids(string $csv): array
    {
        return array_values(array_filter(array_map('intval', preg_split('/[\s,]+/', $csv) ?: [])));
    }

    public static function round(float $v): int
    {
        return match (setting('loyalty_round', 'floor')) {
            'ceil' => (int) ceil($v - 1e-9), 'round' => (int) round($v), default => (int) floor($v + 1e-9),
        };
    }

    /** Value in cents of a number of points. */
    public static function pointsValue(int $points): int
    {
        $rate = max(1, (float) setting('loyalty_redeem_rate', 100));

        return (int) floor($points * 100 / $rate + 1e-9);
    }

    public static function pointsFor(int $cents): int
    {
        $rate = max(0.0001, (float) setting('loyalty_redeem_rate', 100));

        return (int) ceil($cents * $rate / 100 - 1e-9);
    }

    public static function active(?User $u): bool
    {
        return $u && self::on() && $u->loyalty_status !== 'suspended' && ! (setting('loyalty_exclude_staff', '1') === '1' && $u->isStaff());
    }

    // ------------------------------------------------------------------ membership
    public static function newLoyaltyId(): string
    {
        do {
            $id = 'KR-'.strtoupper(Str::random(8));
            $id = strtr($id, ['O' => 'X', '0' => '7', 'I' => 'Y', '1' => '9']);
        } while (User::where('loyalty_id', $id)->exists());

        return $id;
    }

    public static function newReferralCode(User $u): string
    {
        $base = strtoupper(preg_replace('/[^A-Za-z]/', '', Str::ascii(explode(' ', trim($u->name))[0])) ?: 'FRIEND');
        $base = substr($base, 0, 8);
        do {
            $code = $base.random_int(100, 9999);
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    /** Make sure a customer has a loyalty ID, referral code and tier. Safe to call repeatedly. */
    public static function ensureMember(User $u, bool $welcome = false): User
    {
        if (! $u->exists || $u->isStaff()) return $u;
        $upd = [];
        if (! $u->loyalty_id) $upd['loyalty_id'] = self::newLoyaltyId();
        if (! $u->referral_code) $upd['referral_code'] = self::newReferralCode($u);
        if (! $u->loyalty_joined_at) $upd['loyalty_joined_at'] = $u->created_at ?: now();
        if (! $u->tier_id && ($t = LoyaltyTiers::base())) {
            $upd['tier_id'] = $t->id;
            $upd['tier_since'] = now();
            $upd['tier_until'] = LoyaltyTiers::until($t);
        }
        if ($upd) {
            $u->forceFill($upd)->saveQuietly();
        }
        if ($welcome && self::active($u) && (int) setting('pts_welcome', 0) > 0) {
            self::award($u, 'welcome', (int) setting('pts_welcome'), 'welcome:'.$u->id, 'Welcome bonus');
        }

        return $u;
    }

    /** User created hook: enrol, welcome bonus, referral attachment. */
    public static function onUserCreated(User $u): void
    {
        try {
            if (! installed() || ! self::on() || $u->isStaff()) return;
            if (! $u->reg_ip && ! app()->runningInConsole()) $u->forceFill(['reg_ip' => request()->ip()])->saveQuietly();
            self::ensureMember($u, true);
            LoyaltyEngagement::attachReferral($u);
            LoyaltyEngagement::workflows('user.registered', $u, ['ref' => 'reg'.$u->id]);
        } catch (\Throwable $e) {
            \Log::warning('loyalty enrol: '.$e->getMessage());
        }
    }

    // ------------------------------------------------------------------ ledger
    public static function balance(?User $u, string $wallet = 'points'): int
    {
        return $u ? (int) DB::table('loyalty_ledger')->where('user_id', $u->id)->where('wallet', $wallet)->where('status', 'available')->sum('amount') : 0;
    }

    public static function pending(?User $u, string $wallet = 'points'): int
    {
        return $u ? (int) DB::table('loyalty_ledger')->where('user_id', $u->id)->where('wallet', $wallet)->where('status', 'pending')->sum('amount') : 0;
    }

    public static function recalc(int $userId): void
    {
        $sum = fn ($w) => (int) DB::table('loyalty_ledger')->where('user_id', $userId)->where('wallet', $w)->where('status', 'available')->sum('amount');
        DB::table('users')->where('id', $userId)->update(['points' => $sum('points'), 'cashback' => $sum('cashback'), 'loyalty_activity_at' => now()]);
    }

    private static function expiryFor(string $wallet, ?Carbon $from = null): ?Carbon
    {
        $m = (int) setting($wallet === 'cashback' ? 'cashback_expiry_months' : 'loyalty_expiry_months', 0);

        return $m > 0 ? ($from ?: now())->copy()->addMonths($m) : null;
    }

    /**
     * Write one ledger row. Idempotent on `reference`. Returns the row (stdClass) and sets $dup when it already existed.
     * Options: reference, order_id, reason, actor, status (available|pending), expires_at (Carbon|null|'auto'), meta (array), allow_negative (bool)
     *
     * @throws \RuntimeException when a debit would exceed the available balance
     */
    public static function post(User $u, string $wallet, string $type, int $amount, array $o = [], ?bool &$dup = null): object
    {
        $dup = false;
        $ref = isset($o['reference']) ? (string) $o['reference'] : null;
        if ($ref !== null && ($ex = DB::table('loyalty_ledger')->where('reference', $ref)->first())) {
            $dup = true;

            return $ex;
        }
        if ($amount === 0) throw new \RuntimeException('Amount must not be zero.');
        $status = $o['status'] ?? 'available';
        try {
            $row = DB::transaction(function () use ($u, $wallet, $type, $amount, $o, $ref, $status) {
                DB::table('users')->where('id', $u->id)->lockForUpdate()->first();
                if ($amount < 0 && $status === 'available') {
                    if (empty($o['allow_negative']) && self::balance($u, $wallet) < -$amount) {
                        throw new \RuntimeException('Insufficient '.($wallet === 'cashback' ? 'cashback' : 'points').' balance.');
                    }
                    self::consume($u->id, $wallet, -$amount);
                }
                $expires = $o['expires_at'] ?? null;
                if ($expires === 'auto') $expires = $amount > 0 ? self::expiryFor($wallet) : null;
                $id = DB::table('loyalty_ledger')->insertGetId([
                    'user_id' => $u->id, 'wallet' => $wallet, 'type' => $type, 'amount' => $amount,
                    'remaining' => ($amount > 0 && $status === 'available') ? $amount : 0, 'status' => $status, 'reference' => $ref,
                    'order_id' => $o['order_id'] ?? null, 'reason' => isset($o['reason']) ? mb_substr((string) $o['reason'], 0, 190) : null,
                    'actor' => $o['actor'] ?? 'system', 'expires_at' => $amount > 0 ? $expires : null,
                    'available_at' => $status === 'available' ? now() : null, 'meta' => isset($o['meta']) ? json_encode($o['meta']) : null, 'created_at' => now(),
                ]);
                if ($status === 'available') self::recalc($u->id);

                return DB::table('loyalty_ledger')->where('id', $id)->first();
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            $dup = true;

            return DB::table('loyalty_ledger')->where('reference', $ref)->first();
        }
        $u->points = (int) DB::table('users')->where('id', $u->id)->value('points');
        $u->cashback = (int) DB::table('users')->where('id', $u->id)->value('cashback');

        return $row;
    }

    /** Take $amount out of positive lots, first-expiring first. Shortfall (reversals) simply drives the balance negative. */
    private static function consume(int $uid, string $wallet, int $amount): void
    {
        $lots = DB::table('loyalty_ledger')->where('user_id', $uid)->where('wallet', $wallet)->where('status', 'available')->where('remaining', '>', 0)
            ->orderByRaw('expires_at is null')->orderBy('expires_at')->orderBy('id')->lockForUpdate()->get();
        foreach ($lots as $lot) {
            if ($amount <= 0) break;
            $take = min($amount, (int) $lot->remaining);
            DB::table('loyalty_ledger')->where('id', $lot->id)->update(['remaining' => $lot->remaining - $take]);
            $amount -= $take;
        }
    }

    /** Credit (positive) points/cashback with notification. Used for all promotional/event awards. */
    public static function award(User $u, string $event, int $amount, string $ref, string $reason, array $o = []): ?object
    {
        if ($amount <= 0 || ! self::active($u)) return null;
        $wallet = $o['wallet'] ?? 'points';
        if ($wallet === 'cashback' && ! self::cashbackOn()) return null;
        $limit = (int) setting('loyalty_max_points_day', 0);
        if ($wallet === 'points' && $limit > 0 && empty($o['force'])) {
            $today = (int) DB::table('loyalty_ledger')->where('user_id', $u->id)->where('wallet', 'points')->whereIn('type', ['bonus'])->where('created_at', '>=', today())->sum('amount');
            if ($today + $amount > $limit) {
                self::flag($u->id, 'suspicious_points', 'medium', 'Daily bonus limit ('.$limit.') reached while awarding "'.$event.'"', 'cap:'.$u->id.':'.date('Ymd'));

                return null;
            }
        }
        $row = self::post($u, $wallet, $o['type'] ?? 'bonus', $amount, ['reference' => $ref, 'reason' => $reason, 'actor' => $o['actor'] ?? 'system', 'expires_at' => 'auto', 'order_id' => $o['order_id'] ?? null, 'meta' => ['event' => $event]], $dup);
        if (! $dup) {
            self::notify($u, 'points', $wallet === 'cashback' ? 'Cashback credited' : 'Points credited', ($wallet === 'cashback' ? money($amount) : number_format($amount).' points').' — '.$reason, '/account/loyalty?tab=wallet');
            self::hook($wallet === 'cashback' ? 'cashback.credited' : 'points.earned', ['user_id' => $u->id, 'amount' => $amount, 'reason' => $reason, 'event' => $event]);
            LoyaltyTiers::evaluate($u);
        }

        return $dup ? null : $row;
    }

    /** Manual admin adjustment (mandatory reason, audited). Positive or negative. */
    public static function adjust(User $u, string $wallet, int $amount, string $reason, string $actor): object
    {
        $reason = trim($reason);
        if ($reason === '') throw new \RuntimeException('A reason is required for manual adjustments.');
        $row = self::post($u, $wallet, 'adjust', $amount, ['reference' => 'adj:'.Str::uuid(), 'reason' => $reason, 'actor' => $actor, 'expires_at' => 'auto', 'allow_negative' => false]);
        self::audit('adjust.'.$wallet, 'user', $u->id, ['amount' => $amount, 'reason' => $reason], $actor);
        self::notify($u, 'points', $amount > 0 ? 'Rewards added' : 'Rewards deducted', ($wallet === 'cashback' ? money(abs($amount)) : number_format(abs($amount)).' points').' — '.$reason, '/account/loyalty?tab=wallet');
        if ($amount > 0) LoyaltyTiers::evaluate($u);

        return $row;
    }

    /** Expire lots whose time has come (idempotent; writes one `expire` row per lot). */
    public static function expireDue(int $limit = 500): int
    {
        $n = 0;
        $lots = DB::table('loyalty_ledger')->where('status', 'available')->where('remaining', '>', 0)->whereNotNull('expires_at')->where('expires_at', '<=', now())->orderBy('id')->limit($limit)->get();
        foreach ($lots as $lot) {
            DB::transaction(function () use ($lot, &$n) {
                $fresh = DB::table('loyalty_ledger')->where('id', $lot->id)->lockForUpdate()->first();
                if (! $fresh || $fresh->remaining <= 0) return;
                DB::table('loyalty_ledger')->where('id', $lot->id)->update(['remaining' => 0]);
                try {
                    DB::table('loyalty_ledger')->insert(['user_id' => $lot->user_id, 'wallet' => $lot->wallet, 'type' => 'expire', 'amount' => -$fresh->remaining, 'remaining' => 0, 'status' => 'available',
                        'reference' => 'expire:'.$lot->id, 'reason' => 'Expired', 'actor' => 'system', 'created_at' => now(), 'available_at' => now()]);
                } catch (\Throwable) {
                    return;
                }
                self::recalc((int) $lot->user_id);
                $n++;
            });
            if ($u = User::find($lot->user_id)) self::notify($u, 'points', 'Points expired', ($lot->wallet === 'cashback' ? money($lot->remaining) : number_format($lot->remaining).' points').' expired.', '/account/loyalty?tab=wallet');
        }

        return $n;
    }

    // ------------------------------------------------------------------ earning (orders)
    public static function userForOrder(Order $o): ?User
    {
        if ($o->user_id) return User::find($o->user_id);
        if (setting('loyalty_guest_earn', '0') === '1') return User::where('email', mb_strtolower($o->email))->first();

        return null;
    }

    public static function statusMet(Order $o): bool
    {
        if (! $o->isPaid() || in_array($o->status, ['cancelled', 'refunded'], true)) return false;
        $need = (string) setting('loyalty_earn_status', 'completed');
        if ($need === 'paid') return true;

        return (self::RANK[$o->status] ?? 0) >= (self::RANK[$need] ?? 3);
    }

    /** @return array{points:int, cashback:int, lines:array, campaigns:array, base:int} */
    public static function computeEarn(Order $o, User $u): array
    {
        $items = $o->items()->get();
        $gross = (int) $items->sum('total');
        $tender = (int) $o->points_value + (int) $o->cashback_used;
        $factor = $gross > 0 ? max(0, ($gross - (int) $o->discount - (int) $o->tier_discount - $tender) / $gross) : 0;
        $exP = self::ids((string) setting('loyalty_excl_products', ''));
        $exC = self::ids((string) setting('loyalty_excl_categories', ''));
        $products = \App\Models\Product::whereIn('id', $items->pluck('product_id')->filter()->all())->get()->keyBy('id');
        $tier = LoyaltyTiers::of($u);
        $mult = $tier ? (float) $tier->multiplier : 1.0;
        $rules = DB::table('loyalty_rules')->where('active', true)->orderByDesc('priority')->get();
        $baseRate = (float) setting('loyalty_earn_rate', 1);
        $cbBase = self::cashbackOn() ? (float) setting('cashback_pct', 0) + ($tier ? (float) $tier->cashback_pct : 0) : 0;
        $lines = [];
        $pts = 0.0;
        $cash = 0.0;
        $perLine = [];
        $netEligible = 0;
        foreach ($items as $it) {
            if ($it->type === 'giftcard') continue;
            $p = $products[$it->product_id] ?? null;
            if ($p && (in_array((int) $p->id, $exP, true) || in_array((int) $p->category_id, $exC, true))) continue;
            $net = $it->total * $factor;
            $netEligible += $net;
            $rate = $baseRate;
            $extraCb = 0.0;
            foreach ($rules as $r) {
                $ids = self::ids((string) $r->scope_ids);
                $hit = $p && (($r->scope === 'product' && in_array((int) $p->id, $ids, true)) || ($r->scope === 'category' && in_array((int) $p->category_id, $ids, true)));
                if ($hit) {
                    $rate = (float) $r->rate;
                    $extraCb = (float) $r->cashback_pct;
                    break;
                }
            }
            $linePts = $net / 100 * $rate * $mult;
            $pts += $linePts;
            $cash += $net * ($cbBase + $extraCb) / 100;
            $perLine[] = ['pid' => (int) ($p->id ?? 0), 'cat' => (int) ($p->category_id ?? 0), 'pts' => $linePts, 'net' => $net];
            if ($linePts > 0) $lines[] = ['label' => $it->name, 'points' => round($linePts, 2)];
        }
        $base = self::round($pts);
        $extra = 0.0;
        $camps = [];
        $applied = false;
        $first = ! Order::where(fn ($q) => $q->where('user_id', $u->id)->orWhere('email', $u->email))->where('id', '<>', $o->id)->where('payment_status', 'paid')->whereNotIn('status', ['cancelled', 'refunded'])->exists();
        foreach (DB::table('loyalty_campaigns')->where('status', 'active')->orderByDesc('priority')->orderBy('id')->get() as $c) {
            if (! self::campaignLive($c) || ! self::campaignFits($c, $u, $tier, (int) $netEligible, $first)) continue;
            if (! $c->stack && $applied) continue;
            $ids = self::ids((string) $c->scope_ids);
            $scoped = array_filter($perLine, fn ($l) => $c->scope === 'all' || ($c->scope === 'product' && in_array($l['pid'], $ids, true)) || ($c->scope === 'category' && in_array($l['cat'], $ids, true)));
            if (! $scoped) continue;
            $ptsScoped = array_sum(array_column($scoped, 'pts'));
            $netScoped = array_sum(array_column($scoped, 'net'));
            $gain = match ($c->type) {
                'multiplier' => $ptsScoped * ((float) $c->value - 1),
                'bonus_pct' => $ptsScoped * (float) $c->value / 100,
                'bonus_fixed' => (float) $c->value,
                default => 0.0,
            };
            $cbGain = $c->type === 'cashback_pct' ? $netScoped * (float) $c->value / 100 : 0.0;
            if ($c->budget > 0 && $c->spent + ($c->type === 'cashback_pct' ? $cbGain : $gain) > $c->budget) continue;
            if ($gain <= 0 && $cbGain <= 0) continue;
            $extra += $gain;
            $cash += $cbGain;
            $camps[(int) $c->id] = $c->type === 'cashback_pct' ? (int) round($cbGain) : (int) round($gain);
            $lines[] = ['label' => 'Campaign: '.$c->name, 'points' => round($gain, 2)];
            $applied = true;
            if (! $c->stack) break;
        }
        $points = self::round($pts + $extra);
        $cashback = self::cashbackOn() ? (int) round($cash) : 0;

        return ['points' => max(0, $points), 'cashback' => max(0, $cashback), 'lines' => $lines, 'campaigns' => $camps, 'base' => $base];
    }

    private static function campaignLive(object $c): bool
    {
        if ($c->starts_at && Carbon::parse($c->starts_at)->isFuture()) return false;
        if ($c->ends_at && Carbon::parse($c->ends_at)->isPast()) return false;
        if ($c->uses_limit > 0 && $c->uses >= $c->uses_limit) return false;
        if ($c->budget > 0 && $c->spent >= $c->budget) return false;

        return true;
    }

    private static function campaignFits(object $c, User $u, ?object $tier, int $net, bool $first): bool
    {
        $tiers = self::ids((string) $c->tier_ids);
        if ($tiers && (! $tier || ! in_array((int) $tier->id, $tiers, true))) return false;
        if ($c->min_order > 0 && $net < $c->min_order) return false;

        return match ($c->audience) {
            'first_purchase' => $first,
            'birthday_month' => $u->birthday && (int) Carbon::parse($u->birthday)->format('n') === (int) now()->format('n'),
            'customers' => in_array((int) $u->id, self::ids((string) $c->customer_ids), true),
            default => true,
        };
    }

    /** Order just got paid: record PENDING points/cashback, then release if the qualifying status is already met. */
    public static function onOrderPaid(Order $o): void
    {
        try {
            if (! installed() || ! self::on()) return;
            $o->refresh();
            if ($o->loyalty_state !== '') return;
            $u = self::userForOrder($o);
            if (! self::active($u)) return;
            self::ensureMember($u);
            $e = self::computeEarn($o, $u);
            if ($e['points'] > 0) {
                self::post($u, 'points', 'earn', $e['points'], ['reference' => 'order:'.$o->id.':points', 'order_id' => $o->id, 'status' => 'pending', 'reason' => 'Order '.$o->number, 'meta' => ['lines' => $e['lines']]]);
            }
            if ($e['cashback'] > 0) {
                self::post($u, 'cashback', 'earn', $e['cashback'], ['reference' => 'order:'.$o->id.':cashback', 'order_id' => $o->id, 'status' => 'pending', 'reason' => 'Cashback · '.$o->number]);
            }
            foreach ($e['campaigns'] as $cid => $spent) {
                DB::table('loyalty_campaigns')->where('id', $cid)->update(['spent' => DB::raw('spent + '.(int) $spent), 'uses' => DB::raw('uses + 1')]);
            }
            Order::whereKey($o->id)->update(['loyalty_state' => 'pending']);
            $o->loyalty_state = 'pending';
            Orders::event($o, 'Loyalty: '.number_format($e['points']).' points'.($e['cashback'] ? ' + '.money($e['cashback']).' cashback' : '').' pending');
            self::onOrderStatus($o);
        } catch (\Throwable $e) {
            \Log::warning('loyalty onOrderPaid: '.$e->getMessage());
        }
    }

    /** Called when an order's status changes: schedule/perform release of pending rewards. */
    public static function onOrderStatus(Order $o): void
    {
        try {
            $o->refresh();
            if ($o->loyalty_state !== 'pending' || ! self::statusMet($o)) return;
            $at = now()->addDays(max(0, (int) setting('loyalty_hold_days', 0)));
            DB::table('loyalty_ledger')->where('order_id', $o->id)->where('status', 'pending')->whereNull('available_at')->update(['available_at' => $at]);
            if ($at->lte(now())) self::releaseOrder($o->id);
        } catch (\Throwable $e) {
            \Log::warning('loyalty onOrderStatus: '.$e->getMessage());
        }
    }

    /** Release pending rows whose hold period ended (cron). */
    public static function releaseDue(): int
    {
        $ids = DB::table('loyalty_ledger')->where('status', 'pending')->whereNotNull('available_at')->where('available_at', '<=', now())->whereNotNull('order_id')->distinct()->pluck('order_id');
        foreach ($ids as $id) self::releaseOrder((int) $id);

        return count($ids);
    }

    public static function releaseOrder(int $orderId): void
    {
        $o = Order::find($orderId);
        if (! $o || in_array($o->status, ['cancelled', 'refunded'], true)) return;
        $rows = DB::table('loyalty_ledger')->where('order_id', $orderId)->where('status', 'pending')->whereNotNull('available_at')->get();
        if ($rows->isEmpty()) return;
        $u = User::find($rows->first()->user_id);
        if (! $u) return;
        $pts = $cash = 0;
        foreach ($rows as $r) {
            $done = DB::transaction(function () use ($r) {
                DB::table('users')->where('id', $r->user_id)->lockForUpdate()->first();
                $n = DB::table('loyalty_ledger')->where('id', $r->id)->where('status', 'pending')->update(['status' => 'available', 'remaining' => $r->amount, 'available_at' => now(), 'expires_at' => self::expiryFor($r->wallet)]);
                if ($n) self::recalc((int) $r->user_id);

                return $n;
            });
            if ($done) {
                if ($r->wallet === 'points') $pts += $r->amount; else $cash += $r->amount;
            }
        }
        $o->refresh();
        if ($o->loyalty_state === 'pending') Order::whereKey($orderId)->update(['loyalty_state' => 'released']);
        if ($pts > 0) {
            self::notify($u, 'points', 'Points credited', number_format($pts).' points from order '.$o->number.' are now available.', '/account/loyalty?tab=wallet');
            self::hook('points.earned', ['user_id' => $u->id, 'amount' => $pts, 'order' => $o->number]);
        }
        if ($cash > 0) self::notify($u, 'points', 'Cashback credited', money($cash).' cashback from order '.$o->number.' is now available.', '/account/loyalty?tab=wallet');
        LoyaltyEngagement::onOrderReleased($o, $u);
    }

    /**
     * Reverse earned rewards after a cancellation/refund. $refundCents is the amount refunded now (partial refunds reverse proportionally;
     * repeated calls only reverse what is still outstanding).
     */
    public static function onOrderRefund(Order $o, int $refundCents, string $actor = 'system'): void
    {
        try {
            $o->refresh();
            if ($o->total <= 0 || $refundCents <= 0) return;
            $done = min((int) $o->total, (int) $o->loyalty_refunded + $refundCents);
            $ratio = $done / (int) $o->total;
            $u = User::find($o->user_id) ?: self::userForOrder($o);
            foreach (DB::table('loyalty_ledger')->where('order_id', $o->id)->where('type', 'earn')->get() as $lot) {
                if ($lot->status === 'pending') {
                    $meta = json_decode((string) $lot->meta, true) ?: [];
                    $orig = (int) ($meta['original'] ?? $lot->amount);
                    $meta['original'] = $orig;
                    if ($ratio >= 0.9999) {
                        DB::table('loyalty_ledger')->where('id', $lot->id)->update(['status' => 'reversed', 'meta' => json_encode($meta), 'reason' => mb_substr(($lot->reason ?? '').' (reversed)', 0, 190)]);
                    } else {
                        DB::table('loyalty_ledger')->where('id', $lot->id)->update(['amount' => max(0, $orig - (int) round($orig * $ratio)), 'meta' => json_encode($meta)]);
                    }
                    continue;
                }
                $target = (int) round($lot->amount * $ratio);
                $already = (int) abs(DB::table('loyalty_ledger')->where('order_id', $o->id)->where('wallet', $lot->wallet)->where('type', 'reverse')->where('status', 'available')->sum('amount'));
                if ($lot->status !== 'available') continue;
                $delta = $target - $already;
                if ($delta > 0 && $u) {
                    self::post($u, $lot->wallet, 'reverse', -$delta, ['reference' => 'rev:'.$o->id.':'.$lot->wallet.':'.$target, 'order_id' => $o->id, 'reason' => 'Order '.$o->number.($ratio >= 0.9999 ? ' cancelled/refunded' : ' partially refunded'), 'actor' => $actor, 'allow_negative' => true]);
                    self::notify($u, 'points', 'Points reversed', ($lot->wallet === 'cashback' ? money($delta) : number_format($delta).' points').' were removed because order '.$o->number.' was refunded or cancelled.', '/account/loyalty?tab=wallet');
                }
            }
            Order::whereKey($o->id)->update(['loyalty_refunded' => $done, 'loyalty_state' => $ratio >= 0.9999 ? 'reversed' : $o->loyalty_state]);
            if ($ratio >= 0.9999) LoyaltyEngagement::onOrderReversed($o);
            if ($u) LoyaltyTiers::evaluate($u);
        } catch (\Throwable $e) {
            \Log::warning('loyalty onOrderRefund: '.$e->getMessage());
        }
    }

    // ------------------------------------------------------------------ checkout tenders
    /**
     * What the signed-in customer can redeem on this checkout. $room = cents still payable after coupons, gift card and store credit.
     * $reqPoints = points the customer asked for (0 = none), $reqCash = cashback toggle.
     */
    public static function checkout(?User $u, int $room, int $reqPoints, bool $reqCash, bool $hasCoupon): array
    {
        $out = ['on' => false, 'blocked' => '', 'rate' => (float) setting('loyalty_redeem_rate', 100), 'points_balance' => 0, 'points_max' => 0, 'points' => 0, 'points_value' => 0, 'cash_balance' => 0, 'cash' => 0, 'min' => (int) setting('loyalty_min_redeem', 0)];
        if (! self::active($u)) return $out;
        $out['on'] = true;
        $out['points_balance'] = (int) $u->points;
        $out['cash_balance'] = self::cashbackOn() ? (int) $u->cashback : 0;
        if ($hasCoupon && setting('loyalty_stack_coupons', '1') !== '1') {
            $out['blocked'] = 'Points cannot be combined with a discount code.';

            return $out;
        }
        $pct = max(0, min(100, (int) setting('loyalty_max_redeem_pct', 100)));
        $cap = (int) floor($room * $pct / 100);
        $maxPts = min($out['points_balance'], (int) floor($cap * $out['rate'] / 100 + 1e-9));
        $perOrder = (int) setting('loyalty_max_redeem', 0);
        if ($perOrder > 0) $maxPts = min($maxPts, $perOrder);
        if ($maxPts < $out['min']) $maxPts = 0;
        $out['points_max'] = $maxPts;
        if ($reqPoints > 0 && $maxPts > 0 && ($reqPoints >= $out['min'] || $reqPoints >= $maxPts)) {
            $pts = min($reqPoints, $maxPts);
            $out['points'] = $pts;
            $out['points_value'] = min($room, self::pointsValue($pts));
        }
        $roomCb = $room - $out['points_value'];
        if ($reqCash && $out['cash_balance'] > 0 && $roomCb > 0 && $out['cash_balance'] >= (int) setting('cashback_min_redeem', 0)) {
            $capCb = (int) floor($room * max(0, min(100, (int) setting('cashback_max_pct', 100))) / 100) - $out['points_value'];
            $out['cash'] = max(0, min($out['cash_balance'], $roomCb, $capCb));
        }

        return $out;
    }

    /** Called inside Orders::create's transaction. Returns [pointsUsed, pointsValue, cashbackUsed]. */
    public static function spend(User $u, Order $o, int $points, int $pointsValue, int $cash): array
    {
        $usedP = $usedV = $usedC = 0;
        if ($points > 0) {
            $row = self::post($u, 'points', 'redeem', -$points, ['reference' => 'redeem:order:'.$o->id.':points', 'order_id' => $o->id, 'reason' => 'Order '.$o->number, 'actor' => 'customer']);
            if ($row) { $usedP = $points; $usedV = $pointsValue; }
        }
        if ($cash > 0) {
            self::post($u, 'cashback', 'redeem', -$cash, ['reference' => 'redeem:order:'.$o->id.':cashback', 'order_id' => $o->id, 'reason' => 'Order '.$o->number, 'actor' => 'customer']);
            $usedC = $cash;
        }
        if ($usedP || $usedC) {
            Order::whereKey($o->id)->update(['points_used' => $usedP, 'points_value' => $usedV, 'cashback_used' => $usedC]);
        }

        return [$usedP, $usedV, $usedC];
    }

    /** Give points/cashback spent on a cancelled/refunded order back (idempotent). */
    public static function restoreTenders(Order $o, string $why): void
    {
        try {
            $o->refresh();
            $u = $o->user_id ? User::find($o->user_id) : null;
            if (! $u) return;
            foreach (['points' => (int) $o->points_used, 'cashback' => (int) $o->cashback_used] as $wallet => $amt) {
                if ($amt <= 0) continue;
                $row = self::post($u, $wallet, 'refund', $amt, ['reference' => 'restore:order:'.$o->id.':'.$wallet, 'order_id' => $o->id, 'reason' => $why.' — '.$o->number, 'actor' => 'system', 'expires_at' => 'auto'], $dup);
                if (! $dup) Orders::event($o, 'Loyalty '.$wallet.' returned: '.($wallet === 'cashback' ? money($amt) : number_format($amt).' pts'));
            }
        } catch (\Throwable $e) {
            \Log::warning('loyalty restoreTenders: '.$e->getMessage());
        }
    }

    // ------------------------------------------------------------------ notifications, audit, flags, webhooks
    public static function prefs(User $u): array
    {
        $d = ['email' => true, 'site' => true, 'push' => false, 'types' => ['points' => true, 'tier' => true, 'rewards' => true, 'referral' => true, 'challenge' => true, 'promo' => true]];
        $p = json_decode((string) $u->loyalty_prefs, true);

        return is_array($p) ? array_replace_recursive($d, $p) : $d;
    }

    public static function notify(User $u, string $type, string $title, string $body = '', ?string $url = null): void
    {
        try {
            $pr = self::prefs($u);
            if (isset($pr['types'][$type]) && ! $pr['types'][$type]) return;
            DB::table('loyalty_notifications')->insert(['user_id' => $u->id, 'type' => $type, 'title' => mb_substr($title, 0, 190), 'body' => $body, 'url' => $url, 'created_at' => now()]);
            if ($pr['email'] && setting('loyalty_notify_email', '1') === '1' && filter_var($u->email, FILTER_VALIDATE_EMAIL)) {
                $inner = '<p style="color:#cfcfd6;line-height:1.6">'.e($body).'</p>'.($url ? '<p style="margin-top:18px"><a style="display:inline-block;background:#c6ff3d;color:#0b0b0d;padding:11px 20px;border-radius:99px;font-weight:700;text-decoration:none" href="'.e(url($url)).'">Open '.e(self::name()).'</a></p>' : '');
                Mailer::send($u->email, $title.' · '.self::name(), Mailer::wrap($title, $inner));
            }
        } catch (\Throwable $e) {
            \Log::debug('loyalty notify: '.$e->getMessage());
        }
    }

    public static function audit(string $action, ?string $subject = null, ?int $id = null, array $details = [], ?string $actor = null): void
    {
        try {
            $actor ??= auth()->check() ? 'admin:'.auth()->user()->name : 'system';
            DB::table('loyalty_audit')->insert(['actor' => $actor, 'action' => $action, 'subject' => $subject, 'subject_id' => $id, 'details' => json_encode($details, JSON_UNESCAPED_UNICODE), 'ip' => app()->runningInConsole() ? null : request()->ip(), 'created_at' => now()]);
        } catch (\Throwable) {
        }
    }

    public static function flag(?int $userId, string $type, string $severity, string $details, ?string $dedupe = null): void
    {
        try {
            DB::table('loyalty_flags')->insert(['user_id' => $userId, 'type' => $type, 'severity' => $severity, 'details' => mb_substr($details, 0, 250), 'dedupe' => $dedupe, 'created_at' => now()]);
        } catch (\Throwable) {
            // duplicate dedupe key -> already flagged
        }
    }

    /** Outbound webhook (HMAC-SHA256 signed). Sent after the response, failures are swallowed. */
    public static function hook(string $event, array $payload): void
    {
        $url = trim((string) setting('loyalty_webhook_url', ''));
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) return;
        $body = json_encode(['event' => $event, 'time' => now()->toIso8601String(), 'data' => $payload]);
        $secret = (string) setting('loyalty_webhook_secret', '');
        app()->terminating(function () use ($url, $body, $secret) {
            try {
                \Illuminate\Support\Facades\Http::timeout(3)->withHeaders(['X-Loyalty-Signature' => hash_hmac('sha256', $body, $secret), 'Content-Type' => 'application/json'])->withBody($body, 'application/json')->post($url);
            } catch (\Throwable) {
            }
        });
    }

    // ------------------------------------------------------------------ voucher helper
    /** Create a single-customer coupon. Returns the Coupon. */
    public static function makeVoucher(User $u, string $source, string $type, int $value, array $o = []): \App\Models\Coupon
    {
        do {
            $code = ($o['prefix'] ?? 'LR').'-'.strtoupper(Str::random(8));
        } while (\App\Models\Coupon::where('code', $code)->exists());

        return \App\Models\Coupon::create([
            'code' => $code, 'type' => $type === 'percent' ? 'percent' : 'fixed', 'value' => $value, 'min_total' => (int) ($o['min_total'] ?? 0), 'max_uses' => 1, 'used' => 0,
            'starts_at' => null, 'ends_at' => $o['ends_at'] ?? null, 'status' => 'active', 'scope' => $o['scope'] ?? 'cart', 'product_ids' => $o['product_ids'] ?? null, 'category_ids' => $o['category_ids'] ?? null,
            'per_user_limit' => 1, 'free_shipping' => (bool) ($o['free_shipping'] ?? false), 'max_discount' => (int) ($o['max_discount'] ?? 0), 'note' => mb_substr((string) ($o['note'] ?? 'Loyalty voucher'), 0, 190),
            'user_id' => $u->id, 'source' => $source,
        ]);
    }
}
