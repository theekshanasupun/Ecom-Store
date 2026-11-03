<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;

final class Payments
{
    /** Enabled gateways (COD only when physical goods are present). */
    public static function methods(bool $hasPhysical): array
    {
        $m = [];
        if (setting('pay_stripe_on') === '1' && setting('stripe_sk')) $m['stripe'] = ['label' => setting('pay_stripe_label'), 'hint' => 'Visa, Mastercard, Apple Pay & more'];
        if (setting('pay_paypal_on') === '1' && setting('paypal_client') && setting('paypal_secret')) $m['paypal'] = ['label' => setting('pay_paypal_label'), 'hint' => 'Pay with your PayPal balance or card'];
        if (setting('pay_bank_on') === '1') $m['bank'] = ['label' => setting('pay_bank_label'), 'hint' => 'Instructions shown after you order'];
        if (setting('pay_cod_on') === '1' && $hasPhysical) $m['cod'] = ['label' => setting('pay_cod_label'), 'hint' => 'Pay when it arrives'];

        return $m;
    }

    /** Redirect URL for online gateways, null for offline methods. */
    public static function start(Order $o): ?string
    {
        return match ($o->payment_method) {
            'stripe' => self::stripeStart($o),
            'paypal' => self::paypalStart($o),
            default => null,
        };
    }

    // ---- Stripe Checkout (hosted) ----
    private static function stripeStart(Order $o): string
    {
        $r = Http::withBasicAuth((string) setting('stripe_sk'), '')->asForm()->timeout(25)->post('https://api.stripe.com/v1/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => url('pay/stripe/return').'?order='.$o->number.'&t='.$o->token.'&sid={CHECKOUT_SESSION_ID}',
            'cancel_url' => url('pay/cancel').'?order='.$o->number.'&t='.$o->token,
            'customer_email' => $o->email, 'client_reference_id' => $o->number,
            'line_items' => [['quantity' => 1, 'price_data' => ['currency' => strtolower($o->currency), 'unit_amount' => $o->total, 'product_data' => ['name' => setting('store_name').' order '.$o->number]]]],
            'metadata' => ['order' => $o->number],
        ]);
        if ($r->failed() || ! $r->json('url')) {
            throw new \RuntimeException('Stripe error: '.($r->json('error.message') ?? 'could not start checkout'));
        }
        $o->update(['payment_ref' => $r->json('id')]);

        return $r->json('url');
    }

    public static function stripeVerify(Order $o, string $sid): bool
    {
        $r = Http::withBasicAuth((string) setting('stripe_sk'), '')->timeout(25)->get('https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($sid));
        if ($r->ok() && $r->json('payment_status') === 'paid' && $r->json('client_reference_id') === $o->number && (int) $r->json('amount_total') === $o->total) {
            Orders::markPaid($o, (string) ($r->json('payment_intent') ?? $sid), 'stripe');

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
        $calc = hash_hmac('sha256', $t.'.'.$payload, $secret);
        if (! collect($sigs)->contains(fn ($s) => hash_equals($calc, $s))) return false;
        $ev = json_decode($payload, true) ?: [];
        if (($ev['type'] ?? '') === 'checkout.session.completed') {
            $s = $ev['data']['object'] ?? [];
            $o = Order::where('number', (string) ($s['client_reference_id'] ?? ''))->first();
            if ($o && ($s['payment_status'] ?? '') === 'paid' && (int) $s['amount_total'] === $o->total) {
                Orders::markPaid($o, (string) ($s['payment_intent'] ?? ''), 'stripe-webhook');
            }
        }

        return true;
    }

    // ---- PayPal Orders v2 ----
    private static function ppBase(): string
    {
        return setting('paypal_sandbox') === '1' ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    private static function ppToken(): string
    {
        $r = Http::withBasicAuth((string) setting('paypal_client'), (string) setting('paypal_secret'))->asForm()->timeout(25)->post(self::ppBase().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);
        if ($r->failed() || ! $r->json('access_token')) throw new \RuntimeException('PayPal authentication failed. Check your API credentials.');

        return $r->json('access_token');
    }

    private static function paypalStart(Order $o): string
    {
        $r = Http::withToken(self::ppToken())->timeout(25)->post(self::ppBase().'/v2/checkout/orders', [
            'intent' => 'CAPTURE',
            'purchase_units' => [['reference_id' => $o->number, 'custom_id' => $o->number, 'amount' => ['currency_code' => $o->currency, 'value' => from_cents($o->total)]]],
            'application_context' => [
                'brand_name' => setting('store_name'), 'user_action' => 'PAY_NOW', 'shipping_preference' => 'NO_SHIPPING',
                'return_url' => url('pay/paypal/return').'?order='.$o->number.'&t='.$o->token,
                'cancel_url' => url('pay/cancel').'?order='.$o->number.'&t='.$o->token,
            ],
        ]);
        if ($r->failed()) throw new \RuntimeException('PayPal error: '.($r->json('message') ?? 'could not create the order'));
        foreach ($r->json('links', []) as $l) {
            if (in_array($l['rel'], ['approve', 'payer-action'], true)) {
                $o->update(['payment_ref' => $r->json('id')]);

                return $l['href'];
            }
        }
        throw new \RuntimeException('PayPal did not return an approval link.');
    }

    public static function paypalCapture(Order $o, string $ppOrderId): bool
    {
        if ($ppOrderId !== $o->payment_ref) return false;
        $r = Http::withToken(self::ppToken())->withBody('{}', 'application/json')->timeout(25)->post(self::ppBase().'/v2/checkout/orders/'.rawurlencode($ppOrderId).'/capture');
        $cap = $r->json('purchase_units.0.payments.captures.0', []);
        if ($r->json('status') === 'COMPLETED' && (string) ($cap['amount']['value'] ?? '') === from_cents($o->total)) {
            Orders::markPaid($o, (string) ($cap['id'] ?? $ppOrderId), 'paypal');

            return true;
        }

        return false;
    }
}
