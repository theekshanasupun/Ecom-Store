<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Admin analytics, customer segments, exports and default content for a fresh programme. */
final class LoyaltyReports
{
    public const REPORTS = [
        'earning' => 'Points earning', 'redemption' => 'Points redemption', 'expiry' => 'Points expiry', 'tiers' => 'Membership tiers', 'acquisition' => 'Customer acquisition',
        'retention' => 'Customer retention / repeat purchase', 'referrals' => 'Referral performance', 'cashback' => 'Cashback', 'rewards' => 'Reward redemptions', 'campaigns' => 'Campaign performance',
        'spending' => 'Customer spending & lifetime value', 'aov' => 'Average order value', 'roi' => 'Programme ROI', 'liability' => 'Outstanding points & cashback liability', 'fraud' => 'Fraud & risk flags',
    ];

    private static function memberIds(): \Illuminate\Support\Collection
    {
        return User::where('role', 'customer')->whereNotNull('loyalty_id')->pluck('id');
    }

    /** Revenue (cents) of paid, non-cancelled orders net of refunds for the given user ids. */
    private static function revenue($ids, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $q = Order::whereIn('user_id', $ids)->where('payment_status', 'paid')->whereNotIn('status', ['cancelled', 'refunded'])
            ->when($from, fn ($x) => $x->where('paid_at', '>=', $from))->when($to, fn ($x) => $x->where('paid_at', '<=', $to));
        $n = (int) (clone $q)->count();
        $sum = max(0, (int) (clone $q)->sum('total') - (int) (clone $q)->sum('loyalty_refunded'));

        return ['orders' => $n, 'revenue' => $sum, 'aov' => $n ? (int) round($sum / $n) : 0];
    }

    public static function pointValue(): float
    {
        return 100 / max(1, (float) setting('loyalty_redeem_rate', 100));   // cents per point
    }

    public static function overview(?Carbon $from = null, ?Carbon $to = null): array
    {
        $ids = self::memberIds();
        $active = User::whereIn('id', $ids)->where('loyalty_status', 'active')->where(fn ($q) => $q->where('loyalty_activity_at', '>=', now()->subDays(90))->orWhere('last_login_at', '>=', now()->subDays(90)))->count();
        $L = fn () => DB::table('loyalty_ledger')->when($from, fn ($x) => $x->where('created_at', '>=', $from))->when($to, fn ($x) => $x->where('created_at', '<=', $to));
        $issued = (int) $L()->where('wallet', 'points')->whereIn('type', ['earn', 'bonus', 'adjust', 'refund'])->where('amount', '>', 0)->whereIn('status', ['available', 'pending'])->sum('amount');
        $redeemed = (int) abs($L()->where('wallet', 'points')->where('type', 'redeem')->sum('amount'));
        $expired = (int) abs($L()->where('wallet', 'points')->where('type', 'expire')->sum('amount'));
        $outPts = (int) User::sum('points');
        $cbIssued = (int) $L()->where('wallet', 'cashback')->whereIn('type', ['earn', 'bonus', 'adjust', 'refund'])->where('amount', '>', 0)->whereIn('status', ['available', 'pending'])->sum('amount');
        $mem = self::revenue($ids, $from, $to);
        $nonIds = User::where('role', 'customer')->whereNull('loyalty_id')->pluck('id');
        $repeat = DB::table('orders')->whereIn('user_id', $ids)->where('payment_status', 'paid')->whereNotIn('status', ['cancelled', 'refunded'])->selectRaw('user_id, count(*) c')->groupBy('user_id')->get();
        $buyers = $repeat->count();
        $cost = (int) round($redeemed * self::pointValue()) + (int) abs($L()->where('wallet', 'cashback')->where('type', 'redeem')->sum('amount'));
        $tiers = [];
        foreach (LoyaltyTiers::all(false) as $t) $tiers[] = ['name' => $t->name, 'color' => $t->color, 'n' => User::where('tier_id', $t->id)->count()];
        $redemptions = DB::table('loyalty_redemptions')->selectRaw('reward_id, count(*) c')->whereNotIn('status', ['cancelled', 'rejected'])->groupBy('reward_id')->orderByDesc('c')->limit(5)->get();
        $pop = [];
        foreach ($redemptions as $r) $pop[] = ['name' => DB::table('loyalty_rewards')->where('id', $r->reward_id)->value('name') ?: '#'.$r->reward_id, 'n' => (int) $r->c];
        $refs = ['total' => DB::table('loyalty_referrals')->count(), 'rewarded' => DB::table('loyalty_referrals')->where('status', 'rewarded')->count(), 'pending' => DB::table('loyalty_referrals')->whereIn('status', ['registered', 'qualified'])->count()];

        return [
            'members' => $ids->count(), 'active' => $active, 'inactive' => max(0, $ids->count() - $active), 'new' => User::whereIn('id', $ids)->where('loyalty_joined_at', '>=', $from ?: now()->subDays(30))->count(),
            'issued' => $issued, 'redeemed' => $redeemed, 'outstanding' => $outPts, 'expired' => $expired, 'liability' => (int) round($outPts * self::pointValue()),
            'cb_issued' => $cbIssued, 'cb_liability' => (int) User::sum('cashback'), 'rewards_redeemed' => DB::table('loyalty_redemptions')->whereNotIn('status', ['cancelled', 'rejected'])->count(),
            'popular' => $pop, 'tiers' => $tiers, 'referrals' => $refs, 'revenue' => $mem['revenue'], 'orders' => $mem['orders'], 'aov' => $mem['aov'],
            'repeat_rate' => $buyers ? (int) round($repeat->where('c', '>', 1)->count() / $buyers * 100) : 0,
            'clv' => $buyers ? (int) round($mem['revenue'] / $buyers) : 0, 'non_aov' => self::revenue($nonIds, $from, $to)['aov'], 'cost' => $cost,
            'roi' => $cost > 0 ? round(($mem['revenue'] - $cost) / $cost * 100) : null, 'engagement' => [
                'checkins30' => DB::table('loyalty_checkins')->where('day', '>=', now()->subDays(30)->toDateString())->count(), 'plays30' => DB::table('loyalty_plays')->where('day', '>=', now()->subDays(30)->toDateString())->count(),
                'challenges30' => DB::table('loyalty_progress')->where('completed_at', '>=', now()->subDays(30))->count(), 'badges' => DB::table('loyalty_user_badges')->count(),
            ],
        ];
    }

    public static function trend(int $days = 30): array
    {
        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = today()->subDays($i);
            $out[] = ['label' => $d->format('M j'),
                'earned' => (int) DB::table('loyalty_ledger')->where('wallet', 'points')->whereIn('type', ['earn', 'bonus'])->where('amount', '>', 0)->whereBetween('created_at', [$d, $d->copy()->endOfDay()])->sum('amount'),
                'redeemed' => (int) abs(DB::table('loyalty_ledger')->where('wallet', 'points')->where('type', 'redeem')->whereBetween('created_at', [$d, $d->copy()->endOfDay()])->sum('amount'))];
        }

        return $out;
    }

    /** @return array{cols:string[],rows:array[]} */
    public static function report(string $key, ?Carbon $from, ?Carbon $to): array
    {
        $from ??= now()->subDays(30)->startOfDay();
        $to ??= now()->endOfDay();
        $pv = self::pointValue();
        $ledger = fn (string $wallet, array $types) => DB::table('loyalty_ledger as l')->join('users as u', 'u.id', '=', 'l.user_id')->where('l.wallet', $wallet)->whereIn('l.type', $types)->whereBetween('l.created_at', [$from, $to]);
        $nm = fn ($v, $w = 'points') => $w === 'cashback' ? number_format($v / 100, 2) : $v;
        switch ($key) {
            case 'earning': case 'redemption': case 'expiry': case 'cashback':
                $wallet = $key === 'cashback' ? 'cashback' : 'points';
                $types = match ($key) { 'redemption' => ['redeem'], 'expiry' => ['expire'], default => $key === 'cashback' ? ['earn', 'bonus', 'adjust', 'redeem', 'expire', 'reverse', 'refund'] : ['earn', 'bonus', 'adjust'] };
                $rows = $ledger($wallet, $types)->orderByDesc('l.id')->limit(5000)->get(['l.created_at', 'u.name', 'u.email', 'l.type', 'l.amount', 'l.status', 'l.reason', 'l.actor', 'l.order_id'])
                    ->map(fn ($r) => [$r->created_at, $r->name, $r->email, $r->type, $nm($r->amount, $wallet), $r->status, $r->reason, $r->actor, $r->order_id])->all();

                return ['cols' => ['Date', 'Customer', 'Email', 'Type', $wallet === 'cashback' ? 'Amount' : 'Points', 'Status', 'Reason', 'Actor', 'Order'], 'rows' => $rows];
            case 'tiers':
                $rows = [];
                foreach (LoyaltyTiers::all(false) as $t) $rows[] = [$t->name, $t->active ? 'active' : 'inactive', User::where('tier_id', $t->id)->count(), DB::table('loyalty_tier_history')->where('to_tier', $t->id)->whereBetween('created_at', [$from, $to])->count(), (float) $t->multiplier, (float) $t->discount_pct, (float) $t->cashback_pct];

                return ['cols' => ['Tier', 'Status', 'Members', 'Entered (period)', 'Multiplier', 'Discount %', 'Cashback %'], 'rows' => $rows];
            case 'acquisition':
                $rows = [];
                for ($d = $from->copy()->startOfMonth(); $d <= $to; $d->addMonth()) {
                    $e = $d->copy()->endOfMonth();
                    $rows[] = [$d->format('Y-m'), User::where('role', 'customer')->whereBetween('created_at', [$d, $e])->count(), User::where('role', 'customer')->whereNotNull('loyalty_id')->whereBetween('loyalty_joined_at', [$d, $e])->count(), DB::table('loyalty_referrals')->whereBetween('created_at', [$d, $e])->count()];
                }

                return ['cols' => ['Month', 'New customers', 'New members', 'Via referral'], 'rows' => $rows];
            case 'retention':
                $rows = [];
                foreach (User::whereNotNull('loyalty_id')->where('role', 'customer')->get(['id', 'name', 'email']) as $u) {
                    $o = Order::where('user_id', $u->id)->where('payment_status', 'paid')->whereNotIn('status', ['cancelled', 'refunded']);
                    $n = (clone $o)->count();
                    if ($n > 0) $rows[] = [$u->name, $u->email, $n, $n > 1 ? 'repeat' : 'one-time', (clone $o)->min('paid_at'), (clone $o)->max('paid_at')];
                }
                usort($rows, fn ($a, $b) => $b[2] <=> $a[2]);

                return ['cols' => ['Customer', 'Email', 'Orders', 'Type', 'First order', 'Last order'], 'rows' => $rows];
            case 'referrals':
                $rows = DB::table('loyalty_referrals as r')->join('users as a', 'a.id', '=', 'r.referrer_id')->join('users as b', 'b.id', '=', 'r.referee_id')->whereBetween('r.created_at', [$from, $to])->orderByDesc('r.id')->get(['r.created_at', 'a.name as referrer', 'b.name as referee', 'r.status', 'r.reward_points', 'r.reward_cashback', 'r.flagged', 'r.note'])
                    ->map(fn ($r) => [$r->created_at, $r->referrer, $r->referee, $r->status, $r->reward_points, $nm($r->reward_cashback, 'cashback'), $r->flagged ? 'yes' : '', $r->note])->all();

                return ['cols' => ['Date', 'Referrer', 'Referee', 'Status', 'Points', 'Cashback', 'Flagged', 'Note'], 'rows' => $rows];
            case 'rewards':
                $rows = DB::table('loyalty_redemptions as d')->join('loyalty_rewards as w', 'w.id', '=', 'd.reward_id')->join('users as u', 'u.id', '=', 'd.user_id')->whereBetween('d.created_at', [$from, $to])->orderByDesc('d.id')->get(['d.created_at', 'u.name', 'w.name as reward', 'd.points', 'd.code', 'd.status', 'd.expires_at'])
                    ->map(fn ($r) => (array) $r)->map(fn ($r) => array_values($r))->all();

                return ['cols' => ['Date', 'Customer', 'Reward', 'Points', 'Voucher', 'Status', 'Expires'], 'rows' => $rows];
            case 'campaigns':
                $rows = DB::table('loyalty_campaigns')->orderByDesc('id')->get()->map(fn ($c) => [$c->name, $c->type, (float) $c->value, $c->status, $c->starts_at, $c->ends_at, $c->uses, $c->spent, $c->budget ?: 'no cap'])->all();

                return ['cols' => ['Campaign', 'Type', 'Value', 'Status', 'Starts', 'Ends', 'Orders', 'Spent (units)', 'Budget'], 'rows' => $rows];
            case 'spending': case 'aov':
                $rows = [];
                foreach (User::where('role', 'customer')->whereNotNull('loyalty_id')->get(['id', 'name', 'email', 'tier_id']) as $u) {
                    $r = self::revenue([$u->id]);
                    if ($r['orders'] > 0) $rows[] = [$u->name, $u->email, LoyaltyTiers::find((int) $u->tier_id)->name ?? '', $r['orders'], number_format($r['revenue'] / 100, 2), number_format($r['aov'] / 100, 2)];
                }
                usort($rows, fn ($a, $b) => (float) str_replace(',', '', $b[4]) <=> (float) str_replace(',', '', $a[4]));

                return ['cols' => ['Customer', 'Email', 'Tier', 'Orders', 'Lifetime value', 'Average order'], 'rows' => $rows];
            case 'roi':
                $o = self::overview($from, $to);

                return ['cols' => ['Metric', 'Value'], 'rows' => [['Revenue from members (net of refunds)', number_format($o['revenue'] / 100, 2)], ['Orders', $o['orders']], ['Points redeemed', $o['redeemed']], ['Cost of redeemed points + cashback', number_format($o['cost'] / 100, 2)],
                    ['ROI % ((revenue − cost) / cost)', $o['roi'] ?? 'n/a'], ['Member AOV', number_format($o['aov'] / 100, 2)], ['Non-member AOV', number_format($o['non_aov'] / 100, 2)], ['Repeat purchase rate %', $o['repeat_rate']]]];
            case 'liability':
                $rows = User::where(fn ($q) => $q->where('points', '<>', 0)->orWhere('cashback', '<>', 0))->orderByDesc('points')->limit(5000)->get(['name', 'email', 'points', 'cashback', 'credit'])
                    ->map(fn ($u) => [$u->name, $u->email, $u->points, number_format($u->points * $pv / 100, 2), number_format($u->cashback / 100, 2), number_format($u->credit / 100, 2)])->all();

                return ['cols' => ['Customer', 'Email', 'Points', 'Points value', 'Cashback', 'Store credit (separate)'], 'rows' => $rows];
            case 'fraud':
                $rows = DB::table('loyalty_flags as f')->leftJoin('users as u', 'u.id', '=', 'f.user_id')->orderByDesc('f.id')->limit(2000)->get(['f.created_at', 'u.name', 'f.type', 'f.severity', 'f.details', 'f.status'])->map(fn ($r) => array_values((array) $r))->all();

                return ['cols' => ['Date', 'Customer', 'Type', 'Severity', 'Details', 'Status'], 'rows' => $rows];
        }

        return ['cols' => [], 'rows' => []];
    }

    // ------------------------------------------------------------------ segments
    public const SEGMENTS = [
        'high_value' => 'High-value customers (top 10% by lifetime spend)', 'inactive' => 'Inactive customers (no order in 90+ days)', 'at_risk' => 'At risk of leaving (2+ orders, quiet 60–120 days)',
        'frequent' => 'Frequent purchasers (4+ orders in 180 days)', 'near_upgrade' => 'Close to a tier upgrade (80%+)', 'big_balance' => 'Large points balance (1,000+)', 'new_members' => 'New members (30 days)',
        'birthday_month' => 'Birthday this month', 'never_bought' => 'Members who never purchased',
    ];

    /** Apply custom/predefined rules and return users (collection with ->metrics). */
    public static function segment(string $key, array $rules = []): \Illuminate\Support\Collection
    {
        $users = User::where('role', 'customer')->whereNotNull('loyalty_id')->get();
        $rows = $users->map(function ($u) {
            $q = Order::where(fn ($w) => $w->where('user_id', $u->id)->orWhere('email', $u->email))->where('payment_status', 'paid')->whereNotIn('status', ['cancelled', 'refunded']);
            $u->orders_n = (int) (clone $q)->count();
            $u->spend = max(0, (int) (clone $q)->sum('total') - (int) (clone $q)->sum('loyalty_refunded'));
            $u->last_order = (clone $q)->max('paid_at');
            $u->orders_180 = (int) (clone $q)->where('paid_at', '>=', now()->subDays(180))->count();

            return $u;
        });
        $days = fn ($u) => $u->last_order ? (int) Carbon::parse($u->last_order)->diffInDays(now()) : null;
        $top = $rows->sortByDesc('spend')->take(max(1, (int) ceil($rows->count() * 0.1)))->pluck('id')->all();

        return $rows->filter(function ($u) use ($key, $rules, $days, $top) {
            if ($key === 'custom') {
                if (! empty($rules['tier_id']) && (int) $u->tier_id !== (int) $rules['tier_id']) return false;
                if (isset($rules['min_points']) && $rules['min_points'] !== '' && $u->points < (int) $rules['min_points']) return false;
                if (isset($rules['max_points']) && $rules['max_points'] !== '' && $u->points > (int) $rules['max_points']) return false;
                if (! empty($rules['min_spend']) && $u->spend < (int) round((float) $rules['min_spend'] * 100)) return false;
                if (! empty($rules['min_orders']) && $u->orders_n < (int) $rules['min_orders']) return false;
                if (! empty($rules['inactive_days']) && ($days($u) ?? 9999) < (int) $rules['inactive_days']) return false;
                if (! empty($rules['active_days']) && ($days($u) ?? 9999) > (int) $rules['active_days']) return false;

                return true;
            }

            return match ($key) {
                'high_value' => in_array($u->id, $top, true) && $u->spend > 0,
                'inactive' => $u->orders_n > 0 && ($days($u) ?? 0) >= 90,
                'at_risk' => $u->orders_n >= 2 && ($days($u) ?? 0) >= 60 && ($days($u) ?? 0) <= 120,
                'frequent' => $u->orders_180 >= 4,
                'near_upgrade' => (function () use ($u) {
                    $p = LoyaltyTiers::progress($u);

                    return $p['next'] && $p['pct'] >= 80;
                })(),
                'big_balance' => $u->points >= 1000,
                'new_members' => $u->loyalty_joined_at && Carbon::parse($u->loyalty_joined_at)->gte(now()->subDays(30)),
                'birthday_month' => $u->birthday && Carbon::parse($u->birthday)->format('n') === now()->format('n'),
                'never_bought' => $u->orders_n === 0,
                default => false,
            };
        })->values();
    }

    /** Reward suggestions: the most expensive active reward each customer can already afford, and the next one within reach. */
    public static function recommend(User $u): array
    {
        $rewards = DB::table('loyalty_rewards')->where('active', true)->orderBy('points_cost')->get();
        $afford = $rewards->filter(fn ($r) => $r->points_cost <= $u->points && ! LoyaltyRewards::blocked($r, $u, false))->last();
        $next = $rewards->first(fn ($r) => $r->points_cost > $u->points && ! LoyaltyRewards::blocked($r, $u, false));

        return ['afford' => $afford, 'next' => $next];
    }

    // ------------------------------------------------------------------ exports
    public static function csv(string $name, array $cols, array $rows): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($cols, $rows) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, $cols);
            foreach ($rows as $r) fputcsv($f, array_map(fn ($v) => self::safe($v), $r));
            fclose($f);
        }, $name.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Excel-compatible workbook (HTML table with .xls extension — opens natively in Excel/LibreOffice/Sheets). */
    public static function xls(string $name, array $cols, array $rows): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->streamDownload(function () use ($cols, $rows) {
            echo "\xEF\xBB\xBF<html><head><meta charset=\"utf-8\"></head><body><table border=\"1\"><tr>";
            foreach ($cols as $c) echo '<th>'.e($c).'</th>';
            echo '</tr>';
            foreach ($rows as $r) {
                echo '<tr>';
                foreach ($r as $v) echo '<td>'.e(self::safe($v)).'</td>';
                echo '</tr>';
            }
            echo '</table></body></html>';
        }, $name.'.xls', ['Content-Type' => 'application/vnd.ms-excel; charset=UTF-8']);
    }

    /** Neutralise spreadsheet formula injection. */
    private static function safe(mixed $v): string
    {
        $s = (string) $v;

        return ($s !== '' && in_array($s[0], ['=', '+', '-', '@'], true) && ! is_numeric($s)) ? "'".$s : $s;
    }

    // ------------------------------------------------------------------ first-run content
    public static function seedDefaults(): void
    {
        LoyaltyTiers::seedDefaults();
        LoyaltyRewards::seedDefaults();
        $now = now();
        if (! DB::table('loyalty_badges')->exists()) {
            foreach ([['First order', '🛍️', 'Placed your first order', 'orders', 1, 25], ['Regular', '⭐', '5 orders placed', 'orders', 5, 100], ['Big spender', '💎', 'Spent 500+ in total', 'spend', 50000, 200],
                ['Connector', '🤝', '3 friends joined and shopped', 'referrals', 3, 150], ['Streak keeper', '🔥', '30 daily check-ins', 'checkins', 30, 100], ['Challenger', '🏆', '5 challenges completed', 'challenges', 5, 100]] as $b) {
                DB::table('loyalty_badges')->insert(['name' => $b[0], 'icon' => $b[1], 'description' => $b[2], 'rule' => $b[3], 'threshold' => $b[4], 'reward_points' => $b[5], 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        if (! DB::table('loyalty_challenges')->exists()) {
            foreach ([['Weekly shopper', 'Place 1 order this week', 'orders', 'weekly', 1, 50], ['Monthly spender', 'Spend 100 this month', 'spend', 'monthly', 10000, 200], ['Check-in champion', 'Check in 5 days this week', 'checkin', 'weekly', 5, 40]] as $c) {
                DB::table('loyalty_challenges')->insert(['name' => $c[0], 'description' => $c[1], 'kind' => $c[2], 'period' => $c[3], 'target' => $c[4], 'reward_points' => $c[5], 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    /** Enrol every existing customer (no welcome bonus), recalculating tiers. Safe to run repeatedly. */
    public static function backfill(): int
    {
        $n = 0;
        self::seedDefaults();
        User::where('role', 'customer')->whereNull('loyalty_id')->orderBy('id')->chunkById(300, function ($users) use (&$n) {
            foreach ($users as $u) {
                Loyalty::ensureMember($u, false);
                LoyaltyTiers::evaluate($u->fresh());
                $n++;
            }
        });

        return $n;
    }
}
