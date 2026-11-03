<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Services\Cart;
use App\Services\Orders;
use App\Services\Payments;
use App\Support\Meta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Checkout extends Controller
{
    public function show()
    {
        $sum = Cart::summary();
        if (! $sum['lines']) return redirect('/cart');
        if (setting('guest_checkout') !== '1' && ! Auth::check()) {
            flash('info', 'Please sign in or create an account to check out.');
            session(['url.intended' => url('checkout')]);

            return redirect('/login');
        }
        $methods = Payments::methods($sum['has_physical']);
        $u = Auth::user();
        title('Checkout');
        Meta::$data['step'] = 2;
        $last = $u ? \App\Models\Order::where('user_id', $u->id)->where('has_physical', true)->orderByDesc('id')->first() : null;
        $pids = array_map(fn ($l) => (int) $l['p']->id, array_filter($sum['lines'], fn ($l) => empty($l['gc'])));
        $cats = array_map(fn ($l) => (int) $l['p']->category_id, array_filter($sum['lines'], fn ($l) => empty($l['gc'])));
        $bumps = \App\Services\Offers::forPlacement('checkout', $pids, $cats);
        \App\Services\Offers::seen($bumps);

        return page('checkout', compact('sum', 'methods', 'u', 'last', 'bumps'));
    }

    /** Live updates from the checkout page: coupon, gift card, store credit and delivery method. Returns the refreshed summary. */
    public function apply(Request $r)
    {
        $act = (string) $r->input('action');
        $err = null;
        $ok = true;
        switch ($act) {
            case 'coupon':
                $code = trim((string) $r->input('code'));
                Cart::setCoupon($code ?: null);
                $sum = Cart::summary();
                if ($code && $sum['coupon_error']) { $err = $sum['coupon_error']; $ok = false; }
                break;
            case 'gift':
                $code = trim((string) $r->input('code'));
                if ($code === '') { Cart::setGiftCard(null); break; }
                [$g, $e] = \App\Services\GiftCards::check($code);
                if (! $g) { $err = $e; $ok = false; break; }
                Cart::setGiftCard($g->code);
                break;
            case 'credit':
                Cart::setCredit(filter_var($r->input('on'), FILTER_VALIDATE_BOOLEAN));
                break;
            case 'ship':
                Cart::setShip((string) $r->input('id'));
                break;
        }
        $sum = Cart::summary();
        $methods = Payments::methods($sum['has_physical']);

        return response()->json(['ok' => $ok, 'error' => $err, 'html' => partial('partials/checkout_summary', compact('sum')), 'due' => money($sum['due']), 'free' => $sum['due'] <= 0,
            'methods' => array_keys($methods), 'cart_count' => $sum['count']]);
    }

    /** Remember the email typed at checkout so an abandoned cart can be followed up. */
    public function capture(Request $r)
    {
        \App\Support\Marketing::rememberEmail((string) $r->input('email'));

        return response()->json(['ok' => true]);
    }

    public function place(Request $r)
    {
        if ($r->filled('ship')) Cart::setShip((string) $r->input('ship'));
        $sum = Cart::summary();
        if (! $sum['lines'] || $sum['has_sold_out']) {
            flash('error', 'Some items in your cart are no longer available.');

            return redirect('/cart');
        }
        $u = Auth::user();
        $methods = Payments::methods($sum['has_physical']);
        $s = fn ($k) => trim((string) $r->input($k));
        $d = [
            'email' => $s('email'), 'name' => $s('name'), 'phone' => $s('phone'),
            'ship_name' => $s('ship_name') ?: $s('name'), 'ship_address' => $s('ship_address'), 'ship_address2' => $s('ship_address2'),
            'ship_city' => $s('ship_city'), 'ship_state' => $s('ship_state'), 'ship_zip' => $s('ship_zip'), 'ship_country' => $s('ship_country'),
            'notes' => mb_substr($s('notes'), 0, 1000),
        ];
        $method = $s('method');
        $free = $sum['due'] <= 0;
        if ($free) $method = $sum['total'] <= 0 ? 'free' : 'balance';
        $err = [];
        if (! filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Enter a valid email address.';
        if (mb_strlen($d['name']) < 2) $err[] = 'Enter your name.';
        if ($sum['has_physical']) {
            foreach (['ship_address' => 'street address', 'ship_city' => 'city', 'ship_zip' => 'postal code', 'ship_country' => 'country'] as $k => $l) {
                if ($d[$k] === '') $err[] = 'Enter your '.$l.'.';
            }
        }
        if (! $free && ! isset($methods[$method])) $err[] = 'Choose a payment method.';
        if (! $err && ! empty($sum['coupon']) && ! $sum['coupon_auto']) {
            [$okc, $cerr] = \App\Services\Coupons::validate($sum['coupon'], $sum['subtotal'], array_values(array_filter($sum['lines'], fn ($x) => ! $x['sold_out'])), $d['email'], $u?->id);
            if (! $okc) $err[] = $cerr;
        }
        // account: legacy checkbox (create_account=1 + password) or the new account_mode (password | code)
        $mode = (string) $r->input('account_mode');
        if ($mode === '' && $r->input('create_account') === '1') $mode = 'password';
        $create = $mode !== '' && $mode !== 'guest' && ! $u;
        $pass = (string) $r->input('password', '');
        if ($create) {
            if ($mode === 'password' && strlen($pass) < 8) $err[] = 'Password must be at least 8 characters.';
            if (User::where('email', mb_strtolower($d['email']))->exists()) $err[] = 'An account with that email exists. Please sign in.';
        }
        if ($err) {
            keep_old($r->all());
            flash('error', implode(' ', $err));

            return redirect('/checkout');
        }
        if ($create) {
            if ($mode !== 'password') $pass = \Illuminate\Support\Str::random(40); // passwordless: sign in with an email code
            $u = User::create(['name' => $d['name'], 'email' => mb_strtolower($d['email']), 'password' => $pass, 'role' => 'customer', 'phone' => $d['phone'], 'status' => 'active']);
            Auth::login($u);
            $r->session()->regenerate();
            \App\Support\Triggers::fire('customer.registered', ['email' => $u->email, 'name' => $u->name, 'user' => $u, 'ref' => 'u'.$u->id]);
            // the account was just created: re-price the cart for a signed-in customer (store credit etc. is empty, so totals are unchanged)
        }
        $d['user_id'] = $u?->id;
        try {
            $order = Orders::create($d, $sum, $method);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());

            return redirect('/cart');
        }
        \App\Support\Marketing::orderPlaced($order);
        Cart::clear();
        if ($r->boolean('subscribe')) \App\Support\Marketing::subscribe($order->email, $order->name, 'checkout');
        else \App\Support\Marketing::subscribe($order->email, $order->name, 'auto');

        if ($order->due() <= 0) {
            Orders::markPaid($order, $order->total <= 0 ? 'free' : 'balance');

            return redirect($order->publicUrl());
        }
        try {
            $go = Payments::start($order);
        } catch (\Throwable $e) {
            Orders::event($order, 'Payment start failed: '.$e->getMessage());
            flash('error', 'We could not reach the payment provider. Your order is saved — try paying again below.');

            return redirect($order->publicUrl());
        }
        if ($go) return redirect()->away($go);

        Orders::sendPending($order);

        return redirect($order->publicUrl());
    }

    private function access(Request $r, string $number): Order
    {
        $o = Order::where('number', $number)->firstOrFail();
        $tok = (string) $r->query('t');
        $u = Auth::user();
        $ok = ($tok !== '' && hash_equals($o->token, $tok)) || ($u && ($o->user_id === $u->id || $u->isStaff()));

        return $ok ? $o : abort(404);
    }

    public function order(Request $r, string $number)
    {
        $o = $this->access($r, $number);
        $items = $o->items()->get()->all();
        title('Order '.$o->number);
        Meta::$data['step'] = ($o->isPaid() || $o->payment_method !== 'stripe') ? 3 : 2;

        return page('order', compact('o', 'items'));
    }

    public function repay(Request $r, string $number)
    {
        $o = $this->access($r, $number);
        if ($o->isPaid() || ! in_array($o->payment_method, ['stripe', 'paypal'], true) || in_array($o->status, ['cancelled', 'refunded'], true)) {
            return redirect($o->publicUrl());
        }
        try {
            return redirect()->away(Payments::start($o));
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());

            return redirect($o->publicUrl());
        }
    }

    public function stripeReturn(Request $r)
    {
        $o = $this->access($r, (string) $r->query('order'));
        if (! $o->isPaid()) {
            try {
                if (! Payments::stripeVerify($o, (string) $r->query('sid'))) flash('info', 'Your payment is still being confirmed. This page will update shortly.');
            } catch (\Throwable) {
                flash('error', 'Could not verify the payment yet. If you were charged, your order will update automatically.');
            }
        }

        return redirect($o->publicUrl());
    }

    public function stripeWebhook(Request $r)
    {
        $ok = Payments::stripeWebhook($r->getContent(), (string) $r->header('Stripe-Signature'));

        return response($ok ? 'ok' : 'invalid', $ok ? 200 : 400);
    }

    public function paypalReturn(Request $r)
    {
        $o = $this->access($r, (string) $r->query('order'));
        if (! $o->isPaid()) {
            try {
                if (! Payments::paypalCapture($o, (string) $r->query('token'))) flash('error', 'PayPal could not confirm this payment.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
        }

        return redirect($o->publicUrl());
    }

    public function cancel(Request $r)
    {
        $o = $this->access($r, (string) $r->query('order'));
        flash('info', 'Payment was cancelled. Your order is saved — you can pay again any time.');

        return redirect($o->publicUrl());
    }

    public function download(Request $r, int $id)
    {
        $it = OrderItem::with('order')->findOrFail($id);
        $o = $it->order;
        $tok = (string) $r->query('t');
        $u = Auth::user();
        if ($it->type !== 'file' || ! $o->isPaid() || in_array($o->status, ['cancelled', 'refunded'], true)) abort(404);
        if (! (($tok !== '' && hash_equals($o->token, $tok)) || ($u && $u->id === $o->user_id))) abort(404);
        $name = basename((string) $it->file_path);
        $path = storage_path('app/private-files/'.$name);
        if (! $name || ! is_file($path)) abort(404, 'File missing. Please contact support.');

        return response()->download($path, preg_replace('/^[a-f0-9]{20}_/', '', $name));
    }
}
