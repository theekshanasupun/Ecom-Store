<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Referrals, check-ins, challenges, badges, spin/scratch games, competitions, workflows and the nightly lifecycle. */
final class LoyaltyEngagement
{
    public const EVENTS = [
        'user.registered' => 'Customer registered', 'order.first_paid' => 'First purchase rewarded', 'order.released' => 'Order rewards released', 'tier.upgraded' => 'Tier upgraded', 'tier.downgraded' => 'Tier downgraded',
        'birthday' => 'Birthday', 'anniversary' => 'Account anniversary', 'inactive' => 'Customer inactive (days)', 'points.expiring' => 'Points expiring soon', 'referral.rewarded' => 'Referral rewarded',
        'challenge.completed' => 'Challenge completed', 'order.milestone' => 'Nth order milestone',
    ];

    // ------------------------------------------------------------------ events from orders
    public static function onOrderReleased(Order $o, User $u): void
    {
        try {
            $paidBefore = Order::where(fn ($q) => $q->where('user_id', $u->id)->orWhere('email', $u->email))->where('id', '<', $o->id)->where('payment_status', 'paid')->whereNotIn('status', ['cancelled', 'refunded'])->count();
            if ($paidBefore === 0 && (int) setting('pts_first_purchase', 0) > 0) {
                Loyalty::award($u, 'first_purchase', (int) setting('pts_first_purchase'), 'firstpurchase:'.$u->id, 'First purchase bonus', ['order_id' => $o->id, 'force' => true]);
                self::workflows('order.first_paid', $u, ['ref' => 'fp'.$u->id, 'order' => $o]);
            }
            $count = $paidBefore + 1;
            self::workflows('order.milestone', $u, ['ref' => 'om'.$o->id, 'order' => $o, 'nth' => $count]);
            self::workflows('order.released', $u, ['ref' => 'or'.$o->id, 'order' => $o]);
            self::referralOrder($o, $u);
            $items = $o->items()->get();
            $pids = $items->pluck('product_id')->filter()->map(fn ($x) => (int) $x)->all();
            $cids = \App\Models\Product::whereIn('id', $pids)->pluck('category_id')->map(fn ($x) => (int) $x)->all();
            self::track($u, 'order', ['amount' => max(0, (int) $o->total - (int) $o->loyalty_refunded), 'pids' => $pids, 'cids' => $cids, 'ref' => 'o'.$o->id]);
            LoyaltyTiers::evaluate($u);
            self::badges($u);
        } catch (\Throwable $e) {
            \Log::warning('loyalty onOrderReleased: '.$e->getMessage());
        }
    }

    public static function onOrderReversed(Order $o): void
    {
        $ref = DB::table('loyalty_referrals')->where('order_id', $o->id)->where('status', 'rewarded')->first();
        if (! $ref) return;
        foreach (['points' => (int) $ref->reward_points, 'cashback' => (int) $ref->reward_cashback] as $w => $amt) {
            if ($amt > 0 && ($ru = User::find($ref->referrer_id))) {
                Loyalty::post($ru, $w, 'reverse', -$amt, ['reference' => 'refrev:'.$ref->id.':'.$w, 'reason' => 'Referral order refunded', 'allow_negative' => true]);
            }
        }
        DB::table('loyalty_referrals')->where('id', $ref->id)->update(['status' => 'rejected', 'note' => 'Referred order refunded/cancelled', 'updated_at' => now()]);
    }

    // ------------------------------------------------------------------ referrals
    public static function referralLink(User $u): string
    {
        return url('/').'?ref='.($u->referral_code ?: Loyalty::ensureMember($u)->referral_code);
    }

    private static function normEmail(string $e): string
    {
        [$l, $d] = array_pad(explode('@', mb_strtolower($e), 2), 2, '');
        $l = explode('+', $l)[0];
        if (in_array($d, ['gmail.com', 'googlemail.com'], true)) $l = str_replace('.', '', $l);

        return $l.'@'.($d === 'googlemail.com' ? 'gmail.com' : $d);
    }

    public static function attachReferral(User $u): void
    {
        if (setting('ref_on', '1') !== '1') return;
        $code = strtoupper(trim((string) (request()->cookie('kref') ?: request()->query('ref') ?: '')));
        if ($code === '') return;
        $start = setting('ref_start');
        $end = setting('ref_end');
        if (($start && now()->lt(Carbon::parse($start))) || ($end && now()->gt(Carbon::parse($end)->endOfDay()))) return;
        $ref = User::where('referral_code', $code)->first();
        if (! $ref || $ref->id === $u->id || $ref->loyalty_status === 'suspended') return;
        if (DB::table('loyalty_referrals')->where('referee_id', $u->id)->exists()) return;
        $cap = (int) setting('ref_max_per_user', 0);
        if ($cap > 0 && DB::table('loyalty_referrals')->where('referrer_id', $ref->id)->whereNotIn('status', ['rejected'])->count() >= $cap) return;
        $ip = app()->runningInConsole() ? null : request()->ip();
        $flag = '';
        if ($ip && ($ref->reg_ip === $ip || DB::table('users')->where('reg_ip', $ip)->where('id', '<>', $u->id)->where('id', $ref->id)->exists())) $flag = 'Same IP as the referrer';
        elseif (self::normEmail($u->email) === self::normEmail($ref->email)) $flag = 'Email is an alias of the referrer\'s';
        elseif ($ip && DB::table('loyalty_referrals')->where('ip', $ip)->where('referrer_id', $ref->id)->count() >= 2) $flag = 'Several referrals from one IP';
        $rid = DB::table('loyalty_referrals')->insertGetId(['referrer_id' => $ref->id, 'referee_id' => $u->id, 'code' => $code, 'status' => $flag ? 'rejected' : 'registered', 'ip' => $ip, 'flagged' => (bool) $flag, 'note' => $flag ?: null, 'created_at' => now(), 'updated_at' => now()]);
        if ($flag) {
            Loyalty::flag($ref->id, 'referral_abuse', 'high', $flag.' (referral #'.$rid.')', 'ref:'.$rid);

            return;
        }
        if ((int) setting('ref_referee_points', 0) > 0) Loyalty::award($u, 'referral_welcome', (int) setting('ref_referee_points'), 'refwelcome:'.$u->id, 'Welcome gift from a friend', ['force' => true]);
        if ((int) setting('ref_referee_pct', 0) > 0) {
            $v = Loyalty::makeVoucher($u, 'referral', 'percent', (int) setting('ref_referee_pct'), ['prefix' => 'LW', 'ends_at' => now()->addDays(30), 'min_total' => (int) setting('ref_min_order', 0), 'note' => 'Welcome voucher (referral)']);
            Loyalty::notify($u, 'referral', 'Your welcome voucher', 'Use code '.$v->code.' for '.(int) setting('ref_referee_pct').'% off your first order (valid 30 days).', '/cart');
        }
        Loyalty::notify($ref, 'referral', 'A friend joined', $u->name.' signed up with your link. You earn your reward after their first qualifying order.', '/account/loyalty?tab=referrals');
    }

    private static function referralOrder(Order $o, User $u): void
    {
        $ref = DB::table('loyalty_referrals')->where('referee_id', $u->id)->first();
        if (! $ref || $ref->status === 'rejected') return;
        $referrer = User::find($ref->referrer_id);
        if (! $referrer || ! Loyalty::active($referrer)) return;
        $net = (int) $o->total - (int) $o->loyalty_refunded;
        // ambassador commission on every paid order of a referred customer
        if ($referrer->ambassador && (float) $referrer->ambassador_pct > 0 && Loyalty::cashbackOn()) {
            $c = (int) round($net * (float) $referrer->ambassador_pct / 100);
            if ($c > 0) Loyalty::award($referrer, 'ambassador', $c, 'ambassador:'.$o->id, 'Ambassador commission · '.$u->name, ['wallet' => 'cashback', 'force' => true, 'order_id' => $o->id]);
        }
        if (in_array($ref->status, ['qualified', 'rewarded'], true)) return;
        if ($net < (int) setting('ref_min_order', 0)) return;
        DB::table('loyalty_referrals')->where('id', $ref->id)->update(['status' => 'qualified', 'order_id' => $o->id, 'updated_at' => now()]);
        if (setting('ref_approve', '0') !== '1') self::rewardReferral((int) $ref->id);
    }

    public static function rewardReferral(int $id, string $actor = 'system'): bool
    {
        $ref = DB::table('loyalty_referrals')->where('id', $id)->first();
        if (! $ref || $ref->status !== 'qualified') return false;
        $u = User::find($ref->referrer_id);
        if (! $u) return false;
        $pts = (int) setting('ref_referrer_points', 0);
        $cash = (int) setting('ref_referrer_cashback', 0);
        if (DB::table('loyalty_referrals')->where('id', $id)->where('status', 'qualified')->update(['status' => 'rewarded', 'reward_points' => $pts, 'reward_cashback' => $cash, 'updated_at' => now()]) !== 1) return false;
        if ($pts > 0) Loyalty::award($u, 'referral', $pts, 'referral:'.$id.':pts', 'Referral reward', ['force' => true, 'actor' => $actor]);
        if ($cash > 0) Loyalty::award($u, 'referral', $cash, 'referral:'.$id.':cash', 'Referral reward', ['wallet' => 'cashback', 'force' => true, 'actor' => $actor]);
        Loyalty::notify($u, 'referral', 'Referral reward earned', 'Your friend made a qualifying purchase — your reward is in your wallet.', '/account/loyalty?tab=referrals');
        Loyalty::hook('referral.rewarded', ['referrer_id' => $u->id, 'referral_id' => $id]);
        self::track($u, 'referral', ['ref' => 'rf'.$id]);
        self::workflows('referral.rewarded', $u, ['ref' => 'rr'.$id]);
        self::badges($u);

        return true;
    }

    // ------------------------------------------------------------------ daily check-in & streaks
    public static function checkin(User $u): array
    {
        if (setting('checkin_on', '1') !== '1' || ! Loyalty::active($u)) return ['ok' => false, 'msg' => 'Check-in is not available.'];
        $today = now()->toDateString();
        if (DB::table('loyalty_checkins')->where('user_id', $u->id)->where('day', $today)->exists()) return ['ok' => false, 'msg' => 'You already checked in today. Come back tomorrow!'];
        $last = DB::table('loyalty_checkins')->where('user_id', $u->id)->orderByDesc('day')->first();
        $streak = ($last && $last->day === now()->subDay()->toDateString()) ? $last->streak + 1 : 1;
        $pts = (int) setting('checkin_points', 0);
        $every = (int) setting('checkin_streak_every', 0);
        $bonus = ($every > 0 && $streak % $every === 0) ? (int) setting('checkin_streak_bonus', 0) : 0;
        try {
            DB::table('loyalty_checkins')->insert(['user_id' => $u->id, 'day' => $today, 'streak' => $streak, 'points' => $pts + $bonus, 'created_at' => now()]);
        } catch (\Throwable) {
            return ['ok' => false, 'msg' => 'You already checked in today.'];
        }
        if ($pts > 0) Loyalty::award($u, 'checkin', $pts, 'checkin:'.$u->id.':'.$today, 'Daily check-in (day '.$streak.')', ['force' => true]);
        if ($bonus > 0) Loyalty::award($u, 'checkin_streak', $bonus, 'checkinbonus:'.$u->id.':'.$today, $streak.'-day streak bonus', ['force' => true]);
        self::track($u, 'checkin', ['streak' => $streak, 'ref' => 'ci'.$today]);
        self::badges($u);

        return ['ok' => true, 'msg' => 'Checked in! +'.($pts + $bonus).' points · '.$streak.'-day streak.', 'streak' => $streak];
    }

    public static function streak(User $u): int
    {
        $last = DB::table('loyalty_checkins')->where('user_id', $u->id)->orderByDesc('day')->first();
        if (! $last || $last->day < now()->subDay()->toDateString()) return 0;

        return (int) $last->streak;
    }

    // ------------------------------------------------------------------ challenges
    public static function periodKey(object $c, ?Carbon $at = null): string
    {
        $at ??= now();

        return match ($c->period) { 'daily' => $at->format('Ymd'), 'weekly' => $at->format('oW'), 'monthly' => $at->format('Ym'), default => 'all' };
    }

    public static function challengeLive(object $c, ?User $u = null): bool
    {
        if (! $c->active) return false;
        if ($c->starts_at && Carbon::parse($c->starts_at)->isFuture()) return false;
        if ($c->ends_at && Carbon::parse($c->ends_at)->isPast()) return false;
        if ($c->max_rewards > 0 && $c->rewarded >= $c->max_rewards) return false;
        $tiers = Loyalty::ids((string) $c->tier_ids);
        if ($tiers && $u && ! in_array((int) $u->tier_id, $tiers, true)) return false;

        return true;
    }

    /** Feed an event into every matching live challenge. kind: order|checkin|referral|review|custom. */
    public static function track(User $u, string $kind, array $ctx = []): void
    {
        try {
            foreach (DB::table('loyalty_challenges')->where('active', true)->get() as $c) {
                if (! self::challengeLive($c, $u)) continue;
                $inc = 0;
                switch ($c->kind) {
                    case 'orders': if ($kind === 'order') $inc = 1; break;
                    case 'spend': if ($kind === 'order') $inc = (int) ($ctx['amount'] ?? 0); break;
                    case 'category': if ($kind === 'order' && in_array((int) $c->scope_id, $ctx['cids'] ?? [], true)) $inc = 1; break;
                    case 'product': if ($kind === 'order' && in_array((int) $c->scope_id, $ctx['pids'] ?? [], true)) $inc = 1; break;
                    case 'checkin': if ($kind === 'checkin') $inc = 1; break;
                    case 'referral': if ($kind === 'referral') $inc = 1; break;
                    case 'review': if ($kind === 'review') $inc = 1; break;
                    case 'custom': if ($kind === 'custom' && (int) ($ctx['challenge_id'] ?? 0) === (int) $c->id) $inc = (int) ($ctx['amount'] ?? 1); break;
                }
                if ($inc <= 0) continue;
                $key = self::periodKey($c);
                DB::table('loyalty_progress')->insertOrIgnore(['user_id' => $u->id, 'challenge_id' => $c->id, 'period_key' => $key, 'progress' => 0, 'created_at' => now(), 'updated_at' => now()]);
                $p = DB::table('loyalty_progress')->where(['user_id' => $u->id, 'challenge_id' => $c->id, 'period_key' => $key])->first();
                if (! $p || $p->completed_at) continue;
                $new = (int) $p->progress + $inc;
                DB::table('loyalty_progress')->where('id', $p->id)->update(['progress' => $new, 'updated_at' => now()]);
                if ($new >= (int) $c->target) self::completeChallenge($u, $c, $p->id, $key);
            }
        } catch (\Throwable $e) {
            \Log::warning('loyalty track: '.$e->getMessage());
        }
    }

    public static function completeChallenge(User $u, object $c, int $progressId, string $key, string $actor = 'system'): void
    {
        if (DB::table('loyalty_progress')->where('id', $progressId)->whereNull('completed_at')->update(['completed_at' => now()]) !== 1) return;
        DB::table('loyalty_challenges')->where('id', $c->id)->update(['rewarded' => DB::raw('rewarded + 1')]);
        $ref = 'chal:'.$c->id.':'.$u->id.':'.$key;
        if ($c->reward_points > 0) Loyalty::award($u, 'challenge', (int) $c->reward_points, $ref.':pts', 'Challenge: '.$c->name, ['force' => true, 'actor' => $actor]);
        if ($c->reward_cashback > 0) Loyalty::award($u, 'challenge', (int) $c->reward_cashback, $ref.':cash', 'Challenge: '.$c->name, ['wallet' => 'cashback', 'force' => true, 'actor' => $actor]);
        if ($c->badge_id) self::giveBadge($u, (int) $c->badge_id);
        Loyalty::notify($u, 'challenge', 'Challenge complete: '.$c->name, 'Nice work! Your reward has been added.', '/account/loyalty?tab=challenges');
        self::workflows('challenge.completed', $u, ['ref' => 'cc'.$c->id.$key]);
        self::badges($u);
    }

    /** Admin: manually award a challenge reward. */
    public static function manualComplete(User $u, int $challengeId, string $actor): bool
    {
        $c = DB::table('loyalty_challenges')->where('id', $challengeId)->first();
        if (! $c) return false;
        $key = self::periodKey($c);
        DB::table('loyalty_progress')->insertOrIgnore(['user_id' => $u->id, 'challenge_id' => $c->id, 'period_key' => $key, 'progress' => (int) $c->target, 'created_at' => now(), 'updated_at' => now()]);
        $p = DB::table('loyalty_progress')->where(['user_id' => $u->id, 'challenge_id' => $c->id, 'period_key' => $key])->first();
        if (! $p || $p->completed_at) return false;
        DB::table('loyalty_progress')->where('id', $p->id)->update(['progress' => (int) $c->target]);
        self::completeChallenge($u, $c, $p->id, $key, $actor);
        Loyalty::audit('challenge.award', 'user', $u->id, ['challenge' => $c->id], $actor);

        return true;
    }

    // ------------------------------------------------------------------ badges
    public static function giveBadge(User $u, int $badgeId): bool
    {
        $b = DB::table('loyalty_badges')->where('id', $badgeId)->where('active', true)->first();
        if (! $b || DB::table('loyalty_user_badges')->insertOrIgnore(['user_id' => $u->id, 'badge_id' => $badgeId, 'created_at' => now()]) !== 1) return false;
        if ($b->reward_points > 0) Loyalty::award($u, 'badge', (int) $b->reward_points, 'badge:'.$b->id.':'.$u->id, 'Badge: '.$b->name, ['force' => true]);
        Loyalty::notify($u, 'challenge', 'New badge: '.$b->icon.' '.$b->name, (string) $b->description, '/account/loyalty?tab=challenges');

        return true;
    }

    public static function badges(User $u): void
    {
        try {
            $orders = LoyaltyTiers::metrics($u);
            $stats = [
                'orders' => $orders['orders'], 'spend' => $orders['lifetime'],
                'referrals' => (int) DB::table('loyalty_referrals')->where('referrer_id', $u->id)->where('status', 'rewarded')->count(),
                'checkins' => (int) DB::table('loyalty_checkins')->where('user_id', $u->id)->count(),
                'challenges' => (int) DB::table('loyalty_progress')->where('user_id', $u->id)->whereNotNull('completed_at')->count(),
                'tier' => (int) (LoyaltyTiers::of($u)->sort ?? 0),
            ];
            foreach (DB::table('loyalty_badges')->where('active', true)->where('rule', '<>', 'manual')->get() as $b) {
                if (($stats[$b->rule] ?? 0) >= (int) $b->threshold) self::giveBadge($u, (int) $b->id);
            }
        } catch (\Throwable $e) {
            \Log::debug('loyalty badges: '.$e->getMessage());
        }
    }

    // ------------------------------------------------------------------ spin the wheel / scratch & win
    public static function prizes(string $game): array
    {
        $out = [];
        foreach (preg_split('/\R/', (string) setting($game.'_prizes', '')) as $line) {
            $p = array_map('trim', explode('|', $line));
            if (count($p) < 3 || $p[0] === '') continue;
            $out[] = ['label' => $p[0], 'type' => in_array($p[1], ['points', 'cashback', 'nothing'], true) ? $p[1] : 'nothing', 'amount' => max(0, (int) $p[2]), 'weight' => max(1, (int) ($p[3] ?? 1))];
        }

        return $out;
    }

    public static function playsToday(User $u, string $game): int
    {
        return (int) DB::table('loyalty_plays')->where('user_id', $u->id)->where('game', $game)->where('day', now()->toDateString())->count();
    }

    public static function play(User $u, string $game): array
    {
        if (! in_array($game, ['spin', 'scratch'], true) || setting($game.'_on', '0') !== '1' || ! Loyalty::active($u)) return ['ok' => false, 'msg' => 'This game is not available.'];
        $limit = max(1, (int) setting($game.'_per_day', 1));
        $n = self::playsToday($u, $game) + 1;
        if ($n > $limit) return ['ok' => false, 'msg' => 'No plays left today — come back tomorrow!'];
        $prizes = self::prizes($game);
        if (! $prizes) return ['ok' => false, 'msg' => 'No prizes configured.'];
        $roll = random_int(1, array_sum(array_column($prizes, 'weight')));
        $win = $prizes[0];
        foreach ($prizes as $p) {
            $roll -= $p['weight'];
            if ($roll <= 0) { $win = $p; break; }
        }
        try {
            DB::table('loyalty_plays')->insert(['user_id' => $u->id, 'game' => $game, 'day' => now()->toDateString(), 'n' => $n, 'prize' => $win['label'], 'prize_type' => $win['type'], 'amount' => $win['amount'], 'created_at' => now()]);
        } catch (\Throwable) {
            return ['ok' => false, 'msg' => 'Please try again.'];
        }
        if ($win['type'] !== 'nothing' && $win['amount'] > 0) {
            Loyalty::award($u, $game, $win['amount'], $game.':'.$u->id.':'.now()->toDateString().':'.$n, ($game === 'spin' ? 'Spin the wheel' : 'Scratch & win').': '.$win['label'], ['wallet' => $win['type'], 'force' => true]);
        }

        return ['ok' => true, 'prize' => $win['label'], 'type' => $win['type'], 'amount' => $win['amount'], 'index' => array_search($win, $prizes, true), 'left' => $limit - $n, 'msg' => $win['type'] === 'nothing' ? $win['label'] : 'You won: '.$win['label'].'!'];
    }

    // ------------------------------------------------------------------ activities (reviews, Q&A, social) and competitions
    public const ACTIVITIES = ['review' => ['pts_review', 'Verified product review'], 'photo_review' => ['pts_photo_review', 'Photo review'], 'video_review' => ['pts_video_review', 'Video review'], 'qa' => ['pts_qa', 'Answered a product question'], 'social' => ['pts_social', 'Approved social activity']];

    /** Award points for an activity (idempotent via $ref). Used by admin "Award activity" and by future review/Q&A modules. */
    public static function activity(User $u, string $type, string $ref, ?int $points = null, string $actor = 'system'): bool
    {
        if (! isset(self::ACTIVITIES[$type])) return false;
        [$key, $label] = self::ACTIVITIES[$type];
        $pts = $points ?? (int) setting($key, 0);
        $row = Loyalty::award($u, $type, $pts, 'act:'.$type.':'.$u->id.':'.$ref, $label, ['actor' => $actor]);
        if ($row) {
            if (str_contains($type, 'review')) {
                self::track($u, 'review', ['ref' => $ref]);
                if (DB::table('loyalty_ledger')->where('user_id', $u->id)->where('reference', 'like', 'act:%review%')->count() === 1) {
                    Loyalty::award($u, 'first_review', (int) setting('pts_review', 0), 'firstreview:'.$u->id, 'First review bonus', ['actor' => $actor]);
                }
            }
            self::badges($u);
        }

        return (bool) $row;
    }

    public static function metric(string $metric, User|int $u, Carbon $from, Carbon $to): int
    {
        $id = $u instanceof User ? $u->id : $u;

        return match ($metric) {
            'orders' => (int) Order::where('user_id', $id)->where('payment_status', 'paid')->whereNotIn('status', ['cancelled', 'refunded'])->whereBetween('paid_at', [$from, $to])->count(),
            'points' => (int) DB::table('loyalty_ledger')->where('user_id', $id)->where('wallet', 'points')->whereIn('type', ['earn', 'bonus'])->where('status', 'available')->whereBetween('created_at', [$from, $to])->sum('amount'),
            'referrals' => (int) DB::table('loyalty_referrals')->where('referrer_id', $id)->where('status', 'rewarded')->whereBetween('updated_at', [$from, $to])->count(),
            default => max(0, (int) Order::where('user_id', $id)->where('payment_status', 'paid')->whereNotIn('status', ['cancelled', 'refunded'])->whereBetween('paid_at', [$from, $to])->sum('total') - (int) Order::where('user_id', $id)->where('payment_status', 'paid')->whereBetween('paid_at', [$from, $to])->sum('loyalty_refunded')),
        };
    }

    /** Leaderboard rows [user, score] for a competition or for "this month". */
    public static function leaderboard(string $metric, Carbon $from, Carbon $to, int $limit = 10): array
    {
        $ids = User::where('role', 'customer')->where('loyalty_status', 'active')->pluck('id');
        $rows = [];
        foreach ($ids as $id) {
            $s = self::metric($metric, (int) $id, $from, $to);
            if ($s > 0) $rows[] = ['id' => (int) $id, 'score' => $s];
        }
        usort($rows, fn ($a, $b) => $b['score'] <=> $a['score']);
        $rows = array_slice($rows, 0, $limit);
        $names = User::whereIn('id', array_column($rows, 'id'))->pluck('name', 'id');
        foreach ($rows as &$r) $r['name'] = self::maskName((string) ($names[$r['id']] ?? 'Member'));

        return $rows;
    }

    public static function maskName(string $n): string
    {
        $p = preg_split('/\s+/', trim($n)) ?: [];

        return ($p[0] ?? 'Member').(isset($p[1]) ? ' '.mb_strtoupper(mb_substr($p[1], 0, 1)).'.' : '');
    }

    public static function settleCompetitions(): int
    {
        $n = 0;
        foreach (DB::table('loyalty_competitions')->where('active', true)->whereNull('settled_at')->whereNotNull('ends_at')->where('ends_at', '<', now())->get() as $c) {
            if (DB::table('loyalty_competitions')->where('id', $c->id)->whereNull('settled_at')->update(['settled_at' => now()]) !== 1) continue;
            $prizes = [];
            foreach (explode(',', (string) $c->prizes) as $pz) {
                [$rank, $pts] = array_pad(explode(':', trim($pz)), 2, 0);
                if ((int) $rank > 0) $prizes[(int) $rank] = (int) $pts;
            }
            $board = self::leaderboard($c->metric, Carbon::parse($c->starts_at ?: '2000-01-01'), Carbon::parse($c->ends_at), max(1, count($prizes) ?: 3));
            foreach ($board as $i => $row) {
                $pts = $prizes[$i + 1] ?? 0;
                if ($pts > 0 && ($u = User::find($row['id']))) Loyalty::award($u, 'competition', $pts, 'comp:'.$c->id.':'.$u->id, 'Competition: '.$c->name.' (rank '.($i + 1).')', ['force' => true]);
            }
            Loyalty::audit('competition.settled', 'competition', (int) $c->id, ['winners' => count($board)], 'system');
            $n++;
        }

        return $n;
    }

    // ------------------------------------------------------------------ workflows (event -> action)
    public static function workflows(string $event, User $u, array $ctx = []): void
    {
        try {
            if (! installed() || ! Loyalty::active($u)) return;
            foreach (DB::table('loyalty_workflows')->where('enabled', true)->where('event', $event)->get() as $w) {
                $cond = json_decode((string) $w->conditions, true) ?: [];
                $par = json_decode((string) $w->params, true) ?: [];
                $o = $ctx['order'] ?? null;
                if (! empty($cond['min_total']) && $o && (int) $o->total < (int) $cond['min_total']) continue;
                if (! empty($cond['nth']) && (int) ($ctx['nth'] ?? 0) !== (int) $cond['nth']) continue;
                if (! empty($cond['tier_ids']) && ! in_array((int) $u->tier_id, Loyalty::ids((string) $cond['tier_ids']), true)) continue;
                $ref = $w->id.':'.$u->id.':'.($ctx['ref'] ?? $event);
                try {
                    DB::table('loyalty_workflow_logs')->insert(['workflow_id' => $w->id, 'user_id' => $u->id, 'ref' => mb_substr($ref, 0, 118), 'result' => 'running', 'created_at' => now()]);
                } catch (\Throwable) {
                    continue; // already ran for this event
                }
                $result = self::runAction($w, $u, $par, $ref);
                DB::table('loyalty_workflow_logs')->where('workflow_id', $w->id)->where('ref', mb_substr($ref, 0, 118))->update(['result' => mb_substr($result, 0, 190)]);
                DB::table('loyalty_workflows')->where('id', $w->id)->update(['runs' => DB::raw('runs + 1')]);
            }
        } catch (\Throwable $e) {
            \Log::warning('loyalty workflow: '.$e->getMessage());
        }
    }

    private static function runAction(object $w, User $u, array $p, string $ref): string
    {
        $amount = (int) ($p['amount'] ?? 0);
        $msg = (string) ($p['message'] ?? '');
        switch ($w->action) {
            case 'award_points':
                $r = Loyalty::award($u, 'workflow', $amount, 'wf:'.$ref, $w->name, ['force' => true]);

                return $r ? '+'.$amount.' points' : 'skipped';
            case 'award_cashback':
                $r = Loyalty::award($u, 'workflow', $amount, 'wfc:'.$ref, $w->name, ['wallet' => 'cashback', 'force' => true]);

                return $r ? '+'.money($amount).' cashback' : 'skipped';
            case 'issue_voucher':
                $v = Loyalty::makeVoucher($u, 'workflow', ($p['type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent', $amount, ['prefix' => 'LF', 'ends_at' => now()->addDays((int) ($p['days'] ?? 30)), 'note' => $w->name]);
                Loyalty::notify($u, 'promo', $w->name, ($msg ?: 'A voucher for you:').' '.$v->code, '/cart');

                return 'voucher '.$v->code;
            case 'notify':
                Loyalty::notify($u, 'promo', $w->name, $msg ?: $w->name, '/account/loyalty');

                return 'notified';
        }

        return 'noop';
    }

    // ------------------------------------------------------------------ nightly lifecycle
    /** Hourly-safe maintenance: release holds, expire, remind, birthdays, anniversaries, win-back, competitions, fraud scan. */
    public static function tick(bool $force = false): array
    {
        $out = ['released' => Loyalty::releaseDue(), 'expired' => Loyalty::expireDue(), 'vouchers' => LoyaltyRewards::expireVouchers(), 'competitions' => self::settleCompetitions()];
        $day = now()->format('Ymd');
        if (! $force && setting('loyalty_last_tick') === $day) return $out;
        \App\Support\Settings::set('loyalty_last_tick', $day);
        $out += ['birthday' => self::birthdays(), 'anniversary' => self::anniversaries(), 'reminders' => self::expiryReminders(), 'winback' => self::winBack(), 'tiers' => self::tierSweep(), 'scan' => self::fraudScan()];

        return $out;
    }

    /** Driver-neutral "month-day" SQL expression. */
    public static function md(string $col): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? "strftime('%m-%d', $col)" : "DATE_FORMAT($col, '%m-%d')";
    }

    private static function members(): \Illuminate\Database\Eloquent\Builder
    {
        return User::where('role', 'customer')->where('loyalty_status', 'active');
    }

    public static function birthdays(): int
    {
        $n = 0;
        foreach (self::members()->whereNotNull('birthday')->whereRaw(self::md('birthday').' = ?', [now()->format('m-d')])->get() as $u) {
            $n += self::birthdayFor($u) ? 1 : 0;
        }

        return $n;
    }

    private static function birthdayFor(User $u): bool
    {
        $t = LoyaltyTiers::of($u);
        $pts = (int) setting('pts_birthday', 0) + (int) ($t->birthday_points ?? 0);
        $r = $pts > 0 ? Loyalty::award($u, 'birthday', $pts, 'birthday:'.$u->id.':'.now()->year, 'Happy birthday! 🎂', ['force' => true]) : null;
        self::workflows('birthday', $u, ['ref' => 'bd'.now()->year]);

        return (bool) $r;
    }

    public static function anniversaries(): int
    {
        $n = 0;
        foreach (self::members()->whereNotNull('loyalty_joined_at')->whereRaw(self::md('loyalty_joined_at').' = ?', [now()->format('m-d')])->where('loyalty_joined_at', '<', now()->startOfYear())->get() as $u) {
            if ((int) setting('pts_anniversary', 0) > 0 && Loyalty::award($u, 'anniversary', (int) setting('pts_anniversary'), 'anniv:'.$u->id.':'.now()->year, 'Account anniversary', ['force' => true])) $n++;
            self::workflows('anniversary', $u, ['ref' => 'an'.now()->year]);
        }

        return $n;
    }

    public static function expiryReminders(): int
    {
        $days = (int) setting('loyalty_expiry_remind_days', 0);
        if ($days <= 0) return 0;
        $n = 0;
        $rows = DB::table('loyalty_ledger')->where('status', 'available')->where('remaining', '>', 0)->whereNotNull('expires_at')->whereBetween('expires_at', [now(), now()->addDays($days)])->whereNull('reminded_at')->get()->groupBy('user_id');
        foreach ($rows as $uid => $lots) {
            $u = User::find($uid);
            if (! $u) continue;
            $pts = (int) $lots->where('wallet', 'points')->sum('remaining');
            $first = $lots->min('expires_at');
            if ($pts > 0) Loyalty::notify($u, 'points', 'Points expiring soon', number_format($pts).' points expire on '.Carbon::parse($first)->format('M j, Y').'. Use them on a reward or at checkout!', '/account/loyalty?tab=rewards');
            DB::table('loyalty_ledger')->whereIn('id', $lots->pluck('id'))->update(['reminded_at' => now()]);
            self::workflows('points.expiring', $u, ['ref' => 'ex'.now()->format('Ym')]);
            $n++;
        }

        return $n;
    }

    public static function winBack(): int
    {
        $d = (int) setting('comeback_days', 0);
        if ($d <= 0) return 0;
        $n = 0;
        $cut = now()->subDays($d);
        foreach (self::members()->where('created_at', '<', $cut)->get() as $u) {
            $last = Order::where(fn ($q) => $q->where('user_id', $u->id)->orWhere('email', $u->email))->where('payment_status', 'paid')->max('paid_at');
            if (! $last || Carbon::parse($last)->gt($cut)) continue;
            if (Carbon::parse($last)->lt(now()->subDays($d * 3))) continue; // long gone: not worth spamming
            if ((int) setting('pts_comeback', 0) > 0 && Loyalty::award($u, 'comeback', (int) setting('pts_comeback'), 'comeback:'.$u->id.':'.Carbon::parse($last)->format('Ymd'), 'We miss you — comeback bonus', ['force' => true])) $n++;
            self::workflows('inactive', $u, ['ref' => 'in'.Carbon::parse($last)->format('Ymd')]);
        }

        return $n;
    }

    private static function tierSweep(): int
    {
        $n = 0;
        foreach (self::members()->where(fn ($q) => $q->whereNull('tier_until')->orWhere('tier_until', '<', now()->addDays(1)))->limit(500)->get() as $u) {
            LoyaltyTiers::evaluate($u);
            $n++;
        }

        return $n;
    }

    /** Flag rapid earning and clusters of accounts on one IP. */
    public static function fraudScan(): int
    {
        $n = 0;
        $limit = (int) setting('loyalty_max_points_day', 0);
        if ($limit > 0) {
            foreach (DB::table('loyalty_ledger')->selectRaw('user_id, sum(amount) s')->where('wallet', 'points')->whereIn('type', ['earn', 'bonus'])->where('created_at', '>=', now()->subDay())->groupBy('user_id')->havingRaw('sum(amount) > ?', [$limit * 3])->get() as $r) {
                Loyalty::flag((int) $r->user_id, 'suspicious_points', 'high', number_format($r->s).' points earned in 24h', 'pts:'.$r->user_id.':'.date('Ymd'));
                $n++;
            }
        }
        foreach (DB::table('users')->selectRaw('reg_ip, count(*) c')->whereNotNull('reg_ip')->where('role', 'customer')->groupBy('reg_ip')->havingRaw('count(*) >= 4')->get() as $r) {
            Loyalty::flag(null, 'duplicate_account', 'medium', $r->c.' accounts registered from IP '.$r->reg_ip, 'ip:'.$r->reg_ip.':'.$r->c);
            $n++;
        }

        return $n;
    }
}
