<div class="toolbar"><p class="muted" style="margin:0">Departments are the colourful sections of your store. Each has its own colour and headline.</p><a class="btn" href="<?= e(url('admin/categories/new')) ?>">+ New department</a></div>
<section class="apanel flush"><div class="tbl-wrap"><table class="tbl" id="cat-table">
<thead><tr><th></th><th>Name</th><th>Headline</th><th class="r">Products</th><th>Status</th><th class="r">Order</th></tr></thead><tbody>
<?php foreach ($rows as $c): ?>
<tr><td><span class="swatch" style="background:<?= e($c['color']) ?>"></span></td><td><a href="<?= e(url('admin/categories/' . $c['id'])) ?>"><b><?= e($c['name']) ?></b></a><small>/shop/<?= e($c['slug']) ?></small></td><td><?= e($c['headline']) ?></td><td class="r"><?= (int) $c['n'] ?></td><td><span class="badge s-<?= $c['status'] === 'active' ? 'completed' : 'pending' ?>"><?= e($c['status']) ?></span></td><td class="r"><?= (int) $c['sort_order'] ?></td></tr>
<?php endforeach; ?></tbody></table></div></section>
