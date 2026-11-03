<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Mailer;
use App\Support\Settings;
use App\Support\Upload;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public const TABS = [
        'general' => ['label' => 'General', 'keys' => ['store_name', 'store_tagline', 'store_email', 'store_phone', 'store_address', 'currency', 'currency_symbol', 'meta_description', 'footer_text', 'low_stock', 'guest_checkout', 'notify_admin', 'maintenance']],
        'storefront' => ['label' => 'Storefront', 'keys' => ['hero_line1', 'hero_line2', 'hero_text', 'accent', 'social_instagram', 'social_x', 'social_tiktok']],
        'bars' => ['label' => 'Banners & delivery bar', 'keys' => ['announce_on', 'announce_text', 'announce_link', 'announce_style', 'announce_bg', 'announce_fg', 'announce_close', 'announce_start', 'announce_end', 'fd_on', 'fd_text_progress', 'fd_text_done', 'fd_text_empty', 'fd_bg', 'fd_fg', 'fd_bar', 'fd_in_drawer', 'fd_sticky', 'free_shipping_min', 'ticker_enabled', 'ticker'], 'money' => ['free_shipping_min'], 'colors' => ['announce_bg', 'announce_fg', 'fd_bg', 'fd_fg', 'fd_bar']],
        'seo' => ['label' => 'SEO', 'keys' => ['seo_title_tpl', 'seo_home_title', 'meta_description', 'seo_keywords', 'seo_twitter', 'seo_noindex_site', 'seo_canonical', 'seo_schema_org', 'seo_schema_product', 'seo_schema_search', 'seo_breadcrumbs', 'seo_ai_allow', 'seo_llms_on', 'seo_llms_extra', 'seo_robots_extra', 'seo_google_verify', 'seo_bing_verify', 'seo_pinterest_verify', 'ga4_id', 'gtm_id', 'fb_pixel', 'seo_head_code', 'seo_body_code']],
        'geo' => ['label' => 'GEO / local', 'keys' => ['geo_on', 'geo_type', 'geo_name', 'geo_street', 'geo_city', 'geo_region', 'geo_postal', 'geo_country', 'geo_lat', 'geo_lng', 'geo_hours', 'geo_price_range', 'geo_areas', 'geo_same_as']],
        'social' => ['label' => 'Social login', 'keys' => ['login_google_on', 'google_id', 'google_secret', 'login_facebook_on', 'facebook_id', 'facebook_secret', 'login_github_on', 'github_id', 'github_secret', 'login_discord_on', 'discord_id', 'discord_secret']],
        'shipping' => ['label' => 'Shipping & tax', 'keys' => ['shipping_flat', 'free_shipping_min', 'shipping_note', 'ship_std_label', 'shipping_methods', 'tax_rate', 'tax_label'], 'money' => ['shipping_flat', 'free_shipping_min']],
        'accounts' => ['label' => 'Accounts & checkout', 'keys' => ['guest_checkout', 'login_password_on', 'login_otp_on', 'login_otp_staff', 'checkout_subscribe_default', 'newsletter_footer_on', 'newsletter_title', 'newsletter_text', 'credit_on']],
        'payments' => ['label' => 'Payments', 'keys' => ['pay_bank_on', 'pay_bank_label', 'pay_bank_info', 'pay_cod_on', 'pay_cod_label', 'pay_stripe_on', 'pay_stripe_label', 'stripe_pk', 'stripe_sk', 'stripe_whsec', 'pay_paypal_on', 'pay_paypal_label', 'paypal_client', 'paypal_secret', 'paypal_sandbox']],
        'email' => ['label' => 'Email', 'keys' => ['mail_driver', 'mail_from', 'mail_from_name', 'smtp_host', 'smtp_port', 'smtp_enc', 'smtp_user', 'smtp_pass']],
    ];

    public function index(Request $r)
    {
        $tab = $r->query('tab', 'general');
        if (! isset(self::TABS[$tab])) $tab = 'general';
        title('Settings');

        return admin_page('settings', ['tab' => $tab, 'tabs' => self::TABS]);
    }

    public function save(Request $r)
    {
        $tab = (string) $r->input('tab', 'general');
        if (! isset(self::TABS[$tab])) return redirect('/admin/settings');
        $def = self::TABS[$tab];
        $data = [];
        foreach ($def['keys'] as $k) {
            if (! $r->has($k)) continue;
            $v = trim((string) $r->input($k));
            if (in_array($k, $def['money'] ?? [], true)) $v = (string) to_cents($v);
            if ($k === 'accent' && ! preg_match('/^#[0-9a-fA-F]{6}$/', $v)) $v = '#c6ff3d';
            if (in_array($k, $def['colors'] ?? [], true) && ! preg_match('/^#[0-9a-fA-F]{6}$/', $v)) $v = '#c6ff3d';
            if (in_array($k, ['announce_start', 'announce_end'], true) && $v !== '' && ! strtotime($v)) $v = '';
            if (in_array($k, ['geo_lat', 'geo_lng'], true) && $v !== '' && ! is_numeric($v)) $v = '';
            if ($k === 'geo_country') $v = strtoupper(mb_substr($v, 0, 2));
            if (in_array($k, ['seo_head_code', 'seo_body_code'], true) && ! auth()->user()->isAdmin()) continue;
            if ($k === 'tax_rate') $v = (string) max(0, min(100, (float) $v));
            if ($k === 'shipping_methods') $v = implode("\n", array_slice(array_filter(array_map(fn ($l) => mb_substr(trim($l), 0, 160), preg_split('/\R/', (string) $r->input($k)))), 0, 8));
            if ($k === 'login_password_on' && $v === '0' && $r->input('login_otp_on') === '0') $v = '1'; // never lock everyone out
            $data[$k] = $v;
        }
        try {
            if ($tab === 'general') {
                if ($fav = Upload::image($r->file('favicon'), 'site', 1)) {
                    Upload::remove((string) setting('favicon'));
                    $data['favicon'] = $fav;
                } elseif ($r->boolean('remove_favicon')) {
                    Upload::remove((string) setting('favicon'));
                    $data['favicon'] = '';
                }
            }
            if ($tab === 'seo' && ($og = Upload::image($r->file('seo_og_image'), 'site', 3))) {
                Upload::remove((string) setting('seo_og_image'));
                $data['seo_og_image'] = $og;
            }
            if ($tab === 'general') {
                if ($logo = Upload::image($r->file('logo'), 'site', 2)) {
                    Upload::remove((string) setting('logo'));
                    $data['logo'] = $logo;
                } elseif ($r->boolean('remove_logo')) {
                    Upload::remove((string) setting('logo'));
                    $data['logo'] = '';
                }
            }
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());

            return redirect('/admin/settings?tab='.$tab);
        }
        Settings::setMany($data);
        flash('success', 'Settings saved.');

        return redirect('/admin/settings?tab='.$tab);
    }

    public function testMail(Request $r)
    {
        $to = trim((string) $r->input('to')) ?: (string) setting('store_email');
        $ok = Mailer::send($to, 'Test email from '.setting('store_name'), Mailer::wrap('It works!', '<p style="color:#cfcfd6">Your email settings are configured correctly.</p>'));
        flash($ok ? 'success' : 'error', $ok ? "Test email sent to $to." : 'Sending failed. See storage/logs/laravel.log for details.');

        return redirect('/admin/settings?tab=email');
    }
}
