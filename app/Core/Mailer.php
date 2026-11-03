<?php
namespace App\Core;

final class Mailer
{
    public static function send(string $to, string $subject, string $html): bool
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $fromEmail = (string) setting('mail_from') ?: (string) setting('store_email') ?: 'noreply@' . preg_replace('/^www\./', '', explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]);
        $fromName = (string) setting('mail_from_name') ?: (string) setting('store_name');
        $driver = (string) setting('mail_driver', 'mail');
        $text = trim(html_entity_decode(strip_tags(preg_replace('#<(br|/p|/h\d|/tr|/div)>#i', "\n", $html))));
        $boundary = 'b' . bin2hex(random_bytes(8));
        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::enc($fromName) . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: KIOSK',
        ];
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text)) . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html)) . "--$boundary--\r\n";

        try {
            if ($driver === 'log') {
                self::log("LOG MAIL to $to | $subject\n$text");
                return true;
            }
            if ($driver === 'smtp') {
                return self::smtp($fromEmail, $to, $subject, $headers, $body);
            }
            $ok = @mail($to, self::enc($subject), $body, implode("\r\n", $headers));
            if (!$ok) {
                self::log("mail() failed for $to | $subject");
            }
            return $ok;
        } catch (\Throwable $e) {
            self::log('Mail error: ' . $e->getMessage());
            return false;
        }
    }

    private static function enc(string $s): string
    {
        return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    private static function log(string $m): void
    {
        @file_put_contents(STORAGE . '/logs/mail.log', '[' . now() . '] ' . $m . "\n", FILE_APPEND);
    }

    private static function smtp(string $from, string $to, string $subject, array $headers, string $body): bool
    {
        $host = (string) setting('smtp_host');
        $port = (int) setting('smtp_port', 587);
        $enc = (string) setting('smtp_enc', 'tls');
        $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $en, $es, 12, STREAM_CLIENT_CONNECT);
        if (!$fp) {
            self::log("SMTP connect failed: $es");
            return false;
        }
        stream_set_timeout($fp, 12);
        $read = function () use ($fp) {
            $out = '';
            while (($l = fgets($fp, 515)) !== false) {
                $out .= $l;
                if (strlen($l) < 4 || $l[3] === ' ') break;
            }
            return $out;
        };
        $cmd = function (string $c, string $expect) use ($fp, $read) {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            if (!str_starts_with($r, $expect)) {
                throw new \RuntimeException("SMTP '$c' → $r");
            }
            return $r;
        };
        try {
            $r = $read();
            if (!str_starts_with($r, '220')) throw new \RuntimeException('SMTP greeting: ' . $r);
            $name = explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0];
            $cmd('EHLO ' . $name, '250');
            if ($enc === 'tls') {
                $cmd('STARTTLS', '220');
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('TLS failed');
                }
                $cmd('EHLO ' . $name, '250');
            }
            $user = (string) setting('smtp_user');
            if ($user !== '') {
                $cmd('AUTH LOGIN', '334');
                $cmd(base64_encode($user), '334');
                $cmd(base64_encode((string) setting('smtp_pass')), '235');
            }
            $cmd('MAIL FROM:<' . $from . '>', '250');
            $cmd('RCPT TO:<' . $to . '>', '250');
            $cmd('DATA', '354');
            $msg = implode("\r\n", $headers) . "\r\nTo: <$to>\r\nSubject: " . self::enc($subject) . "\r\n\r\n" . $body;
            $msg = preg_replace('/^\./m', '..', $msg);
            fwrite($fp, $msg . "\r\n.\r\n");
            $r = $read();
            if (!str_starts_with($r, '250')) throw new \RuntimeException('SMTP data: ' . $r);
            fwrite($fp, "QUIT\r\n");
            fclose($fp);
            return true;
        } catch (\Throwable $e) {
            self::log($e->getMessage());
            @fclose($fp);
            return false;
        }
    }

    /** Wrap content in the branded email shell. */
    public static function wrap(string $title, string $inner): string
    {
        $name = e(setting('store_name'));
        $accent = e(setting('accent', '#c6ff3d'));
        return '<!doctype html><html><body style="margin:0;background:#0b0b0d;font-family:Arial,Helvetica,sans-serif;color:#f3f1ec">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="background:#0b0b0d"><tr><td align="center" style="padding:28px 12px">'
            . '<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#16161b;border-radius:20px;overflow:hidden">'
            . '<tr><td style="padding:26px 30px;border-bottom:1px solid #26262d;font-size:22px;font-weight:900;letter-spacing:3px;color:#f3f1ec">' . $name . '<span style="color:' . $accent . '">.</span></td></tr>'
            . '<tr><td style="padding:30px"><h1 style="margin:0 0 14px;font-size:24px;line-height:1.2;color:#fff">' . e($title) . '</h1>' . $inner . '</td></tr>'
            . '<tr><td style="padding:18px 30px;font-size:12px;color:#8a8a93;border-top:1px solid #26262d">© ' . date('Y') . ' ' . $name . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}
