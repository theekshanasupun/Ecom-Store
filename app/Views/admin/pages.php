<div class="toolbar"><p class="muted" style="margin:0">Static pages such as About, Terms and Shipping. Shown in the footer.</p><a class="btn" href="<?= e(url('admin/pages/new')) ?>">+ New page</a></div>
<section class="apanel flush"><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Title</th><th>URL</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><a href="<?= e(url('admin/pages/' . $r['id'])) ?>"><b><?= e($r['title']) ?></b></a></td><td class="muted">/p/<?= e($r['slug']) ?></td><td><span class="badge s-<?= $r['status'] === 'published' ? 'completed' : 'pending' ?>"><?= e($r['status']) ?></span></td></tr><?php endforeach; ?>
</tbody></table></div></section>
