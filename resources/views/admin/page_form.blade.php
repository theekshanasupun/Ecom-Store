<?php $v = fn($k, $d = '') => $pg[$k] ?? $d; ?>
<form method="post" action="<?= e(url('admin/pages/save')) ?>" class="cols"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($pg['id'] ?? 0) ?>">
<section class="apanel wide"><h2>Content</h2>
<?= field_text('title', 'Title', $v('title'), ['required' => true]) ?>
<?= field_area('body', 'Body (HTML allowed)', $v('body'), ['rows' => 16, 'hint' => 'Basic HTML: &lt;h2&gt;, &lt;p&gt;, &lt;a&gt;, &lt;ul&gt;…']) ?></section>
<section class="apanel"><h2>Publish</h2>
<?= field_text('slug', 'URL slug', $v('slug'), ['placeholder' => 'auto']) ?>
<?= field_select('status', 'Status', ['published' => 'Published', 'draft' => 'Draft'], $v('status', 'published')) ?>
<?= field_check('in_footer', 'Show in footer', (bool) $v('in_footer', 1)) ?>
<p class="muted small" id="pg-url">Page URL: <code><?= e(url('p/')) ?>/<span data-slug-out><?= e($v('slug', '…')) ?></span></code></p></section>
<div class="wide"><?= partial('admin/_seo_fields', ['o' => $pg]) ?></div>
<div class="stickybar wide" style="grid-column:1/-1"><span class="grow muted small"><?= $pg ? 'Editing “' . e($pg['title']) . '”' : 'New page' ?></span><a class="btn ghost" href="<?= e(url('admin/pages')) ?>">Cancel</a><button class="btn">Save page</button></div></form>
<?php if ($pg): ?><section class="apanel" style="margin-top:16px"><h2>Visual builder</h2><p class="muted small">Design this page with drag-and-drop blocks (hero, product grids, FAQ, countdown…). When the builder is switched on it replaces the HTML body above.</p>
<a class="btn" href="<?= e(url('admin/builder/page/' . $pg['id'])) ?>">Open visual builder <?= $pg['use_builder'] ? '(active)' : '' ?></a></section><?php endif; ?>
<?php if ($pg): ?><form method="post" action="<?= e(url('admin/pages/' . $pg['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this page?')" style="margin-top:20px"><?= csrf_field() ?><button class="btn sm ghost danger">Delete page</button></form><?php endif; ?>
