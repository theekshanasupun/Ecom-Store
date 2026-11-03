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
        'storefront' => ['label' => 'Storefront', 'keys' => ['hero_line1', 'hero_line2', 'hero_text', 'ticker', 'ticker_enabled', 'accent', 'social_instagram', 'social_x', 'social_tiktok']],
        'shipping' => ['label' => 'Shipping & tax', 'keys' => ['shipping_flat', 'free_shipping_min', 'shipping_note', 'tax_rate', 'tax_label'], 'money' => ['shipping_flat', 'free_shipping_min']],
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
            if ($k === 'tax_rate') $v = (string) max(0, min(100, (float) $v));
            $data[$k] = $v;
        }
        try {
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
