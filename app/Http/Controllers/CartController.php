<?php

namespace App\Http\Controllers;

use App\Services\Cart;
use App\Services\Catalog;
use Illuminate\Http\Request;

class CartController extends Controller
{
    private function payload(array $extra = []): array
    {
        $sum = Cart::summary();

        return $extra + ['ok' => true, 'count' => $sum['count'], 'html' => partial('partials/cart_drawer', ['sum' => $sum]), 'subtotal' => money($sum['subtotal'])];
    }

    public function show(Request $r)
    {
        $sum = Cart::summary();
        $cats = Catalog::categories();
        if ($r->query('partial')) {
            return response(partial('partials/cart_body', compact('sum', 'cats')));
        }
        title('Your cart');
        \App\Support\Meta::$data['step'] = 1;

        return page('cart', compact('sum', 'cats'));
    }

    public function mini()
    {
        return response()->json($this->payload());
    }

    public function add(Request $r)
    {
        $opts = collect((array) $r->input('opt', []))->filter(fn ($v, $k) => is_string($v) && is_string($k))->all();
        $res = Cart::add((int) $r->input('product_id'), max(1, (int) $r->input('qty', 1)), $opts);
        if (! $res['ok']) {
            if ($r->expectsJson()) return response()->json($res, 422);
            flash('error', $res['error']);

            return back();
        }
        if ($r->input('buy_now')) return redirect('/checkout');
        if ($r->expectsJson()) return response()->json($this->payload(['message' => $res['message'], 'name' => $res['name']]));
        flash('success', $res['name'].' added to your cart.');

        return back();
    }

    public function update(Request $r)
    {
        Cart::setQty((string) $r->input('key'), (int) $r->input('qty'));

        return $r->expectsJson() ? response()->json($this->payload()) : redirect('/cart');
    }

    public function remove(Request $r)
    {
        Cart::remove((string) $r->input('key'));

        return $r->expectsJson() ? response()->json($this->payload()) : redirect('/cart');
    }

    public function coupon(Request $r)
    {
        $code = trim((string) $r->input('code'));
        Cart::setCoupon($code ?: null);
        $sum = Cart::summary();
        if (! $r->expectsJson()) {
            if ($code && $sum['coupon_error']) flash('error', $sum['coupon_error']);
            elseif ($code && $sum['coupon']) flash('success', 'Code '.$sum['coupon']->code.' applied.');

            return back();
        }

        return response()->json($this->payload(['error' => $sum['coupon_error']]));
    }
}
