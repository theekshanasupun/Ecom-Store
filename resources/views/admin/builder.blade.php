<?php /** @var string $target @var ?int $id @var string $label @var array $blocks @var bool $on @var string $viewUrl @var array $reg @var array $cats */ ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Builder · <?= e($label) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Mono:wght@400;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_v('css/admin.css')) ?>"><style>:root{--accent:<?= e(setting('accent', '#c6ff3d')) ?>}body{overflow:hidden}</style>
</head><body class="bld-wrap">
<div class="bld-top">
  <a class="btn sm ghost" href="<?= e(url($target === 'home' ? 'admin/pages' : 'admin/pages/' . $id)) ?>">← Back</a>
  <h1>Visual builder · <?= e($label) ?></h1>
  <div class="seg" id="devices"><button type="button" class="on" data-w="">Desktop</button><button type="button" data-w="tablet">Tablet</button><button type="button" data-w="mobile">Mobile</button></div>
  <button type="button" class="btn sm ghost" id="undo" disabled>↶ Undo</button>
  <label class="chk" style="margin:0"><input type="checkbox" id="use" <?= $on ? 'checked' : '' ?>><i></i><span><?= $target === 'home' ? 'Use this layout for the home page' : 'Use builder for this page' ?></span></label>
  <span class="savestate" id="state">Saved</span>
  <a class="btn sm ghost" href="<?= e($viewUrl) ?>" target="_blank" rel="noopener">View ↗</a>
  <button type="button" class="btn sm" id="save">Save</button>
</div>
<div class="bld-grid">
  <aside class="bld-l">
    <h3>Add a block</h3><div class="palette" id="palette"></div>
    <h3>Page structure</h3><ul class="blist" id="blist"></ul>
    <p class="bld-empty" id="empty" hidden>No blocks yet — add one above.</p>
  </aside>
  <main class="bld-mid"><iframe class="bld-frame" id="frame" title="Page preview"></iframe></main>
  <aside class="bld-r" id="inspector"><p class="bld-empty">Select a block in the preview or in the list to edit it.</p></aside>
</div>
<script>window.BUILDER=<?= json_encode(['target' => $target, 'id' => $id, 'blocks' => $blocks, 'reg' => $reg, 'cats' => $cats, 'csrf' => csrf_token(), 'saveUrl' => url('admin/builder/save'), 'previewUrl' => url('admin/builder/preview')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset_v('js/builder.js')) ?>"></script>
</body></html>
