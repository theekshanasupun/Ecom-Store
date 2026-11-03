<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Automation;
use App\Models\AutomationQueue;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\EmailLog;
use App\Models\Product;
use App\Models\SavedCart;
use App\Models\Subscriber;
use App\Support\EmailBlocks;
use App\Support\Marketing;
use App\Support\Settings;
use App\Support\Triggers;
use Illuminate\Http\Request;

/** Email automations: built-in flows + custom trigger based automations with a drag & drop email builder. */
class Automations extends Controller
{
    private const UNITS = ['minutes' => 1, 'hours' => 60, 'days' => 1440];

    public function index()
    {
        $rows = Automation::orderByRaw("case when type = 'custom' then 1 else 0 end")->orderBy('id')->get()->all();
        $coupons = Coupon::where('status', 'active')->orderBy('code')->pluck('code')->all();
        $q = AutomationQueue::selectRaw('automation_id, status, count(*) n')->groupBy('automation_id', 'status')->get();
        $queue = [];
        foreach ($q as $r) $queue[$r->automation_id][$r->status] = (int) $r->n;
        $stats = [
            'open' => SavedCart::where('status', 'open')->whereNotNull('email')->count(),
            'open_value' => (int) SavedCart::where('status', 'open')->whereNotNull('email')->sum('total'),
            'recovered' => SavedCart::where('status', 'recovered')->count(),
            'recovered_value' => (int) SavedCart::where('status', 'recovered')->sum('total'),
            'sent30' => EmailLog::where('created_at', '>=', now()->subDays(30))->count(),
            'subs' => Subscriber::where('status', 'subscribed')->count(),
            'pending' => AutomationQueue::where('status', 'pending')->count(),
        ];
        $recent = EmailLog::orderByDesc('id')->limit(12)->get()->all();
        title('Email automations');

        return admin_page('automations', compact('rows', 'coupons', 'stats', 'recent', 'queue'));
    }

    public function form(?int $id = null)
    {
        $a = $id ? Automation::where('type', 'custom')->findOrFail($id) : null;
        $products = Product::orderBy('name')->limit(500)->get(['id', 'name', 'sku'])->all();
        $cats = Category::orderBy('sort_order')->get(['id', 'name'])->all();
        $coupons = Coupon::where('status', 'active')->orderBy('code')->pluck('code')->all();
        $queue = $a ? AutomationQueue::where('automation_id', $a->id)->orderByDesc('id')->limit(20)->get()->all() : [];
        $qstats = $a ? AutomationQueue::where('automation_id', $a->id)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all() : [];
        title($a ? 'Automation · '.$a->name : 'New automation');

        return admin_page('automation_form', ['a' => $a, 'products' => $products, 'cats' => $cats, 'coupons' => $coupons, 'queue' => $queue, 'qstats' => $qstats, 'events' => Triggers::EVENTS]);
    }

    /** Create / update a custom automation. */
    public function saveCustom(Request $r, ?int $id = null)
    {
        $a = $id ? Automation::where('type', 'custom')->findOrFail($id) : null;
        $event = (string) $r->input('trigger_event');
        $name = mb_substr(trim((string) $r->input('name')), 0, 150);
        $back = $a ? "/admin/automations/{$a->id}/edit" : '/admin/automations/new';
        if ($name === '' || ! isset(Triggers::EVENTS[$event])) {
            flash('error', 'Give the automation a name and choose what triggers it.');
            keep_old($r->all());

            return redirect($back);
        }
        $old = $a ? $a->steps() : [];
        $steps = [];
        foreach ((array) $r->input('steps', []) as $s) {
            if (count($steps) >= 5) break;
            $mult = self::UNITS[$s['unit'] ?? 'hours'] ?? 60;
            $orig = isset($s['orig']) && $s['orig'] !== '' ? (int) $s['orig'] : null;
            $steps[] = [
                'delay_minutes' => max(0, min(60 * 24 * 365, (int) round(((float) ($s['delay'] ?? 0)) * $mult))),
                'subject' => mb_substr(trim((string) ($s['subject'] ?? '')), 0, 190), 'preheader' => mb_substr(trim((string) ($s['preheader'] ?? '')), 0, 190),
                'blocks' => $orig !== null && isset($old[$orig]) ? ($old[$orig]['blocks'] ?? []) : [],
            ];
        }
        if (! $steps) $steps[] = ['delay_minutes' => 0, 'subject' => $name, 'preheader' => '', 'blocks' => []];
        $cond = array_filter([
            'min_total' => to_cents($r->input('min_total', 0)) ?: null, 'max_total' => to_cents($r->input('max_total', 0)) ?: null,
            'first_order' => $r->boolean('first_order') ?: null,
            'product_ids' => array_values(array_filter(array_map('intval', (array) $r->input('product_ids')))) ?: null,
            'category_ids' => array_values(array_filter(array_map('intval', (array) $r->input('category_ids')))) ?: null,
            'payment_method' => in_array($r->input('payment_method'), ['stripe', 'paypal', 'bank', 'cod'], true) ? $r->input('payment_method') : null,
            'country' => mb_substr(trim((string) $r->input('country')), 0, 60) ?: null,
            'credit_cents' => to_cents($r->input('credit', 0)) ?: null,
        ], fn ($v) => $v !== null);
        $data = [
            'name' => $name, 'type' => 'custom', 'trigger_event' => $event, 'trigger_label' => Triggers::EVENTS[$event],
            'conditions' => $cond ? json_encode($cond) : null, 'design' => json_encode(['steps' => $steps], JSON_UNESCAPED_UNICODE),
            'coupon_code' => strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $r->input('coupon_code'))) ?: null,
            'enabled' => $r->boolean('enabled'), 'delay_hours' => 0, 'subject' => $steps[0]['subject'], 'body' => $a->body ?? '',
        ];
        if ($a) {
            $a->update($data);
        } else {
            $a = Automation::create($data + ['key' => 'custom_'.bin2hex(random_bytes(6))]);
        }
        flash('success', 'Automation saved. Use “Design email” on a step to build its email.');

        return redirect("/admin/automations/{$a->id}/edit");
    }

    /** Save a built-in (system) automation (text version + switch). */
    public function save(Request $r, int $id)
    {
        $a = Automation::findOrFail($id);
        if ($a->isCustom()) return $this->saveCustom($r, $id);
        $a->update([
            'enabled' => $r->boolean('enabled'), 'delay_hours' => max(0, min(24 * 365, (int) $r->input('delay_hours'))),
            'subject' => mb_substr(trim((string) $r->input('subject')) ?: $a->subject, 0, 190), 'body' => trim((string) $r->input('body')) ?: $a->body,
            'coupon_code' => strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $r->input('coupon_code'))) ?: null,
        ]);
        flash('success', $a->name.' saved'.($a->enabled ? ' and switched on.' : '.'));

        return redirect('/admin/automations#a'.$a->id);
    }

    public function toggle(int $id)
    {
        $a = Automation::findOrFail($id);
        $a->update(['enabled' => ! $a->enabled]);

        return redirect()->back();
    }

    public function test(Request $r, int $id)
    {
        $a = Automation::findOrFail($id);
        $to = trim((string) $r->input('to')) ?: (string) auth()->user()->email;
        if ($a->isCustom()) {
            $steps = $a->steps();
            $i = max(0, min(count($steps) - 1, (int) $r->input('step', 0)));
            [$subject, $html] = Marketing::composeStep($a, $steps[$i] ?? [], EmailBlocks::sampleCtx($to), $to, 'Alex Example');
            $ok = \App\Support\Mailer::send($to, '[Test] '.$subject, $html);
        } else {
            $ok = Marketing::test($a, $to);
        }
        flash($ok ? 'success' : 'error', $ok ? "Test email sent to $to." : 'Sending failed — check Settings → Email.');

        return redirect()->back();
    }

    public function duplicate(int $id)
    {
        $a = Automation::where('type', 'custom')->findOrFail($id);
        $c = $a->replicate(['sent', 'recovered', 'revenue']);
        $c->key = 'custom_'.bin2hex(random_bytes(6));
        $c->name = mb_substr($a->name.' (copy)', 0, 150);
        $c->enabled = false;
        $c->save();
        flash('success', 'Automation duplicated (switched off).');

        return redirect("/admin/automations/{$c->id}/edit");
    }

    public function delete(int $id)
    {
        $a = Automation::where('type', 'custom')->findOrFail($id);
        AutomationQueue::where('automation_id', $a->id)->delete();
        $a->delete();
        flash('success', 'Automation deleted.');

        return redirect('/admin/automations');
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

        return redirect()->back();
    }

    /* ---------------- builder ---------------- */

    public function design(Request $r, int $id)
    {
        $a = Automation::findOrFail($id);
        $i = max(0, (int) $r->query('step', 0));
        $steps = $a->steps();
        $step = $steps[$i] ?? null;
        if (! $step && ! $a->isCustom() && $i === 0) $step = ['blocks' => [], 'subject' => $a->subject, 'preheader' => (string) $a->preheader];
        abort_unless($step !== null, 404);
        $cfg = [
            'mode' => 'automation', 'id' => $a->id, 'step' => $i, 'title' => $a->name.($a->isCustom() && count($steps) > 1 ? ' · email '.($i + 1) : ''),
            'blocks' => EmailBlocks::decode(json_encode($step['blocks'] ?? [])), 'subject' => (string) ($step['subject'] ?? $a->subject), 'preheader' => (string) ($step['preheader'] ?? $a->preheader),
            'saveUrl' => url("admin/automations/{$a->id}/design"), 'backUrl' => url($a->isCustom() ? "admin/automations/{$a->id}/edit" : 'admin/automations#a'.$a->id),
        ];

        return EmailTools::builder($cfg);
    }

    public function designSave(Request $r, int $id)
    {
        $a = Automation::findOrFail($id);
        $i = max(0, (int) $r->input('step', 0));
        $d = json_decode((string) $a->design, true);
        $steps = is_array($d) && isset($d['steps']) ? array_values($d['steps']) : [];
        if (! $a->isCustom()) $i = 0;
        if (! isset($steps[$i])) {
            if ($a->isCustom()) return response()->json(['ok' => false, 'error' => 'Step not found'], 404);
            $steps[0] = ['delay_minutes' => 0];
        }
        $steps[$i]['blocks'] = json_decode(EmailBlocks::clean((string) $r->input('layout', '[]')), true);
        $steps[$i]['subject'] = mb_substr(trim((string) $r->input('subject')), 0, 190);
        $steps[$i]['preheader'] = mb_substr(trim((string) $r->input('preheader')), 0, 190);
        $upd = ['design' => json_encode(['steps' => $steps], JSON_UNESCAPED_UNICODE)];
        if ($i === 0 && $steps[0]['subject'] !== '') $upd['subject'] = $steps[0]['subject'];
        $a->update($upd);

        return response()->json(['ok' => true, 'saved' => now()->format('H:i:s')]);
    }

    public function resetDesign(int $id)
    {
        $a = Automation::where('type', 'system')->findOrFail($id);
        $a->update(['design' => null]);
        flash('success', 'Back to the plain-text version of “'.$a->name.'”.');

        return redirect('/admin/automations#a'.$a->id);
    }
}
