<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class Account extends Controller
{
    public function login()
    {
        if (Auth::check()) return redirect('/account');
        title('Sign in');

        return page('account/login');
    }

    public function loginPost(Request $r)
    {
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

    public function register()
    {
        if (Auth::check()) return redirect('/account');
        title('Create account');

        return page('account/register');
    }

    public function registerPost(Request $r)
    {
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
        title('My account');

        return page('account/index', compact('u', 'orders'));
    }

    public function profile(Request $r)
    {
        $u = Auth::user();
        $upd = ['name' => trim((string) $r->input('name')) ?: $u->name, 'phone' => trim((string) $r->input('phone'))];
        $new = (string) $r->input('new_password');
        if ($new !== '') {
            if (! password_verify((string) $r->input('current_password'), $u->password) || strlen($new) < 8) {
                flash('error', 'Current password is wrong, or the new one is shorter than 8 characters.');

                return redirect('/account');
            }
            $upd['password'] = $new;
        }
        $u->update($upd);
        flash('success', 'Profile updated.');

        return redirect('/account');
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
