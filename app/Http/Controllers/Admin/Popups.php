<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Popup;
use App\Support\Popups as Render;
use App\Support\Upload;
use Illuminate\Http\Request;

class Popups extends Controller
{
    public function index()
    {
        $rows = Popup::orderByDesc('id')->get()->all();
        title('Popups');

        return admin_page('popups', compact('rows'));
    }

    public function form(?int $id = null)
    {
        $p = $id ? Popup::findOrFail($id) : new Popup(['status' => 'draft', 'layout' => 'modal', 'position' => 'center', 'trigger' => 'delay', 'trigger_value' => 5, 'frequency' => 'once', 'frequency_days' => 7, 'devices' => 'all', 'audience' => 'all', 'pages' => 'all',
            'bg' => '#16161b', 'fg' => '#f3f1ec', 'btn_bg' => setting('accent', '#c6ff3d'), 'btn_fg' => '#0b0b0d', 'overlay' => 60, 'width' => 480, 'radius' => 22, 'title' => 'Get 10% off your first order', 'body' => '<p>Join our list for deals and new drops.</p>', 'button_text' => 'Subscribe', 'capture_email' => true]);
        title($id ? 'Edit popup' : 'New popup');

        return admin_page('popup_form', compact('p'));
    }

    public function save(Request $r)
    {
        $id = (int) $r->input('id');
        $old = $id ? Popup::find($id) : null;
        $name = trim((string) $r->input('name'));
        if ($name === '') {
            flash('error', 'Give the popup an internal name.');

            return redirect($id ? "/admin/popups/$id" : '/admin/popups/new');
        }
        $col = fn ($k, $d) => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $r->input($k)) ? $r->input($k) : $d;
        $in = fn ($k, array $ok, $d) => in_array($r->input($k), $ok, true) ? $r->input($k) : $d;
        $data = [
            'name' => mb_substr($name, 0, 150), 'status' => $r->input('status') === 'active' ? 'active' : 'draft',
            'layout' => $in('layout', ['modal', 'slide', 'bar', 'fullscreen'], 'modal'), 'position' => $in('position', ['center', 'bottom-right', 'bottom-left', 'top', 'bottom'], 'center'),
            'title' => mb_substr(trim((string) $r->input('title')), 0, 190), 'body' => (string) $r->input('body'),
            'button_text' => mb_substr(trim((string) $r->input('button_text')), 0, 60), 'button_url' => mb_substr(trim((string) $r->input('button_url')), 0, 250),
            'capture_email' => $r->boolean('capture_email'), 'coupon_code' => strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $r->input('coupon_code'))) ?: null,
            'trigger' => $in('trigger', ['delay', 'exit', 'scroll', 'click', 'load'], 'delay'), 'trigger_value' => max(0, min(3600, (int) $r->input('trigger_value'))),
            'frequency' => $in('frequency', ['always', 'session', 'once', 'days'], 'once'), 'frequency_days' => max(1, min(365, (int) $r->input('frequency_days', 7))),
            'devices' => $in('devices', ['all', 'desktop', 'mobile'], 'all'), 'audience' => $in('audience', ['all', 'guests', 'customers'], 'all'),
            'pages' => $in('pages', ['all', 'home', 'products', 'cart', 'custom'], 'all'), 'page_rules' => (string) $r->input('page_rules'),
            'bg' => $col('bg', '#16161b'), 'fg' => $col('fg', '#f3f1ec'), 'btn_bg' => $col('btn_bg', '#c6ff3d'), 'btn_fg' => $col('btn_fg', '#0b0b0d'),
            'overlay' => max(0, min(90, (int) $r->input('overlay', 60))), 'width' => max(280, min(900, (int) $r->input('width', 480))), 'radius' => max(0, min(40, (int) $r->input('radius', 22))),
            'success_text' => mb_substr(trim((string) $r->input('success_text')), 0, 190),
            'starts_at' => $r->input('starts_at') ? date('Y-m-d 00:00:00', strtotime($r->input('starts_at'))) : null,
            'ends_at' => $r->input('ends_at') ? date('Y-m-d 23:59:59', strtotime($r->input('ends_at'))) : null,
        ];
        try {
            if ($img = Upload::image($r->file('image'), 'site', 3)) {
                if ($old) Upload::remove((string) $old->image);
                $data['image'] = $img;
            } elseif ($r->boolean('remove_image') && $old) {
                Upload::remove((string) $old->image);
                $data['image'] = null;
            }
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());

            return redirect($id ? "/admin/popups/$id" : '/admin/popups/new');
        }
        $p = $old ?: new Popup;
        $p->fill($data)->save();
        flash('success', 'Popup saved.'.($p->status === 'active' ? ' It is live.' : ' It is a draft — set Status to Active to publish.'));

        return redirect('/admin/popups/'.$p->id);
    }

    public function toggle(int $id)
    {
        $p = Popup::findOrFail($id);
        $p->update(['status' => $p->status === 'active' ? 'draft' : 'active']);

        return redirect('/admin/popups');
    }

    public function duplicate(int $id)
    {
        $c = Popup::findOrFail($id)->replicate();
        $c->name .= ' (copy)';
        $c->status = 'draft';
        $c->views = 0;
        $c->conversions = 0;
        $c->save();
        flash('success', 'Popup duplicated as a draft.');

        return redirect('/admin/popups/'.$c->id);
    }

    public function delete(int $id)
    {
        if ($p = Popup::find($id)) {
            Upload::remove((string) $p->image);
            $p->delete();
        }
        flash('success', 'Popup deleted.');

        return redirect('/admin/popups');
    }

    /** Live preview used inside the editor. */
    public function preview(Request $r)
    {
        $p = new Popup($r->except(['_token', 'image']));
        $p->capture_email = $r->boolean('capture_email');
        $p->id = 0;
        $html = Render::html($p, true);

        return response('<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="'.e(asset_v('css/store.css')).'"><style>body{background:#101014;margin:0;min-height:100vh}.pop{position:absolute!important;inset:0!important}.pop[hidden]{display:block!important}</style></head><body>'.$html.'<script>document.querySelectorAll(".pop").forEach(function(e){e.classList.add("show")})</script></body></html>');
    }
}
