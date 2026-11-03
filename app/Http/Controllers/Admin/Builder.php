<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Support\Meta;
use App\Support\Blocks;
use App\Support\Settings;
use Illuminate\Http\Request;

/** Visual (block based) builder for the home page and custom pages. */
class Builder extends Controller
{
    public function home()
    {
        $saved = Blocks::decode((string) setting('home_builder'));
        $seeded = ! $saved;

        return $this->screen('home', null, 'Home page', $saved ?: Blocks::currentHome(), $seeded ? true : setting('home_use_builder') === '1', url('/'), Blocks::currentHome(), $seeded);
    }

    public function page(int $id)
    {
        $pg = Page::findOrFail($id);
        $saved = Blocks::decode((string) $pg->builder);
        $orig = $this->pageSeed($pg);

        return $this->screen('page', $pg->id, $pg->title, $saved ?: $orig, (bool) $pg->use_builder, url('p/'.$pg->slug), $orig, ! $saved);
    }

    /** A custom page's existing body as a single rich-text block. */
    private function pageSeed(Page $pg): array
    {
        return [['id' => 'cur'.substr(md5('p'.$pg->id), 0, 6), 'type' => 'text', 'props' => ['title' => $pg->title, 'body' => (string) $pg->body], 'style' => Blocks::defaultStyle('text'), 'current' => true]];
    }

    private function screen(string $target, ?int $id, string $label, array $blocks, bool $on, string $viewUrl, array $original, bool $seeded)
    {
        title('Builder · '.$label);
        $reg = [];
        foreach (Blocks::registry() as $k => $d) $reg[$k] = ['label' => $d[0], 'icon' => $d[1], 'fields' => $d[2], 'defaults' => $d[3], 'style' => Blocks::defaultStyle($k)];
        $cats = Category::orderBy('sort_order')->get(['id', 'name'])->map(fn ($c) => [(string) $c->id, $c->name])->all();
        $groups = Blocks::groups();
        $templates = $this->templates();

        return response(view('admin.builder', compact('target', 'id', 'label', 'blocks', 'on', 'viewUrl', 'reg', 'cats', 'groups', 'original', 'seeded', 'templates'))->render());
    }

    private function templates(): array
    {
        $t = json_decode((string) setting('builder_templates', '[]'), true);

        return is_array($t) ? $t : [];
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

    /** Save the current layout as a reusable template (duplicate layout). */
    public function template(Request $r)
    {
        $name = mb_substr(trim((string) $r->input('name')) ?: 'Template '.date('M j H:i'), 0, 60);
        $list = $this->templates();
        $list[] = ['id' => bin2hex(random_bytes(4)), 'name' => $name, 'layout' => json_decode(Blocks::clean((string) $r->input('layout', '[]')), true), 'at' => now()->toDateTimeString()];
        Settings::set('builder_templates', json_encode(array_slice($list, -20), JSON_UNESCAPED_UNICODE));

        return response()->json(['ok' => true, 'templates' => $this->templates()]);
    }

    public function templateDelete(Request $r)
    {
        $id = (string) $r->input('id');
        Settings::set('builder_templates', json_encode(array_values(array_filter($this->templates(), fn ($t) => ($t['id'] ?? '') !== $id)), JSON_UNESCAPED_UNICODE));

        return response()->json(['ok' => true, 'templates' => $this->templates()]);
    }

    /** Render the current (unsaved) layout inside the real storefront shell. */
    public function preview(Request $r)
    {
        $blocks = Blocks::decode(Blocks::clean((string) $r->input('layout', '[]')));
        Meta::$data['builder_preview'] = true;
        title('Preview');
        Meta::$data['home'] = $r->input('target') === 'home';
        $html = $blocks ? Blocks::renderAll($blocks) : '<div style="padding:120px 20px;text-align:center;color:#8a8a93;font:14px sans-serif">Add a block from the left to start building.</div>';
        $js = <<<'JS'
<style>[data-bid]{cursor:pointer;position:relative;outline:2px solid transparent;outline-offset:-2px;transition:outline-color .15s}[data-bid]:hover{outline-color:rgba(198,255,61,.55)}[data-bid]:hover::after{content:attr(data-bt);position:absolute;left:8px;top:8px;z-index:50;background:#c6ff3d;color:#0b0b0d;font:700 10px/1 monospace;letter-spacing:.08em;text-transform:uppercase;padding:5px 8px;border-radius:99px;pointer-events:none}.bb-sel{outline-color:#c6ff3d!important;outline-width:3px}.hdr{position:relative!important}</style>
<script>
document.addEventListener('click',function(e){var s=e.target.closest('[data-bid]');e.preventDefault();if(s)parent.postMessage({kioskBlock:s.getAttribute('data-bid')},'*');},true);
document.addEventListener('dblclick',function(e){var s=e.target.closest('[data-bid]');if(s){var t=(window.getSelection()||'').toString().trim()||(e.target.textContent||'').trim().slice(0,80);parent.postMessage({kioskDbl:s.getAttribute('data-bid'),text:t},'*');}},true);
document.addEventListener('submit',function(e){e.preventDefault()},true);
document.querySelectorAll('.bb-count').forEach(function(c){var end=+c.dataset.end;function t(){var d=Math.max(0,end-Date.now())/1000;c.querySelector('[data-u=d]').textContent=Math.floor(d/86400);c.querySelector('[data-u=h]').textContent=Math.floor(d%86400/3600);c.querySelector('[data-u=m]').textContent=Math.floor(d%3600/60);c.querySelector('[data-u=s]').textContent=Math.floor(d%60)}t();setInterval(t,1000)});
window.addEventListener('message',function(e){if(e.data&&e.data.kioskSelect!==undefined){document.querySelectorAll('[data-bid]').forEach(function(x){x.classList.toggle('bb-sel',x.getAttribute('data-bid')===e.data.kioskSelect)});var el=document.querySelector('[data-bid="'+e.data.kioskSelect+'"]');if(el&&e.data.scroll)el.scrollIntoView({behavior:'smooth',block:'center'})}});
</script>
JS;
        $resp = page('builder_page', ['html' => $html]);
        $doc = (string) $resp->getContent();
        $doc = str_contains($doc, '</body>') ? str_replace('</body>', $js.'</body>', $doc) : $doc.$js;

        return response($doc);
    }
}
