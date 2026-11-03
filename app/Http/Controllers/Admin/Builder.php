<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Support\Art;
use App\Support\Blocks;
use App\Support\Settings;
use Illuminate\Http\Request;

/** Visual (block based) builder for the home page and custom pages. */
class Builder extends Controller
{
    public function home()
    {
        return $this->screen('home', null, 'Home page', Blocks::decode((string) setting('home_builder')), setting('home_use_builder') === '1', url('/'));
    }

    public function page(int $id)
    {
        $pg = Page::findOrFail($id);

        return $this->screen('page', $pg->id, $pg->title, Blocks::decode((string) $pg->builder), (bool) $pg->use_builder, url('p/'.$pg->slug));
    }

    private function screen(string $target, ?int $id, string $label, array $blocks, bool $on, string $viewUrl)
    {
        title('Builder · '.$label);
        $reg = [];
        foreach (Blocks::registry() as $k => $d) $reg[$k] = ['label' => $d[0], 'icon' => $d[1], 'fields' => $d[2], 'defaults' => $d[3]];
        $cats = Category::orderBy('sort_order')->get(['id', 'name'])->map(fn ($c) => [(string) $c->id, $c->name])->all();

        return response(view('admin.builder', compact('target', 'id', 'label', 'blocks', 'on', 'viewUrl', 'reg', 'cats'))->render());
    }

    public function save(Request $r)
    {
        $json = Blocks::clean((string) $r->input('layout', '[]'));
        $on = $r->boolean('use_builder');
        if ($r->input('target') === 'home') {
            Settings::setMany(['home_builder' => $json, 'home_use_builder' => $on ? '1' : '0']);
        } else {
            $pg = Page::findOrFail((int) $r->input('id'));
            $pg->update(['builder' => $json, 'use_builder' => $on]);
        }

        return response()->json(['ok' => true, 'saved' => now()->format('H:i:s')]);
    }

    /** Render the current (unsaved) layout exactly like the storefront does. */
    public function preview(Request $r)
    {
        $blocks = Blocks::decode(Blocks::clean((string) $r->input('layout', '[]')));
        $accent = preg_match('/^#[0-9a-f]{6}$/i', (string) setting('accent')) ? setting('accent') : '#c6ff3d';
        $body = $blocks ? Blocks::renderAll($blocks) : '<div style="padding:80px 20px;text-align:center;color:#8a8a93;font:14px sans-serif">Add a block from the left to start building.</div>';
        $js = <<<'JS'
<script>
document.addEventListener('click',function(e){var s=e.target.closest('[data-bid]');e.preventDefault();if(s)parent.postMessage({kioskBlock:s.getAttribute('data-bid')},'*');},true);
document.addEventListener('submit',function(e){e.preventDefault()},true);
document.querySelectorAll('.bb-count').forEach(function(c){var end=+c.dataset.end;function t(){var d=Math.max(0,end-Date.now())/1000;c.querySelector('[data-u=d]').textContent=Math.floor(d/86400);c.querySelector('[data-u=h]').textContent=Math.floor(d%86400/3600);c.querySelector('[data-u=m]').textContent=Math.floor(d%3600/60);c.querySelector('[data-u=s]').textContent=Math.floor(d%60)}t();setInterval(t,1000)});
var sel=location.hash.slice(1);
window.addEventListener('message',function(e){if(e.data&&e.data.kioskSelect!==undefined){document.querySelectorAll('[data-bid]').forEach(function(x){x.classList.toggle('bb-sel',x.getAttribute('data-bid')===e.data.kioskSelect)});var el=document.querySelector('[data-bid="'+e.data.kioskSelect+'"]');if(el&&e.data.scroll)el.scrollIntoView({behavior:'smooth',block:'center'})}});
</script>
JS;

        return response('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Mono:wght@400;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="'.e(asset_v('css/store.css')).'"><style>:root{--accent:'.e($accent).';--accent-ink:'.e(Art::ink($accent)).'}[data-bid]{cursor:pointer;outline:2px solid transparent;outline-offset:-2px;transition:outline-color .15s}[data-bid]:hover{outline-color:rgba(198,255,61,.45)}.bb-sel{outline-color:#c6ff3d!important}</style></head><body>'.$body.$js.'</body></html>');
    }
}
