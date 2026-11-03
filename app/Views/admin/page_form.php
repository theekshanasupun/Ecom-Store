<?php $v = fn($k, $d = '') => $pg[$k] ?? $d; ?>
<form method="post" action="<?= e(url('admin/pages/save')) ?>" class="cols"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($pg['id'] ?? 0) ?>">
<section class="apanel wide"><h2>Content</h2>
<?= field_text('title', 'Title', $v('title'), ['required' => true]) ?>
<?= field_area('body', 'Body (HTML allowed)', $v('body'), ['rows' => 16, 'hint' => 'Basic HTML: &lt;h2&gt;, &lt;p&gt;, &lt;a&gt;, &lt;ul&gt;…']) ?></section>
<section class="apanel"><h2>Publish</h2>
<?= field_text('slug', 'URL slug', $v('slug'), ['placeholder' => 'auto']) ?>
<?= field_select('status', 'Status', ['published' => 'Published', 'draft' => 'Draft'], $v('status', 'published')) ?>
<?= field_check('in_footer', 'Show in footer', (bool) $v('in_footer', 1)) ?>
<div class="actions"><button class="btn block">Save page</button></div></section></form>
<?php if ($pg): ?><form method="post" action="<?= e(url('admin/pages/' . $pg['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this page?')" style="margin-top:20px"><?= csrf_field() ?><button class="btn sm ghost danger">Delete page</button></form><?php endif; ?>
