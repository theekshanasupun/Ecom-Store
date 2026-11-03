<?php
use App\Services\Catalog;
use App\Models\Product as ProductModel;
/** @var array $rows @var array $cats @var string $q @var int $cat @var string $type @var int $page @var int $pages @var int $total */
?>
<div class="toolbar">
  <form class="filters" method="get">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name or SKU…">
    <select name="cat" onchange="this.form.submit()"><option value="0">All departments</option><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $cat == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select>
    <select name="type" onchange="this.form.submit()"><option value="">All types</option><?php foreach (ProductModel::TYPES as $k => $v): ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
    <button class="btn sm ghost">Filter</button>
  </form>
  <a class="btn" href="<?= e(url('admin/products/new')) ?>">+ New product</a>
</div>
<section class="apanel flush">
<div class="tbl-wrap"><table class="tbl">
  <thead><tr><th></th><th>Product</th><th>Type</th><th class="r">Price</th><th class="r">Stock</th><th>Status</th><th class="r">Sold</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $p): $left = in_array($p['type'], ['key', 'account']) ? (int) $p['pool'] : ($p['unlimited'] || in_array($p['type'], ['file', 'service']) ? '∞' : (int) $p['stock']); ?>
  <tr>
    <td class="thumbcell" style="--c:<?= e($p['color']) ?>"><div class="thumb"><?= Catalog::media($p, false) ?></div></td>
    <td><a href="<?= e(url('admin/products/' . $p['id'])) ?>"><b><?= e($p['name']) ?></b></a><small><?= e($p['cat']['name'] ?? '—') ?> · <?= e($p['sku']) ?></small></td>
    <td><span class="badge t-<?= e($p['type']) ?>"><?= e(ucfirst($p['type'])) ?></span></td>
    <td class="r"><?= money($p['price']) ?><?php if ($p['compare_price'] > $p['price']): ?><small><s><?= money($p['compare_price']) ?></s></small><?php endif; ?></td>
    <td class="r"><span class="<?= is_int($left) && $left <= (int) setting('low_stock', 3) ? 'warn' : '' ?>"><?= $left ?></span><?= in_array($p['type'], ['key', 'account']) ? '<small>keys</small>' : '' ?></td>
    <td><span class="badge s-<?= $p['status'] === 'active' ? 'completed' : 'pending' ?>"><?= e($p['status']) ?></span><?= $p['featured'] ? ' <span class="badge">★</span>' : '' ?></td>
    <td class="r muted"><?= (int) $p['sales'] ?></td>
  </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted">No products found.</td></tr><?php endif; ?>
  </tbody></table></div>
</section>
<?= partial('admin/_pager', compact('page', 'pages')) ?>
