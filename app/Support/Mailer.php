<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Sends HTML email through the transport chosen in Admin → Settings → Email. */
final class Mailer
{
    public static function send(string $to, string $subject, string $html): bool
    {
        $to = trim($to);
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $host = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $from = (string) (setting('mail_from') ?: setting('store_email') ?: 'noreply@'.preg_replace('/^www\./', '', $host));
        $name = (string) (setting('mail_from_name') ?: setting('store_name'));

        try {
            $driver = (string) setting('mail_driver', 'sendmail');
            $mailer = match ($driver) {
                'smtp' => Mail::build([
                    'transport' => 'smtp', 'host' => setting('smtp_host'), 'port' => (int) setting('smtp_port', 587),
                    'encryption' => setting('smtp_enc') === 'none' ? null : setting('smtp_enc', 'tls'),
                    'username' => setting('smtp_user') ?: null, 'password' => setting('smtp_pass') ?: null, 'timeout' => 12,
                ]),
                'log' => Mail::build(['transport' => 'log']),
                default => Mail::build(['transport' => 'sendmail', 'path' => '/usr/sbin/sendmail -bs -i']),
            };
            $mailer->html($html, function ($m) use ($to, $subject, $from, $name) {
                $m->to($to)->subject($subject)->from($from, $name);
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('Mail failed: '.$e->getMessage());

            return false;
        }
    }

    /** Branded email shell. */
    public static function wrap(string $title, string $inner): string
    {
        $name = e(setting('store_name'));
        $accent = e(setting('accent', '#c6ff3d'));

        return '<!doctype html><html><body style="margin:0;background:#0b0b0d;font-family:Arial,Helvetica,sans-serif;color:#f3f1ec">'
            .'<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0d"><tr><td align="center" style="padding:28px 12px">'
            .'<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#16161b;border-radius:20px;overflow:hidden">'
            .'<tr><td style="padding:26px 30px;border-bottom:1px solid #26262d;font-size:22px;font-weight:900;letter-spacing:3px">'.$name.'<span style="color:'.$accent.'">.</span></td></tr>'
            .'<tr><td style="padding:30px"><h1 style="margin:0 0 14px;font-size:24px;line-height:1.2;color:#fff">'.e($title).'</h1>'.$inner.'</td></tr>'
            .'<tr><td style="padding:18px 30px;font-size:12px;color:#8a8a93;border-top:1px solid #26262d">© '.date('Y').' '.$name.'</td></tr>'
            .'</table></td></tr></table></body></html>';
    }
}
