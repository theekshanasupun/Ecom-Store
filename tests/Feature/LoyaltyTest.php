<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\Loyalty;
use App\Services\LoyaltyEngagement;
use App\Services\LoyaltyReports;
use App\Services\LoyaltyRewards;
use App\Services\LoyaltyTiers;
use App\Services\Orders;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoyaltyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        LoyaltyReports::seedDefaults();
        DB::table('loyalty_badges')->delete();      // isolate: badge/challenge bonuses are tested on their own
        DB::table('loyalty_challenges')->delete();
        Settings::setMany(['loyalty_earn_spend' => '1', 'loyalty_earn_points' => '1', 'loyalty_earn_status' => 'completed', 'loyalty_redeem_rate' => '100', 'loyalty_min_redeem' => '0', 'loyalty_expiry_months' => '12', 'pts_welcome' => '0', 'pts_first_purchase' => '0']);
    }

    private function user(string $email = null): User
    {
        $email ??= 'u'.uniqid().'@example.com';

        return User::create(['name' => 'Test User', 'email' => $email, 'password' => 'secret123', 'role' => 'customer', 'status' => 'active'])->fresh();
    }

    /** A paid order of $cents with one physical item. */
    private function order(User $u, int $cents = 10000, string $status = 'processing', array $extra = []): Order
    {
        $o = Order::create($extra + ['number' => 'T'.uniqid(), 'token' => 'x', 'user_id' => $u->id, 'email' => $u->email, 'name' => $u->name, 'status' => $status, 'payment_status' => 'paid', 'payment_method' => 'bank',
            'subtotal' => $cents, 'shipping' => 0, 'tax' => 0, 'discount' => 0, 'total' => $cents, 'currency' => 'USD', 'has_physical' => true, 'paid_at' => now()]);
        $o->items()->create(['product_id' => 0, 'name' => 'Thing', 'type' => 'physical', 'qty' => 1, 'price' => $cents, 'total' => $cents, 'art' => '', 'art_color' => '', 'color' => '', 'category' => 'x', 'orig_price' => $cents]);

        return $o->fresh();
    }

    public function test_every_member_gets_id_code_and_base_tier(): void
    {
        $u = $this->user();
        $this->assertStringStartsWith('KR-', $u->loyalty_id);
        $this->assertNotEmpty($u->referral_code);
        $this->assertSame('Bronze', LoyaltyTiers::of($u)->name);
    }

    public function test_points_are_pending_until_status_reached_then_released_once(): void
    {
        $u = $this->user();
        $o = $this->order($u, 10000, 'processing');          // 100.00 → 100 points at 1 pt/unit
        Loyalty::onOrderPaid($o);
        $this->assertSame(0, Loyalty::balance($u));
        $this->assertSame(100, Loyalty::pending($u));
        Orders::setStatus($o->fresh(), 'completed', 'test');
        $this->assertSame(100, Loyalty::balance($u));
        $this->assertSame(100, (int) $u->fresh()->points);   // cache equals ledger
        Loyalty::releaseOrder($o->id);                        // second release is a no-op
        Loyalty::onOrderPaid($o->fresh());                    // second award is a no-op
        $this->assertSame(100, Loyalty::balance($u));
        $this->assertSame(1, DB::table('loyalty_ledger')->where('order_id', $o->id)->where('wallet', 'points')->count());
    }

    public function test_duplicate_reference_never_double_credits(): void
    {
        $u = $this->user();
        Loyalty::post($u, 'points', 'bonus', 50, ['reference' => 'same']);
        Loyalty::post($u, 'points', 'bonus', 50, ['reference' => 'same'], $dup);
        $this->assertTrue($dup);
        $this->assertSame(50, Loyalty::balance($u));
    }

    public function test_cannot_overspend_and_spend_is_first_expiring_first(): void
    {
        $u = $this->user();
        Loyalty::post($u, 'points', 'bonus', 100, ['reference' => 'late', 'expires_at' => now()->addDays(60)]);
        Loyalty::post($u, 'points', 'bonus', 100, ['reference' => 'soon', 'expires_at' => now()->addDays(5)]);
        $this->expectException(\RuntimeException::class);
        try {
            Loyalty::post($u, 'points', 'redeem', -50, ['reference' => 'spend1']);
            $this->assertSame(50, (int) DB::table('loyalty_ledger')->where('reference', 'soon')->value('remaining')); // soon lot is consumed first
            $this->assertSame(100, (int) DB::table('loyalty_ledger')->where('reference', 'late')->value('remaining'));
            Loyalty::post($u, 'points', 'redeem', -999, ['reference' => 'spend2']);
        } finally {
            $this->assertSame(150, Loyalty::balance($u));
        }
    }

    public function test_expiry_writes_one_ledger_row_and_is_idempotent(): void
    {
        $u = $this->user();
        Loyalty::post($u, 'points', 'bonus', 80, ['reference' => 'old', 'expires_at' => now()->subDay()]);
        $this->assertSame(1, Loyalty::expireDue());
        $this->assertSame(0, Loyalty::expireDue());
        $this->assertSame(0, Loyalty::balance($u));
        $this->assertSame(1, DB::table('loyalty_ledger')->where('type', 'expire')->count());
    }

    public function test_full_cancellation_reverses_points_and_partial_refund_is_proportional(): void
    {
        $u = $this->user();
        $o = $this->order($u, 20000, 'completed');
        Loyalty::onOrderPaid($o);
        $this->assertSame(200, Loyalty::balance($u));
        Loyalty::onOrderRefund($o->fresh(), 5000, 'test');   // 25% refunded
        $this->assertSame(150, Loyalty::balance($u));
        Loyalty::onOrderRefund($o->fresh(), 5000, 'test');   // another 25%
        $this->assertSame(100, Loyalty::balance($u));
        Orders::setStatus($o->fresh(), 'cancelled', 'test'); // the rest
        $this->assertSame(0, Loyalty::balance($u));
        Orders::setStatus($o->fresh(), 'refunded', 'test');  // nothing left to reverse
        $this->assertSame(0, Loyalty::balance($u));
    }

    public function test_refund_before_release_cancels_pending_points(): void
    {
        $u = $this->user();
        $o = $this->order($u, 10000, 'processing');
        Loyalty::onOrderPaid($o);
        $this->assertSame(100, Loyalty::pending($u));
        Orders::setStatus($o->fresh(), 'cancelled', 'test');
        $this->assertSame(0, Loyalty::pending($u));
        $this->assertSame(0, Loyalty::balance($u));
    }

    public function test_checkout_redemption_limits_and_restore_on_cancel(): void
    {
        $u = $this->user();
        Loyalty::post($u, 'points', 'bonus', 5000, ['reference' => 'seed']);
        Settings::setMany(['loyalty_min_redeem' => '100', 'loyalty_max_redeem_pct' => '50']);
        $u = $u->fresh();
        $c = Loyalty::checkout($u, 10000, 99999, false, false);       // 100.00 order, 50% cap → 50.00 → 5000 points
        $this->assertSame(5000, $c['points']);
        $this->assertSame(5000, $c['points_value']);
        $c2 = Loyalty::checkout($u, 10000, 50, false, false);          // below minimum → nothing
        $this->assertSame(0, $c2['points']);
        $o = $this->order($u, 10000, 'pending', ['payment_status' => 'unpaid']);
        Loyalty::spend($u, $o, 5000, 5000, 0);
        $this->assertSame(0, Loyalty::balance($u));
        $this->assertSame(5000, (int) $o->fresh()->due());
        Loyalty::restoreTenders($o->fresh(), 'Order cancelled');
        Loyalty::restoreTenders($o->fresh(), 'Order cancelled');       // idempotent
        $this->assertSame(5000, Loyalty::balance($u));
    }

    public function test_points_cashback_and_store_credit_are_separate_wallets(): void
    {
        $u = $this->user();
        Loyalty::post($u, 'points', 'bonus', 300, ['reference' => 'p']);
        Loyalty::post($u, 'cashback', 'bonus', 450, ['reference' => 'c']);
        \App\Services\StoreCredit::add($u, 1000, 'refund');
        $u = $u->fresh();
        $this->assertSame([300, 450, 1000], [(int) $u->points, (int) $u->cashback, (int) $u->credit]);
        Loyalty::post($u, 'points', 'redeem', -100, ['reference' => 'rp']);
        $u = $u->fresh();
        $this->assertSame([200, 450, 1000], [(int) $u->points, (int) $u->cashback, (int) $u->credit]);
    }

    public function test_manual_adjustment_requires_reason_and_is_audited(): void
    {
        $u = $this->user();
        try {
            Loyalty::adjust($u, 'points', 10, '  ', 'admin:test');
            $this->fail('reason should be required');
        } catch (\RuntimeException) {
        }
        Loyalty::adjust($u, 'points', 10, 'goodwill', 'admin:test');
        $this->assertSame(10, Loyalty::balance($u));
        $this->assertSame(1, DB::table('loyalty_audit')->where('action', 'adjust.points')->count());
    }

    public function test_reward_redemption_checks_balance_stock_limits_and_double_clicks(): void
    {
        $u = $this->user();
        $rid = DB::table('loyalty_rewards')->insertGetId(['name' => 'Ten off', 'type' => 'discount_pct', 'value' => 10, 'points_cost' => 100, 'stock' => 1, 'per_customer' => 1, 'active' => true, 'voucher_days' => 30, 'created_at' => now(), 'updated_at' => now()]);
        try {
            LoyaltyRewards::redeem($u, $rid, 'tok1');
            $this->fail('not enough points');
        } catch (\RuntimeException) {
        }
        Loyalty::post($u, 'points', 'bonus', 500, ['reference' => 'seed']);
        $a = LoyaltyRewards::redeem($u->fresh(), $rid, 'tok2');
        $b = LoyaltyRewards::redeem($u->fresh(), $rid, 'tok2');            // double click, same token
        $this->assertSame($a->id, $b->id);
        $this->assertSame(400, Loyalty::balance($u));
        $this->assertNotEmpty($a->code);
        $c = \App\Models\Coupon::where('code', $a->code)->first();
        $this->assertSame($u->id, (int) $c->user_id);
        try {
            LoyaltyRewards::redeem($u->fresh(), $rid, 'tok3');             // per-customer limit / stock
            $this->fail('limit');
        } catch (\RuntimeException) {
        }
        $this->assertTrue(LoyaltyRewards::cancel($a->id, 'customer'));
        $this->assertSame(500, Loyalty::balance($u));
        $this->assertSame(1, (int) DB::table('loyalty_rewards')->where('id', $rid)->value('stock'));
        $this->assertFalse(LoyaltyRewards::cancel($a->id, 'customer'));    // cannot refund twice
    }

    public function test_tier_upgrade_and_downgrade_after_grace(): void
    {
        $u = $this->user();
        Settings::setMany(['loyalty_earn_status' => 'paid']);
        $o = $this->order($u, 30000, 'processing');                          // 300.00 ≥ Silver (250.00)
        Loyalty::onOrderPaid($o);
        $this->assertSame('Silver', LoyaltyTiers::of($u->fresh())->name);
        Orders::setStatus($o->fresh(), 'refunded', 'test');                   // qualifying purchase refunded → re-evaluated
        $u = $u->fresh();
        $this->assertSame('Silver', LoyaltyTiers::of($u)->name, 'still inside the renewal period');
        DB::table('users')->where('id', $u->id)->update(['tier_until' => now()->subDays(40)]);
        LoyaltyTiers::evaluate($u->fresh());
        $this->assertSame('Bronze', LoyaltyTiers::of($u->fresh())->name);
        $this->assertGreaterThanOrEqual(2, DB::table('loyalty_tier_history')->where('user_id', $u->id)->count());
    }

    public function test_multiplier_campaigns_stack_and_exclusive_blocks_lower_priority(): void
    {
        $u = $this->user();
        DB::table('loyalty_campaigns')->insert(['name' => 'Double', 'type' => 'multiplier', 'value' => 2, 'scope' => 'all', 'audience' => 'all', 'priority' => 10, 'stack' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('loyalty_campaigns')->insert(['name' => 'Bonus', 'type' => 'bonus_fixed', 'value' => 25, 'scope' => 'all', 'audience' => 'all', 'priority' => 5, 'stack' => true, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $o = $this->order($u, 10000, 'completed');
        $this->assertSame(100 + 100 + 25, Loyalty::computeEarn($o, $u)['points']);
        DB::table('loyalty_campaigns')->insert(['name' => 'Triple only', 'type' => 'multiplier', 'value' => 3, 'scope' => 'all', 'audience' => 'all', 'priority' => 50, 'stack' => false, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(300, Loyalty::computeEarn($o, $u)['points']);
    }

    public function test_referral_rewards_referrer_once_and_blocks_self_and_alias_referrals(): void
    {
        Settings::setMany(['loyalty_earn_status' => 'paid', 'ref_referrer_points' => '500', 'ref_referee_points' => '50', 'ref_referee_pct' => '0', 'ref_min_order' => '0']);
        $a = $this->user('alice@gmail.com');
        $this->app['request']->cookies->set('kref', $a->referral_code);
        $b = $this->user('bob@example.com');
        $this->assertSame('registered', DB::table('loyalty_referrals')->where('referee_id', $b->id)->value('status'));
        $this->assertSame(50, Loyalty::balance($b));
        $o = $this->order($b, 5000, 'processing');
        Loyalty::onOrderPaid($o);
        $this->assertSame(500, Loyalty::balance($a));
        $o2 = $this->order($b, 5000, 'processing');
        Loyalty::onOrderPaid($o2);
        $this->assertSame(500, Loyalty::balance($a), 'reward only on the first qualifying order');
        $alias = $this->user('a.lice+x@gmail.com');                           // alias of the referrer
        $this->assertSame('rejected', DB::table('loyalty_referrals')->where('referee_id', $alias->id)->value('status'));
        $this->assertGreaterThan(0, DB::table('loyalty_flags')->where('type', 'referral_abuse')->count());
    }

    public function test_checkin_once_per_day_with_streak_bonus_and_challenge_completes(): void
    {
        Settings::setMany(['checkin_points' => '5', 'checkin_streak_every' => '0']);
        $u = $this->user();
        $r1 = LoyaltyEngagement::checkin($u);
        $r2 = LoyaltyEngagement::checkin($u);
        $this->assertTrue($r1['ok']);
        $this->assertFalse($r2['ok']);
        $this->assertSame(5, Loyalty::balance($u));
        $cid = DB::table('loyalty_challenges')->insertGetId(['name' => 'Spend', 'kind' => 'spend', 'period' => 'monthly', 'target' => 5000, 'reward_points' => 70, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        LoyaltyEngagement::track($u, 'order', ['amount' => 3000]);
        LoyaltyEngagement::track($u, 'order', ['amount' => 3000]);
        LoyaltyEngagement::track($u, 'order', ['amount' => 3000]);          // already completed → no double reward
        $this->assertSame(75, Loyalty::balance($u));
        $this->assertSame(1, (int) DB::table('loyalty_challenges')->where('id', $cid)->value('rewarded'));
    }

    public function test_suspended_accounts_earn_and_redeem_nothing(): void
    {
        $u = $this->user();
        DB::table('users')->where('id', $u->id)->update(['loyalty_status' => 'suspended']);
        $u = $u->fresh();
        Loyalty::onOrderPaid($this->order($u, 10000, 'completed'));
        $this->assertSame(0, Loyalty::balance($u) + Loyalty::pending($u));
        $this->assertFalse(Loyalty::checkout($u, 10000, 100, false, false)['on']);
    }

    public function test_spend_100_earn_1_point_and_product_estimate_uses_discounted_price(): void
    {
        Settings::setMany(['loyalty_earn_spend' => '100', 'loyalty_earn_points' => '1']);
        $u = $this->user();
        $this->assertSame(2, Loyalty::computeEarn($this->order($u, 25000, 'completed'), $u)['points']);   // 250.00 → 2 points (rounded down)
        $this->assertSame(9, Loyalty::computeEarn($this->order($u, 99900, 'completed'), $u)['points']);
        $p = (object) ['id' => 1, 'category_id' => 1, 'type' => 'physical'];
        $rate = Loyalty::productRate($p, $u);
        $this->assertSame(4, Loyalty::estimate(45000, $rate));      // discounted price 450.00 → 4 (not the MRP)
        $this->assertSame(0, Loyalty::estimate(9900, $rate));
        $sum = ['subtotal' => 100000, 'discount' => 50000, 'offer_discount' => 0, 'tier_discount' => 0, 'lpoints_value' => 0, 'lcash' => 0, 'lines' => [['p' => $p, 'total' => 100000, 'qty' => 1]]];
        $this->assertSame(5, Loyalty::estimateCart($sum, $u));      // 1000 − 500 discount = 500 → 5 points
    }
}
