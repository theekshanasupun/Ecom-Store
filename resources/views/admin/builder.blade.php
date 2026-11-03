<?php /** @var string $target @var ?int $id @var string $label @var array $blocks @var bool $on @var string $viewUrl @var array $reg @var array $cats @var array $groups @var array $original @var bool $seeded @var array $templates */ ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Builder · <?= e($label) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Mono:wght@400;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_v('css/admin.css')) ?>"><style>:root{--accent:<?= e(setting('accent', '#c6ff3d')) ?>}body{overflow:hidden}
.bld-wrap{display:grid;grid-template-rows:auto 1fr;height:100vh}.bld-grid{display:grid;grid-template-columns:300px minmax(0,1fr) 340px;min-height:0}
.bld-top{gap:10px}.bld-top h1{display:flex;align-items:center;gap:10px}
.tag-cur{font:700 10px var(--fm);letter-spacing:.1em;text-transform:uppercase;background:var(--accent);color:#0b0b0d;border-radius:99px;padding:3px 9px}
.bld-l h3,.bld-r h3{font:700 11px var(--fm);letter-spacing:.14em;text-transform:uppercase;color:var(--mut);margin:18px 0 10px}.bld-l h3:first-child,.bld-r h3:first-child{margin-top:0}
.palette-g{margin-bottom:8px}.palette-g summary{cursor:pointer;font:700 11px var(--fm);letter-spacing:.14em;text-transform:uppercase;color:var(--mut);padding:8px 2px;list-style:none;display:flex;justify-content:space-between}.palette-g summary::-webkit-details-marker{display:none}.palette-g summary em{font-style:normal;opacity:.6;margin-left:6px}.palette-g summary::after{content:'▾';margin-left:auto;padding-left:8px}.palette-g:not([open]) summary::after{content:'▸'}.palette-g .palette{margin-top:6px}.palette button{cursor:grab}.palette button:active{cursor:grabbing}
.bld-mid{position:relative;background:#1a1a20;overflow:auto;padding:18px;display:flex;justify-content:center;align-items:flex-start}
.devwrap{position:relative;width:100%;transition:width .25s;height:calc(100vh - 110px);flex:none}.devwrap.tablet{width:820px}.devwrap.mobile{width:390px}
.devwrap.tablet .bld-frame,.devwrap.mobile .bld-frame{border-radius:26px;box-shadow:0 0 0 8px #0b0b0d,0 30px 80px rgba(0,0,0,.6)}
.bld-frame{position:absolute;inset:0;width:100%;height:100%;border:0;background:#0b0b0d;border-radius:12px}
.bld-frame.fresh{visibility:hidden}
.shield{position:absolute;inset:0;z-index:5;display:none}body.dragging .shield{display:block}
.dropline{position:absolute;left:0;right:0;height:4px;background:var(--accent);border-radius:4px;box-shadow:0 0 0 4px rgba(198,255,61,.2);pointer-events:none;display:none;z-index:6}
.blist li{position:relative}.blist li.ins-before::before,.blist li.ins-after::after{content:"";position:absolute;left:0;right:0;height:3px;background:var(--accent);border-radius:3px}.blist li.ins-before::before{top:-5px}.blist li.ins-after::after{bottom:-5px}
.blist li .cur{font:700 9px var(--fm);color:var(--accent);letter-spacing:.08em}
.blist-drop{border:2px dashed var(--line);border-radius:12px;padding:14px;text-align:center;color:var(--mut);font-size:12.5px;margin-top:8px}body.dragging .blist-drop{border-color:var(--accent);color:var(--accent)}
.state-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:#2e8b57;margin-right:6px}.state-dot.dirty{background:#ffb020;animation:pulse 1s infinite}@keyframes pulse{50%{opacity:.4}}
.tplbar{display:flex;gap:6px;flex-wrap:wrap}.tplbar .btn{flex:1}
.tpl-item{display:flex;align-items:center;gap:6px;margin-top:6px}.tpl-item button.use{flex:1;text-align:left}.tpl-item .del{color:#ff6b7d}
.hint2{color:var(--mut);font-size:12px;margin:6px 0 0;line-height:1.5}
@media(max-width:1000px){.bld-grid{grid-template-columns:1fr}.bld-wrap{height:auto;overflow:auto}body{overflow:auto}.devwrap{height:70vh}}
</style>
</head><body class="bld-wrap">
<div class="bld-top">
  <a class="btn sm ghost" href="<?= e(url($target === 'home' ? 'admin/pages' : 'admin/pages/' . $id)) ?>">← Back</a>
  <h1>Visual builder · <?= e($label) ?><?php if ($seeded): ?><span class="tag-cur" title="This is your live page, converted into editable blocks">Current layout loaded</span><?php endif; ?></h1>
  <div class="seg" id="devices"><button type="button" class="on" data-w="">Desktop</button><button type="button" data-w="tablet">Tablet</button><button type="button" data-w="mobile">Mobile</button></div>
  <button type="button" class="btn sm ghost" id="undo" disabled>↶ Undo</button>
  <label class="chk" style="margin:0"><input type="checkbox" id="use" <?= $on ? 'checked' : '' ?>><i></i><span><?= $target === 'home' ? 'Use this layout for the home page' : 'Use builder for this page' ?></span></label>
  <span class="savestate" id="statewrap"><span class="state-dot" id="dot"></span><span id="state">Saved</span></span>
  <a class="btn sm ghost" href="<?= e($viewUrl) ?>" target="_blank" rel="noopener">View ↗</a>
  <button type="button" class="btn sm" id="save">Save</button>
</div>
<div class="bld-grid">
  <aside class="bld-l">
    <h3>Add a block <span style="text-transform:none;letter-spacing:0;font-family:inherit;font-weight:400">— click or drag</span></h3>
    <div id="palette"></div>
    <h3>Page structure</h3><ul class="blist" id="blist"></ul>
    <div class="blist-drop" id="dropzone">Drop a block here to add it at the end</div>
    <p class="bld-empty" id="empty" hidden>No blocks yet — add one above.</p>
    <h3>Layout tools</h3>
    <div class="tplbar"><button type="button" class="btn sm ghost" id="reset" title="Go back to the layout your site has today">Reset to original</button><button type="button" class="btn sm ghost" id="savetpl">Duplicate as template</button></div>
    <div id="tpls"></div>
    <p class="hint2">Templates are saved layouts you can load into any page. Loading replaces the current blocks (you can undo).</p>
  </aside>
  <main class="bld-mid" id="mid"><div class="devwrap" id="devwrap"><div class="shield" id="shield"></div><div class="dropline" id="dropline"></div></div></main>
  <aside class="bld-r" id="inspector"><p class="bld-empty">Select a block in the preview or in the list to edit it. Double-click text in the preview to jump to its field.</p></aside>
</div>
<script>window.BUILDER=<?= json_encode(['target' => $target, 'id' => $id, 'blocks' => $blocks, 'reg' => $reg, 'cats' => $cats, 'groups' => $groups, 'original' => $original, 'seeded' => $seeded, 'templates' => $templates, 'csrf' => csrf_token(), 'saveUrl' => url('admin/builder/save'), 'previewUrl' => url('admin/builder/preview'), 'tplUrl' => url('admin/builder/template'), 'tplDelUrl' => url('admin/builder/template/delete')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset_v('js/builder.js')) ?>"></script>
</body></html>
