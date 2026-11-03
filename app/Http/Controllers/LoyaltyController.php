<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Loyalty;
use App\Services\LoyaltyEngagement;
use App\Services\LoyaltyReports;
use App\Services\LoyaltyRewards;
use App\Services\LoyaltyTiers;
use App\Support\Mailer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Customer-facing loyalty area (inside /account). */
class LoyaltyController extends Controller
{
    public const TABS = ['overview' => 'Overview', 'wallet' => 'Wallet', 'tiers' => 'Tiers', 'rewards' => 'Rewards', 'challenges' => 'Challenges', 'referrals' => 'Referrals', 'activity' => 'Notifications', 'settings' => 'Settings'];

    public function index(Request $r)
    {
        if (! Loyalty::on()) {
            flash('info', 'The rewards programme is not available right now.');

            return redirect('/account');
        }
        $u = Loyalty::ensureMember(Auth::user());
        $u = $u->fresh();
        LoyaltyTiers::evaluate($u);
        $u = $u->fresh();
        $tab = isset(self::TABS[$r->query('tab')]) ? (string) $r->query('tab') : 'overview';
        $d = ['u' => $u, 'tab' => $tab, 'tabs' => self::TABS, 'active' => Loyalty::active($u)];
        $d['tier'] = LoyaltyTiers::of($u);
        $d['progress'] = LoyaltyTiers::progress($u);
        $d['pending'] = Loyalty::pending($u);
        $d['pendingCash'] = Loyalty::pending($u, 'cashback');
        $d['unread'] = (int) DB::table('loyalty_notifications')->where('user_id', $u->id)->whereNull('read_at')->count();
        $q = fn ($w) => DB::table('loyalty_ledger')->where('user_id', $u->id)->where('wallet', $w);
        $d['stats'] = [
            'lifetime' => (int) $q('points')->whereIn('type', ['earn', 'bonus', 'adjust', 'refund'])->where('amount', '>', 0)->where('status', 'available')->sum('amount'),
            'redeemed' => (int) abs($q('points')->where('type', 'redeem')->sum('amount')),
            'expired' => (int) abs($q('points')->where('type', 'expire')->sum('amount')),
            'soon' => (int) $q('points')->where('status', 'available')->where('remaining', '>', 0)->whereNotNull('expires_at')->whereBetween('expires_at', [now(), now()->addDays(max(1, (int) setting('loyalty_expiry_remind_days', 30)))])->sum('remaining'),
            'soon_at' => $q('points')->where('status', 'available')->where('remaining', '>', 0)->whereNotNull('expires_at')->where('expires_at', '>', now())->min('expires_at'),
        ];
        $d['badges'] = DB::table('loyalty_badges')->where('active', true)->get()->map(function ($b) use ($u) {
            $b->owned = DB::table('loyalty_user_badges')->where('user_id', $u->id)->where('badge_id', $b->id)->exists();

            return $b;
        })->all();
        $d['recent'] = DB::table('loyalty_ledger')->where('user_id', $u->id)->whereIn('status', ['available', 'pending'])->orderByDesc('id')->limit(8)->get()->all();
        $d['promos'] = collect(DB::table('loyalty_campaigns')->where('status', 'active')->get())->filter(fn ($c) => (! $c->starts_at || Carbon::parse($c->starts_at)->isPast()) && (! $c->ends_at || Carbon::parse($c->ends_at)->isFuture()))->values()->all();
        $d['rec'] = LoyaltyReports::recommend($u);
        match ($tab) {
            'wallet' => $d += $this->wallet($r, $u),
            'tiers' => $d['tiers'] = LoyaltyTiers::all(),
            'rewards' => $d += $this->rewards($r, $u),
            'challenges' => $d += $this->challenges($u),
            'referrals' => $d += $this->referrals($u),
            'activity' => $d['notes'] = DB::table('loyalty_notifications')->where('user_id', $u->id)->orderByDesc('id')->limit(60)->get()->all(),
            default => null,
        };
        title(Loyalty::name());

        return page('account/loyalty', $d);
    }

    private function wallet(Request $r, $u): array
    {
        $w = $r->query('wallet') === 'cashback' ? 'cashback' : 'points';
        $f = (string) $r->query('type');
        $map = ['earned' => ['earn', 'bonus', 'adjust', 'refund'], 'redeemed' => ['redeem'], 'expired' => ['expire'], 'reversed' => ['reverse']];
        $rows = DB::table('loyalty_ledger')->where('user_id', $u->id)->where('wallet', $w)->when(isset($map[$f]), fn ($x) => $x->whereIn('type', $map[$f]))->orderByDesc('id')->paginate(20)->withQueryString();
        $credit = DB::table('credit_log')->where('user_id', $u->id)->orderByDesc('id')->limit(10)->get()->all();

        return ['w' => $w, 'f' => $f, 'rows' => $rows, 'credit' => $credit, 'creditOn' => \App\Services\StoreCredit::enabled()];
    }

    private function rewards(Request $r, $u): array
    {
        $q = trim((string) $r->query('q'));
        $cat = trim((string) $r->query('cat'));
        $sort = (string) $r->query('sort', 'points');
        $list = DB::table('loyalty_rewards')->where('active', true)
            ->where(fn ($x) => $x->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('description', 'like', "%$q%")))
            ->when($cat !== '', fn ($x) => $x->where('category', $cat))
            ->orderBy($sort === 'points_desc' ? 'points_cost' : 'points_cost', $sort === 'points_desc' ? 'desc' : 'asc')->get();
        foreach ($list as $rw) $rw->blocked = LoyaltyRewards::blocked($rw, $u);
        $cats = DB::table('loyalty_rewards')->where('active', true)->whereNotNull('category')->distinct()->pluck('category')->all();
        $mine = DB::table('loyalty_redemptions as d')->join('loyalty_rewards as w', 'w.id', '=', 'd.reward_id')->where('d.user_id', $u->id)->orderByDesc('d.id')->limit(30)->get(['d.*', 'w.name'])->all();

        return ['rewards' => $list->all(), 'cats' => $cats, 'q' => $q, 'cat' => $cat, 'sort' => $sort, 'mine' => $mine, 'token' => bin2hex(random_bytes(8))];
    }

    private function challenges($u): array
    {
        $out = [];
        foreach (DB::table('loyalty_challenges')->where('active', true)->orderBy('period')->get() as $c) {
            if (! LoyaltyEngagement::challengeLive($c, $u)) continue;
            $p = DB::table('loyalty_progress')->where(['user_id' => $u->id, 'challenge_id' => $c->id, 'period_key' => LoyaltyEngagement::periodKey($c)])->first();
            $c->progress = (int) ($p->progress ?? 0);
            $c->done = (bool) ($p->completed_at ?? false);
            $out[] = $c;
        }
        $hist = DB::table('loyalty_progress as p')->join('loyalty_challenges as c', 'c.id', '=', 'p.challenge_id')->where('p.user_id', $u->id)->whereNotNull('p.completed_at')->orderByDesc('p.completed_at')->limit(20)->get(['c.name', 'p.completed_at', 'c.reward_points'])->all();
        $comps = DB::table('loyalty_competitions')->where('active', true)->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()->subDays(7)))->get()->map(function ($c) {
            $c->board = LoyaltyEngagement::leaderboard($c->metric, Carbon::parse($c->starts_at ?: '2000-01-01'), Carbon::parse($c->ends_at ?: now()->addYear()), 10);

            return $c;
        })->all();
        $month = LoyaltyEngagement::leaderboard('spend', now()->startOfMonth(), now()->endOfMonth(), 10);

        return ['challenges' => $out, 'hist' => $hist, 'comps' => $comps, 'month' => $month, 'streak' => LoyaltyEngagement::streak($u), 'checked' => DB::table('loyalty_checkins')->where('user_id', $u->id)->where('day', now()->toDateString())->exists(),
            'spin' => ['on' => setting('spin_on') === '1', 'left' => max(0, (int) setting('spin_per_day', 1) - LoyaltyEngagement::playsToday($u, 'spin')), 'prizes' => LoyaltyEngagement::prizes('spin')],
            'scratch' => ['on' => setting('scratch_on') === '1', 'left' => max(0, (int) setting('scratch_per_day', 1) - LoyaltyEngagement::playsToday($u, 'scratch'))]];
    }

    private function referrals($u): array
    {
        $rows = DB::table('loyalty_referrals as r')->join('users as b', 'b.id', '=', 'r.referee_id')->where('r.referrer_id', $u->id)->orderByDesc('r.id')->get(['r.*', 'b.name as referee_name'])->all();

        return ['refs' => $rows, 'link' => LoyaltyEngagement::referralLink($u), 'refOn' => setting('ref_on', '1') === '1'];
    }

    // ------------------------------------------------------------------ actions
    public function checkin()
    {
        $res = LoyaltyEngagement::checkin(Auth::user());
        flash($res['ok'] ? 'success' : 'info', $res['msg']);

        return back();
    }

    public function redeem(Request $r)
    {
        try {
            $red = LoyaltyRewards::redeem(Auth::user(), (int) $r->input('reward_id'), (string) $r->input('token'));
            flash('success', $red->status === 'pending' ? 'Reward requested — we will confirm shortly.' : 'Reward unlocked!'.($red->code ? ' Your voucher code is '.$red->code.' (also saved below).' : ''));
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }

        return redirect('/account/loyalty?tab=rewards');
    }

    public function cancelRedemption(Request $r, int $id)
    {
        $red = DB::table('loyalty_redemptions')->where('id', $id)->where('user_id', Auth::id())->first();
        if ($red && LoyaltyRewards::cancel($id, 'customer')) flash('success', 'Reward cancelled — your points were returned.');
        else flash('error', 'This reward can no longer be cancelled.');

        return redirect('/account/loyalty?tab=rewards');
    }

    public function play(Request $r)
    {
        $game = (string) $r->input('game');
        $res = LoyaltyEngagement::play(Auth::user(), $game);
        if ($r->expectsJson()) return response()->json($res);
        flash($res['ok'] ? 'success' : 'info', $res['msg']);

        return redirect('/account/loyalty?tab=challenges');
    }

    public function prefs(Request $r)
    {
        $u = Auth::user();
        $types = [];
        foreach (['points', 'tier', 'rewards', 'referral', 'challenge', 'promo'] as $t) $types[$t] = $r->boolean('t_'.$t);
        $u->forceFill(['loyalty_prefs' => json_encode(['email' => $r->boolean('email'), 'site' => $r->boolean('site'), 'push' => $r->boolean('push'), 'types' => $types])])->saveQuietly();
        if ($r->filled('birthday') && ! $u->birthday) {
            try {
                $b = Carbon::parse((string) $r->input('birthday'));
                if ($b->isPast() && $b->year > 1900) $u->forceFill(['birthday' => $b->toDateString()])->saveQuietly();
            } catch (\Throwable) {
            }
        }
        flash('success', 'Preferences saved.');

        return redirect('/account/loyalty?tab=settings');
    }

    public function read(Request $r)
    {
        $q = DB::table('loyalty_notifications')->where('user_id', Auth::id())->whereNull('read_at');
        if ($id = (int) $r->input('id')) $q->where('id', $id);
        $q->update(['read_at' => now()]);

        return back();
    }

    public function assist(Request $r)
    {
        $u = Auth::user();
        $msg = trim((string) $r->input('message'));
        if (mb_strlen($msg) < 5) {
            flash('error', 'Please describe what you need help with.');

            return redirect('/account/loyalty?tab=settings');
        }
        $to = (string) setting('store_email');
        if ($to) {
            $t = LoyaltyTiers::of($u);
            Mailer::send($to, '['.Loyalty::name().'] Help request from '.$u->name.($t && $t->priority_support ? ' (PRIORITY)' : ''), Mailer::wrap('Loyalty assistance request', '<p>'.e($u->name).' &lt;'.e($u->email).'&gt; · '.e((string) $u->loyalty_id).'</p><p>'.nl2br(e(mb_substr($msg, 0, 2000))).'</p>'));
        }
        Loyalty::audit('assist.request', 'user', $u->id, ['message' => mb_substr($msg, 0, 300)], 'customer:'.$u->id);
        flash('success', 'Thanks — we received your request and will reply by email.');

        return redirect('/account/loyalty?tab=settings');
    }

    /** Protected read-only API: GET /api/loyalty/member?email=… with `Authorization: Bearer <token>`. */
    public function api(Request $r)
    {
        $tok = (string) setting('loyalty_api_token', '');
        $given = (string) $r->bearerToken();
        if ($tok === '' || ! hash_equals($tok, $given)) return response()->json(['error' => 'unauthorized'], 401);
        $u = \App\Models\User::where('email', mb_strtolower((string) $r->query('email')))->orWhere('loyalty_id', (string) $r->query('id'))->first();
        if (! $u) return response()->json(['error' => 'not_found'], 404);

        return response()->json(['loyalty_id' => $u->loyalty_id, 'email' => $u->email, 'status' => $u->loyalty_status, 'points' => (int) $u->points, 'cashback' => (int) $u->cashback, 'store_credit' => (int) $u->credit,
            'tier' => LoyaltyTiers::of($u)->name ?? null, 'currency' => currency_code()]);
    }
}
