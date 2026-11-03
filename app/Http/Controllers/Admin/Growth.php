<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\Coupon;
use App\Models\EmailLog;
use App\Models\SavedCart;
use App\Models\Subscriber;
use App\Support\Marketing;
use App\Support\Settings;
use Illuminate\Http\Request;

/** Email automations, abandoned carts and subscribers. */
class Growth extends Controller
{
    public function automations()
    {
        $rows = Automation::orderBy('id')->get()->all();
        $coupons = Coupon::where('status', 'active')->orderBy('code')->pluck('code')->all();
        $stats = [
            'open' => SavedCart::where('status', 'open')->whereNotNull('email')->count(),
            'open_value' => (int) SavedCart::where('status', 'open')->whereNotNull('email')->sum('total'),
            'recovered' => SavedCart::where('status', 'recovered')->count(),
            'recovered_value' => (int) SavedCart::where('status', 'recovered')->sum('total'),
            'sent30' => EmailLog::where('created_at', '>=', now()->subDays(30))->count(),
            'subs' => Subscriber::where('status', 'subscribed')->count(),
        ];
        $recent = EmailLog::orderByDesc('id')->limit(12)->get()->all();
        title('Email automations');

        return admin_page('automations', compact('rows', 'coupons', 'stats', 'recent'));
    }

    public function automationSave(Request $r, int $id)
    {
        $a = Automation::findOrFail($id);
        $a->update([
            'enabled' => $r->boolean('enabled'), 'delay_hours' => max(0, min(24 * 365, (int) $r->input('delay_hours'))),
            'subject' => mb_substr(trim((string) $r->input('subject')) ?: $a->subject, 0, 190), 'body' => trim((string) $r->input('body')) ?: $a->body,
            'coupon_code' => strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $r->input('coupon_code'))) ?: null,
        ]);
        flash('success', $a->name.' saved'.($a->enabled ? ' and switched on.' : '.'));

        return redirect('/admin/automations#a'.$a->id);
    }

    public function automationToggle(int $id)
    {
        $a = Automation::findOrFail($id);
        $a->update(['enabled' => ! $a->enabled]);

        return redirect('/admin/automations#a'.$a->id);
    }

    public function automationTest(Request $r, int $id)
    {
        $a = Automation::findOrFail($id);
        $to = trim((string) $r->input('to')) ?: (string) auth()->user()->email;
        $ok = Marketing::test($a, $to);
        flash($ok ? 'success' : 'error', $ok ? "Test email sent to $to." : 'Sending failed — check Settings → Email.');

        return redirect('/admin/automations#a'.$a->id);
    }

    public function settings(Request $r)
    {
        Settings::set('automations_autorun', $r->boolean('autorun') ? '1' : '0');
        flash('success', 'Automation settings saved.');

        return redirect('/admin/automations');
    }

    public function runNow()
    {
        $n = Marketing::runDue(100);
        flash('success', "Automations ran — $n email(s) sent.");

        return redirect('/admin/automations');
    }

    public function carts(Request $r)
    {
        $status = in_array($r->query('status'), ['open', 'recovered', 'ordered'], true) ? $r->query('status') : '';
        $p = SavedCart::when($status, fn ($q) => $q->where('status', $status))->orderByDesc('last_activity_at')->paginate(25)->withQueryString();
        $rows = $p->items();
        $page = $p->currentPage();
        $pages = $p->lastPage();
        title('Abandoned carts');

        return admin_page('carts', compact('rows', 'status', 'page', 'pages'));
    }

    public function cartAction(Request $r, int $id)
    {
        $c = SavedCart::findOrFail($id);
        if ($r->input('do') === 'delete') {
            $c->delete();
            flash('success', 'Cart removed.');
        } elseif ($r->input('do') === 'remind') {
            $a = Automation::where('key', 'abandoned_'.min(3, $c->reminders + 1))->first() ?? Automation::where('key', 'abandoned_1')->first();
            [$sub, $html] = Marketing::compose($a, ['email' => $c->email, 'name' => $c->name, 'lines' => $c->lines()['lines'] ?? [], 'total' => $c->total, 'cart_url' => url('cart/recover/'.$c->token)]);
            $ok = \App\Support\Mailer::send((string) $c->email, $sub, $html);
            if ($ok) $c->update(['reminders' => $c->reminders + 1, 'last_reminder_at' => now()]);
            flash($ok ? 'success' : 'error', $ok ? 'Reminder sent.' : 'Sending failed — check Settings → Email.');
        }

        return redirect()->back();
    }

    public function subscribers(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $p = Subscriber::when($q !== '', fn ($x) => $x->where('email', 'like', "%$q%")->orWhere('name', 'like', "%$q%"))->orderByDesc('id')->paginate(30)->withQueryString();
        $rows = $p->items();
        $page = $p->currentPage();
        $pages = $p->lastPage();
        $total = $p->total();
        title('Subscribers');

        return admin_page('subscribers', compact('rows', 'q', 'page', 'pages', 'total'));
    }

    public function subscriberAdd(Request $r)
    {
        $lines = preg_split('/[\r\n,;]+/', (string) $r->input('emails'));
        $n = 0;
        foreach ($lines as $e) if (Marketing::subscribe(trim($e), null, 'admin')) $n++;
        flash($n ? 'success' : 'error', $n ? "$n address(es) added." : 'No valid email addresses found.');

        return redirect('/admin/subscribers');
    }

    public function subscriberBulk(Request $r)
    {
        $ids = array_values(array_filter(array_map('intval', (array) $r->input('ids'))));
        $act = (string) $r->input('action');
        if ($ids && $act === 'delete') Subscriber::whereIn('id', $ids)->delete();
        elseif ($ids && in_array($act, ['subscribe', 'unsubscribe'], true)) Subscriber::whereIn('id', $ids)->update(['status' => $act === 'subscribe' ? 'subscribed' : 'unsubscribed']);
        else { flash('error', 'Select subscribers and an action.'); return redirect()->back(); }
        flash('success', count($ids).' subscriber(s) updated.');

        return redirect()->back();
    }

    public function export()
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['email', 'name', 'status', 'source', 'joined']);
        foreach (Subscriber::orderBy('id')->cursor() as $s) {
            // neutralise spreadsheet formula injection
            $safe = fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'".$v : $v;
            fputcsv($out, [$safe($s->email), $safe($s->name), $s->status, $safe($s->source), $s->created_at]);
        }
        rewind($out);

        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="subscribers-'.date('Ymd').'.csv"']);
    }
}
