<?php
namespace App\Controllers;

use App\Core\View;
use App\Services\Cart;
use App\Services\Catalog;

final class CartController
{
    private function payload(array $extra = []): array
    {
        $sum = Cart::summary();
        return $extra + [
            'ok' => true,
            'count' => $sum['count'],
            'html' => partial('partials/cart_drawer', ['sum' => $sum]),
            'subtotal' => money($sum['subtotal']),
        ];
    }

    public function show(): void
    {
        $sum = Cart::summary();
        $cats = Catalog::categories();
        if (input('partial')) {
            echo partial('partials/cart_body', compact('sum', 'cats'));
            return;
        }
        title('Your cart');
        View::$meta['step'] = 1;
        View::page('cart', compact('sum', 'cats'));
    }

    public function mini(): void
    {
        json_out($this->payload());
    }

    public function add(): void
    {
        $pid = int_in('product_id');
        $qty = max(1, int_in('qty', 1));
        $opts = [];
        foreach ((array) input('opt', []) as $k => $v) {
            if (is_string($k) && is_string($v)) $opts[$k] = $v;
        }
        $r = Cart::add($pid, $qty, $opts);
        if (!$r['ok']) {
            if (is_ajax()) json_out($r, 422);
            flash('error', $r['error']);
            back('cart');
        }
        if (input('buy_now')) {
            redirect('checkout');
        }
        if (is_ajax()) {
            json_out($this->payload(['message' => $r['message'], 'name' => $r['name']]));
        }
        flash('success', $r['name'] . ' added to your cart.');
        back('cart');
    }

    public function update(): void
    {
        Cart::setQty(str_in('key'), int_in('qty'));
        is_ajax() ? json_out($this->payload()) : redirect('cart');
    }

    public function remove(): void
    {
        Cart::remove(str_in('key'));
        is_ajax() ? json_out($this->payload()) : redirect('cart');
    }

    public function coupon(): void
    {
        $code = str_in('code');
        Cart::setCoupon($code ?: null);
        $sum = Cart::summary();
        if ($code && $sum['coupon_error']) {
            flash('error', $sum['coupon_error']);
        } elseif ($code && $sum['coupon']) {
            flash('success', 'Code ' . $sum['coupon']['code'] . ' applied.');
        }
        is_ajax() ? json_out($this->payload(['error' => $sum['coupon_error']])) : back('cart');
    }
}
