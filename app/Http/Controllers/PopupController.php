<?php

namespace App\Http\Controllers;

use App\Models\Popup;
use App\Support\Marketing;
use Illuminate\Http\Request;

class PopupController extends Controller
{
    public function track(Request $r)
    {
        $p = Popup::find((int) $r->input('id'));
        if ($p) {
            match ($r->input('event')) {
                'view' => $p->increment('views'),
                'click' => $p->increment('conversions'),
                default => null,
            };
        }

        return response()->json(['ok' => true]);
    }

    /** Newsletter / popup / builder-block signup. */
    public function subscribe(Request $r)
    {
        if ($r->filled('website')) return response()->json(['ok' => true]); // honeypot
        $email = mb_strtolower(trim((string) $r->input('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) return response()->json(['ok' => false, 'error' => 'Enter a valid email address.'], 422);
        $p = $r->input('popup') ? Popup::find((int) $r->input('popup')) : null;
        Marketing::subscribe($email, null, $p ? 'popup:'.$p->id : 'form');
        Marketing::rememberEmail($email);
        $p?->increment('conversions');

        return response()->json(['ok' => true, 'message' => $p?->success_text ?: 'Thanks — you are subscribed!', 'coupon' => $p?->coupon_code]);
    }

    public function unsubscribe(string $token)
    {
        $s = \App\Models\Subscriber::where('token', $token)->first();
        $s?->update(['status' => 'unsubscribed']);
        title('Unsubscribed');

        return page('message', ['heading' => $s ? 'You are unsubscribed' : 'Link not recognised', 'text' => $s ? 'We will not send you marketing emails any more. Order confirmations still arrive.' : 'This unsubscribe link is invalid.']);
    }
}
