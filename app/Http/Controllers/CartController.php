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

        return $extra + ['ok' => true, 'free' => Cart::freeBar(), 'count' => $sum['count'], 'html' => partial('partials/cart_drawer', ['sum' => $sum]), 'subtotal' => money($sum['subtotal'])];
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
        \App\Support\Marketing::trackCart();
        if ($r->input('buy_now')) return redirect('/checkout');
        if ($r->expectsJson()) return response()->json($this->payload(['message' => $res['message'], 'name' => $res['name']]));
        flash('success', $res['name'].' added to your cart.');

        return back();
    }

    /** Add several products at once (bundles, "frequently bought together", checkout order-bumps). */
    public function addMany(Request $r)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $r->input('ids')))));
        $added = 0;
        $errors = [];
        foreach (array_slice($ids, 0, 12) as $id) {
            $res = Cart::add($id, 1, (array) ($r->input('opt.'.$id) ?? []));
            $res['ok'] ? $added++ : $errors[] = $res['error'];
        }
        if ($oid = (int) $r->input('offer')) {
            try { \App\Models\Offer::whereKey($oid)->increment('adds'); } catch (\Throwable) {}
        }
        \App\Support\Marketing::trackCart();
        if (! $added) {
            if ($r->expectsJson()) return response()->json(['ok' => false, 'error' => $errors[0] ?? 'Nothing could be added.'], 422);
            flash('error', $errors[0] ?? 'Nothing could be added.');

            return back();
        }
        $msg = $added === 1 ? 'Added to cart' : $added.' items added to cart';
        if ($r->expectsJson()) return response()->json($this->payload(['message' => $msg, 'name' => '', 'warnings' => $errors]));
        flash('success', $msg.'.');

        return back();
    }

    public function update(Request $r)
    {
        Cart::setQty((string) $r->input('key'), (int) $r->input('qty'));
        \App\Support\Marketing::trackCart();

        return $r->expectsJson() ? response()->json($this->payload()) : redirect('/cart');
    }

    public function remove(Request $r)
    {
        Cart::remove((string) $r->input('key'));
        \App\Support\Marketing::trackCart();

        return $r->expectsJson() ? response()->json($this->payload()) : redirect('/cart');
    }

    public function coupon(Request $r)
    {
        $code = trim((string) $r->input('code'));
        Cart::setCoupon($code ?: null);
        \App\Support\Marketing::trackCart();
        $sum = Cart::summary();
        if (! $r->expectsJson()) {
            if ($code && $sum['coupon_error']) flash('error', $sum['coupon_error']);
            elseif ($code && $sum['coupon']) flash('success', 'Code '.$sum['coupon']->code.' applied.');

            return back();
        }

        return response()->json($this->payload(['error' => $sum['coupon_error']]));
    }

    /** Abandoned-cart email link: refill the cart and continue. */
    public function recover(Request $r, string $token)
    {
        $row = \App\Models\SavedCart::where('token', $token)->where('status', 'open')->first();
        if (! $row) {
            flash('info', 'That cart link has expired — here is your current cart.');

            return redirect('/cart');
        }
        Cart::restore($row->lines()['lines'] ?? [], $r->query('c') ?: ($row->lines()['coupon'] ?? null));
        session(['saved_cart' => $row->token, 'capture_email' => $row->email]);
        $row->update(['last_activity_at' => now()]);
        flash('success', 'Welcome back! Your cart is ready.');

        return redirect('/cart');
    }
}
