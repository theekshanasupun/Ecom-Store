<?php /** @var array $rows @var ?\App\Models\Coupon $edit @var array $cats @var array $products */
$e0 = $edit ?? null; $v = fn($k, $d = '') => $e0[$k] ?? $d;
$selP = $e0 ? $e0->productIds() : []; $selC = $e0 ? $e0->categoryIds() : [];
$scopeLabel = ['cart' => 'Whole cart', 'products' => 'Specific items', 'categories' => 'Departments'];
?>
<div class="cols">
<form method="post" action="<?= e(url('admin/coupons/bulk')) ?>" class="wide" data-bulk><?= csrf_field() ?>
<?= partial('admin/_bulkbar', ['cats' => [], 'needs' => [], 'actions' => ['enable' => 'Enable', 'disable' => 'Disable', 'delete' => 'Delete']]) ?>
<section class="apanel wide flush"><div class="tbl-wrap"><table class="tbl">
<thead><tr><th class="chkc"><input type="checkbox" data-selall aria-label="Select all"></th><th>Code</th><th>Applies to</th><th>Discount</th><th>Rules</th><th class="r">Used</th><th>Valid</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $c): $rules = array_filter([$c['min_total'] ? 'min ' . money($c['min_total']) : '', $c['min_qty'] ? 'min qty ' . $c['min_qty'] : '', $c['exclude_sale'] ? 'no sale items' : '', $c['first_order_only'] ? 'first order' : '', $c['per_user_limit'] ? $c['per_user_limit'] . '× per customer' : '', $c['max_discount'] ? 'cap ' . money($c['max_discount']) : '', $c['auto_apply'] ? 'AUTO' : '']); ?>
<tr><td class="chkc"><input type="checkbox" name="ids[]" value="<?= (int) $c['id'] ?>" class="rowchk" aria-label="Select"></td>
<td><b><a href="<?= e(url('admin/coupons?edit=' . $c['id'])) ?>"><?= e($c['code']) ?></a></b><?php if ($c['note']): ?><small><?= e($c['note']) ?></small><?php endif; ?></td>
<td><?= e($scopeLabel[$c['scope']] ?? 'Whole cart') ?><?php if ($c['scope'] === 'products'): ?> <small>(<?= count($c->productIds()) ?> items)</small><?php elseif ($c['scope'] === 'categories'): ?> <small>(<?= count($c->categoryIds()) ?>)</small><?php endif; ?></td>
<td><?= $c['value'] > 0 ? ($c['type'] === 'percent' ? (int) $c['value'] . '%' : money($c['value'])) : '' ?><?= $c['free_shipping'] ? ($c['value'] > 0 ? ' + ' : '') . 'Free shipping' : '' ?></td>
<td class="muted small"><?= e($rules ? implode(' · ', $rules) : '—') ?></td>
<td class="r"><?= (int) $c['used'] ?><?= $c['max_uses'] ? ' / ' . (int) $c['max_uses'] : '' ?></td>
<td class="muted"><?= $c['starts_at'] || $c['ends_at'] ? e(($c['starts_at'] ? substr($c['starts_at'], 0, 10) : '…') . ' → ' . ($c['ends_at'] ? substr($c['ends_at'], 0, 10) : '…')) : 'Always' ?></td>
<td><span class="badge s-<?= $c['status'] === 'active' ? 'completed' : 'pending' ?>"><?= e($c['status']) ?></span></td>
<td class="r"><a class="btn sm ghost" href="<?= e(url('admin/coupons?edit=' . $c['id'])) ?>">Edit</a></td></tr>
<?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="9" class="muted">No coupons yet.</td></tr><?php endif; ?>
</tbody></table></div></section></form>

<section class="apanel"><h2><?= $e0 ? 'Edit coupon' : 'New coupon' ?></h2>
<form method="post" action="<?= e(url('admin/coupons/save')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($e0['id'] ?? 0) ?>">
<?= field_text('code', 'Code', $v('code'), ['required' => true, 'placeholder' => 'WELCOME10']) ?>
<?= field_text('note', 'Internal note', $v('note'), ['placeholder' => 'e.g. Summer newsletter']) ?>
<h3>What does it apply to?</h3>
<?= field_select('scope', 'Coupon type', ['cart' => 'Cart coupon — discount on the whole cart', 'products' => 'Item coupon — only specific products', 'categories' => 'Department coupon — products in chosen departments'], $v('scope', 'cart')) ?>
<div class="scopebox" data-scope="products" hidden><span class="muted small">Only these products are discounted:</span>
  <input type="search" class="pickfilter" placeholder="Filter products…"><div class="pick"><?php foreach ($products as $p): ?><label><input type="checkbox" name="product_ids[]" value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], $selP, true) ? 'checked' : '' ?>><span><?= e($p['name']) ?><?= $p['sku'] ? ' · ' . e($p['sku']) : '' ?></span></label><?php endforeach; ?></div></div>
<div class="scopebox" data-scope="categories" hidden><span class="muted small">Products in these departments are discounted:</span>
  <div class="pick"><?php foreach ($cats as $c): ?><label><input type="checkbox" name="category_ids[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $selC, true) ? 'checked' : '' ?>><span><?= e($c['name']) ?></span></label><?php endforeach; ?></div></div>
<h3>Discount</h3>
<div class="g2"><?= field_select('type', 'Type', ['percent' => 'Percent %', 'fixed' => 'Fixed amount'], $v('type', 'percent')) ?>
<?= field_text('value', 'Value', ($e0 && $e0['type'] === 'fixed') ? from_cents((int) $e0['value']) : $v('value'), ['type' => 'number', 'step' => '0.01', 'min' => '0']) ?></div>
<?= field_check('free_shipping', 'Also give free shipping (value may be 0 for a shipping-only code)', (bool) ($e0['free_shipping'] ?? false)) ?>
<?= field_text('max_discount', 'Maximum discount (cap, 0 = none)', $e0 && $e0['max_discount'] ? from_cents((int) $e0['max_discount']) : '', ['type' => 'number', 'step' => '0.01', 'min' => '0']) ?>
<h3>Conditions</h3>
<div class="g2"><?= field_text('min_total', 'Min. cart spend', $e0 && $e0['min_total'] ? from_cents((int) $e0['min_total']) : '', ['type' => 'number', 'step' => '0.01']) ?>
<?= field_text('min_qty', 'Min. qualifying items', $v('min_qty', 0), ['type' => 'number', 'min' => '0']) ?></div>
<div class="g2"><?= field_text('max_uses', 'Total uses (0 = ∞)', $v('max_uses', 0), ['type' => 'number', 'min' => '0']) ?>
<?= field_text('per_user_limit', 'Uses per customer (0 = ∞)', $v('per_user_limit', 0), ['type' => 'number', 'min' => '0']) ?></div>
<?= field_check('exclude_sale', 'Exclude items already on sale', (bool) ($e0['exclude_sale'] ?? false)) ?>
<?= field_check('first_order_only', 'First order only (new customers)', (bool) ($e0['first_order_only'] ?? false)) ?>
<?= field_check('auto_apply', 'Apply automatically — no code needed (best matching one wins)', (bool) ($e0['auto_apply'] ?? false)) ?>
<div class="g2"><?= field_text('starts_at', 'Starts', $e0 && $e0['starts_at'] ? substr($e0['starts_at'], 0, 10) : '', ['type' => 'date']) ?>
<?= field_text('ends_at', 'Ends', $e0 && $e0['ends_at'] ? substr($e0['ends_at'], 0, 10) : '', ['type' => 'date']) ?></div>
<?= field_select('status', 'Status', ['active' => 'Active', 'disabled' => 'Disabled'], $v('status', 'active')) ?>
<button class="btn block">Save coupon</button></form>
<?php if ($e0): ?><form method="post" action="<?= e(url('admin/coupons/' . $e0['id'] . '/delete')) ?>" onsubmit="return confirm('Delete coupon?')" style="margin-top:10px"><?= csrf_field() ?><button class="btn sm ghost danger">Delete</button> <a class="btn sm ghost" href="<?= e(url('admin/coupons')) ?>">New coupon</a></form><?php endif; ?>
</section></div>
