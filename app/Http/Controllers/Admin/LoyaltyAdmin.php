<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\Loyalty;
use App\Services\LoyaltyEngagement;
use App\Services\LoyaltyReports;
use App\Services\LoyaltyRewards;
use App\Services\LoyaltyTiers;
use App\Support\LoyaltySchema;
use App\Support\Settings;
use App\Support\Upload;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** "Loyalty & Rewards" section of the admin dashboard. */
class LoyaltyAdmin extends Controller
{
    private function actor(): string
    {
        return 'admin:'.auth()->user()->name;
    }

    private function seeded(): void
    {
        if (! DB::table('loyalty_tiers')->exists()) LoyaltyReports::seedDefaults();
    }

    // ------------------------------------------------------------------ overview & reports
    public function overview(Request $r)
    {
        $this->seeded();
        $from = $r->filled('from') ? Carbon::parse((string) $r->query('from'))->startOfDay() : null;
        $to = $r->filled('to') ? Carbon::parse((string) $r->query('to'))->endOfDay() : null;
        $o = LoyaltyReports::overview($from, $to);
        $trend = LoyaltyReports::trend(30);
        $unenrolled = User::where('role', 'customer')->whereNull('loyalty_id')->count();
        title('Loyalty & Rewards');

        return admin_page('loyalty_overview', compact('o', 'trend', 'unenrolled') + ['from' => $r->query('from'), 'to' => $r->query('to')]);
    }

    public function enrol()
    {
        $n = LoyaltyReports::backfill();
        Loyalty::audit('members.enrol', null, null, ['count' => $n]);
        flash('success', $n.' existing customer(s) enrolled in the programme.');

        return redirect('/admin/loyalty');
    }

    public function reports(Request $r)
    {
        $key = isset(LoyaltyReports::REPORTS[$r->query('report')]) ? (string) $r->query('report') : 'earning';
        $from = $r->filled('from') ? Carbon::parse((string) $r->query('from'))->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $r->filled('to') ? Carbon::parse((string) $r->query('to'))->endOfDay() : now()->endOfDay();
        $rep = LoyaltyReports::report($key, $from, $to);
        if (in_array($r->query('export'), ['csv', 'xls'], true)) {
            Loyalty::audit('report.export', 'report', null, ['report' => $key, 'format' => $r->query('export')]);
            $fn = 'loyalty-'.$key.'-'.date('Ymd');

            return $r->query('export') === 'xls' ? LoyaltyReports::xls($fn, $rep['cols'], $rep['rows']) : LoyaltyReports::csv($fn, $rep['cols'], $rep['rows']);
        }
        $total = count($rep['rows']);
        $page = max(1, (int) $r->query('page', 1));
        $rep['rows'] = array_slice($rep['rows'], ($page - 1) * 50, 50);
        $pages = max(1, (int) ceil($total / 50));
        title('Loyalty reports');

        return admin_page('loyalty_reports', compact('key', 'rep', 'total', 'page', 'pages') + ['from' => $from->toDateString(), 'to' => $to->toDateString()]);
    }

    // ------------------------------------------------------------------ settings
    private function settingKeys(): array
    {
        return [
            'bool' => ['loyalty_on', 'loyalty_guest_earn', 'loyalty_exclude_staff', 'loyalty_stack_coupons', 'loyalty_tier_auto_up', 'loyalty_tier_auto_down', 'cashback_on', 'ref_on', 'ref_approve', 'checkin_on', 'spin_on', 'scratch_on', 'loyalty_notify_email'],
            'money' => ['cashback_min_redeem', 'ref_referrer_cashback', 'ref_min_order'],
        ];
    }

    public function settings()
    {
        title('Loyalty settings');

        return admin_page('loyalty_settings', ['k' => $this->settingKeys(), 'tiers' => LoyaltyTiers::all(false)]);
    }

    public function settingsSave(Request $r)
    {
        $k = $this->settingKeys();
        $out = [];
        foreach (array_keys(Loyalty::defaults()) as $key) {
            if ($key === 'loyalty_last_tick' || ! $r->has($key)) continue;
            $v = $r->input($key);
            if (in_array($key, $k['bool'], true)) $v = $r->boolean($key) ? '1' : '0';
            elseif (in_array($key, $k['money'], true)) $v = (string) to_cents($v);
            elseif (in_array($key, ['loyalty_color'], true)) $v = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $v) ? $v : '#c6ff3d';
            elseif (in_array($key, ['ref_start', 'ref_end'], true)) $v = $v ? date('Y-m-d', strtotime((string) $v)) : '';
            elseif (str_ends_with($key, '_prizes') || str_starts_with($key, 'loyalty_terms') || str_ends_with($key, '_policy') || $key === 'loyalty_desc') $v = mb_substr((string) $v, 0, 4000);
            else $v = mb_substr(trim((string) $v), 0, 400);
            $out[$key] = $v;
        }
        if (isset($out['loyalty_redeem_rate']) && (float) $out['loyalty_redeem_rate'] <= 0) $out['loyalty_redeem_rate'] = '100';
        if ($r->boolean('gen_token')) $out['loyalty_api_token'] = bin2hex(random_bytes(24));
        if ($r->boolean('gen_secret')) $out['loyalty_webhook_secret'] = bin2hex(random_bytes(16));
        Settings::setMany($out);
        Loyalty::audit('settings.save', 'settings', null, array_diff_key($out, array_flip(['loyalty_api_token', 'loyalty_webhook_secret'])));
        flash('success', 'Loyalty settings saved.');

        return redirect('/admin/loyalty/settings');
    }

    // ------------------------------------------------------------------ generic CRUD (tiers, rewards, rules, campaigns, challenges, badges, competitions, workflows)
    public function crud(Request $r, string $e)
    {
        $s = LoyaltySchema::entities()[$e] ?? abort(404);
        $this->seeded();
        $q = DB::table($s['table'])->orderBy($s['order'] ?? 'id', $s['order_dir'] ?? 'asc');
        $total = (clone $q)->count();
        $rows = $q->limit(200)->get();
        if ($e === 'tiers') foreach ($rows as $x) $x->users = User::where('tier_id', $x->id)->count();
        $edit = $r->filled('edit') ? DB::table($s['table'])->where('id', (int) $r->query('edit'))->first() : null;
        if ($edit && $e === 'workflows') {
            foreach (['conditions' => 'c_', 'params' => 'p_'] as $col => $pre) foreach ((json_decode((string) $edit->$col, true) ?: []) as $kk => $vv) $edit->{$pre.$kk} = $vv;
        }
        title($s['title']);

        return admin_page('loyalty_crud', ['e' => $e, 's' => $s, 'rows' => $rows, 'edit' => $edit, 'tiersAll' => LoyaltyTiers::all(false), 'total' => $total]);
    }

    public function crudSave(Request $r, string $e)
    {
        $s = LoyaltySchema::entities()[$e] ?? abort(404);
        $id = (int) $r->input('id');
        $data = [];
        $json = ['conditions' => [], 'params' => []];
        foreach ($s['fields'] as $name => $f) {
            $type = $f[1];
            $v = $r->input($name);
            if ($type === 'image') {
                $path = Upload::image($r->file($name), 'site');
                if ($path) $data[$name] = $path;
                continue;
            }
            $val = match ($type) {
                'number' => max(0, (int) $v), 'nullint' => ($v === null || $v === '') ? null : (int) $v, 'decimal' => max(0, round((float) $v, 2)), 'money' => to_cents($v),
                'bool' => $r->boolean($name) ? 1 : 0, 'datetime' => $v ? date('Y-m-d H:i:s', strtotime((string) $v)) : null, 'tiers' => implode(',', array_map('intval', (array) $r->input($name, []))) ?: null,
                'valuefield' => in_array($r->input('type'), ['discount_fixed', 'voucher'], true) ? to_cents($v) : max(0, min(100, (int) $v)),
                'select' => in_array((string) $v, array_map('strval', array_keys($f['options'])), true) ? $v : array_key_first($f['options']),
                'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $v) ? $v : '#c6ff3d',
                default => mb_substr(trim((string) $v), 0, 2000),
            };
            if (! empty($f['req']) && ($val === '' || $val === null || $val === 0 && $type !== 'bool')) {
                flash('error', $f[0].' is required.');
                keep_old($r->all());

                return redirect("/admin/loyalty/$e".($id ? "?edit=$id" : '?new=1'));
            }
            if (isset($f['json'])) { $json[$f['json']][preg_replace('/^[cp]_/', '', $name)] = $val; continue; }
            $data[$name] = $val;
        }
        if ($e === 'workflows') { $data['conditions'] = json_encode(array_filter($json['conditions'], fn ($x) => $x !== null && $x !== '' && $x !== 0)); $data['params'] = json_encode($json['params']); }
        if ($e === 'competitions' && ! empty($data['ends_at']) && empty($data['starts_at'])) $data['starts_at'] = now()->toDateTimeString();
        $data['updated_at'] = now();
        if ($id) {
            DB::table($s['table'])->where('id', $id)->update($data);
        } else {
            $data['created_at'] = now();
            $id = (int) DB::table($s['table'])->insertGetId($data);
        }
        Loyalty::audit("$e.save", $e, $id, array_diff_key($data, ['image' => 1]));
        if ($e === 'tiers') foreach (User::whereNotNull('loyalty_id')->limit(2000)->get() as $u) if (! $u->tier_id) LoyaltyTiers::evaluate($u);
        flash('success', ucfirst($s['single']).' saved.');

        return redirect("/admin/loyalty/$e");
    }

    public function crudToggle(Request $r, string $e, int $id)
    {
        $s = LoyaltySchema::entities()[$e] ?? abort(404);
        $col = $s['toggle'] ?? 'active';
        $on = $r->input('value') === '1';
        if (str_contains($col, ':')) { [$col, $a, $b] = explode(':', $col); $val = $on ? $a : $b; } else { $val = $on ? 1 : 0; }
        DB::table($s['table'])->where('id', $id)->update([$col => $val]);
        Loyalty::audit("$e.toggle", $e, $id, [$col => $val]);

        return response()->json(['ok' => true, 'message' => $on ? 'Enabled' : 'Disabled']);
    }

    public function crudDelete(string $e, int $id)
    {
        $s = LoyaltySchema::entities()[$e] ?? abort(404);
        if ($e === 'tiers') {
            if (User::where('tier_id', $id)->exists()) {
                DB::table('loyalty_tiers')->where('id', $id)->update(['active' => false]);
                flash('info', 'Members still hold this tier, so it was deactivated instead of deleted.');

                return redirect('/admin/loyalty/tiers');
            }
        }
        if ($e === 'rewards' && DB::table('loyalty_redemptions')->where('reward_id', $id)->exists()) {
            DB::table('loyalty_rewards')->where('id', $id)->update(['active' => false]);
            flash('info', 'This reward has been redeemed before, so it was deactivated (history is kept).');

            return redirect('/admin/loyalty/rewards');
        }
        DB::table($s['table'])->where('id', $id)->delete();
        Loyalty::audit("$e.delete", $e, $id);
        flash('success', ucfirst($s['single']).' deleted.');

        return redirect("/admin/loyalty/$e");
    }

    // ------------------------------------------------------------------ members
    private function memberQuery(Request $r)
    {
        $q = trim((string) $r->query('q'));

        return User::where('role', 'customer')->whereNotNull('loyalty_id')
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('email', 'like', "%$q%")->orWhere('phone', 'like', "%$q%")->orWhere('loyalty_id', 'like', "%$q%")))
            ->when($r->filled('tier'), fn ($x) => $x->where('tier_id', (int) $r->query('tier')))
            ->when($r->filled('status'), fn ($x) => $x->where('loyalty_status', (string) $r->query('status')))
            ->when($r->filled('min_points'), fn ($x) => $x->where('points', '>=', (int) $r->query('min_points')))
            ->when($r->filled('max_points'), fn ($x) => $x->where('points', '<=', (int) $r->query('max_points')))
            ->when($r->filled('joined_from'), fn ($x) => $x->where('loyalty_joined_at', '>=', Carbon::parse((string) $r->query('joined_from'))->startOfDay()))
            ->when($r->filled('joined_to'), fn ($x) => $x->where('loyalty_joined_at', '<=', Carbon::parse((string) $r->query('joined_to'))->endOfDay()))
            ->when($r->query('buyers') === 'yes', fn ($x) => $x->whereIn('id', Order::where('payment_status', 'paid')->whereNotNull('user_id')->select('user_id')))
            ->when($r->query('buyers') === 'no', fn ($x) => $x->whereNotIn('id', Order::where('payment_status', 'paid')->whereNotNull('user_id')->select('user_id')));
    }

    public function members(Request $r)
    {
        $q = $this->memberQuery($r)->orderByDesc('points')->orderByDesc('id');
        if (in_array($r->query('export'), ['csv', 'xls'], true)) {
            $rows = $q->get()->map(fn ($u) => [$u->loyalty_id, $u->name, $u->email, $u->phone, LoyaltyTiers::find((int) $u->tier_id)->name ?? '', $u->points, number_format($u->cashback / 100, 2), number_format($u->credit / 100, 2), $u->loyalty_status, $u->loyalty_joined_at])->all();
            Loyalty::audit('members.export', null, null, ['count' => count($rows)]);
            $cols = ['Loyalty ID', 'Name', 'Email', 'Phone', 'Tier', 'Points', 'Cashback', 'Store credit', 'Status', 'Joined'];

            return $r->query('export') === 'xls' ? LoyaltyReports::xls('loyalty-members', $cols, $rows) : LoyaltyReports::csv('loyalty-members', $cols, $rows);
        }
        $p = $q->paginate(25)->withQueryString();
        $tiers = LoyaltyTiers::all(false);
        title('Loyalty members');

        return admin_page('loyalty_members', ['rows' => $p->items(), 'page' => $p->currentPage(), 'pages' => $p->lastPage(), 'total' => $p->total(), 'tiers' => $tiers, 'rewards' => DB::table('loyalty_rewards')->where('active', true)->get()->all(), 'tierMap' => collect($tiers)->keyBy('id')]);
    }

    public function member(int $id)
    {
        $u = User::where('role', 'customer')->findOrFail($id);
        Loyalty::ensureMember($u);
        $u = $u->fresh();
        $w = fn ($wallet) => DB::table('loyalty_ledger')->where('user_id', $id)->where('wallet', $wallet)->orderByDesc('id')->limit(40)->get()->all();
        $d = [
            'u' => $u, 'tier' => LoyaltyTiers::of($u), 'tiers' => LoyaltyTiers::all(false), 'points' => $w('points'), 'cash' => $w('cashback'), 'pending' => Loyalty::pending($u), 'credit' => DB::table('credit_log')->where('user_id', $id)->orderByDesc('id')->limit(15)->get()->all(),
            'thist' => DB::table('loyalty_tier_history')->where('user_id', $id)->orderByDesc('id')->limit(20)->get()->all(),
            'reds' => DB::table('loyalty_redemptions as d')->join('loyalty_rewards as w', 'w.id', '=', 'd.reward_id')->where('d.user_id', $id)->orderByDesc('d.id')->limit(20)->get(['d.*', 'w.name'])->all(),
            'refs' => DB::table('loyalty_referrals as r')->join('users as b', 'b.id', '=', 'r.referee_id')->where('r.referrer_id', $id)->orderByDesc('r.id')->get(['r.*', 'b.name as referee'])->all(),
            'chal' => DB::table('loyalty_progress as p')->join('loyalty_challenges as c', 'c.id', '=', 'p.challenge_id')->where('p.user_id', $id)->orderByDesc('p.updated_at')->limit(20)->get(['c.name', 'p.progress', 'c.target', 'p.completed_at'])->all(),
            'orders' => Order::where('user_id', $id)->orderByDesc('id')->limit(15)->get()->all(), 'metrics' => LoyaltyTiers::metrics($u), 'challenges' => DB::table('loyalty_challenges')->where('active', true)->get()->all(),
            'rewards' => DB::table('loyalty_rewards')->where('active', true)->get()->all(), 'badges' => DB::table('loyalty_badges as b')->join('loyalty_user_badges as ub', 'ub.badge_id', '=', 'b.id')->where('ub.user_id', $id)->get(['b.icon', 'b.name'])->all(),
            'audit' => DB::table('loyalty_audit')->where('subject', 'user')->where('subject_id', $id)->orderByDesc('id')->limit(15)->get()->all(), 'limit' => (int) setting('loyalty_staff_adjust_limit', 1000),
        ];
        title('Member · '.$u->name);

        return admin_page('loyalty_member', $d);
    }

    public function memberAction(Request $r, int $id, string $act)
    {
        $u = User::where('role', 'customer')->findOrFail($id);
        $admin = auth()->user()->isAdmin();
        $back = redirect('/admin/loyalty/members/'.$id);
        try {
            switch ($act) {
                case 'adjust':
                    $wallet = $r->input('wallet') === 'cashback' ? 'cashback' : 'points';
                    $amt = $wallet === 'cashback' ? to_cents($r->input('amount')) : (int) $r->input('amount');
                    if ($r->input('dir') === 'remove') $amt = -abs($amt);
                    if ($amt === 0) throw new \RuntimeException('Enter an amount.');
                    if (! $admin && $wallet === 'points' && abs($amt) > (int) setting('loyalty_staff_adjust_limit', 1000)) throw new \RuntimeException('Staff can adjust up to '.number_format((int) setting('loyalty_staff_adjust_limit', 1000)).' points. Ask an administrator.');
                    if (! $admin && $wallet === 'cashback') throw new \RuntimeException('Only administrators can adjust cashback.');
                    Loyalty::adjust($u, $wallet, $amt, (string) $r->input('reason'), $this->actor());
                    flash('success', 'Adjustment recorded in the ledger.');
                    break;
                case 'credit':
                    if (! $admin) throw new \RuntimeException('Only administrators can change store credit.');
                    $c = to_cents($r->input('amount'));
                    if ($c <= 0) throw new \RuntimeException('Enter an amount.');
                    \App\Services\StoreCredit::add($u, $r->input('dir') === 'remove' ? -$c : $c, trim((string) $r->input('reason')) ?: 'Manual adjustment', null, $this->actor());
                    Loyalty::audit('credit.adjust', 'user', $id, ['amount' => $c, 'dir' => $r->input('dir')]);
                    flash('success', 'Store credit updated.');
                    break;
                case 'tier':
                    $t = LoyaltyTiers::find((int) $r->input('tier_id'));
                    if (! $admin) throw new \RuntimeException('Only administrators can assign tiers.');
                    if ($r->input('tier_id') === 'auto') {
                        DB::table('users')->where('id', $id)->update(['tier_locked' => false]);
                        LoyaltyTiers::evaluate($u->fresh(), true);
                    } elseif ($t) {
                        LoyaltyTiers::set($u->fresh(), $t, 'Assigned by '.auth()->user()->name.': '.(trim((string) $r->input('reason')) ?: 'manual'), $this->actor(), true);
                    }
                    Loyalty::audit('tier.assign', 'user', $id, ['tier' => $r->input('tier_id')]);
                    flash('success', 'Tier updated.');
                    break;
                case 'status':
                    $s = $r->input('status') === 'suspended' ? 'suspended' : 'active';
                    DB::table('users')->where('id', $id)->update(['loyalty_status' => $s]);
                    Loyalty::audit('account.'.$s, 'user', $id, ['reason' => (string) $r->input('reason')]);
                    flash('success', 'Loyalty account '.($s === 'active' ? 'reactivated' : 'suspended').'.');
                    break;
                case 'reward':
                    $rw = DB::table('loyalty_rewards')->where('id', (int) $r->input('reward_id'))->first();
                    if (! $rw) throw new \RuntimeException('Pick a reward.');
                    $rid = DB::table('loyalty_redemptions')->insertGetId(['user_id' => $id, 'reward_id' => $rw->id, 'points' => 0, 'status' => 'issued', 'reference' => 'admin:'.\Illuminate\Support\Str::uuid(), 'note' => 'Issued by '.$this->actor(), 'created_at' => now(), 'updated_at' => now()]);
                    LoyaltyRewards::fulfil($rid);
                    Loyalty::audit('reward.issue', 'user', $id, ['reward' => $rw->id]);
                    Loyalty::notify($u, 'rewards', 'A reward for you', '"'.$rw->name.'" was added to your rewards.', '/account/loyalty?tab=rewards');
                    flash('success', 'Reward issued (no points deducted).');
                    break;
                case 'voucher':
                    $val = (int) $r->input('value');
                    $pct = $r->input('type') === 'percent';
                    if ($val <= 0) throw new \RuntimeException('Enter a value.');
                    $v = Loyalty::makeVoucher($u, 'admin', $pct ? 'percent' : 'fixed', $pct ? min(100, $val) : to_cents($r->input('value')), ['prefix' => 'LA', 'ends_at' => now()->addDays(max(1, (int) $r->input('days', 30))), 'note' => 'Issued by admin']);
                    Loyalty::notify($u, 'promo', 'A voucher for you', 'Use code '.$v->code.' at checkout.', '/cart');
                    Loyalty::audit('voucher.issue', 'user', $id, ['code' => $v->code]);
                    flash('success', 'Voucher '.$v->code.' issued.');
                    break;
                case 'challenge':
                    if (! LoyaltyEngagement::manualComplete($u, (int) $r->input('challenge_id'), $this->actor())) throw new \RuntimeException('Already completed for this period.');
                    flash('success', 'Challenge reward awarded.');
                    break;
                case 'activity':
                    $ok = LoyaltyEngagement::activity($u, (string) $r->input('type'), 'admin-'.($r->input('ref') ?: now()->timestamp), null, $this->actor());
                    Loyalty::audit('activity.award', 'user', $id, ['type' => $r->input('type')]);
                    flash($ok ? 'success' : 'info', $ok ? 'Activity points awarded.' : 'Nothing awarded (limit reached or amount is 0).');
                    break;
                case 'profile':
                    if (! $admin) throw new \RuntimeException('Only administrators can edit this.');
                    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $r->input('referral_code')));
                    $upd = ['ambassador' => $r->boolean('ambassador'), 'ambassador_pct' => max(0, min(50, (float) $r->input('ambassador_pct'))), 'birthday' => $r->filled('birthday') ? Carbon::parse((string) $r->input('birthday'))->toDateString() : null];
                    if ($code !== '' && $code !== $u->referral_code) {
                        if (User::where('referral_code', $code)->where('id', '<>', $id)->exists()) throw new \RuntimeException('That referral code is taken.');
                        $upd['referral_code'] = substr($code, 0, 24);
                    }
                    DB::table('users')->where('id', $id)->update($upd);
                    Loyalty::audit('profile.save', 'user', $id, $upd);
                    flash('success', 'Profile saved.');
                    break;
            }
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
        }

        return $back;
    }

    public function bulk(Request $r)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $r->input('ids')))));
        if (! $ids && $r->filled('segment')) {
            $key = (string) $r->input('segment');
            $ids = LoyaltyReports::segment($key === 'custom' ? 'custom' : $key, (array) json_decode((string) $r->input('rules'), true))->pluck('id')->all();
        }
        $act = (string) $r->input('act');
        if (! $ids) {
            flash('error', 'Select at least one customer.');

            return back();
        }
        $n = 0;
        $batch = 'bulk:'.\Illuminate\Support\Str::uuid();
        try {
            foreach (User::whereIn('id', $ids)->where('role', 'customer')->get() as $u) {
                if ($act === 'award' || $act === 'deduct') {
                    $amt = (int) $r->input('amount');
                    if ($amt <= 0) throw new \RuntimeException('Enter an amount above zero.');
                    $reason = trim((string) $r->input('reason'));
                    if ($reason === '') throw new \RuntimeException('A reason is required.');
                    Loyalty::post($u, 'points', 'adjust', $act === 'award' ? $amt : -$amt, ['reference' => $batch.':'.$u->id, 'reason' => $reason, 'actor' => $this->actor(), 'expires_at' => 'auto', 'allow_negative' => false]);
                    if ($act === 'award') Loyalty::notify($u, 'points', 'Bonus points', number_format($amt).' points — '.$reason, '/account/loyalty?tab=wallet');
                    $n++;
                } elseif ($act === 'reward') {
                    $rw = DB::table('loyalty_rewards')->where('id', (int) $r->input('reward_id'))->first();
                    if (! $rw) throw new \RuntimeException('Pick a reward.');
                    $rid = DB::table('loyalty_redemptions')->insertGetId(['user_id' => $u->id, 'reward_id' => $rw->id, 'points' => 0, 'status' => 'issued', 'reference' => $batch.':'.$u->id, 'note' => 'Bulk by '.$this->actor(), 'created_at' => now(), 'updated_at' => now()]);
                    LoyaltyRewards::fulfil($rid);
                    Loyalty::notify($u, 'rewards', 'A reward for you', '"'.$rw->name.'" was added to your rewards.', '/account/loyalty?tab=rewards');
                    $n++;
                }
            }
        } catch (\RuntimeException $e) {
            flash('error', ($n ? $n.' done, then: ' : '').$e->getMessage());

            return back();
        }
        Loyalty::audit('bulk.'.$act, null, null, ['count' => $n, 'amount' => $r->input('amount'), 'batch' => $batch]);
        flash('success', 'Done for '.$n.' customer(s).');

        return back();
    }

    // ------------------------------------------------------------------ referrals
    public function referrals(Request $r)
    {
        $st = (string) $r->query('status');
        $rows = DB::table('loyalty_referrals as r')->join('users as a', 'a.id', '=', 'r.referrer_id')->join('users as b', 'b.id', '=', 'r.referee_id')
            ->when($st !== '', fn ($q) => $q->where('r.status', $st))->orderByDesc('r.id')->paginate(25, ['r.*', 'a.name as referrer', 'a.email as referrer_email', 'b.name as referee', 'b.email as referee_email'])->withQueryString();
        $stats = ['total' => DB::table('loyalty_referrals')->count(), 'rewarded' => DB::table('loyalty_referrals')->where('status', 'rewarded')->count(), 'pending' => DB::table('loyalty_referrals')->whereIn('status', ['registered', 'qualified'])->count(),
            'flagged' => DB::table('loyalty_referrals')->where('flagged', true)->count(), 'awaiting' => DB::table('loyalty_referrals')->where('status', 'qualified')->count()];
        $stats['conv'] = $stats['total'] ? (int) round(DB::table('loyalty_referrals')->whereIn('status', ['qualified', 'rewarded'])->count() / $stats['total'] * 100) : 0;
        $top = DB::table('loyalty_referrals as r')->join('users as a', 'a.id', '=', 'r.referrer_id')->selectRaw('a.id, a.name, a.ambassador, count(*) c, sum(case when r.status = ? then 1 else 0 end) ok', ['rewarded'])->groupBy('a.id', 'a.name', 'a.ambassador')->orderByDesc('ok')->limit(8)->get();
        title('Referrals');

        return admin_page('loyalty_referrals', ['rows' => $rows->items(), 'page' => $rows->currentPage(), 'pages' => $rows->lastPage(), 'st' => $st, 'stats' => $stats, 'top' => $top]);
    }

    public function referralAction(Request $r, int $id, string $act)
    {
        $ref = DB::table('loyalty_referrals')->where('id', $id)->first();
        abort_unless($ref, 404);
        if ($act === 'approve') {
            if ($ref->status === 'registered') DB::table('loyalty_referrals')->where('id', $id)->update(['status' => 'qualified']);
            $ok = LoyaltyEngagement::rewardReferral($id, $this->actor());
            flash($ok ? 'success' : 'error', $ok ? 'Referral reward released.' : 'This referral cannot be approved (already rewarded or rejected).');
        } elseif ($act === 'reject') {
            DB::table('loyalty_referrals')->where('id', $id)->whereIn('status', ['registered', 'qualified'])->update(['status' => 'rejected', 'note' => mb_substr((string) $r->input('note', 'Rejected by admin'), 0, 190), 'updated_at' => now()]);
            flash('success', 'Referral rejected.');
        }
        Loyalty::audit('referral.'.$act, 'referral', $id);

        return back();
    }

    // ------------------------------------------------------------------ segments
    public function segments(Request $r)
    {
        $key = (string) $r->query('segment');
        $saved = DB::table('loyalty_segments')->get();
        $rules = [];
        if (str_starts_with($key, 'saved:')) {
            $sg = $saved->firstWhere('id', (int) substr($key, 6));
            $rules = $sg ? (json_decode((string) $sg->rules, true) ?: []) : [];
            $run = 'custom';
        } elseif ($key === 'custom') {
            $rules = $r->only(['tier_id', 'min_points', 'max_points', 'min_spend', 'min_orders', 'inactive_days', 'active_days']);
            $run = 'custom';
        } else {
            $run = isset(LoyaltyReports::SEGMENTS[$key]) ? $key : '';
        }
        $members = $run ? LoyaltyReports::segment($run, $rules) : collect();
        if ($run && in_array($r->query('export'), ['csv', 'xls'], true)) {
            $rows = $members->map(fn ($u) => [$u->loyalty_id, $u->name, $u->email, $u->points, number_format($u->spend / 100, 2), $u->orders_n, $u->last_order])->all();
            $cols = ['Loyalty ID', 'Name', 'Email', 'Points', 'Spend', 'Orders', 'Last order'];

            return $r->query('export') === 'xls' ? LoyaltyReports::xls('segment', $cols, $rows) : LoyaltyReports::csv('segment', $cols, $rows);
        }
        $counts = [];
        foreach (array_keys(LoyaltyReports::SEGMENTS) as $k) $counts[$k] = LoyaltyReports::segment($k)->count();
        title('Customer segments');

        return admin_page('loyalty_segments', ['key' => $key, 'run' => $run, 'members' => $members->take(200), 'counts' => $counts, 'saved' => $saved, 'rules' => $rules, 'tiers' => LoyaltyTiers::all(false), 'total' => $members->count(), 'rewards' => DB::table('loyalty_rewards')->where('active', true)->get()->all()]);
    }

    public function segmentSave(Request $r)
    {
        $rules = array_filter($r->only(['tier_id', 'min_points', 'max_points', 'min_spend', 'min_orders', 'inactive_days', 'active_days']), fn ($v) => $v !== null && $v !== '');
        $id = DB::table('loyalty_segments')->insertGetId(['name' => mb_substr(trim((string) $r->input('name')) ?: 'Segment', 0, 100), 'rules' => json_encode($rules), 'created_at' => now(), 'updated_at' => now()]);
        Loyalty::audit('segment.save', 'segment', $id);
        flash('success', 'Segment saved.');

        return redirect('/admin/loyalty/segments?segment=saved:'.$id);
    }

    public function segmentDelete(int $id)
    {
        DB::table('loyalty_segments')->where('id', $id)->delete();

        return redirect('/admin/loyalty/segments');
    }

    // ------------------------------------------------------------------ redemptions (approval queue), security
    public function redemptions(Request $r)
    {
        $st = (string) $r->query('status', 'pending');
        $rows = DB::table('loyalty_redemptions as d')->join('loyalty_rewards as w', 'w.id', '=', 'd.reward_id')->join('users as u', 'u.id', '=', 'd.user_id')->when($st !== 'all', fn ($q) => $q->where('d.status', $st))->orderByDesc('d.id')->limit(100)->get(['d.*', 'w.name as reward', 'u.name as customer', 'u.email'])->all();
        title('Redeemed rewards');

        return admin_page('loyalty_redemptions', ['rows' => $rows, 'st' => $st]);
    }

    public function redemptionAction(int $id, string $act)
    {
        $ok = match ($act) { 'approve' => (function () use ($id) { LoyaltyRewards::approve($id); return true; })(), 'reject' => LoyaltyRewards::cancel($id, $this->actor(), true), default => LoyaltyRewards::cancel($id, $this->actor()) };
        flash($ok ? 'success' : 'error', $ok ? 'Done.' : 'This redemption cannot be changed (already used?).');

        return back();
    }

    public function security(Request $r)
    {
        $flags = DB::table('loyalty_flags as f')->leftJoin('users as u', 'u.id', '=', 'f.user_id')->when($r->query('status', 'open') !== 'all', fn ($q) => $q->where('f.status', (string) $r->query('status', 'open')))->orderByDesc('f.id')->limit(100)->get(['f.*', 'u.name', 'u.loyalty_status'])->all();
        $audit = DB::table('loyalty_audit')->orderByDesc('id')->limit(80)->get()->all();
        $dups = DB::table('users')->selectRaw('phone, count(*) c')->whereNotNull('phone')->where('phone', '<>', '')->where('role', 'customer')->groupBy('phone')->havingRaw('count(*) > 1')->limit(20)->get();
        title('Security & fraud');

        return admin_page('loyalty_security', ['flags' => $flags, 'audit' => $audit, 'dups' => $dups, 'st' => (string) $r->query('status', 'open')]);
    }

    public function flagAction(Request $r, int $id)
    {
        $f = DB::table('loyalty_flags')->where('id', $id)->first();
        abort_unless($f, 404);
        if ($r->input('act') === 'suspend' && $f->user_id) {
            DB::table('users')->where('id', $f->user_id)->update(['loyalty_status' => 'suspended']);
            Loyalty::audit('account.suspended', 'user', (int) $f->user_id, ['flag' => $id]);
        }
        DB::table('loyalty_flags')->where('id', $id)->update(['status' => 'resolved']);
        flash('success', 'Flag resolved.');

        return back();
    }

    public function runNow()
    {
        $r = LoyaltyEngagement::tick(true);
        flash('success', 'Maintenance run: '.json_encode($r));

        return back();
    }
}
