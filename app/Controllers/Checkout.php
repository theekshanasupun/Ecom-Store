<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\View;
use App\Services\Cart;
use App\Services\Orders;
use App\Services\Payments;

final class Checkout
{
    public function show(): void
    {
        $sum = Cart::summary();
        if (!$sum['lines']) redirect('cart');
        if (setting('guest_checkout') !== '1' && !auth_user()) {
            flash('info', 'Please sign in or create an account to check out.');
            $_SESSION['after_login'] = 'checkout';
            redirect('login');
        }
        $methods = Payments::methods($sum['has_physical']);
        $u = auth_user();
        title('Checkout');
        View::$meta['step'] = 2;
        View::page('checkout', compact('sum', 'methods', 'u'));
    }

    public function place(): void
    {
        $sum = Cart::summary();
        if (!$sum['lines'] || $sum['has_sold_out']) {
            flash('error', 'Some items in your cart are no longer available.');
            redirect('cart');
        }
        $u = auth_user();
        $methods = Payments::methods($sum['has_physical']);
        $d = [
            'email' => str_in('email'), 'name' => str_in('name'), 'phone' => str_in('phone'),
            'ship_name' => str_in('ship_name') ?: str_in('name'), 'ship_address' => str_in('ship_address'), 'ship_address2' => str_in('ship_address2'),
            'ship_city' => str_in('ship_city'), 'ship_state' => str_in('ship_state'), 'ship_zip' => str_in('ship_zip'), 'ship_country' => str_in('ship_country'),
            'notes' => mb_substr(str_in('notes'), 0, 1000),
        ];
        $method = str_in('method');
        $err = [];
        if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Enter a valid email address.';
        if (mb_strlen($d['name']) < 2) $err[] = 'Enter your name.';
        if ($sum['has_physical']) {
            foreach (['ship_address' => 'street address', 'ship_city' => 'city', 'ship_zip' => 'postal code', 'ship_country' => 'country'] as $k => $l) {
                if ($d[$k] === '') $err[] = 'Enter your ' . $l . '.';
            }
        }
        if (!isset($methods[$method])) $err[] = 'Choose a payment method.';
        $create = str_in('create_account') === '1' && !$u;
        $pass = (string) input('password', '');
        if ($create) {
            if (strlen($pass) < 8) $err[] = 'Password must be at least 8 characters.';
            if (Db::val('SELECT COUNT(*) FROM users WHERE email = ?', [mb_strtolower($d['email'])])) $err[] = 'An account with that email exists. Please sign in.';
        }
        if ($err) {
            keep_old($_POST);
            flash('error', implode(' ', $err));
            redirect('checkout');
        }
        if ($create) {
            $uid = Db::insert('users', ['name' => $d['name'], 'email' => mb_strtolower($d['email']), 'password' => Auth::hash($pass), 'role' => 'customer', 'phone' => $d['phone'], 'status' => 'active', 'created_at' => now()]);
            Auth::login(Db::one('SELECT * FROM users WHERE id = ?', [$uid]));
            $u = auth_user();
        }
        $d['user_id'] = $u['id'] ?? null;
        try {
            $order = Orders::create($d, $sum, $method);
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('cart');
        }
        Cart::clear();
        forget_old();
        $_SESSION['last_order'] = $order['number'];

        try {
            $go = Payments::start($order);
        } catch (\Throwable $e) {
            Orders::event((int) $order['id'], 'Payment start failed: ' . $e->getMessage(), 'system');
            flash('error', 'We could not reach the payment provider. Your order is saved — try paying again below.');
            redirect('order/' . $order['number'] . '?t=' . $order['token']);
        }
        if ($go) redirect($go);

        // offline methods
        if ($order['total'] <= 0) {
            Orders::markPaid((int) $order['id'], 'free', 'system');
        } else {
            Orders::sendPending($order);
        }
        redirect('order/' . $order['number'] . '?t=' . $order['token']);
    }

    private function access(string $number): array
    {
        $o = Orders::byNumber($number);
        $tok = str_in('t');
        $u = auth_user();
        if (!$o) abort(404);
        $ok = ($tok !== '' && hash_equals($o['token'], $tok)) || ($u && ((int) $o['user_id'] === (int) $u['id'] || Auth::isStaff()));
        if (!$ok) abort(404);
        return $o;
    }

    public function order(array $a): void
    {
        $o = $this->access($a['number']);
        $items = Orders::items((int) $o['id']);
        title('Order ' . $o['number']);
        View::$meta['step'] = $o['payment_status'] === 'paid' || $o['payment_method'] !== 'stripe' ? 3 : 2;
        View::page('order', compact('o', 'items'));
    }

    public function repay(array $a): void
    {
        $o = $this->access($a['number']);
        if ($o['payment_status'] === 'paid' || !in_array($o['payment_method'], ['stripe', 'paypal'], true) || in_array($o['status'], ['cancelled', 'refunded'], true)) {
            redirect('order/' . $o['number'] . '?t=' . $o['token']);
        }
        try {
            redirect(Payments::start($o));
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect('order/' . $o['number'] . '?t=' . $o['token']);
        }
    }

    public function stripeReturn(): void
    {
        $o = $this->access(str_in('order'));
        if ($o['payment_status'] !== 'paid') {
            try {
                if (!Payments::stripeVerify($o, str_in('sid'))) flash('info', 'Your payment is still being confirmed. This page will update shortly.');
            } catch (\Throwable $e) {
                flash('error', 'Could not verify the payment yet. If you were charged, your order will update automatically.');
            }
        }
        redirect('order/' . $o['number'] . '?t=' . $o['token']);
    }

    public function stripeWebhook(): void
    {
        $ok = Payments::stripeWebhook((string) file_get_contents('php://input'), $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '');
        http_response_code($ok ? 200 : 400);
        echo $ok ? 'ok' : 'invalid';
    }

    public function paypalReturn(): void
    {
        $o = $this->access(str_in('order'));
        if ($o['payment_status'] !== 'paid') {
            try {
                if (!Payments::paypalCapture($o, str_in('token'))) flash('error', 'PayPal could not confirm this payment.');
            } catch (\Throwable $e) {
                flash('error', $e->getMessage());
            }
        }
        redirect('order/' . $o['number'] . '?t=' . $o['token']);
    }

    public function cancel(): void
    {
        $o = $this->access(str_in('order'));
        flash('info', 'Payment was cancelled. Your order is saved — you can pay again any time.');
        redirect('order/' . $o['number'] . '?t=' . $o['token']);
    }

    public function download(array $a): void
    {
        $it = Db::one('SELECT oi.*, o.token, o.user_id, o.payment_status, o.status FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.id = ?', [(int) $a['id']]);
        $tok = str_in('t');
        $u = auth_user();
        if (!$it || $it['type'] !== 'file' || $it['payment_status'] !== 'paid' || in_array($it['status'], ['cancelled', 'refunded'], true)) abort(404);
        if (!(($tok !== '' && hash_equals($it['token'], $tok)) || ($u && (int) $u['id'] === (int) $it['user_id']))) abort(404);
        $name = basename((string) $it['file_path']);
        $path = STORAGE . '/files/' . $name;
        if (!$name || !is_file($path)) abort(404, 'File missing. Please contact support.');
        $dl = preg_replace('/^[a-f0-9]{20}_/', '', $name);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . addslashes($dl) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }
}
