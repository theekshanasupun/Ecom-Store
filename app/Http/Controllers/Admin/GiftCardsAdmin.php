<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Models\GiftCardLog;
use App\Services\GiftCards;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Gift card management: issue, bulk-generate, adjust, void, settings and activity. */
class GiftCardsAdmin extends Controller
{
    public function index(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $st = (string) $r->query('status');
        $p = GiftCard::query()
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('code', 'like', '%'.strtoupper($q).'%')->orWhere('recipient_email', 'like', "%$q%")->orWhere('recipient_name', 'like', "%$q%")->orWhere('sender_name', 'like', "%$q%")))
            ->when(in_array($st, ['active', 'used', 'void'], true), fn ($x) => $x->where('status', $st))
            ->orderByDesc('id')->paginate(25)->withQueryString();
        $rows = $p->items();
        $page = $p->currentPage();
        $pages = $p->lastPage();
        $stats = [
            'issued' => (int) GiftCard::sum('initial'), 'outstanding' => (int) GiftCard::where('status', 'active')->sum('balance'),
            'redeemed' => (int) GiftCard::where('status', '<>', 'void')->sum('initial') - (int) GiftCard::where('status', '<>', 'void')->sum('balance'),
            'count' => GiftCard::count(), 'active' => GiftCard::where('status', 'active')->count(), 'scheduled' => GiftCard::whereNull('sent_at')->whereNotNull('deliver_at')->where('deliver_at', '>', now())->count(),
        ];
        $activity = GiftCardLog::orderByDesc('id')->limit(15)->get()->all();
        $codes = GiftCard::whereIn('id', array_column($activity, 'gift_card_id'))->pluck('code', 'id')->all();
        title('Gift cards');

        return admin_page('giftcards', compact('rows', 'page', 'pages', 'q', 'st', 'stats', 'activity', 'codes'));
    }

    public function show(int $id)
    {
        $g = GiftCard::findOrFail($id);
        $log = $g->log()->get()->all();
        title('Gift card '.$g->masked());

        return admin_page('giftcard_show', compact('g', 'log'));
    }

    public function issue(Request $r)
    {
        $amount = to_cents($r->input('amount'));
        $qty = max(1, min(100, (int) $r->input('qty', 1)));
        if ($amount < 100 || $amount > 1000000) {
            flash('error', 'Enter an amount between 1.00 and 10,000.00.');
            keep_old($r->all());

            return redirect('/admin/gift-cards?new=1');
        }
        $email = mb_strtolower(trim((string) $r->input('recipient_email')));
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'That recipient email is not valid.');
            keep_old($r->all());

            return redirect('/admin/gift-cards?new=1');
        }
        foreach (['expires_at', 'deliver_at'] as $dk) {
            if ($r->input($dk) && ! strtotime((string) $r->input($dk))) { flash('error', 'That date is not valid.'); keep_old($r->all()); return redirect('/admin/gift-cards?new=1'); }
        }
        $exp = null;
        if ($r->input('expires_at')) $exp = Carbon::parse($r->input('expires_at').' 23:59:59');
        elseif ((string) $r->input('expiry') === 'never') $exp = false;
        $deliver = $r->input('deliver_at') ? Carbon::parse($r->input('deliver_at').' 08:00:00') : null;
        $made = [];
        for ($i = 0; $i < $qty; $i++) {
            $d = ['amount' => $amount, 'design' => $r->input('design'), 'note' => mb_substr(trim((string) $r->input('note')), 0, 190) ?: null, 'created_via' => $qty > 1 ? 'import' : 'admin'];
            if ($exp !== null) $d['expires_at'] = $exp ?: null;
            if ($qty === 1) {
                $d += ['recipient_name' => trim((string) $r->input('recipient_name')) ?: null, 'recipient_email' => $email ?: null, 'sender_name' => trim((string) $r->input('sender_name')) ?: (string) setting('store_name'),
                    'message' => trim((string) $r->input('message')) ?: null, 'deliver_at' => $deliver, 'send' => $r->boolean('send') && $email !== ''];
            }
            $made[] = GiftCards::issue($d, 'admin:'.auth()->user()->name);
        }
        if ($qty === 1) {
            $g = $made[0];
            flash('success', 'Gift card '.$g->code.' ('.money($g->initial).') created'.($g->sent_at ? ' and emailed to '.$g->recipient_email : ($g->deliver_at && $email ? ' — scheduled for '.$g->deliver_at->format('M j') : '')).'.');

            return redirect('/admin/gift-cards/'.$g->id);
        }
        flash('success', $qty.' gift cards of '.money($amount).' created. Use Export CSV to download the codes.');

        return redirect('/admin/gift-cards');
    }

    public function adjust(Request $r, int $id)
    {
        $g = GiftCard::findOrFail($id);
        $delta = to_cents($r->input('amount'));
        if ($r->input('dir') === 'remove') $delta = -$delta;
        if ($delta === 0) {
            flash('error', 'Enter an amount.');

            return redirect('/admin/gift-cards/'.$id);
        }
        GiftCards::adjust($g, $delta, trim((string) $r->input('reason')) ?: 'Manual adjustment', 'admin:'.auth()->user()->name);
        flash('success', 'Balance updated: '.money($g->balance));

        return redirect('/admin/gift-cards/'.$id);
    }

    public function status(Request $r, int $id)
    {
        $g = GiftCard::findOrFail($id);
        $to = $r->input('to') === 'void' ? 'void' : 'active';
        $g->update(['status' => $to === 'active' && $g->balance <= 0 ? 'used' : $to]);
        GiftCards::log($g, 0, $to === 'void' ? 'Card voided' : 'Card re-activated', null, 'admin:'.auth()->user()->name);
        flash('success', $to === 'void' ? 'Card voided — it can no longer be used.' : 'Card re-activated.');

        return redirect('/admin/gift-cards/'.$id);
    }

    public function resend(Request $r, int $id)
    {
        $g = GiftCard::findOrFail($id);
        $to = mb_strtolower(trim((string) $r->input('email'))) ?: $g->recipient_email;
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter the email address to send this card to.');

            return redirect('/admin/gift-cards/'.$id);
        }
        if ($to !== $g->recipient_email) $g->update(['recipient_email' => $to]);
        $ok = GiftCards::send($g);
        flash($ok ? 'success' : 'error', $ok ? 'Gift card emailed to '.$to.'.' : 'Sending failed — check Settings → Email.');

        return redirect('/admin/gift-cards/'.$id);
    }

    public function settings(Request $r)
    {
        $amounts = array_values(array_unique(array_filter(array_map(fn ($x) => (float) $x, preg_split('/[\s,;]+/', (string) $r->input('gc_amounts'))), fn ($x) => $x > 0)));
        sort($amounts);
        Settings::setMany([
            'gc_on' => $r->boolean('gc_on') ? '1' : '0', 'gc_amounts' => implode(',', $amounts) ?: '25,50,100',
            'gc_custom' => $r->boolean('gc_custom') ? '1' : '0', 'gc_min' => max(1, (float) $r->input('gc_min', 10)), 'gc_max' => max(1, (float) $r->input('gc_max', 500)),
            'gc_expiry_months' => max(0, (int) $r->input('gc_expiry_months')), 'gc_designs' => implode(',', array_values(array_intersect((array) $r->input('gc_designs'), array_keys(GiftCards::DESIGNS)))) ?: 'classic',
            'gc_email_subject' => mb_substr(trim((string) $r->input('gc_email_subject')), 0, 190) ?: 'You received a {store} gift card', 'gc_terms' => mb_substr(trim((string) $r->input('gc_terms')), 0, 1000),
        ]);
        flash('success', 'Gift card settings saved.');

        return redirect('/admin/gift-cards?tab=settings');
    }

    public function export()
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['code', 'initial', 'balance', 'status', 'expires', 'recipient', 'recipient_email', 'sender', 'created', 'note']);
        $safe = fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'".$v : $v;
        foreach (GiftCard::orderBy('id')->cursor() as $g) {
            fputcsv($out, [$g->code, from_cents($g->initial), from_cents($g->balance), $g->status, $g->expires_at?->format('Y-m-d'), $safe($g->recipient_name), $safe($g->recipient_email), $safe($g->sender_name), $g->created_at, $safe($g->note)]);
        }
        rewind($out);

        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="gift-cards-'.date('Ymd').'.csv"']);
    }
}
