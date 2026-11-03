<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2.7 — Loyalty & Rewards.
 * Three separate wallets: loyalty POINTS and CASHBACK live in `loyalty_ledger` (separate `wallet` column, separate histories/rules);
 * STORE CREDIT is untouched (users.credit + credit_log from 2.6).
 */
return new class extends Migration
{
    private function col(string $table, string $name, callable $def): void
    {
        if (! Schema::hasColumn($table, $name)) Schema::table($table, $def);
    }

    private function make(string $name, callable $def): void
    {
        if (! Schema::hasTable($name)) Schema::create($name, $def);
    }

    public function up(): void
    {
        // ---- users: loyalty profile (balances are CACHES recomputed from the ledger) ----
        $this->col('users', 'loyalty_id', fn (Blueprint $t) => $t->string('loyalty_id', 20)->nullable()->unique());
        $this->col('users', 'loyalty_status', fn (Blueprint $t) => $t->string('loyalty_status', 12)->default('active'));   // active | suspended
        $this->col('users', 'loyalty_joined_at', fn (Blueprint $t) => $t->timestamp('loyalty_joined_at')->nullable());
        $this->col('users', 'points', fn (Blueprint $t) => $t->bigInteger('points')->default(0));
        $this->col('users', 'cashback', fn (Blueprint $t) => $t->bigInteger('cashback')->default(0));
        $this->col('users', 'tier_id', fn (Blueprint $t) => $t->unsignedBigInteger('tier_id')->nullable()->index());
        $this->col('users', 'tier_since', fn (Blueprint $t) => $t->timestamp('tier_since')->nullable());
        $this->col('users', 'tier_until', fn (Blueprint $t) => $t->timestamp('tier_until')->nullable());
        $this->col('users', 'tier_locked', fn (Blueprint $t) => $t->boolean('tier_locked')->default(false));
        $this->col('users', 'birthday', fn (Blueprint $t) => $t->date('birthday')->nullable());
        $this->col('users', 'referral_code', fn (Blueprint $t) => $t->string('referral_code', 24)->nullable()->unique());
        $this->col('users', 'ambassador', fn (Blueprint $t) => $t->boolean('ambassador')->default(false));
        $this->col('users', 'ambassador_pct', fn (Blueprint $t) => $t->decimal('ambassador_pct', 5, 2)->default(0));
        $this->col('users', 'loyalty_prefs', fn (Blueprint $t) => $t->text('loyalty_prefs')->nullable());
        $this->col('users', 'reg_ip', fn (Blueprint $t) => $t->string('reg_ip', 45)->nullable()->index());
        $this->col('users', 'loyalty_activity_at', fn (Blueprint $t) => $t->timestamp('loyalty_activity_at')->nullable());

        // ---- orders: loyalty tenders and bookkeeping ----
        $this->col('orders', 'points_used', fn (Blueprint $t) => $t->unsignedInteger('points_used')->default(0));
        $this->col('orders', 'points_value', fn (Blueprint $t) => $t->unsignedInteger('points_value')->default(0));
        $this->col('orders', 'cashback_used', fn (Blueprint $t) => $t->unsignedInteger('cashback_used')->default(0));
        $this->col('orders', 'tier_discount', fn (Blueprint $t) => $t->unsignedInteger('tier_discount')->default(0));
        $this->col('orders', 'loyalty_refunded', fn (Blueprint $t) => $t->unsignedInteger('loyalty_refunded')->default(0));
        $this->col('orders', 'loyalty_state', fn (Blueprint $t) => $t->string('loyalty_state', 12)->default(''));   // '' | pending | released | reversed

        // ---- coupons: single-customer vouchers ----
        $this->col('coupons', 'user_id', fn (Blueprint $t) => $t->unsignedBigInteger('user_id')->nullable()->index());
        $this->col('coupons', 'source', fn (Blueprint $t) => $t->string('source', 12)->nullable());   // reward | referral | workflow | admin

        // ---- products: VIP gating / early access ----
        $this->col('products', 'vip_tier_id', fn (Blueprint $t) => $t->unsignedBigInteger('vip_tier_id')->nullable());
        $this->col('products', 'early_access_until', fn (Blueprint $t) => $t->timestamp('early_access_until')->nullable());
        $this->col('products', 'early_access_tier_id', fn (Blueprint $t) => $t->unsignedBigInteger('early_access_tier_id')->nullable());

        // ---- the permanent ledger (points + cashback) ----
        $this->make('loyalty_ledger', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('wallet', 10)->default('points');           // points | cashback
            $t->string('type', 12);                                // earn bonus redeem expire reverse adjust refund
            $t->bigInteger('amount');                              // signed (points, or cents for cashback)
            $t->bigInteger('remaining')->default(0);               // unspent part of a positive lot (FIFO expiry)
            $t->string('status', 10)->default('available');       // pending | available | reversed
            $t->string('reference', 120)->nullable()->unique();   // idempotency key
            $t->unsignedBigInteger('order_id')->nullable()->index();
            $t->string('reason', 190)->nullable();
            $t->string('actor', 100)->nullable();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamp('available_at')->nullable();
            $t->timestamp('reminded_at')->nullable();
            $t->text('meta')->nullable();
            $t->timestamp('created_at')->nullable()->index();
            $t->index(['user_id', 'wallet', 'status']);
        });

        $this->make('loyalty_tiers', function (Blueprint $t) {
            $t->id();
            $t->string('name', 80);
            $t->string('color', 9)->default('#c6ff3d');
            $t->string('icon', 190)->nullable();
            $t->string('image', 190)->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->unsignedBigInteger('min_annual_spend')->default(0);
            $t->unsignedBigInteger('min_lifetime_spend')->default(0);
            $t->unsignedBigInteger('min_points')->default(0);
            $t->decimal('multiplier', 6, 2)->default(1);
            $t->decimal('discount_pct', 5, 2)->default(0);
            $t->decimal('cashback_pct', 5, 2)->default(0);
            $t->boolean('free_delivery')->default(false);
            $t->boolean('early_access')->default(false);
            $t->unsignedInteger('birthday_points')->default(0);
            $t->boolean('priority_support')->default(false);
            $t->unsignedInteger('renewal_months')->default(12);
            $t->unsignedInteger('grace_days')->default(30);
            $t->text('perks')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        $this->make('loyalty_tier_history', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('from_tier')->nullable();
            $t->unsignedBigInteger('to_tier')->nullable();
            $t->string('reason', 190)->nullable();
            $t->string('actor', 100)->nullable();
            $t->timestamp('created_at')->nullable();
        });

        $this->make('loyalty_rewards', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->text('description')->nullable();
            $t->string('image', 190)->nullable();
            $t->string('category', 60)->nullable();
            $t->string('type', 16)->default('discount_fixed');   // discount_pct | discount_fixed | free_shipping | free_product | voucher | exclusive
            $t->unsignedInteger('points_cost')->default(0);
            $t->unsignedBigInteger('cash_cost')->default(0);    // "points + cash": what the customer still pays for a free_product reward
            $t->unsignedBigInteger('value')->default(0);        // cents (fixed/voucher) or percent
            $t->unsignedBigInteger('product_id')->nullable();
            $t->unsignedBigInteger('min_order')->default(0);
            $t->text('tier_ids')->nullable();
            $t->boolean('vip_only')->default(false);
            $t->integer('stock')->nullable();                    // null = unlimited
            $t->unsignedInteger('per_customer')->default(0);     // 0 = unlimited
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->unsignedInteger('voucher_days')->default(30);
            $t->boolean('needs_approval')->default(false);
            $t->text('product_ids')->nullable();
            $t->text('category_ids')->nullable();
            $t->unsignedInteger('redeemed')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        $this->make('loyalty_redemptions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->unsignedBigInteger('reward_id')->index();
            $t->unsignedInteger('points')->default(0);
            $t->string('code', 40)->nullable()->index();
            $t->string('status', 12)->default('issued');         // issued | pending | used | cancelled | expired | rejected
            $t->string('reference', 120)->nullable()->unique();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('used_at')->nullable();
            $t->string('note', 190)->nullable();
            $t->timestamps();
        });

        $this->make('loyalty_campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->string('type', 14)->default('multiplier');       // multiplier | bonus_fixed | bonus_pct | cashback_pct
            $t->decimal('value', 10, 2)->default(2);
            $t->string('scope', 10)->default('all');             // all | category | product
            $t->text('scope_ids')->nullable();
            $t->text('tier_ids')->nullable();
            $t->string('audience', 14)->default('all');          // all | first_purchase | birthday_month | customers
            $t->text('customer_ids')->nullable();
            $t->unsignedBigInteger('min_order')->default(0);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->integer('priority')->default(10);
            $t->boolean('stack')->default(true);                 // false = exclusive: blocks lower-priority campaigns
            $t->unsignedBigInteger('budget')->default(0);        // max reward units it may issue (0 = no cap)
            $t->unsignedBigInteger('spent')->default(0);
            $t->unsignedInteger('uses_limit')->default(0);
            $t->unsignedInteger('uses')->default(0);
            $t->string('status', 10)->default('active');         // active | paused | ended
            $t->timestamps();
        });

        $this->make('loyalty_rules', function (Blueprint $t) {   // earning-rate overrides
            $t->id();
            $t->string('name', 150);
            $t->string('scope', 10)->default('category');        // category | product
            $t->text('scope_ids')->nullable();
            $t->decimal('rate', 10, 2)->default(1);              // points per currency unit for matching items
            $t->decimal('cashback_pct', 5, 2)->default(0);       // extra cashback % for matching items
            $t->integer('priority')->default(10);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        $this->make('loyalty_challenges', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->text('description')->nullable();
            $t->string('kind', 14)->default('orders');           // orders | spend | category | product | checkin | review | referral | custom
            $t->string('period', 10)->default('monthly');        // daily | weekly | monthly | lifetime
            $t->unsignedBigInteger('target')->default(1);
            $t->unsignedBigInteger('scope_id')->nullable();      // category or product id
            $t->unsignedInteger('reward_points')->default(0);
            $t->unsignedBigInteger('reward_cashback')->default(0);
            $t->unsignedBigInteger('badge_id')->nullable();
            $t->text('tier_ids')->nullable();
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->unsignedInteger('max_rewards')->default(0);
            $t->unsignedInteger('rewarded')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        $this->make('loyalty_progress', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('challenge_id');
            $t->string('period_key', 12);
            $t->unsignedBigInteger('progress')->default(0);
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'challenge_id', 'period_key']);
        });

        $this->make('loyalty_badges', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->string('icon', 16)->default('🏅');
            $t->text('description')->nullable();
            $t->string('rule', 14)->default('orders');           // orders | spend | referrals | checkins | challenges | tier | manual
            $t->unsignedBigInteger('threshold')->default(1);
            $t->unsignedInteger('reward_points')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });

        $this->make('loyalty_user_badges', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('badge_id');
            $t->timestamp('created_at')->nullable();
            $t->unique(['user_id', 'badge_id']);
        });

        $this->make('loyalty_checkins', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->date('day');
            $t->unsignedInteger('streak')->default(1);
            $t->unsignedInteger('points')->default(0);
            $t->timestamp('created_at')->nullable();
            $t->unique(['user_id', 'day']);
        });

        $this->make('loyalty_plays', function (Blueprint $t) {   // spin the wheel / scratch & win
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('game', 10);
            $t->date('day');
            $t->unsignedInteger('n')->default(1);
            $t->string('prize', 120)->nullable();
            $t->string('prize_type', 10)->default('nothing');
            $t->unsignedBigInteger('amount')->default(0);
            $t->timestamp('created_at')->nullable();
            $t->unique(['user_id', 'game', 'day', 'n']);
        });

        $this->make('loyalty_competitions', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->string('metric', 10)->default('spend');          // spend | orders | points | referrals
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->string('prizes', 255)->nullable();               // "1:1000,2:500,3:250" (points per rank)
            $t->boolean('active')->default(true);
            $t->timestamp('settled_at')->nullable();
            $t->timestamps();
        });

        $this->make('loyalty_referrals', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('referrer_id')->index();
            $t->unsignedBigInteger('referee_id')->unique();
            $t->string('code', 24)->nullable();
            $t->string('status', 12)->default('registered');     // registered | qualified | rewarded | rejected
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedBigInteger('reward_points')->default(0);
            $t->unsignedBigInteger('reward_cashback')->default(0);
            $t->string('ip', 45)->nullable();
            $t->boolean('flagged')->default(false);
            $t->string('note', 190)->nullable();
            $t->timestamps();
        });

        $this->make('loyalty_notifications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('type', 20)->default('general');
            $t->string('title', 190);
            $t->text('body')->nullable();
            $t->string('url', 190)->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamp('created_at')->nullable();
        });

        $this->make('loyalty_audit', function (Blueprint $t) {
            $t->id();
            $t->string('actor', 100)->nullable();
            $t->string('action', 60);
            $t->string('subject', 60)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->text('details')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->nullable()->index();
        });

        $this->make('loyalty_flags', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable()->index();
            $t->string('type', 24);                              // duplicate_account | referral_abuse | suspicious_points | unusual_redemption
            $t->string('severity', 8)->default('medium');
            $t->string('details', 255)->nullable();
            $t->string('dedupe', 120)->nullable()->unique();
            $t->string('status', 10)->default('open');           // open | resolved
            $t->timestamp('created_at')->nullable();
        });

        $this->make('loyalty_workflows', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150);
            $t->string('event', 24);
            $t->text('conditions')->nullable();
            $t->string('action', 16)->default('award_points');   // award_points | award_cashback | issue_voucher | notify
            $t->text('params')->nullable();
            $t->boolean('enabled')->default(true);
            $t->unsignedInteger('runs')->default(0);
            $t->timestamps();
        });

        $this->make('loyalty_workflow_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('workflow_id')->index();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('ref', 120)->nullable();
            $t->string('result', 190)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->unique(['workflow_id', 'ref']);
        });

        $this->make('loyalty_segments', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100);
            $t->text('rules')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['loyalty_segments', 'loyalty_workflow_logs', 'loyalty_workflows', 'loyalty_flags', 'loyalty_audit', 'loyalty_notifications', 'loyalty_referrals', 'loyalty_competitions', 'loyalty_plays', 'loyalty_checkins', 'loyalty_user_badges', 'loyalty_badges', 'loyalty_progress', 'loyalty_challenges', 'loyalty_rules', 'loyalty_campaigns', 'loyalty_redemptions', 'loyalty_rewards', 'loyalty_tier_history', 'loyalty_tiers', 'loyalty_ledger'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
