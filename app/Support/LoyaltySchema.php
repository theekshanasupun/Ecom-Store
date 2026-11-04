<?php

namespace App\Support;

use App\Services\LoyaltyRewards;

/** Field definitions that drive the generic "Loyalty & Rewards" CRUD screens (form + list + validation). */
final class LoyaltySchema
{
    public static function entities(): array
    {
        $tiers = fn () => \App\Services\LoyaltyTiers::all(false);

        return [
            'tiers' => ['table' => 'loyalty_tiers', 'title' => 'Membership tiers', 'single' => 'tier', 'hint' => 'Unlimited tiers. A customer gets the highest tier whose requirements they meet (all filled-in thresholds).', 'order' => 'sort', 'toggle' => 'active', 'image' => 'image',
                'cols' => ['name' => 'Tier', 'multiplier' => '× points', 'min_annual_spend' => 'Annual spend', 'min_points' => 'Points', 'discount_pct' => 'Discount %', 'cashback_pct' => 'Cashback %', 'users' => 'Members'],
                'fields' => [
                    'name' => ['Name', 'text', 'req' => true], 'color' => ['Colour', 'color'], 'sort' => ['Order (low → high)', 'number'], 'image' => ['Tier image / icon', 'image'],
                    'min_annual_spend' => ['Annual spend required', 'money', 'hint' => 'Within the qualification period (Settings). 0 = not required'], 'min_lifetime_spend' => ['Lifetime spend required', 'money'], 'min_points' => ['Points earned required (period)', 'number'],
                    'multiplier' => ['Earning multiplier (×)', 'decimal'], 'discount_pct' => ['Member discount %', 'decimal'], 'cashback_pct' => ['Extra cashback %', 'decimal'], 'birthday_points' => ['Birthday points', 'number'],
                    'free_delivery' => ['Free delivery', 'bool'], 'early_access' => ['Early access to launches', 'bool'], 'priority_support' => ['Priority support', 'bool'],
                    'renewal_months' => ['Renewal period (months, 0 = never)', 'number'], 'grace_days' => ['Grace period before downgrade (days)', 'number'], 'perks' => ['Extra benefits (one per line)', 'textarea'], 'active' => ['Active', 'bool'],
                ], 'defaults' => ['color' => '#c6ff3d', 'multiplier' => 1, 'renewal_months' => 12, 'grace_days' => 30, 'active' => 1, 'sort' => 10]],
            'rewards' => ['table' => 'loyalty_rewards', 'title' => 'Reward catalogue', 'single' => 'reward', 'hint' => 'Vouchers are generated automatically as single-customer coupons.', 'order' => 'points_cost', 'toggle' => 'active', 'image' => 'image',
                'cols' => ['name' => 'Reward', 'type' => 'Type', 'points_cost' => 'Points', 'stock' => 'Stock', 'redeemed' => 'Redeemed', 'expires_at' => 'Ends'],
                'fields' => [
                    'name' => ['Name', 'text', 'req' => true], 'description' => ['Description', 'textarea'], 'image' => ['Image', 'image'], 'category' => ['Category label', 'text'],
                    'type' => ['Reward type', 'select', 'options' => LoyaltyRewards::TYPES], 'points_cost' => ['Points required', 'number', 'req' => true], 'value' => ['Value (% for percentage, amount for fixed/voucher)', 'valuefield', 'hint' => 'Percentage rewards: whole number. Fixed/voucher: amount in currency'],
                    'product_id' => ['Free product ID (free-product type)', 'number'], 'cash_cost' => ['Cash price of free product (points + cash)', 'money', 'hint' => '0 = completely free'], 'min_order' => ['Minimum order value', 'money'],
                    'product_ids' => ['Restrict to product IDs (comma)', 'text'], 'category_ids' => ['Restrict to department IDs (comma)', 'text'],
                    'tier_ids' => ['Restrict to tiers', 'tiers'], 'vip_only' => ['VIP-only (above the base tier)', 'bool'], 'stock' => ['Stock limit (empty = unlimited)', 'nullint'], 'per_customer' => ['Redemption limit per customer (0 = unlimited)', 'number'],
                    'starts_at' => ['Available from', 'datetime'], 'expires_at' => ['Reward ends', 'datetime'], 'voucher_days' => ['Voucher valid for (days)', 'number'], 'needs_approval' => ['Needs manual approval', 'bool'], 'active' => ['Active', 'bool'],
                ], 'defaults' => ['type' => 'discount_pct', 'voucher_days' => 30, 'active' => 1]],
            'rules' => ['table' => 'loyalty_rules', 'title' => 'Earning rules', 'single' => 'rule', 'hint' => 'Override the points-per-currency rate (Settings) for specific products or categories. Highest priority matching rule wins.', 'order' => 'priority', 'order_dir' => 'desc', 'toggle' => 'active',
                'cols' => ['name' => 'Rule', 'scope' => 'Scope', 'rate' => 'Points / block', 'cashback_pct' => 'Extra cashback %', 'priority' => 'Priority'],
                'fields' => ['name' => ['Name', 'text', 'req' => true], 'scope' => ['Applies to', 'select', 'options' => ['category' => 'Departments', 'product' => 'Products']], 'scope_ids' => ['IDs (comma separated)', 'text', 'req' => true], 'rate' => ['Points earned per spend block (block size: Settings → Earning, default 100)', 'decimal'],
                    'cashback_pct' => ['Extra cashback %', 'decimal'], 'priority' => ['Priority', 'number'], 'active' => ['Active', 'bool']], 'defaults' => ['scope' => 'category', 'rate' => 2, 'priority' => 10, 'active' => 1]],
            'campaigns' => ['table' => 'loyalty_campaigns', 'title' => 'Campaigns', 'single' => 'campaign', 'hint' => 'Double/triple points, bonus points, extra cashback. Stackable campaigns add their bonus; an exclusive one blocks lower-priority campaigns.', 'order' => 'priority', 'order_dir' => 'desc', 'toggle' => 'status:active:paused',
                'cols' => ['name' => 'Campaign', 'type' => 'Type', 'value' => 'Value', 'starts_at' => 'Starts', 'ends_at' => 'Ends', 'uses' => 'Orders', 'spent' => 'Spent'],
                'fields' => ['name' => ['Name', 'text', 'req' => true], 'type' => ['Type', 'select', 'options' => ['multiplier' => 'Points multiplier (2 = double, 3 = triple)', 'bonus_pct' => 'Bonus points %', 'bonus_fixed' => 'Fixed bonus points per order', 'cashback_pct' => 'Extra cashback %']],
                    'value' => ['Value', 'decimal', 'req' => true], 'scope' => ['Applies to', 'select', 'options' => ['all' => 'Whole order', 'category' => 'Departments', 'product' => 'Products']], 'scope_ids' => ['Department / product IDs', 'text'],
                    'audience' => ['Audience', 'select', 'options' => ['all' => 'Everyone', 'first_purchase' => 'First purchase', 'birthday_month' => 'Birthday month', 'customers' => 'Specific customers']], 'customer_ids' => ['Customer IDs (for specific customers)', 'text'], 'tier_ids' => ['Tier-exclusive (tiers)', 'tiers'],
                    'min_order' => ['Minimum order value', 'money'], 'starts_at' => ['Starts', 'datetime'], 'ends_at' => ['Ends', 'datetime'], 'priority' => ['Priority (higher first)', 'number'], 'stack' => ['Stackable with other campaigns', 'bool'],
                    'budget' => ['Budget (max points/cents it may issue, 0 = none)', 'number'], 'uses_limit' => ['Max orders (0 = unlimited)', 'number'], 'status' => ['Status', 'select', 'options' => ['active' => 'Active', 'paused' => 'Paused', 'ended' => 'Terminated']]],
                'defaults' => ['type' => 'multiplier', 'value' => 2, 'priority' => 10, 'stack' => 1, 'status' => 'active', 'scope' => 'all', 'audience' => 'all']],
            'challenges' => ['table' => 'loyalty_challenges', 'title' => 'Challenges & missions', 'single' => 'challenge', 'hint' => 'Progress is tracked automatically from orders, check-ins, referrals and reviews.', 'order' => 'id', 'order_dir' => 'desc', 'toggle' => 'active',
                'cols' => ['name' => 'Challenge', 'kind' => 'Goal', 'period' => 'Period', 'target' => 'Target', 'reward_points' => 'Reward pts', 'rewarded' => 'Completed'],
                'fields' => ['name' => ['Name', 'text', 'req' => true], 'description' => ['Description', 'textarea'], 'kind' => ['Goal', 'select', 'options' => ['orders' => 'Place orders', 'spend' => 'Spend amount', 'category' => 'Buy from a department', 'product' => 'Buy a product', 'checkin' => 'Daily check-ins / login streak', 'referral' => 'Successful referrals', 'review' => 'Write reviews', 'custom' => 'Custom mission (award manually)']],
                    'period' => ['Period', 'select', 'options' => ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'lifetime' => 'One time / custom dates']], 'target' => ['Target (count, or amount in cents for spend)', 'number', 'req' => true], 'scope_id' => ['Department / product ID', 'number'],
                    'reward_points' => ['Reward points', 'number'], 'reward_cashback' => ['Reward cashback', 'money'], 'badge_id' => ['Badge ID to award', 'number'], 'tier_ids' => ['Eligible tiers', 'tiers'], 'starts_at' => ['Starts', 'datetime'], 'ends_at' => ['Ends', 'datetime'],
                    'max_rewards' => ['Reward limit (0 = unlimited)', 'number'], 'active' => ['Active', 'bool']], 'defaults' => ['kind' => 'orders', 'period' => 'monthly', 'target' => 1, 'active' => 1]],
            'badges' => ['table' => 'loyalty_badges', 'title' => 'Achievement badges', 'single' => 'badge', 'hint' => 'Awarded automatically when the requirement is reached.', 'order' => 'id', 'toggle' => 'active',
                'cols' => ['icon' => '', 'name' => 'Badge', 'rule' => 'Rule', 'threshold' => 'Threshold', 'reward_points' => 'Reward pts'],
                'fields' => ['name' => ['Name', 'text', 'req' => true], 'icon' => ['Icon (emoji)', 'text'], 'description' => ['Description', 'textarea'], 'rule' => ['Requirement', 'select', 'options' => ['orders' => 'Orders placed', 'spend' => 'Lifetime spend (cents)', 'referrals' => 'Rewarded referrals', 'checkins' => 'Check-ins', 'challenges' => 'Challenges completed', 'tier' => 'Tier level reached', 'manual' => 'Manual only']],
                    'threshold' => ['Threshold', 'number'], 'reward_points' => ['Reward points', 'number'], 'active' => ['Active', 'bool']], 'defaults' => ['icon' => '🏅', 'rule' => 'orders', 'threshold' => 1, 'active' => 1]],
            'competitions' => ['table' => 'loyalty_competitions', 'title' => 'Leaderboard competitions', 'single' => 'competition', 'hint' => 'Ranks customers in a date range and pays prizes automatically when it ends.', 'order' => 'id', 'order_dir' => 'desc', 'toggle' => 'active',
                'cols' => ['name' => 'Competition', 'metric' => 'Metric', 'starts_at' => 'Starts', 'ends_at' => 'Ends', 'prizes' => 'Prizes', 'settled_at' => 'Settled'],
                'fields' => ['name' => ['Name', 'text', 'req' => true], 'metric' => ['Ranked by', 'select', 'options' => ['spend' => 'Spend', 'orders' => 'Orders', 'points' => 'Points earned', 'referrals' => 'Referrals']], 'starts_at' => ['Starts', 'datetime'], 'ends_at' => ['Ends', 'datetime', 'req' => true],
                    'prizes' => ['Prizes (rank:points, comma separated)', 'text', 'hint' => 'e.g. 1:1000,2:500,3:250'], 'active' => ['Active', 'bool']], 'defaults' => ['metric' => 'spend', 'prizes' => '1:1000,2:500,3:250', 'active' => 1]],
            'workflows' => ['table' => 'loyalty_workflows', 'title' => 'Automation workflows', 'single' => 'workflow', 'hint' => 'When an event happens (and conditions match) run an action once per event. Built-in automations (welcome, first purchase, birthday…) are configured in Settings.', 'order' => 'id', 'order_dir' => 'desc', 'toggle' => 'enabled',
                'cols' => ['name' => 'Workflow', 'event' => 'Trigger', 'action' => 'Action', 'runs' => 'Runs'],
                'fields' => ['name' => ['Name', 'text', 'req' => true], 'event' => ['Trigger event', 'select', 'options' => \App\Services\LoyaltyEngagement::EVENTS], 'action' => ['Action', 'select', 'options' => ['award_points' => 'Award points', 'award_cashback' => 'Award cashback', 'issue_voucher' => 'Issue a voucher', 'notify' => 'Send a notification']],
                    'c_min_total' => ['Condition: minimum order value', 'money', 'json' => 'conditions'], 'c_nth' => ['Condition: Nth order only (milestone)', 'number', 'json' => 'conditions'], 'c_tier_ids' => ['Condition: tier IDs (comma)', 'text', 'json' => 'conditions'],
                    'p_amount' => ['Amount (points, cashback cents, or voucher value: % or cents)', 'number', 'json' => 'params'], 'p_type' => ['Voucher type', 'select', 'options' => ['percent' => 'Percent', 'fixed' => 'Fixed (cents)'], 'json' => 'params'], 'p_days' => ['Voucher valid days', 'number', 'json' => 'params'], 'p_message' => ['Message', 'textarea', 'json' => 'params'],
                    'enabled' => ['Active', 'bool']], 'defaults' => ['event' => 'order.first_paid', 'action' => 'award_points', 'enabled' => 1, 'p_amount' => 100, 'p_days' => 30]],
        ];
    }
}
