<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Support\Marketing;
use Illuminate\Http\Request;

/** Front-end newsletter signup (footer form, builder block). */
class NewsletterController extends Controller
{
    private static function sig(string $t): string
    {
        return hash_hmac('sha256', 'nl|'.$t, (string) config('app.key'));
    }

    /** Hidden anti-spam fields: honeypot + signed timestamp. */
    public static function fields(): string
    {
        $t = (string) time();

        return '<input type="text" name="website" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true"><input type="hidden" name="_t" value="'.$t.'"><input type="hidden" name="_s" value="'.self::sig($t).'">';
    }

    public function subscribe(Request $r)
    {
        if ($r->filled('website')) return response()->json(['ok' => true, 'message' => 'Thanks — you are subscribed!']);
        $t = (string) $r->input('_t');
        if (! hash_equals(self::sig($t), (string) $r->input('_s')) || (time() - (int) $t) < 2) {
            return response()->json(['ok' => false, 'error' => 'Please try again in a moment.'], 422);
        }
        $email = mb_strtolower(trim((string) $r->input('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) return response()->json(['ok' => false, 'error' => 'Enter a valid email address.'], 422);
        Marketing::subscribe($email, trim((string) $r->input('name')) ?: null, 'footer');
        Marketing::rememberEmail($email);

        return response()->json(['ok' => true, 'message' => 'Thanks — you are subscribed!']);
    }

    /** Account page preference. */
    public function preference(Request $r)
    {
        $u = $r->user();
        $s = Subscriber::where('email', mb_strtolower($u->email))->first();
        if ($r->boolean('newsletter')) {
            if ($s) $s->update(['status' => 'subscribed']);
            else Marketing::subscribe($u->email, $u->name, 'account');
        } elseif ($s) {
            $s->update(['status' => 'unsubscribed']);
        }
        flash('success', 'Newsletter preference saved.');

        return redirect('/account');
    }
}
