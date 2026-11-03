<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function login()
    {
        if (Auth::user()?->isStaff()) return redirect('/admin');

        return page('admin/login', [], 'admin/bare');
    }

    public function loginPost(Request $r)
    {
        $key = 'admin-login:'.$r->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) {
            flash('error', 'Too many attempts. Try again in 15 minutes.');

            return redirect('/admin/login');
        }
        $email = mb_strtolower(trim((string) $r->input('email')));
        if (Auth::attempt(['email' => $email, 'password' => (string) $r->input('password'), 'status' => 'active'])) {
            if (Auth::user()->isStaff()) {
                RateLimiter::clear($key);
                $r->session()->regenerate();
                Auth::user()->update(['last_login_at' => now()]);

                return redirect()->intended('/admin');
            }
            Auth::logout();
        }
        RateLimiter::hit($key, 900);
        keep_old(['email' => $email]);
        flash('error', 'Wrong email or password.');

        return redirect('/admin/login');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/admin/login');
    }
}
