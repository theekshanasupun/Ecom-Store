<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Membership tiers: qualification, automatic up/downgrades with grace, history and benefits. */
final class LoyaltyTiers
{
    public static function all(bool $activeOnly = true): array
    {
        return DB::table('loyalty_tiers')->when($activeOnly, fn ($q) => $q->where('active', true))->orderBy('sort')->orderBy('id')->get()->all();
    }

    public static function base(): ?object
    {
        return DB::table('loyalty_tiers')->where('active', true)->orderBy('sort')->orderBy('id')->first();
    }

    public static function find(?int $id): ?object
    {
        return $id ? DB::table('loyalty_tiers')->where('id', $id)->first() : null;
    }

    public static function of(?User $u): ?object
    {
        if (! $u) return null;

        return self::find((int) $u->tier_id) ?: null;
    }

    public static function until(object $t): ?Carbon
    {
        return $t->renewal_months > 0 ? now()->addMonths((int) $t->renewal_months) : null;
    }

    /** Qualifying metrics for a customer: net spend (paid, not cancelled/refunded, minus refunded part), annual window and points earned. */
    public static function metrics(User $u): array
    {
        $months = max(1, (int) setting('loyalty_tier_period', 12));
        $since = now()->subMonths($months);
        $q = fn () => Order::where(fn ($w) => $w->where('user_id', $u->id)->orWhere(fn ($x) => $x->whereNull('user_id')->where('email', $u->email)))
            ->where('payment_status', 'paid')->whereNotIn('status', ['cancelled', 'refunded']);
        $net = fn ($from) => max(0, (int) $q()->when($from, fn ($x) => $x->where('paid_at', '>=', $from))->sum('total') - (int) $q()->when($from, fn ($x) => $x->where('paid_at', '>=', $from))->sum('loyalty_refunded'));
        $pts = (int) DB::table('loyalty_ledger')->where('user_id', $u->id)->where('wallet', 'points')->where('status', 'available')->whereIn('type', ['earn', 'bonus', 'adjust'])->where('amount', '>', 0)->where('created_at', '>=', $since)->sum('amount');

        return ['annual' => $net($since), 'lifetime' => $net(null), 'points' => $pts, 'months' => $months, 'orders' => (int) $q()->count()];
    }

    public static function qualifies(object $t, array $m): bool
    {
        return $m['annual'] >= (int) $t->min_annual_spend && $m['lifetime'] >= (int) $t->min_lifetime_spend && $m['points'] >= (int) $t->min_points;
    }

    /** Highest tier the metrics qualify for. */
    public static function target(array $m): ?object
    {
        $hit = null;
        foreach (self::all() as $t) {
            if (self::qualifies($t, $m)) $hit = $t;
        }

        return $hit ?: self::base();
    }

    /** Re-evaluate a customer (call after purchases, refunds, awards and on the nightly run). */
    public static function evaluate(User $u, bool $force = false): ?object
    {
        try {
            if (! Loyalty::active($u)) return self::of($u);
            $u = $u->fresh() ?: $u;
            if ($u->tier_locked && ! $force) return self::of($u);
            $cur = self::of($u);
            $m = self::metrics($u);
            $tgt = self::target($m);
            if (! $tgt) return $cur;
            if (! $cur || ! $cur->active) {
                self::set($u, $tgt, 'Initial tier', 'system');

                return $tgt;
            }
            if ($tgt->id === $cur->id) {
                // still qualified: roll the renewal date forward when it ran out
                if ($u->tier_until && Carbon::parse($u->tier_until)->isPast()) DB::table('users')->where('id', $u->id)->update(['tier_until' => self::until($cur)]);

                return $cur;
            }
            $up = ($tgt->sort <=> $cur->sort) > 0 || (($tgt->sort === $cur->sort) && $tgt->id > $cur->id);
            if ($up && setting('loyalty_tier_auto_up', '1') === '1') {
                self::set($u, $tgt, 'Qualified: spend '.money($m['annual']).' in '.$m['months'].' months', 'system');

                return $tgt;
            }
            if (! $up && setting('loyalty_tier_auto_down', '1') === '1') {
                $end = $u->tier_until ? Carbon::parse($u->tier_until)->addDays((int) $cur->grace_days) : null;
                if ($end && $end->isFuture()) return $cur; // still within the renewal period + grace
                self::set($u, $tgt, 'Did not renew: spend '.money($m['annual']).' in '.$m['months'].' months', 'system');

                return $tgt;
            }

            return $cur;
        } catch (\Throwable $e) {
            \Log::warning('loyalty tier: '.$e->getMessage());

            return null;
        }
    }

    public static function set(User $u, object $t, string $reason, string $actor, bool $lock = false): void
    {
        $from = $u->tier_id ? (int) $u->tier_id : null;
        if ($from === (int) $t->id && ! $lock && ! $u->tier_locked) return;
        DB::table('users')->where('id', $u->id)->update(['tier_id' => $t->id, 'tier_since' => now(), 'tier_until' => self::until($t), 'tier_locked' => $lock]);
        DB::table('loyalty_tier_history')->insert(['user_id' => $u->id, 'from_tier' => $from, 'to_tier' => $t->id, 'reason' => mb_substr($reason, 0, 190), 'actor' => $actor, 'created_at' => now()]);
        $u->tier_id = $t->id;
        if ($from === null || $from === (int) $t->id) return;
        $old = self::find($from);
        $up = $old ? $t->sort >= $old->sort : true;
        Loyalty::notify($u, 'tier', $up ? 'You reached '.$t->name : 'Your tier changed to '.$t->name, $up ? 'Congratulations! Enjoy your new '.$t->name.' benefits.' : 'Your membership moved to '.$t->name.'. Keep shopping to climb back up.', '/account/loyalty?tab=tiers');
        Loyalty::hook('tier.changed', ['user_id' => $u->id, 'from' => $from, 'to' => $t->id]);
        LoyaltyEngagement::workflows($up ? 'tier.upgraded' : 'tier.downgraded', $u, ['ref' => 'tier'.$t->id.'-'.now()->format('Ymd'), 'tier_id' => $t->id]);
    }

    /** Progress toward the next tier: ['next'=>?tier, 'pct'=>0-100, 'needs'=>[labels]]. */
    public static function progress(User $u): array
    {
        $cur = self::of($u);
        $m = self::metrics($u);
        $next = null;
        foreach (self::all() as $t) {
            if ($cur && ($t->sort > $cur->sort || ($t->sort === $cur->sort && $t->id > $cur->id))) {
                $next = $t;
                break;
            }
        }
        if (! $next) return ['cur' => $cur, 'next' => null, 'pct' => 100, 'needs' => [], 'm' => $m];
        $parts = [];
        $needs = [];
        foreach (['annual' => ['min_annual_spend', true], 'lifetime' => ['min_lifetime_spend', true], 'points' => ['min_points', false]] as $k => [$col, $money]) {
            $req = (int) $next->$col;
            if ($req <= 0) continue;
            $parts[] = min(1, $m[$k] / $req);
            $left = max(0, $req - $m[$k]);
            if ($left > 0) $needs[] = ($money ? money($left) : number_format($left).' points').' more '.($k === 'annual' ? 'spend in '.$m['months'].' months' : ($k === 'lifetime' ? 'lifetime spend' : 'points earned'));
        }
        $pct = $parts ? (int) floor(array_sum($parts) / count($parts) * 100) : 100;

        return ['cur' => $cur, 'next' => $next, 'pct' => $pct, 'needs' => $needs, 'm' => $m];
    }

    // ------------------------------------------------------------------ benefits used by the cart
    public static function discount(?User $u, int $base): int
    {
        $t = Loyalty::active($u) ? self::of($u) : null;

        return ($t && $t->discount_pct > 0 && $base > 0) ? (int) round($base * (float) $t->discount_pct / 100) : 0;
    }

    public static function freeDelivery(?User $u): bool
    {
        $t = Loyalty::active($u) ? self::of($u) : null;

        return (bool) ($t && $t->free_delivery);
    }

    /** May this customer buy/see a product gated by tier or early access? Returns null when allowed, or the message. */
    public static function gate(?object $product, ?User $u): ?string
    {
        if (! $product || ! Loyalty::on()) return null;
        $vip = (int) ($product->vip_tier_id ?? 0);
        $mine = Loyalty::active($u) ? self::of($u) : null;
        if ($vip) {
            $need = self::find($vip);
            if ($need && (! $mine || $mine->sort < $need->sort)) return 'VIP only — available to '.$need->name.' members and above.';
        }
        $until = $product->early_access_until ?? null;
        if ($until && Carbon::parse($until)->isFuture()) {
            $need = self::find((int) ($product->early_access_tier_id ?? 0));
            if ($need) {
                if (! $mine || $mine->sort < $need->sort) return 'Early access for '.$need->name.' members until '.Carbon::parse($until)->format('M j, H:i').'.';
            } elseif (! $mine || ! $mine->early_access) {
                return 'Early access for loyalty members until '.Carbon::parse($until)->format('M j, H:i').'.';
            }
        }

        return null;
    }

    public static function seedDefaults(): void
    {
        if (DB::table('loyalty_tiers')->exists()) return;
        $now = now();
        $rows = [
            ['name' => 'Bronze', 'color' => '#cd7f32', 'sort' => 1, 'multiplier' => 1, 'perks' => "Earn 1× points on every order\nBirthday bonus", 'birthday_points' => 100, 'min_annual_spend' => 0],
            ['name' => 'Silver', 'color' => '#c0c0c8', 'sort' => 2, 'multiplier' => 1.25, 'perks' => "1.25× points\nExtra birthday bonus\nEarly access to launches", 'birthday_points' => 200, 'early_access' => true, 'min_annual_spend' => 25000],
            ['name' => 'Gold', 'color' => '#ffc83d', 'sort' => 3, 'multiplier' => 1.5, 'perks' => "1.5× points\nFree delivery\n2% cashback\nPriority support", 'birthday_points' => 400, 'early_access' => true, 'free_delivery' => true, 'cashback_pct' => 2, 'priority_support' => true, 'min_annual_spend' => 75000],
            ['name' => 'Platinum', 'color' => '#9fe8ff', 'sort' => 4, 'multiplier' => 2, 'perks' => "2× points\nFree delivery\n5% member discount\n3% cashback\nVIP rewards", 'birthday_points' => 800, 'early_access' => true, 'free_delivery' => true, 'discount_pct' => 5, 'cashback_pct' => 3, 'priority_support' => true, 'min_annual_spend' => 200000],
        ];
        foreach ($rows as $r) DB::table('loyalty_tiers')->insert($r + ['active' => true, 'renewal_months' => 12, 'grace_days' => 30, 'created_at' => $now, 'updated_at' => $now,
            'min_lifetime_spend' => 0, 'min_points' => 0, 'discount_pct' => $r['discount_pct'] ?? 0, 'cashback_pct' => $r['cashback_pct'] ?? 0, 'free_delivery' => $r['free_delivery'] ?? false, 'early_access' => $r['early_access'] ?? false, 'priority_support' => $r['priority_support'] ?? false]);
    }
}
