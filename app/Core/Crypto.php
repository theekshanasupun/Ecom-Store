<?php
namespace App\Core;

/** AES-256-GCM encryption for stored digital goods (keys, accounts). */
final class Crypto
{
    private static function key(): string
    {
        return hash('sha256', (string) cfg('app_key', 'kiosk-default'), true);
    }

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $c = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return 'v1:' . base64_encode($iv . $tag . $c);
    }

    public static function decrypt(string $blob): string
    {
        if (!str_starts_with($blob, 'v1:')) {
            return $blob;
        }
        $raw = base64_decode(substr($blob, 3), true);
        if ($raw === false || strlen($raw) < 28) {
            return '';
        }
        $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $p === false ? '' : $p;
    }
}
