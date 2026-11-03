<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Social;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SocialController extends Controller
{
    public function redirect(string $provider)
    {
        if (! isset(Social::enabled()[$provider])) abort(404);

        return redirect()->away(Social::redirectUrl($provider));
    }

    public function callback(Request $r, string $provider)
    {
        if (! isset(Social::enabled()[$provider])) abort(404);
        $state = (string) session('oauth_state');
        $ok = $state !== '' && hash_equals($state, (string) $r->query('state')) && session('oauth_provider') === $provider;
        session()->forget(['oauth_state', 'oauth_provider']);
        if (! $ok || $r->query('error') || ! $r->query('code')) {
            flash('error', 'Sign-in was cancelled or could not be verified. Please try again.');

            return redirect('/login');
        }
        try {
            $u = Social::resolveUser($provider, Social::fetchProfile($provider, (string) $r->query('code')));
        } catch (\Throwable $e) {
            \Log::warning('Social login failed: '.$e->getMessage());
            flash('error', $e instanceof \RuntimeException ? $e->getMessage() : 'Sign-in failed. Please try again or use email.');

            return redirect('/login');
        }
        Order::where('email', $u->email)->whereNull('user_id')->update(['user_id' => $u->id]);
        Auth::login($u, true);
        $r->session()->regenerate();
        $u->update(['last_login_at' => now()]);
        \App\Support\Marketing::trackCart();

        return redirect()->intended('/account');
    }
}
