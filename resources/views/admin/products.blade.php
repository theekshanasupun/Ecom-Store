<?php
use App\Services\Catalog;
use App\Models\Product as ProductModel;
/** @var array $rows @var array $cats @var string $q @var int $cat @var string $type @var int $page @var int $pages @var int $total @var int $per */
?>
<div class="toolbar">
  <form class="filters" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name or SKU…">
    <select name="cat" onchange="this.form.submit()"><option value="0">All departments</option><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $cat == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
    <select name="type" onchange="this.form.submit()"><option value="">All types</option><?php foreach (ProductModel::TYPES as $k => $v): ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <select name="per" onchange="this.form.submit()" title="Rows per page"><?php foreach ([20, 50, 100] as $n): ?><option value="<?= $n ?>" <?= $per === $n ? 'selected' : '' ?>><?= $n ?> / page</option><?php endforeach; ?></select>
    <button class="btn sm ghost">Filter</button>
  </form>
  <a class="btn" href="<?= e(url('admin/products/new')) ?>">+ New product</a>
</div>
<p class="muted small" style="margin:-4px 0 12px">Quick edit: change a price, stock level, status or name right in the table — it saves automatically. Tick rows to use bulk actions (shift-click selects a range).</p>
<form method="post" action="<?= e(url('admin/products/bulk')) ?>" data-bulk data-qe-url="<?= e(url('admin/products')) ?>" data-qe-token="<?= e(csrf_token()) ?>"><?= csrf_field() ?>
<?= partial('admin/_bulkbar', ['cats' => array_map(fn ($c) => ['id' => $c['id'], 'name' => $c['name']], $cats->all()), 'actions' => [
  'activate' => 'Publish (active)', 'draft' => 'Move to draft', 'feature' => 'Mark featured', 'unfeature' => 'Remove featured', 'category' => 'Move to department…',
  'price_pct' => 'Change price by % …', 'sale_pct' => 'Put on sale (% off) …', 'sale_end' => 'End sale (restore prices)', 'stock_set' => 'Set stock to …', 'stock_add' => 'Add to stock …', 'delete' => 'Delete',
], 'needs' => ['category' => 'category', 'price_pct' => 'number', 'sale_pct' => 'number', 'stock_set' => 'number', 'stock_add' => 'number']]) ?>
<section class="apanel flush">
<div class="tbl-wrap"><table class="tbl">
  <thead><tr><th class="chkc"><input type="checkbox" data-selall aria-label="Select all"></th><th></th><th>Product</th><th>Type</th><th class="r">Price</th><th class="r">Compare</th><th class="r">Stock</th><th>Status</th><th class="r">Sold</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $p): $keyed = in_array($p['type'], ['key', 'account']); $inf = !$keyed && ($p['unlimited'] || in_array($p['type'], ['file', 'service'])); ?>
  <tr data-id="<?= (int) $p['id'] ?>">
    <td class="chkc"><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" class="rowchk" aria-label="Select <?= e($p['name']) ?>"></td>
    <td class="thumbcell" style="--c:<?= e($p['color']) ?>"><div class="thumb"><?= Catalog::media($p, false) ?></div></td>
    <td><input class="qe qe-name" data-field="name" value="<?= e($p['name']) ?>" aria-label="Name"><small><?= e($p['cat']['name'] ?? '—') ?> · <input class="qe qe-sku" data-field="sku" value="<?= e($p['sku']) ?>" placeholder="SKU" aria-label="SKU"> · <a href="<?= e(url('admin/products/' . $p['id'])) ?>">Full edit</a></small></td>
    <td><span class="badge t-<?= e($p['type']) ?>"><?= e(ucfirst($p['type'])) ?></span></td>
    <td class="r"><input class="qe qe-num" data-field="price" inputmode="decimal" value="<?= e(from_cents((int) $p['price'])) ?>" aria-label="Price"></td>
    <td class="r"><input class="qe qe-num" data-field="compare_price" inputmode="decimal" value="<?= $p['compare_price'] ? e(from_cents((int) $p['compare_price'])) : '' ?>" placeholder="—" aria-label="Compare-at price"></td>
    <td class="r"><?php if ($keyed): ?><span class="<?= (int) $p['pool'] <= (int) setting('low_stock', 3) ? 'warn' : '' ?>"><?= (int) $p['pool'] ?></span><small>keys</small>
      <?php elseif ($inf): ?>∞
      <?php else: ?><input class="qe qe-num qe-stock<?= (int) $p['stock'] <= (int) setting('low_stock', 3) ? ' warn' : '' ?>" data-field="stock" inputmode="numeric" value="<?= (int) $p['stock'] ?>" aria-label="Stock"><?php endif; ?></td>
    <td><select class="qe qe-status s-<?= $p['status'] === 'active' ? 'on' : 'off' ?>" data-field="status" aria-label="Status"><option value="active" <?= $p['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="draft" <?= $p['status'] !== 'active' ? 'selected' : '' ?>>Draft</option></select>
      <button type="button" class="star<?= $p['featured'] ? ' on' : '' ?>" data-feature title="Toggle featured" aria-label="Toggle featured">★</button></td>
    <td class="r muted"><?= (int) $p['sales'] ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="9" class="muted">No products found.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
</form>
<?= partial('admin/_pager', compact('page', 'pages')) ?>
