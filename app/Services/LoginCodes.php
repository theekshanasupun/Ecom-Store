<?php

namespace App\Services;

use App\Models\LoginCode;
use App\Support\Mailer;
use Illuminate\Support\Facades\RateLimiter;

/** Six-digit email codes (10 minutes, 5 attempts, single use, stored hashed). */
final class LoginCodes
{
    public const TTL_MIN = 10;
    public const MAX_ATTEMPTS = 5;
    public const RESEND_SECONDS = 30;

    public static string $error = '';

    private static function hash(string $email, string $code, string $purpose): string
    {
        return hash_hmac('sha256', $email.'|'.$purpose.'|'.$code, (string) config('app.key'));
    }

    /** Seconds until another code may be requested (0 = now). */
    public static function wait(string $email, string $purpose = 'login'): int
    {
        $last = LoginCode::where('email', mb_strtolower($email))->where('purpose', $purpose)->orderByDesc('id')->first();
        if (! $last || ! $last->created_at) return 0;

        return max(0, self::RESEND_SECONDS - (int) $last->created_at->diffInSeconds(now(), true));
    }

    /** Create + email a code. Returns false (see ::$error) when throttled or the mail fails. */
    public static function issue(string $email, string $purpose = 'login'): bool
    {
        self::$error = '';
        $email = mb_strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) { self::$error = 'Enter a valid email address.'; return false; }
        if (($w = self::wait($email, $purpose)) > 0) { self::$error = "Please wait {$w}s before requesting another code."; return false; }
        $key = 'otp-send:'.sha1($email.'|'.request()->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) { self::$error = 'Too many codes requested. Try again later.'; return false; }
        $ek = 'otp-send-email:'.sha1($email);
        if (RateLimiter::tooManyAttempts($ek, 8)) { self::$error = 'Too many codes requested for this address. Try again later.'; return false; }
        RateLimiter::hit($key, 3600);
        RateLimiter::hit($ek, 3600);
        LoginCode::where('email', $email)->where('purpose', $purpose)->whereNull('used_at')->update(['used_at' => now()]);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        LoginCode::create(['email' => $email, 'code_hash' => self::hash($email, $code, $purpose), 'purpose' => $purpose, 'expires_at' => now()->addMinutes(self::TTL_MIN), 'ip' => request()->ip(), 'created_at' => now()]);
        $accent = e((string) setting('accent', '#c6ff3d'));
        $inner = '<p style="color:#cfcfd6;line-height:1.6">Use this code to sign in to <b>'.e((string) setting('store_name')).'</b>. It expires in '.self::TTL_MIN.' minutes and works once.</p>'
            .'<div style="margin:18px 0;padding:18px;background:#0b0b0d;border:1px dashed '.$accent.';border-radius:14px;text-align:center;font:800 36px monospace;letter-spacing:10px;color:'.$accent.'">'.$code.'</div>'
            .'<p style="color:#8a8a93;font-size:12px">If you did not ask for this code you can ignore this email — nobody can sign in without it.</p>';
        if (! Mailer::send($email, $code.' is your '.setting('store_name').' sign-in code', Mailer::wrap('Your sign-in code', $inner))) {
            self::$error = 'We could not send the email. Please try again later.';

            return false;
        }

        return true;
    }

    /** Check a code. Consumes it on success. */
    public static function verify(string $email, string $code, string $purpose = 'login'): bool
    {
        $email = mb_strtolower(trim($email));
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) return false;
        $key = 'otp-verify:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 20)) return false;
        $row = LoginCode::where('email', $email)->where('purpose', $purpose)->whereNull('used_at')->where('expires_at', '>', now())->orderByDesc('id')->first();
        if (! $row || $row->attempts >= self::MAX_ATTEMPTS) { RateLimiter::hit($key, 600); return false; }
        // atomic attempt counter + per-email verify cap
        $ek = 'otp-verify-email:'.sha1($email);
        if (RateLimiter::tooManyAttempts($ek, 12)) return false;
        RateLimiter::hit($ek, 3600);
        if (LoginCode::whereKey($row->id)->where('attempts', '<', self::MAX_ATTEMPTS)->increment('attempts') !== 1) return false;
        if (! hash_equals($row->code_hash, self::hash($email, $code, $purpose))) { RateLimiter::hit($key, 600); return false; }
        $row->update(['used_at' => now()]);

        return true;
    }
}
