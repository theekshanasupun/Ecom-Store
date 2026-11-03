<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Rewards marketplace: eligibility, redemption (atomic, idempotent), voucher generation, cancellation. */
final class LoyaltyRewards
{
    public const TYPES = [
        'discount_pct' => 'Percentage discount voucher', 'discount_fixed' => 'Fixed-value discount voucher', 'free_shipping' => 'Free delivery',
        'free_product' => 'Free / discounted product', 'voucher' => 'Gift voucher', 'exclusive' => 'Exclusive reward (fulfilled manually)',
    ];

    public static function find(int $id): ?object
    {
        return DB::table('loyalty_rewards')->where('id', $id)->first();
    }

    /** Why the customer cannot redeem right now (null = can). Does not check balance when $checkBalance is false. */
    public static function blocked(object $r, User $u, bool $checkBalance = true): ?string
    {
        if (! $r->active) return 'Not available';
        if ($r->starts_at && Carbon::parse($r->starts_at)->isFuture()) return 'Starts '.Carbon::parse($r->starts_at)->format('M j');
        if ($r->expires_at && Carbon::parse($r->expires_at)->isPast()) return 'Expired';
        if ($r->stock !== null && (int) $r->stock <= 0) return 'Out of stock';
        $tiers = Loyalty::ids((string) $r->tier_ids);
        $tier = LoyaltyTiers::of($u);
        if ($tiers && (! $tier || ! in_array((int) $tier->id, $tiers, true))) {
            $names = array_map(fn ($id) => LoyaltyTiers::find($id)->name ?? '', $tiers);

            return 'For '.implode('/', array_filter($names)).' members';
        }
        if ($r->vip_only && (! $tier || $tier->sort < (LoyaltyTiers::all()[1]->sort ?? 2))) return 'VIP members only';
        if ($r->per_customer > 0 && DB::table('loyalty_redemptions')->where('user_id', $u->id)->where('reward_id', $r->id)->whereNotIn('status', ['cancelled', 'rejected'])->count() >= $r->per_customer) return 'Limit reached';
        if ($checkBalance && (int) $u->points < (int) $r->points_cost) return 'Need '.number_format($r->points_cost - (int) $u->points).' more points';

        return null;
    }

    /**
     * Redeem a reward. $token (from the form) makes a double click/refresh a no-op.
     *
     * @return object the redemption row
     *
     * @throws \RuntimeException with a customer-friendly message
     */
    public static function redeem(User $u, int $rewardId, string $token): object
    {
        if (! Loyalty::active($u)) throw new \RuntimeException('Your loyalty account is not active.');
        $ref = 'redeem:reward:'.$u->id.':'.substr(preg_replace('/[^A-Za-z0-9]/', '', $token) ?: bin2hex(random_bytes(6)), 0, 40);
        if ($ex = DB::table('loyalty_redemptions')->where('reference', $ref)->first()) return $ex;

        $red = DB::transaction(function () use ($u, $rewardId, $ref) {
            DB::table('users')->where('id', $u->id)->lockForUpdate()->first();
            $r = DB::table('loyalty_rewards')->where('id', $rewardId)->lockForUpdate()->first();
            if (! $r) throw new \RuntimeException('That reward no longer exists.');
            $fresh = User::find($u->id);
            if ($msg = self::blocked($r, $fresh)) throw new \RuntimeException($msg === 'Out of stock' ? 'Sorry, that reward just ran out.' : $msg.'.');
            $needsApproval = $r->needs_approval || ((int) setting('loyalty_redeem_approval', 0) > 0 && $r->points_cost >= (int) setting('loyalty_redeem_approval'));
            Loyalty::post($fresh, 'points', 'redeem', -(int) $r->points_cost, ['reference' => $ref.':pts', 'reason' => 'Reward: '.$r->name, 'actor' => 'customer', 'meta' => ['reward_id' => $r->id]]);
            if ($r->stock !== null) DB::table('loyalty_rewards')->where('id', $r->id)->update(['stock' => DB::raw('stock - 1')]);
            DB::table('loyalty_rewards')->where('id', $r->id)->update(['redeemed' => DB::raw('redeemed + 1')]);
            $id = DB::table('loyalty_redemptions')->insertGetId(['user_id' => $u->id, 'reward_id' => $r->id, 'points' => $r->points_cost, 'status' => $needsApproval ? 'pending' : 'issued', 'reference' => $ref, 'created_at' => now(), 'updated_at' => now()]);
            if (! $needsApproval) self::fulfil($id);

            return DB::table('loyalty_redemptions')->where('id', $id)->first();
        });
        $r = self::find($rewardId);
        Loyalty::notify($u, 'rewards', $red->status === 'pending' ? 'Reward requested' : 'Reward unlocked', ($red->status === 'pending' ? 'Your request for "' : 'Your reward "').($r->name ?? 'reward').($red->status === 'pending' ? '" is awaiting approval.' : '" is ready'.($red->code ? ' — voucher code '.$red->code : '').'.'), '/account/loyalty?tab=rewards');
        Loyalty::hook('reward.redeemed', ['user_id' => $u->id, 'reward_id' => $rewardId, 'code' => $red->code]);
        self::checkPattern($u);

        return $red;
    }

    /** Generate the voucher for an issued redemption. Idempotent. */
    public static function fulfil(int $redemptionId): void
    {
        $red = DB::table('loyalty_redemptions')->where('id', $redemptionId)->first();
        if (! $red || $red->code) return;
        $r = self::find((int) $red->reward_id);
        $u = User::find($red->user_id);
        if (! $r || ! $u) return;
        $ends = $r->voucher_days > 0 ? now()->addDays((int) $r->voucher_days) : null;
        if ($r->expires_at && (! $ends || Carbon::parse($r->expires_at)->lt($ends))) $ends = Carbon::parse($r->expires_at);
        $o = ['min_total' => (int) $r->min_order, 'ends_at' => $ends, 'note' => 'Reward: '.$r->name, 'scope' => 'cart', 'product_ids' => $r->product_ids, 'category_ids' => $r->category_ids];
        if ($r->product_ids || $r->category_ids) $o['scope'] = $r->product_ids ? 'products' : 'categories';
        $c = null;
        switch ($r->type) {
            case 'discount_pct': $c = Loyalty::makeVoucher($u, 'reward', 'percent', (int) $r->value, $o); break;
            case 'discount_fixed': case 'voucher': $c = Loyalty::makeVoucher($u, 'reward', 'fixed', (int) $r->value, $o); break;
            case 'free_shipping': $c = Loyalty::makeVoucher($u, 'reward', 'fixed', 0, $o + ['free_shipping' => true]); break;
            case 'free_product':
                $p = $r->product_id ? \App\Models\Product::find($r->product_id) : null;
                if ($p) {
                    $price = (int) $p->price;
                    $off = max(0, $price - (int) $r->cash_cost);
                    $c = Loyalty::makeVoucher($u, 'reward', 'fixed', $off, ['scope' => 'products', 'product_ids' => (string) $p->id, 'ends_at' => $ends, 'max_discount' => $off, 'note' => 'Reward: '.$r->name]);
                }
                break;
        }
        DB::table('loyalty_redemptions')->where('id', $redemptionId)->update(['code' => $c?->code, 'expires_at' => $ends, 'status' => 'issued', 'updated_at' => now()]);
    }

    public static function approve(int $redemptionId): void
    {
        $red = DB::table('loyalty_redemptions')->where('id', $redemptionId)->where('status', 'pending')->first();
        if (! $red) return;
        DB::table('loyalty_redemptions')->where('id', $redemptionId)->update(['status' => 'issued', 'updated_at' => now()]);
        self::fulfil($redemptionId);
        if ($u = User::find($red->user_id)) Loyalty::notify($u, 'rewards', 'Reward approved', 'Your reward request was approved.', '/account/loyalty?tab=rewards');
        Loyalty::audit('reward.approve', 'redemption', $redemptionId);
    }

    /** Cancel an unused redemption: points go back, stock returns, voucher is disabled. */
    public static function cancel(int $redemptionId, string $actor, bool $reject = false): bool
    {
        return (bool) DB::transaction(function () use ($redemptionId, $actor, $reject) {
            $red = DB::table('loyalty_redemptions')->where('id', $redemptionId)->lockForUpdate()->first();
            if (! $red || ! in_array($red->status, ['issued', 'pending'], true)) return false;
            if ($red->code && ($c = Coupon::where('code', $red->code)->first()) && $c->used > 0) return false;
            $u = User::find($red->user_id);
            if ($red->code) Coupon::where('code', $red->code)->update(['status' => 'disabled']);
            DB::table('loyalty_redemptions')->where('id', $redemptionId)->update(['status' => $reject ? 'rejected' : 'cancelled', 'updated_at' => now()]);
            $r = self::find((int) $red->reward_id);
            if ($r && $r->stock !== null) DB::table('loyalty_rewards')->where('id', $r->id)->update(['stock' => DB::raw('stock + 1')]);
            if ($r) DB::table('loyalty_rewards')->where('id', $r->id)->update(['redeemed' => DB::raw('CASE WHEN redeemed > 0 THEN redeemed - 1 ELSE 0 END')]);
            if ($u && $red->points > 0) {
                Loyalty::post($u, 'points', 'refund', (int) $red->points, ['reference' => 'redeem-cancel:'.$red->id, 'reason' => 'Reward cancelled: '.($r->name ?? ''), 'actor' => $actor, 'expires_at' => 'auto']);
                Loyalty::notify($u, 'rewards', $reject ? 'Reward declined' : 'Reward cancelled', number_format($red->points).' points were returned to your wallet.', '/account/loyalty?tab=wallet');
            }
            Loyalty::audit('reward.'.($reject ? 'reject' : 'cancel'), 'redemption', $redemptionId, [], $actor);

            return true;
        });
    }

    /** A coupon was spent on an order: mark the matching redemption used. */
    public static function couponUsed(?string $code): void
    {
        if (! $code || ! str_starts_with($code, 'LR-')) return;
        DB::table('loyalty_redemptions')->where('code', $code)->where('status', 'issued')->update(['status' => 'used', 'used_at' => now(), 'updated_at' => now()]);
    }

    /** Flag many redemptions in a short time. */
    private static function checkPattern(User $u): void
    {
        $n = DB::table('loyalty_redemptions')->where('user_id', $u->id)->where('created_at', '>=', now()->subDay())->count();
        if ($n >= 5) Loyalty::flag($u->id, 'unusual_redemption', 'high', $n.' reward redemptions in 24 hours', 'redeem:'.$u->id.':'.date('Ymd'));
    }

    /** Expire redemptions whose voucher ran out (cron). */
    public static function expireVouchers(): int
    {
        return DB::table('loyalty_redemptions')->where('status', 'issued')->whereNotNull('expires_at')->where('expires_at', '<', now())->update(['status' => 'expired', 'updated_at' => now()]);
    }

    public static function seedDefaults(): void
    {
        if (DB::table('loyalty_rewards')->exists()) return;
        $now = now();
        $rows = [
            ['name' => '5% off your next order', 'type' => 'discount_pct', 'value' => 5, 'points_cost' => 500, 'category' => 'Discounts', 'description' => 'Single-use code, valid for 30 days.'],
            ['name' => '10% off your next order', 'type' => 'discount_pct', 'value' => 10, 'points_cost' => 1000, 'category' => 'Discounts', 'description' => 'Single-use code, valid for 30 days.'],
            ['name' => 'Free delivery', 'type' => 'free_shipping', 'value' => 0, 'points_cost' => 400, 'category' => 'Delivery', 'description' => 'Free delivery on one order.'],
            ['name' => '5.00 gift voucher', 'type' => 'voucher', 'value' => 500, 'points_cost' => 600, 'category' => 'Vouchers', 'description' => 'Fixed-value voucher for one order.'],
            ['name' => 'VIP 20% off', 'type' => 'discount_pct', 'value' => 20, 'points_cost' => 3000, 'category' => 'VIP', 'vip_only' => true, 'description' => 'Exclusive to VIP tiers.'],
        ];
        foreach ($rows as $r) DB::table('loyalty_rewards')->insert($r + ['vip_only' => $r['vip_only'] ?? false, 'active' => true, 'voucher_days' => 30, 'created_at' => $now, 'updated_at' => $now]);
    }
}
