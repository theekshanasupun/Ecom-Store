<?php

namespace App\Support;

use App\Models\Setting;

final class Settings
{
    private static ?array $cache = null;

    public static function defaults(): array
    {
        return \App\Services\Loyalty::defaults() + [
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
            // branding
            'favicon' => '',
            // upper banner bar + free delivery bar
            'announce_on' => '0', 'announce_text' => "Welcome to our store · New arrivals every week", 'announce_link' => '', 'announce_style' => 'static',
            'announce_bg' => '#c6ff3d', 'announce_fg' => '#0b0b0d', 'announce_close' => '1', 'announce_start' => '', 'announce_end' => '',
            'fd_on' => '0', 'fd_text_progress' => "You're {amount} away from free delivery", 'fd_text_done' => 'You have unlocked free delivery!',
            'fd_text_empty' => 'Free delivery on orders over {min}', 'fd_bg' => '#141418', 'fd_fg' => '#f3f1ec', 'fd_bar' => '#c6ff3d', 'fd_in_drawer' => '1', 'fd_sticky' => '0',
            // SEO
            'seo_title_tpl' => '{title} — {site}', 'seo_home_title' => '', 'seo_keywords' => '', 'seo_og_image' => '', 'seo_twitter' => '',
            'seo_noindex_site' => '0', 'seo_robots_extra' => '', 'seo_google_verify' => '', 'seo_bing_verify' => '', 'seo_pinterest_verify' => '',
            'ga4_id' => '', 'gtm_id' => '', 'fb_pixel' => '', 'seo_head_code' => '', 'seo_body_code' => '',
            'seo_schema_org' => '1', 'seo_schema_product' => '1', 'seo_schema_search' => '1', 'seo_breadcrumbs' => '1', 'seo_canonical' => '1',
            'seo_ai_allow' => '1', 'seo_llms_on' => '1', 'seo_llms_extra' => '',
            // GEO / local
            'geo_on' => '0', 'geo_type' => 'Store', 'geo_name' => '', 'geo_street' => '', 'geo_city' => '', 'geo_region' => '', 'geo_postal' => '',
            'geo_country' => '', 'geo_lat' => '', 'geo_lng' => '', 'geo_hours' => '', 'geo_price_range' => '', 'geo_areas' => '', 'geo_same_as' => '',
            // social login
            'login_google_on' => '0', 'google_id' => '', 'google_secret' => '', 'login_facebook_on' => '0', 'facebook_id' => '', 'facebook_secret' => '',
            'login_github_on' => '0', 'github_id' => '', 'github_secret' => '', 'login_discord_on' => '0', 'discord_id' => '', 'discord_secret' => '',
            // v3.0 themes
            'theme_mode' => 'auto', 'theme_toggle' => '1', 'theme_light_bg' => '#faf7f2', 'theme_light_surface' => '#ffffff', 'theme_light_text' => '#1c1917',
            'theme_light_accent' => '', 'theme_light_font' => 'fraunces', 'theme_light_radius' => 'soft', 'theme_light_logo' => '',
            // page builder / popups / automations
            'home_use_builder' => '0', 'home_builder' => '[]', 'popups_on' => '1', 'automations_autorun' => '1', 'automations_last_run' => '',
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
