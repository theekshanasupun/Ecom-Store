<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Minimal OAuth2 "Sign in with …" (Google, Facebook, GitHub, Discord) — no extra packages needed. */
final class Social
{
    public const PROVIDERS = [
        'google' => ['name' => 'Google', 'auth' => 'https://accounts.google.com/o/oauth2/v2/auth', 'token' => 'https://oauth2.googleapis.com/token', 'user' => 'https://openidconnect.googleapis.com/v1/userinfo', 'scope' => 'openid email profile', 'verified' => true],
        'facebook' => ['name' => 'Facebook', 'auth' => 'https://www.facebook.com/v19.0/dialog/oauth', 'token' => 'https://graph.facebook.com/v19.0/oauth/access_token', 'user' => 'https://graph.facebook.com/me?fields=id,name,email', 'scope' => 'email,public_profile', 'verified' => false],
        'github' => ['name' => 'GitHub', 'auth' => 'https://github.com/login/oauth/authorize', 'token' => 'https://github.com/login/oauth/access_token', 'user' => 'https://api.github.com/user', 'scope' => 'read:user user:email', 'verified' => true],
        'discord' => ['name' => 'Discord', 'auth' => 'https://discord.com/api/oauth2/authorize', 'token' => 'https://discord.com/api/oauth2/token', 'user' => 'https://discord.com/api/users/@me', 'scope' => 'identify email', 'verified' => true],
    ];

    public static function enabled(): array
    {
        $o = [];
        foreach (self::PROVIDERS as $k => $p) {
            if (setting("login_{$k}_on") === '1' && setting("{$k}_id") && setting("{$k}_secret")) $o[$k] = $p['name'];
        }

        return $o;
    }

    public static function callback(string $p): string
    {
        return url("auth/$p/callback");
    }

    public static function redirectUrl(string $p): string
    {
        $c = self::PROVIDERS[$p];
        $state = Str::random(40);
        session(['oauth_state' => $state, 'oauth_provider' => $p]);

        return $c['auth'].'?'.http_build_query(['client_id' => setting("{$p}_id"), 'redirect_uri' => self::callback($p), 'response_type' => 'code', 'scope' => $c['scope'], 'state' => $state] + ($p === 'google' ? ['access_type' => 'online', 'prompt' => 'select_account'] : []));
    }

    /** @return array{id:string,email:?string,name:string,verified:bool} */
    public static function fetchProfile(string $p, string $code): array
    {
        $c = self::PROVIDERS[$p];
        $tok = Http::asForm()->acceptJson()->timeout(15)->post($c['token'], ['client_id' => setting("{$p}_id"), 'client_secret' => setting("{$p}_secret"), 'code' => $code, 'redirect_uri' => self::callback($p), 'grant_type' => 'authorization_code'])->json();
        $at = $tok['access_token'] ?? null;
        if (! $at) throw new \RuntimeException('The provider did not return an access token.');
        $me = Http::withToken($at)->acceptJson()->withHeaders(['User-Agent' => 'KIOSK-Store'])->timeout(15)->get($c['user'])->json();
        if (! is_array($me) || empty($me['id'] ?? $me['sub'] ?? null)) throw new \RuntimeException('Could not read your profile from the provider.');
        $email = $me['email'] ?? null;
        $verified = $c['verified'];
        if ($p === 'google') $verified = (bool) ($me['email_verified'] ?? false);
        if ($p === 'discord') $verified = (bool) ($me['verified'] ?? false);
        if ($p === 'github') {
            $verified = false;
            $emails = Http::withToken($at)->acceptJson()->withHeaders(['User-Agent' => 'KIOSK-Store'])->timeout(15)->get('https://api.github.com/user/emails')->json();
            foreach ((array) $emails as $e) {
                if (! empty($e['primary']) && ! empty($e['verified'])) { $email = $e['email']; $verified = true; break; }
            }
        }

        return ['id' => (string) ($me['id'] ?? $me['sub']), 'email' => $email ? mb_strtolower($email) : null, 'name' => (string) ($me['name'] ?? $me['global_name'] ?? $me['login'] ?? $me['username'] ?? ''), 'verified' => $verified];
    }

    /** Find / link / create the local account. Throws on anything unsafe. */
    public static function resolveUser(string $p, array $prof): User
    {
        $link = SocialAccount::where(['provider' => $p, 'provider_id' => $prof['id']])->first();
        if ($link && ($u = User::find($link->user_id))) return self::active($u);

        $email = $prof['email'];
        if (! $email) throw new \RuntimeException('Your '.self::PROVIDERS[$p]['name'].' account did not share an email address. Please register with email instead.');
        $u = User::where('email', $email)->first();
        if ($u) {
            // never silently attach an unverified provider identity to an existing account (account-takeover guard)
            if (! $prof['verified']) throw new \RuntimeException('That email already has an account. Sign in with your password first, then connect '.self::PROVIDERS[$p]['name'].'.');
        } else {
            $u = User::create(['name' => $prof['name'] ?: explode('@', $email)[0], 'email' => $email, 'password' => Str::random(40), 'role' => 'customer', 'status' => 'active']);
            \App\Support\Marketing::subscribe($email, $u->name, 'auto');
        }
        SocialAccount::updateOrCreate(['provider' => $p, 'provider_id' => $prof['id']], ['user_id' => $u->id, 'email' => $email]);

        return self::active($u);
    }

    private static function active(User $u): User
    {
        if ($u->status !== 'active') throw new \RuntimeException('This account is disabled.');

        return $u;
    }
}
