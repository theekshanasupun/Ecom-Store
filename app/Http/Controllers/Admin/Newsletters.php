<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Models\Newsletter;
use App\Models\Subscriber;
use App\Support\EmailBlocks;
use App\Support\Marketing;
use Illuminate\Http\Request;

/** Newsletters: design with the drag & drop builder, send now or schedule to a segment of subscribers. */
class Newsletters extends Controller
{
    public function index()
    {
        $rows = Newsletter::orderByDesc('id')->get()->all();
        $subs = Subscriber::where('status', 'subscribed')->count();
        $sent30 = EmailLog::where('automation', 'like', 'nl-%')->where('created_at', '>=', now()->subDays(30))->count();
        title('Newsletters');

        return admin_page('newsletters', compact('rows', 'subs', 'sent30'));
    }

    public function form(?int $id = null)
    {
        $n = $id ? Newsletter::findOrFail($id) : null;
        $reach = $n ? (clone $n->audience())->count() : Subscriber::where('status', 'subscribed')->count();
        $recent = $n ? EmailLog::where('automation', 'nl-'.$n->id)->orderByDesc('id')->limit(10)->get()->all() : [];
        title($n ? 'Newsletter · '.$n->name : 'New newsletter');

        return admin_page('newsletter_form', compact('n', 'reach', 'recent'));
    }

    public function save(Request $r)
    {
        $id = (int) $r->input('id');
        $n = $id ? Newsletter::findOrFail($id) : new Newsletter(['status' => 'draft']);
        if ($n->exists && in_array($n->status, ['sending', 'sent'], true)) {
            flash('error', 'This newsletter has already gone out. Duplicate it to send a new version.');

            return redirect('/admin/newsletters/'.$n->id);
        }
        $name = mb_substr(trim((string) $r->input('name')), 0, 150);
        if ($name === '') {
            flash('error', 'Give the newsletter a name.');
            keep_old($r->all());

            return redirect($id ? "/admin/newsletters/$id" : '/admin/newsletters/new');
        }
        $when = trim((string) $r->input('scheduled_at'));
        $n->fill([
            'name' => $name, 'subject' => mb_substr(trim((string) $r->input('subject')), 0, 190), 'preheader' => mb_substr(trim((string) $r->input('preheader')), 0, 190),
            'segment' => isset(Newsletter::SEGMENTS[$r->input('segment')]) ? $r->input('segment') : 'all',
            'scheduled_at' => $when !== '' && strtotime($when) ? date('Y-m-d H:i:s', strtotime($when)) : null,
        ]);
        $n->save();
        $act = (string) $r->input('do');
        if ($act === 'design') return redirect("/admin/newsletters/{$n->id}/design");
        if ($act === 'send') return $this->send($n->id);
        if ($act === 'schedule') {
            if (! $n->scheduled_at || $n->scheduled_at->isPast() || ! $n->blocks()) {
                flash('error', ! $n->blocks() ? 'Design the email first.' : 'Pick a future date and time to schedule.');

                return redirect('/admin/newsletters/'.$n->id);
            }
            $n->update(['status' => 'scheduled']);
            flash('success', 'Scheduled for '.fmt_date($n->scheduled_at).'.');

            return redirect('/admin/newsletters/'.$n->id);
        }
        flash('success', 'Newsletter saved.');

        return redirect('/admin/newsletters/'.$n->id);
    }

    public function send(int $id)
    {
        $n = Newsletter::findOrFail($id);
        if (! in_array($n->status, ['draft', 'scheduled'], true)) {
            flash('error', 'This newsletter is already sending or sent.');

            return redirect('/admin/newsletters/'.$n->id);
        }
        if (! $n->blocks() || trim((string) $n->subject) === '') {
            flash('error', 'Add a subject and design the email before sending.');

            return redirect('/admin/newsletters/'.$n->id);
        }
        Marketing::startNewsletter($n);
        $sent = Marketing::newsletterBatch($n->refresh(), 40);
        flash('success', 'Sending started — '.$sent.' delivered so far. The rest goes out automatically (keep this page open, or use cron / visitor traffic).');

        return redirect('/admin/newsletters/'.$n->id);
    }

    /** Sends the next batch (polled by the form page while a newsletter is going out). */
    public function batch(int $id)
    {
        $n = Newsletter::findOrFail($id);
        if ($n->status === 'scheduled' && $n->scheduled_at && $n->scheduled_at->isPast()) Marketing::startNewsletter($n);
        $sent = Marketing::newsletterBatch($n->refresh(), 40);
        $n->refresh();

        return response()->json(['ok' => true, 'status' => $n->status, 'sent' => $n->sent, 'failed' => $n->failed, 'recipients' => $n->recipients, 'batch' => $sent]);
    }

    public function pause(int $id)
    {
        $n = Newsletter::findOrFail($id);
        if (in_array($n->status, ['scheduled', 'sending'], true)) $n->update(['status' => 'draft']);
        flash('success', 'Newsletter moved back to draft.');

        return redirect('/admin/newsletters/'.$n->id);
    }

    public function duplicate(int $id)
    {
        $n = Newsletter::findOrFail($id);
        $c = $n->replicate(['sent', 'failed', 'recipients', 'last_id', 'sent_at', 'scheduled_at']);
        $c->name = mb_substr($n->name.' (copy)', 0, 150);
        $c->status = 'draft';
        $c->save();
        flash('success', 'Newsletter duplicated.');

        return redirect('/admin/newsletters/'.$c->id);
    }

    public function delete(int $id)
    {
        Newsletter::whereKey($id)->delete();
        flash('success', 'Newsletter deleted.');

        return redirect('/admin/newsletters');
    }

    public function design(int $id)
    {
        $n = Newsletter::findOrFail($id);
        if (in_array($n->status, ['sending', 'sent'], true)) {
            flash('error', 'This newsletter has already gone out and can no longer be edited. Duplicate it instead.');

            return redirect('/admin/newsletters/'.$n->id);
        }

        return EmailTools::builder([
            'mode' => 'newsletter', 'id' => $n->id, 'step' => 0, 'title' => $n->name, 'blocks' => EmailBlocks::decode(json_encode($n->blocks())),
            'subject' => (string) $n->subject, 'preheader' => (string) $n->preheader,
            'saveUrl' => url("admin/newsletters/{$n->id}/design"), 'backUrl' => url("admin/newsletters/{$n->id}"),
        ]);
    }

    public function designSave(Request $r, int $id)
    {
        $n = Newsletter::findOrFail($id);
        if (in_array($n->status, ['sending', 'sent'], true)) return response()->json(['ok' => false, 'error' => 'Already sent'], 409);
        $n->update([
            'design' => json_encode(['blocks' => json_decode(EmailBlocks::clean((string) $r->input('layout', '[]')), true)], JSON_UNESCAPED_UNICODE),
            'subject' => mb_substr(trim((string) $r->input('subject')), 0, 190), 'preheader' => mb_substr(trim((string) $r->input('preheader')), 0, 190),
        ]);

        return response()->json(['ok' => true, 'saved' => now()->format('H:i:s')]);
    }
}
