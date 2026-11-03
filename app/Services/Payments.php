<?php
namespace App\Services;

final class Payments
{
    /** Enabled gateways for a cart (COD only when physical goods are present). */
    public static function methods(bool $hasPhysical): array
    {
        $m = [];
        if (setting('pay_stripe_on') === '1' && setting('stripe_sk')) {
            $m['stripe'] = ['label' => setting('pay_stripe_label'), 'hint' => 'Visa, Mastercard, Apple Pay & more'];
        }
        if (setting('pay_paypal_on') === '1' && setting('paypal_client') && setting('paypal_secret')) {
            $m['paypal'] = ['label' => setting('pay_paypal_label'), 'hint' => 'Pay with your PayPal balance or card'];
        }
        if (setting('pay_bank_on') === '1') {
            $m['bank'] = ['label' => setting('pay_bank_label'), 'hint' => 'Instructions shown after you order'];
        }
        if (setting('pay_cod_on') === '1' && $hasPhysical) {
            $m['cod'] = ['label' => setting('pay_cod_label'), 'hint' => 'Pay when it arrives'];
        }
        return $m;
    }

    private static function http(string $method, string $url, array $headers = [], mixed $body = null, ?string $userpwd = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => true,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        if ($userpwd) curl_setopt($ch, CURLOPT_USERPWD, $userpwd);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new \RuntimeException('Payment gateway unreachable: ' . $err);
        }
        return [$code, json_decode((string) $raw, true) ?: []];
    }

    /** Returns redirect URL, or null for offline methods. */
    public static function start(array $order): ?string
    {
        return match ($order['payment_method']) {
            'stripe' => self::stripeStart($order),
            'paypal' => self::paypalStart($order),
            default => null,
        };
    }

    // ---- Stripe Checkout (hosted) -------------------------------------------------
    private static function stripeStart(array $o): string
    {
        $q = http_build_query([
            'mode' => 'payment',
            'success_url' => abs_url('pay/stripe/return?order=' . $o['number'] . '&t=' . $o['token'] . '&sid={CHECKOUT_SESSION_ID}'),
            'cancel_url' => abs_url('pay/cancel?order=' . $o['number'] . '&t=' . $o['token']),
            'customer_email' => $o['email'],
            'client_reference_id' => $o['number'],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => ['currency' => strtolower($o['currency']), 'unit_amount' => (int) $o['total'], 'product_data' => ['name' => setting('store_name') . ' order ' . $o['number']]],
            ]],
            'metadata' => ['order' => $o['number']],
        ]);
        [$code, $r] = self::http('POST', 'https://api.stripe.com/v1/checkout/sessions', ['Content-Type: application/x-www-form-urlencoded'], $q, setting('stripe_sk') . ':');
        if ($code >= 400 || empty($r['url'])) {
            throw new \RuntimeException('Stripe error: ' . ($r['error']['message'] ?? 'could not start checkout'));
        }
        \App\Core\Db::update('orders', ['payment_ref' => $r['id']], 'id = ?', [$o['id']]);
        return $r['url'];
    }

    public static function stripeVerify(array $o, string $sid): bool
    {
        [$code, $r] = self::http('GET', 'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sid), [], null, setting('stripe_sk') . ':');
        if ($code === 200 && ($r['payment_status'] ?? '') === 'paid' && ($r['client_reference_id'] ?? '') === $o['number'] && (int) ($r['amount_total'] ?? -1) === (int) $o['total']) {
            Orders::markPaid((int) $o['id'], (string) ($r['payment_intent'] ?? $sid), 'stripe');
            return true;
        }
        return false;
    }

    public static function stripeWebhook(string $payload, string $sigHeader): bool
    {
        $secret = (string) setting('stripe_whsec');
        if ($secret === '') return false;
        $t = '';
        $sigs = [];
        foreach (explode(',', $sigHeader) as $part) {
            [$k, $v] = array_pad(explode('=', trim($part), 2), 2, '');
            if ($k === 't') $t = $v;
            if ($k === 'v1') $sigs[] = $v;
        }
        if ($t === '' || abs(time() - (int) $t) > 600) return false;
        $calc = hash_hmac('sha256', $t . '.' . $payload, $secret);
        $ok = false;
        foreach ($sigs as $s) if (hash_equals($calc, $s)) $ok = true;
        if (!$ok) return false;
        $ev = json_decode($payload, true) ?: [];
        if (($ev['type'] ?? '') === 'checkout.session.completed') {
            $s = $ev['data']['object'] ?? [];
            $o = Orders::byNumber((string) ($s['client_reference_id'] ?? ''));
            if ($o && ($s['payment_status'] ?? '') === 'paid' && (int) $s['amount_total'] === (int) $o['total']) {
                Orders::markPaid((int) $o['id'], (string) ($s['payment_intent'] ?? ''), 'stripe-webhook');
            }
        }
        return true;
    }

    // ---- PayPal Orders v2 ------------------------------------------------------------
    private static function paypalBase(): string
    {
        return setting('paypal_sandbox') === '1' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    private static function paypalToken(): string
    {
        [$code, $r] = self::http('POST', self::paypalBase() . '/v1/oauth2/token', ['Content-Type: application/x-www-form-urlencoded'], 'grant_type=client_credentials', setting('paypal_client') . ':' . setting('paypal_secret'));
        if ($code !== 200 || empty($r['access_token'])) {
            throw new \RuntimeException('PayPal authentication failed. Check your API credentials.');
        }
        return $r['access_token'];
    }

    private static function paypalStart(array $o): string
    {
        $tok = self::paypalToken();
        $body = json_encode([
            'intent' => 'CAPTURE',
            'purchase_units' => [['reference_id' => $o['number'], 'custom_id' => $o['number'], 'amount' => ['currency_code' => $o['currency'], 'value' => from_cents((int) $o['total'])]]],
            'application_context' => [
                'brand_name' => setting('store_name'), 'user_action' => 'PAY_NOW', 'shipping_preference' => 'NO_SHIPPING',
                'return_url' => abs_url('pay/paypal/return?order=' . $o['number'] . '&t=' . $o['token']),
                'cancel_url' => abs_url('pay/cancel?order=' . $o['number'] . '&t=' . $o['token']),
            ],
        ]);
        [$code, $r] = self::http('POST', self::paypalBase() . '/v2/checkout/orders', ['Content-Type: application/json', 'Authorization: Bearer ' . $tok], $body);
        if ($code >= 400 || empty($r['links'])) {
            throw new \RuntimeException('PayPal error: ' . ($r['message'] ?? 'could not create the order'));
        }
        foreach ($r['links'] as $l) {
            if (in_array($l['rel'], ['approve', 'payer-action'], true)) {
                \App\Core\Db::update('orders', ['payment_ref' => $r['id']], 'id = ?', [$o['id']]);
                return $l['href'];
            }
        }
        throw new \RuntimeException('PayPal did not return an approval link.');
    }

    public static function paypalCapture(array $o, string $paypalOrderId): bool
    {
        if ($paypalOrderId !== $o['payment_ref']) return false;
        $tok = self::paypalToken();
        [$code, $r] = self::http('POST', self::paypalBase() . '/v2/checkout/orders/' . rawurlencode($paypalOrderId) . '/capture', ['Content-Type: application/json', 'Authorization: Bearer ' . $tok], '{}');
        $status = $r['status'] ?? '';
        $cap = $r['purchase_units'][0]['payments']['captures'][0] ?? [];
        if ($status === 'COMPLETED' && (string) ($cap['amount']['value'] ?? '') === from_cents((int) $o['total'])) {
            Orders::markPaid((int) $o['id'], (string) ($cap['id'] ?? $paypalOrderId), 'paypal');
            return true;
        }
        return false;
    }
}
