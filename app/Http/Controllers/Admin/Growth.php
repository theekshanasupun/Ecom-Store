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
