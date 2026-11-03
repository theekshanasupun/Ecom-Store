<?php

namespace App\Http\Controllers;

use App\Services\Cart;
use App\Services\GiftCards;
use Illuminate\Http\Request;

/** Storefront gift cards: buy, check balance. */
class GiftCardController extends Controller
{
    public function index()
    {
        abort_unless(GiftCards::enabled(), 404);
        title('Gift cards');
        meta_desc('Send a '.setting('store_name').' gift card by email — instant or scheduled.');

        return page('giftcards/buy', ['amounts' => GiftCards::amounts(), 'designs' => GiftCards::designs()]);
    }

    public function add(Request $r)
    {
        abort_unless(GiftCards::enabled(), 404);
        $amount = $r->filled('custom') ? to_cents($r->input('custom')) : (int) $r->input('amount');
        $res = Cart::addGiftCard([
            'amount' => $amount, 'design' => $r->input('design'), 'to_name' => $r->input('to_name'), 'to_email' => $r->input('to_email'),
            'from' => $r->input('from'), 'msg' => $r->input('msg'), 'deliver_at' => $r->input('deliver_at'),
        ]);
        if (! $res['ok']) {
            keep_old($r->all());
            flash('error', $res['error']);

            return redirect('/gift-cards');
        }
        \App\Support\Marketing::trackCart();
        flash('success', $res['name'].' added to your cart.');

        return redirect($r->input('buy_now') ? '/checkout' : '/cart');
    }

    public function balance()
    {
        title('Gift card balance');

        return page('giftcards/balance', ['card' => null, 'error' => null]);
    }

    public function balancePost(Request $r)
    {
        title('Gift card balance');
        [$g, $err] = GiftCards::check((string) $r->input('code'));
        // expired / used cards still show their (zero) balance, but never reveal anything else
        $card = $g ?: \App\Models\GiftCard::where('code', GiftCards::normalise((string) $r->input('code')))->first();

        return page('giftcards/balance', ['card' => $card, 'error' => $g ? null : $err]);
    }
}
