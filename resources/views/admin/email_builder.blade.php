<?php /** @var array $cfg */ ?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Email builder · <?= e($cfg['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Mono:wght@400;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_v('css/admin.css')) ?>"><link rel="stylesheet" href="<?= e(asset_v('css/email-builder.css')) ?>">
<style>:root{--accent:<?= e($cfg['accent']) ?>}</style>
</head><body class="eb-wrap">
<div class="eb-top">
  <a class="btn sm ghost" href="<?= e($cfg['backUrl']) ?>" id="eb-back">← Back</a>
  <h1>Email builder · <?= e($cfg['title']) ?></h1>
  <button type="button" class="btn sm ghost" id="eb-undo" disabled title="Undo (Ctrl+Z)">↶</button>
  <button type="button" class="btn sm ghost" id="eb-redo" disabled title="Redo (Ctrl+Shift+Z)">↷</button>
  <div class="seg" id="eb-devices"><button type="button" class="on" data-w="">Desktop</button><button type="button" data-w="mobile">Mobile</button></div>
  <button type="button" class="btn sm ghost" id="eb-tpl">Templates</button>
  <button type="button" class="btn sm ghost" id="eb-test">Send test</button>
  <span class="savestate" id="eb-state">Saved</span>
  <button type="button" class="btn sm" id="eb-save">Save</button>
</div>
<div class="eb-grid">
  <aside class="eb-l">
    <h3>Blocks <small>drag onto the email</small></h3>
    <div class="eb-palette" id="eb-palette"></div>
    <h3>Structure</h3>
    <ul class="eb-list" id="eb-list"></ul>
  </aside>
  <main class="eb-mid">
    <div class="eb-inbox">
      <div class="eb-from"><span class="eb-av"><?= e(strtoupper(mb_substr($cfg['sender'], 0, 1))) ?></span><b><?= e($cfg['sender']) ?></b><small>to me</small></div>
      <input type="text" id="eb-subject" placeholder="Subject line" value="<?= e($cfg['subject']) ?>" maxlength="190" aria-label="Subject">
      <input type="text" id="eb-pre" placeholder="Preview text (shown next to the subject in the inbox)" value="<?= e($cfg['preheader']) ?>" maxlength="190" aria-label="Preview text">
      <div class="eb-tags" id="eb-tags"><span class="muted small">Insert:</span></div>
    </div>
    <div class="eb-stage" id="eb-stage">
      <iframe class="eb-frame" id="eb-frame" title="Email preview"></iframe>
      <div class="eb-overlay" id="eb-overlay"><div class="eb-cline" id="eb-cline"></div><div class="eb-hint">Drop to place the block here</div></div>
    </div>
  </main>
  <aside class="eb-r" id="eb-insp"><p class="eb-empty">Select a block in the preview or the structure list to edit it.</p></aside>
</div>

<div class="eb-modal" id="eb-tplm" hidden><div class="eb-card eb-wide"><div class="eb-mh"><h2>Start from a template</h2><button type="button" data-close>✕</button></div><div class="eb-tpls" id="eb-tpls"></div></div></div>
<div class="eb-modal" id="eb-testm" hidden><div class="eb-card"><div class="eb-mh"><h2>Send a test email</h2><button type="button" data-close>✕</button></div>
  <label class="fld"><span>Send to</span><input type="email" id="eb-to" value="<?= e($cfg['defaultTo']) ?>"></label>
  <p class="muted small" id="eb-testmsg">Uses sample customer, order and product data.</p>
  <div class="actions"><button type="button" class="btn" id="eb-testgo">Send test</button></div></div></div>

<script>window.EMAILB=<?= json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<script src="<?= e(asset_v('js/email-builder.js')) ?>"></script>
</body></html>
