<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Mailer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Customers extends Controller
{
    public function index(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $p = User::where('role', 'customer')
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('email', 'like', "%$q%")->orWhere('phone', 'like', "%$q%")))
            ->withCount('orders as orders_n')
            ->withSum(['orders as spent' => fn ($o) => $o->where('payment_status', 'paid')], 'total')
            ->orderByDesc('id')->paginate(25)->withQueryString();
        $rows = $p->items();
        $page = $p->currentPage();
        $pages = $p->lastPage();
        $total = $p->total();
        title('Customers');

        return admin_page('customers', compact('rows', 'q', 'page', 'pages', 'total'));
    }

    /** Add a customer straight from the backend (phone / in-store / manual orders). */
    public function store(Request $r)
    {
        $name = trim((string) $r->input('name'));
        $email = mb_strtolower(trim((string) $r->input('email')));
        $err = null;
        if (mb_strlen($name) < 2) $err = 'Enter the customer name.';
        elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Enter a valid email address.';
        elseif (User::where('email', $email)->exists()) $err = 'A user with that email already exists.';
        if ($err) {
            keep_old($r->all());
            flash('error', $err);

            return redirect('/admin/customers?add=1');
        }
        $pass = trim((string) $r->input('password')) ?: Str::password(12, true, true, false);
        $u = User::create(['name' => $name, 'email' => $email, 'password' => $pass, 'phone' => trim((string) $r->input('phone')), 'role' => 'customer', 'status' => 'active']);
        Order::where('email', $email)->whereNull('user_id')->update(['user_id' => $u->id]);
        if ($r->boolean('subscribe')) \App\Support\Marketing::subscribe($email, $name, 'admin');
        $msg = "Customer {$u->name} added.";
        if ($r->boolean('send_email')) {
            $inner = '<p style="color:#cfcfd6">An account was created for you at <b>'.e(setting('store_name')).'</b>.</p><p style="color:#cfcfd6">Email: <b>'.e($email).'</b><br>Temporary password: <b>'.e($pass).'</b></p><p><a style="color:'.e(setting('accent')).'" href="'.e(url('login')).'">Sign in</a> and change your password from your account page.</p>';
            $msg .= Mailer::send($email, 'Your '.setting('store_name').' account', Mailer::wrap('Your account is ready', $inner)) ? ' Login details emailed.' : ' (Email could not be sent.)';
        } elseif (! trim((string) $r->input('password'))) {
            $msg .= " Generated password: $pass (shown once).";
        }
        flash('success', $msg);

        return redirect('/admin/customers');
    }

    public function bulk(Request $r)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $r->input('ids')))));
        $act = (string) $r->input('action');
        if (! $ids) {
            flash('error', 'Select at least one customer.');

            return redirect()->back();
        }
        $q = User::where('role', 'customer')->whereIn('id', $ids);
        $n = (clone $q)->count();
        if ($act === 'delete') {
            $real = (clone $q)->pluck('id');
            Order::whereIn('user_id', $real)->update(['user_id' => null]);
            SocialAccount::whereIn('user_id', $real)->delete();
            $q->delete();
            flash('success', "$n customer(s) deleted. Their orders were kept as guest orders.");
        } elseif (in_array($act, ['suspend', 'activate'], true)) {
            $q->update(['status' => $act === 'suspend' ? 'suspended' : 'active']);
            flash('success', "$n customer(s) ".($act === 'suspend' ? 'suspended' : 'activated').'.');
        } elseif ($act === 'subscribe') {
            foreach ($q->get() as $u) \App\Support\Marketing::subscribe($u->email, $u->name, 'admin');
            flash('success', "$n customer(s) added to the mailing list.");
        } else {
            flash('error', 'Choose an action.');
        }

        return redirect()->back();
    }
}
