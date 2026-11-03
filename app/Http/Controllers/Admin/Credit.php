<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreditLog;
use App\Models\User;
use App\Services\StoreCredit;
use App\Support\Mailer;
use App\Support\Settings;
use Illuminate\Http\Request;

/** Store credit wallets: balances, manual adjustments and ledger. */
class Credit extends Controller
{
    public function index(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $only = $r->boolean('with');
        $p = User::where('role', 'customer')
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('email', 'like', "%$q%")))
            ->when($only, fn ($x) => $x->where('credit', '>', 0))
            ->orderByDesc('credit')->orderByDesc('id')->paginate(25)->withQueryString();
        $rows = $p->items();
        $page = $p->currentPage();
        $pages = $p->lastPage();
        $stats = ['outstanding' => (int) User::sum('credit'), 'holders' => User::where('credit', '>', 0)->count(), 'granted30' => (int) CreditLog::where('amount', '>', 0)->where('created_at', '>=', now()->subDays(30))->sum('amount'),
            'spent30' => (int) abs(CreditLog::where('amount', '<', 0)->where('created_at', '>=', now()->subDays(30))->sum('amount'))];
        $recent = CreditLog::orderByDesc('id')->limit(12)->get()->all();
        $who = User::whereIn('id', array_column($recent, 'user_id'))->pluck('name', 'id')->all();
        $pick = $r->query('user') ? User::find((int) $r->query('user')) : null;
        title('Store credit');

        return admin_page('credit', compact('rows', 'page', 'pages', 'q', 'only', 'stats', 'recent', 'who', 'pick'));
    }

    public function show(int $id)
    {
        $u = User::findOrFail($id);
        $log = CreditLog::where('user_id', $id)->orderByDesc('id')->limit(100)->get()->all();
        title('Store credit · '.$u->name);

        return admin_page('credit_show', compact('u', 'log'));
    }

    public function adjust(Request $r)
    {
        $u = User::where('role', 'customer')->where(fn ($q) => $q->where('id', (int) $r->input('user_id'))->orWhere('email', mb_strtolower(trim((string) $r->input('email')))))->first();
        $amount = to_cents($r->input('amount'));
        $back = $r->input('back') === 'user' && $u ? '/admin/credit/'.$u->id : '/admin/credit';
        if (! $u || $amount <= 0) {
            flash('error', ! $u ? 'Customer not found — use the customer\'s account email.' : 'Enter an amount above zero.');
            keep_old($r->all());

            return redirect('/admin/credit?new=1');
        }
        $remove = $r->input('dir') === 'remove';
        $reason = trim((string) $r->input('reason')) ?: ($remove ? 'Manual deduction' : 'Store credit added');
        $bal = StoreCredit::add($u, $remove ? -$amount : $amount, $reason, null, 'admin:'.auth()->user()->name);
        if (! $remove && $r->boolean('notify')) {
            $inner = '<p style="color:#cfcfd6;line-height:1.6">Hi '.e(explode(' ', $u->name)[0]).', we added <b style="color:#c6ff3d">'.money($amount).'</b> of store credit to your account'.($reason ? ' ('.e($reason).')' : '').'.</p>'
                .'<p style="color:#cfcfd6">Your balance is now <b>'.money($bal).'</b>. It is applied at checkout — just tick “use my store credit”.</p>'
                .'<p style="margin-top:20px"><a style="display:inline-block;background:#c6ff3d;color:#0b0b0d;padding:12px 22px;border-radius:99px;font-weight:700;text-decoration:none" href="'.e(url('/')).'">Start shopping</a></p>';
            Mailer::send($u->email, 'You received store credit', Mailer::wrap('Store credit added', $inner));
        }
        flash('success', ($remove ? 'Removed ' : 'Added ').money($amount).' — '.$u->name.' now has '.money($bal).'.');

        return redirect($back);
    }

    public function settings(Request $r)
    {
        Settings::set('credit_on', $r->boolean('credit_on') ? '1' : '0');
        flash('success', 'Store credit settings saved.');

        return redirect('/admin/credit');
    }
}
