<?php

namespace App\Support;

use App\Models\Setting;

final class Settings
{
    private static ?array $cache = null;

    public static function defaults(): array
    {
        return [
            'store_name' => 'KIOSK', 'store_tagline' => 'One store. Every want.', 'store_email' => '', 'store_phone' => '', 'store_address' => '',
            'currency' => 'USD', 'currency_symbol' => '$',
            'hero_line1' => 'One store.', 'hero_line2' => 'Every want.',
            'hero_text' => 'Toys, clothing and digital keys, side by side. Open a department below, or jump straight in.',
            'ticker' => 'Everything · Keys delivered in seconds · Free shipping on physical orders over $75 · One cart for everything',
            'ticker_enabled' => '1', 'accent' => '#c6ff3d', 'footer_text' => 'A store concept.',
            'meta_description' => 'Toys, clothing and instant digital keys in one cart.',
            'tax_rate' => '0', 'tax_label' => 'Tax', 'shipping_flat' => '599', 'free_shipping_min' => '7500', 'shipping_note' => 'Ships in 2 days',
            'guest_checkout' => '1', 'low_stock' => '3',
            'pay_bank_on' => '1', 'pay_bank_label' => 'Bank transfer', 'pay_bank_info' => "Bank: Your Bank\nAccount: 0000 0000\nUse your order number as the reference.",
            'pay_cod_on' => '0', 'pay_cod_label' => 'Cash on delivery',
            'pay_stripe_on' => '0', 'pay_stripe_label' => 'Card (Stripe)', 'stripe_pk' => '', 'stripe_sk' => '', 'stripe_whsec' => '',
            'pay_paypal_on' => '0', 'pay_paypal_label' => 'PayPal', 'paypal_client' => '', 'paypal_secret' => '', 'paypal_sandbox' => '1',
            'mail_driver' => 'sendmail', 'mail_from' => '', 'mail_from_name' => '',
            'smtp_host' => '', 'smtp_port' => '587', 'smtp_enc' => 'tls', 'smtp_user' => '', 'smtp_pass' => '',
            'notify_admin' => '1', 'maintenance' => '0', 'logo' => '', 'update_history' => '[]',
            'social_instagram' => '', 'social_x' => '', 'social_tiktok' => '',
        ];
    }

    private static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = self::defaults();
            if (installed()) {
                try {
                    foreach (Setting::query()->pluck('value', 'key') as $k => $v) {
                        self::$cache[$k] = $v;
                    }
                } catch (\Throwable) {
                }
            }
        }

        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::load();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        self::$cache = null;
    }

    public static function setMany(array $data): void
    {
        foreach ($data as $k => $v) {
            self::set((string) $k, $v);
        }
    }

    public static function reset(): void
    {
        self::$cache = null;
    }
}
