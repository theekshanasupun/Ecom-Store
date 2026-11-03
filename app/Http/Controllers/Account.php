<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class Account extends Controller
{
    private function methods(): array
    {
        $pw = setting('login_password_on', '1') === '1';
        $otp = setting('login_otp_on', '1') === '1';
        if (! $pw && ! $otp) $pw = true;

        return ['password' => $pw, 'otp' => $otp];
    }

    public function login()
    {
        if (Auth::check()) return redirect('/account');
        title('Sign in');
        $m = $this->methods();
        $otp = ['email' => session('otp_email'), 'name' => session('otp_name'), 'wait' => session('otp_email') ? \App\Services\LoginCodes::wait((string) session('otp_email')) : 0];

        return page('account/login', ['methods' => $m, 'otp' => $otp, 'tab' => (session('otp_email') || ! $m['password']) ? 'code' : 'password']);
    }

    private function finishLogin(Request $r, User $u, bool $remember = false)
    {
        Auth::login($u, $remember);
        $r->session()->regenerate();
        $u->update(['last_login_at' => now()]);
        Order::where('email', $u->email)->whereNull('user_id')->update(['user_id' => $u->id]);
        \App\Support\Marketing::trackCart();

        return redirect()->intended('/account');
    }

    public function loginPost(Request $r)
    {
        if (! $this->methods()['password']) return redirect('/login');
        $key = 'login:'.$r->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) {
            flash('error', 'Too many attempts. Try again in a few minutes.');

            return redirect('/login');
        }
        $email = mb_strtolower(trim((string) $r->input('email')));
        if (Auth::attempt(['email' => $email, 'password' => (string) $r->input('password'), 'status' => 'active'], $r->boolean('remember'))) {
            RateLimiter::clear($key);
            $r->session()->regenerate();
            Auth::user()->update(['last_login_at' => now()]);
            \App\Support\Marketing::trackCart();

            return redirect()->intended('/account');
        }
        RateLimiter::hit($key, 900);
        keep_old(['email' => $email]);
        flash('error', 'Wrong email or password.');

        return redirect('/login');
    }

    /** Step 1 of email-code sign in / sign up: send the code. */
    public function loginCode(Request $r)
    {
        if (! $this->methods()['otp']) return redirect('/login');
        $email = mb_strtolower(trim((string) $r->input('email')));
        $name = mb_substr(trim((string) $r->input('name')), 0, 150);
        $back = $r->input('from') === 'register' ? '/register' : '/login';
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid email address.');

            return redirect($back);
        }
        $u = User::where('email', $email)->first();
        session(['otp_email' => $email, 'otp_name' => $name ?: null]);
        // staff must use their password (unless the owner allowed codes for staff); same message either way so accounts are not revealed
        $blocked = $u && ($u->status !== 'active' || ($u->isStaff() && setting('login_otp_staff', '0') !== '1'));
        if (! $blocked && ! \App\Services\LoginCodes::issue($email)) {
            if (\App\Services\LoginCodes::wait($email) === 0) { session()->forget(['otp_email', 'otp_name']); }
            flash('error', \App\Services\LoginCodes::$error);

            return redirect($back);
        }
        flash('success', 'If that address can receive codes, a 6-digit code is on its way. It expires in '.\App\Services\LoginCodes::TTL_MIN.' minutes.');

        return redirect('/login?tab=code');
    }

    /** Step 2: verify the code (creates the account for new emails). */
    public function loginVerify(Request $r)
    {
        $email = (string) session('otp_email');
        if ($email === '') return redirect('/login');
        if (! \App\Services\LoginCodes::verify($email, (string) $r->input('code'))) {
            flash('error', 'That code is wrong or has expired. Check the email, or request a new code.');

            return redirect('/login?tab=code');
        }
        $u = User::where('email', $email)->first();
        if ($u && ($u->status !== 'active' || ($u->isStaff() && setting('login_otp_staff', '0') !== '1'))) {
            flash('error', 'This account must sign in with its password.');
            session()->forget(['otp_email', 'otp_name']);

            return redirect('/login');
        }
        $new = false;
        if (! $u) {
            $name = trim((string) $r->input('name')) ?: (string) session('otp_name');
            $name = mb_substr($name !== '' ? $name : ucfirst(explode('@', $email)[0]), 0, 150);
            $u = User::create(['name' => $name, 'email' => $email, 'password' => \Illuminate\Support\Str::random(40), 'has_password' => false, 'role' => 'customer', 'status' => 'active']);
            $new = true;
            \App\Support\Marketing::subscribe($email, $name, 'auto');
            \App\Support\Triggers::fire('customer.registered', ['email' => $email, 'name' => $name, 'user' => $u, 'ref' => 'u'.$u->id]);
        }
        if (! $u->email_verified_at) {
            // an unverified account that already had a password may have been pre-registered by someone else: neutralise that password
            if ($u->has_password) $u->update(['password' => \Illuminate\Support\Str::random(40), 'has_password' => false, 'remember_token' => \Illuminate\Support\Str::random(60)]);
            $u->update(['email_verified_at' => now()]);
        }
        session()->forget(['otp_email', 'otp_name']);
        if ($new) flash('success', 'Welcome aboard, '.explode(' ', $u->name)[0].'! Your account is ready.');

        return $this->finishLogin($r, $u);
    }

    public function register()
    {
        if (Auth::check()) return redirect('/account');
        title('Create account');

        return page('account/register', ['methods' => $this->methods()]);
    }

    public function registerPost(Request $r)
    {
        $m = $this->methods();
        if ($r->input('method') === 'code' && $m['otp']) return $this->loginCode($r->merge(['from' => 'register']));
        if (! $m['password']) return redirect('/register');
        $name = trim((string) $r->input('name'));
        $email = mb_strtolower(trim((string) $r->input('email')));
        $pass = (string) $r->input('password');
        $err = null;
        if (mb_strlen($name) < 2) $err = 'Enter your name.';
        elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Enter a valid email.';
        elseif (strlen($pass) < 8) $err = 'Password must be at least 8 characters.';
        elseif (User::where('email', $email)->exists()) $err = 'An account with that email already exists.';
        if ($err) {
            keep_old(['name' => $name, 'email' => $email]);
            flash('error', $err);

            return redirect('/register');
        }
        $u = User::create(['name' => $name, 'email' => $email, 'password' => $pass, 'role' => 'customer', 'status' => 'active']);
        Order::where('email', $email)->whereNull('user_id')->update(['user_id' => $u->id]);
        Auth::login($u);
        $r->session()->regenerate();
        \App\Support\Marketing::subscribe($email, $name, 'auto');
        \App\Support\Marketing::trackCart();
        \App\Support\Triggers::fire('customer.registered', ['email' => $email, 'name' => $name, 'user' => $u, 'ref' => 'u'.$u->id]);
        flash('success', 'Welcome aboard, '.explode(' ', $name)[0].'!');

        return redirect()->intended('/account');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }

    public function index()
    {
        $u = Auth::user();
        $orders = Order::where('user_id', $u->id)->orderByDesc('id')->limit(100)->get();
        $sub = \App\Models\Subscriber::where('email', mb_strtolower($u->email))->first();
        $newsletter = $sub && $sub->status === 'subscribed';
        $social = \App\Models\SocialAccount::where('user_id', $u->id)->pluck('provider')->all();
        $stats = ['orders' => $orders->count(), 'spent' => (int) $orders->where('payment_status', 'paid')->sum('total'), 'credit' => (int) ($u->credit ?? 0)];
        title('My account');

        return page('account/index', compact('u', 'orders', 'newsletter', 'social', 'stats'));
    }

    public function profile(Request $r)
    {
        $u = Auth::user();
        $u->update(['name' => trim((string) $r->input('name')) ?: $u->name, 'phone' => trim((string) $r->input('phone'))]);
        flash('success', 'Profile updated.');

        return redirect('/account');
    }

    /** Set (no password yet) or change the password. */
    public function password(Request $r)
    {
        $u = Auth::user();
        $new = (string) $r->input('new_password');
        if (strlen($new) < 8) {
            flash('error', 'The new password must be at least 8 characters.');

            return redirect('/account#security');
        }
        if ($u->has_password && ! password_verify((string) $r->input('current_password'), $u->password)) {
            flash('error', 'Your current password is not correct.');

            return redirect('/account#security');
        }
        $u->update(['password' => $new, 'has_password' => true]);
        flash('success', $u->wasChanged('has_password') ? 'Password set — you can now sign in with it or with email codes.' : 'Password updated.');

        return redirect('/account#security');
    }

    public function track()
    {
        title('Track an order');

        return page('account/track');
    }

    public function trackPost(Request $r)
    {
        $key = 'track:'.$r->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) {
            flash('error', 'Too many tries. Please wait a few minutes.');

            return redirect('/track');
        }
        $o = Order::where('number', strtoupper(trim((string) $r->input('number'))))->where('email', mb_strtolower(trim((string) $r->input('email'))))->first();
        if (! $o) {
            RateLimiter::hit($key, 900);
            flash('error', 'No order matches those details.');

            return redirect('/track');
        }

        return redirect($o->publicUrl());
    }
}
