<?php /** @var array $rows @var ?\App\Models\Coupon $edit @var array $cats @var array $products @var array $stats */
$e0 = $edit ?? null;
$v = fn ($k, $d = '') => old($k, $e0[$k] ?? $d);
$selP = $e0 ? $e0->productIds() : []; $selC = $e0 ? $e0->categoryIds() : [];
$scopeLabel = ['cart' => 'Whole cart', 'products' => 'Specific items', 'categories' => 'Departments'];
$kind = old('kind', $e0 ? ($e0['free_shipping'] && (int) $e0['value'] === 0 ? 'shipping' : ($e0['scope'] ?: 'cart')) : 'cart');
$open = $e0 || request()->boolean('new') || old('code') !== null;
$sym = (string) setting('currency_symbol', '$');
?>
<div class="kpis">
  <div class="kpi"><b><?= (int) $stats['total'] ?></b><small>Coupons</small></div>
  <div class="kpi"><b><?= (int) $stats['active'] ?></b><small>Active now</small></div>
  <div class="kpi"><b><?= (int) $stats['uses'] ?></b><small>Times redeemed</small></div>
  <div class="kpi"><b><?= (int) $stats['auto'] ?></b><small>Automatic discounts</small></div>
</div>
<div class="toolbar">
  <div class="quick"><input type="search" data-tablefilter="#cpn-table" placeholder="Filter coupons…" aria-label="Filter coupons"><span class="muted small">Click a code to edit · use the switch to pause a coupon instantly</span></div>
  <button type="button" class="btn" data-drawer-open="#coupon-drawer">＋ New coupon</button>
</div>

<form method="post" action="<?= e(url('admin/coupons/bulk')) ?>" data-bulk><?= csrf_field() ?>
<?= partial('admin/_bulkbar', ['cats' => [], 'needs' => [], 'actions' => ['enable' => 'Enable', 'disable' => 'Disable', 'delete' => 'Delete']]) ?>
<section class="apanel flush"><div class="tbl-wrap"><table class="tbl" id="cpn-table">
<thead><tr><th class="chkc"><input type="checkbox" data-selall aria-label="Select all"></th><th>Code</th><th>Applies to</th><th>Discount</th><th>Rules</th><th>Used</th><th>Valid</th><th>On</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $c): $rules = array_filter([$c['min_total'] ? 'min ' . money($c['min_total']) : '', $c['min_qty'] ? 'min qty ' . $c['min_qty'] : '', $c['exclude_sale'] ? 'no sale items' : '', $c['first_order_only'] ? 'first order' : '', $c['per_user_limit'] ? $c['per_user_limit'] . '× per customer' : '', $c['max_discount'] ? 'cap ' . money($c['max_discount']) : '', $c['auto_apply'] ? 'AUTO' : '']);
  $pct = $c['max_uses'] ? min(100, (int) round($c['used'] / $c['max_uses'] * 100)) : 0; ?>
<tr><td class="chkc"><input type="checkbox" name="ids[]" value="<?= (int) $c['id'] ?>" class="rowchk" aria-label="Select"></td>
<td><b><a href="<?= e(url('admin/coupons?edit=' . $c['id'])) ?>"><?= e($c['code']) ?></a></b><?php if ($c['note']): ?><small><?= e($c['note']) ?></small><?php endif; ?></td>
<td><?= e($scopeLabel[$c['scope']] ?? 'Whole cart') ?><?php if ($c['scope'] === 'products'): ?> <small>(<?= count($c->productIds()) ?> items)</small><?php elseif ($c['scope'] === 'categories'): ?> <small>(<?= count($c->categoryIds()) ?>)</small><?php endif; ?></td>
<td><?= $c['value'] > 0 ? ($c['type'] === 'percent' ? (int) $c['value'] . '%' : money($c['value'])) : '' ?><?= $c['free_shipping'] ? ($c['value'] > 0 ? ' + ' : '') . 'Free shipping' : '' ?></td>
<td class="muted small"><?= e($rules ? implode(' · ', $rules) : '—') ?></td>
<td><?= (int) $c['used'] ?><?= $c['max_uses'] ? ' / ' . (int) $c['max_uses'] : '' ?><?php if ($c['max_uses']): ?><div class="ubar"><i style="width:<?= $pct ?>%"></i></div><?php endif; ?></td>
<td class="muted"><?= $c['starts_at'] || $c['ends_at'] ? e(($c['starts_at'] ? substr($c['starts_at'], 0, 10) : '…') . ' → ' . ($c['ends_at'] ? substr($c['ends_at'], 0, 10) : '…')) : 'Always' ?><?php if ($c['ends_at'] && $c['ends_at']->isPast()): ?> <span class="badge bad">expired</span><?php endif; ?></td>
<td><label class="switch" title="Enable / disable"><input type="checkbox" data-switch-url="<?= e(url('admin/coupons/' . $c['id'] . '/toggle')) ?>" <?= $c['status'] === 'active' ? 'checked' : '' ?> aria-label="Enabled"><i></i></label></td>
<td class="r"><div class="rowmenu"><a class="btn sm ghost" href="<?= e(url('admin/coupons?edit=' . $c['id'])) ?>">Edit</a>
  <button type="button" class="btn sm ghost" data-post-url="<?= e(url('admin/coupons/' . $c['id'] . '/duplicate')) ?>">Duplicate</button></div></td></tr>
<?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="9" class="empty">No coupons yet — <a href="#coupon-drawer" data-drawer-open="#coupon-drawer">create your first one</a>.</td></tr><?php endif; ?>
</tbody></table></div></section></form>

<div class="drawer-panel dp-wide" id="coupon-drawer" hidden <?= $open ? 'data-autoopen' : '' ?>>
<form method="post" action="<?= e(url('admin/coupons/save')) ?>" id="coupon-form" data-sym="<?= e($sym) ?>"><?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) ($e0['id'] ?? 0) ?>"><input type="hidden" name="kind" value="<?= e($kind) ?>"><input type="hidden" name="scope" value="<?= e($v('scope', 'cart')) ?>"><input type="hidden" name="free_shipping_only" value="0">
  <div class="dp-head"><h2><?= $e0 ? 'Edit coupon ' . e($e0['code']) : 'New coupon' ?></h2><button type="button" data-drawer-close aria-label="Close">✕</button></div>
  <div class="dp-body"><div class="dp-split">
    <div>
      <div class="tabs2" data-tabs><button type="button" data-tab="type" class="on">1 · Type &amp; code</button><button type="button" data-tab="disc">2 · Discount</button><button type="button" data-tab="cond">3 · Conditions</button><button type="button" data-tab="when">4 · Schedule &amp; limits</button></div>
      <section data-pane="type">
        <div class="typecards">
          <label class="typecard" data-kind="cart"><span class="ti">🛒</span><b>Whole cart</b><small>Discount on everything in the cart</small></label>
          <label class="typecard" data-kind="products"><span class="ti">🏷️</span><b>Specific products</b><small>Only the items you pick</small></label>
          <label class="typecard" data-kind="categories"><span class="ti">🗂️</span><b>Departments</b><small>All products in a department</small></label>
          <label class="typecard" data-kind="shipping"><span class="ti">🚚</span><b>Free shipping</b><small>Waives delivery cost</small></label>
        </div>
        <div class="codebox"><?= field_text('code', 'Coupon code', $v('code'), ['required' => true, 'placeholder' => 'WELCOME10', 'maxlength' => 40, 'autocomplete' => 'off']) ?>
          <label class="fld" style="max-width:110px"><span>Prefix</span><input name="code_prefix" placeholder="SUMMER" maxlength="12" autocomplete="off"></label>
          <button type="button" class="btn ghost" data-gen-code>Generate</button></div>
        <label class="chk"><input type="hidden" name="auto_apply" value="0"><input type="checkbox" name="auto_apply" value="1" <?= $v('auto_apply', 0) ? 'checked' : '' ?>><i></i><span>Apply automatically — no code needed <small class="hint">The best matching automatic coupon is applied to the cart for you.</small></span></label>
        <?= field_text('note', 'Internal note', $v('note'), ['placeholder' => 'e.g. Summer newsletter']) ?>
        <?= field_select('status', 'Status', ['active' => 'Active', 'disabled' => 'Disabled'], $v('status', 'active')) ?>
      </section>
      <section data-pane="disc" hidden>
        <div data-only="cart products categories">
          <div class="g2"><?= field_select('type', 'Discount type', ['percent' => 'Percent %', 'fixed' => 'Fixed amount (' . $sym . ')'], $v('type', 'percent')) ?>
          <?= field_text('value', 'Value', old('value', ($e0 && $e0['type'] === 'fixed') ? from_cents((int) $e0['value']) : ($e0['value'] ?? '')), ['type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => '10']) ?></div>
          <?= field_text('max_discount', 'Maximum discount (cap, blank = none)', old('max_discount', $e0 && $e0['max_discount'] ? from_cents((int) $e0['max_discount']) : ''), ['type' => 'number', 'step' => '0.01', 'min' => '0']) ?>
          <label class="chk"><input type="hidden" name="free_shipping" value="0"><input type="checkbox" name="free_shipping" value="1" <?= $v('free_shipping', 0) && (int) ($e0['value'] ?? 1) > 0 ? 'checked' : '' ?>><i></i><span>Also give free shipping</span></label>
        </div>
        <p class="muted" data-only="shipping">This coupon removes the shipping cost on physical orders. No discount value is needed.</p>
        <div class="sp" data-search-pick data-scope="products" hidden><div class="sp-top"><input type="search" data-pick-filter placeholder="Search products to include…"></div>
          <div class="pick"><?php foreach ($products as $p): ?><label><input type="checkbox" name="product_ids[]" value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], $selP, true) ? 'checked' : '' ?>><span><?= e($p['name']) ?><?= $p['sku'] ? ' · ' . e($p['sku']) : '' ?></span></label><?php endforeach; ?></div></div>
        <div class="sp" data-search-pick data-scope="categories" hidden><div class="sp-top"><input type="search" data-pick-filter placeholder="Search departments…"></div>
          <div class="pick"><?php foreach ($cats as $c): ?><label><input type="checkbox" name="category_ids[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $selC, true) ? 'checked' : '' ?>><span><?= e($c['name']) ?></span></label><?php endforeach; ?></div></div>
      </section>
      <section data-pane="cond" hidden>
        <div class="g2"><?= field_text('min_total', 'Minimum cart spend', old('min_total', $e0 && $e0['min_total'] ? from_cents((int) $e0['min_total']) : ''), ['type' => 'number', 'step' => '0.01', 'min' => '0']) ?>
        <?= field_text('min_qty', 'Minimum qualifying items', $v('min_qty', 0), ['type' => 'number', 'min' => '0']) ?></div>
        <label class="chk"><input type="hidden" name="exclude_sale" value="0"><input type="checkbox" name="exclude_sale" value="1" <?= $v('exclude_sale', 0) ? 'checked' : '' ?>><i></i><span>Exclude items already on sale</span></label>
        <label class="chk"><input type="hidden" name="first_order_only" value="0"><input type="checkbox" name="first_order_only" value="1" <?= $v('first_order_only', 0) ? 'checked' : '' ?>><i></i><span>First order only (new customers)</span></label>
      </section>
      <section data-pane="when" hidden>
        <div class="g2"><?= field_text('starts_at', 'Starts', old('starts_at', $e0 && $e0['starts_at'] ? substr($e0['starts_at'], 0, 10) : ''), ['type' => 'date']) ?>
        <?= field_text('ends_at', 'Ends', old('ends_at', $e0 && $e0['ends_at'] ? substr($e0['ends_at'], 0, 10) : ''), ['type' => 'date']) ?></div>
        <div class="g2"><?= field_text('max_uses', 'Total uses (0 = unlimited)', $v('max_uses', 0), ['type' => 'number', 'min' => '0']) ?>
        <?= field_text('per_user_limit', 'Uses per customer (0 = unlimited)', $v('per_user_limit', 0), ['type' => 'number', 'min' => '0']) ?></div>
        <?php if ($e0): ?><div class="ustat"><span>Used <b><?= (int) $e0['used'] ?></b> time(s)</span><span>Created <?= e(fmt_date($e0['created_at'], false)) ?></span></div><?php endif; ?>
      </section>
    </div>
    <aside class="livepane"><h4>Live preview</h4>
      <div class="cpn-card" id="cpn-card"><div class="off">10% OFF</div><div class="what">on the whole cart</div><span class="code">YOURCODE</span><span class="fine">No restrictions</span></div>
      <h4 style="margin-top:16px">In plain words</h4><div class="cpn-sum" id="cpn-sum" style="margin:0"></div>
    </aside>
  </div></div>
  <div class="dp-foot"><?php if ($e0): ?><button type="submit" class="btn ghost danger grow" formaction="<?= e(url('admin/coupons/' . $e0['id'] . '/delete')) ?>" formnovalidate data-confirm="Delete this coupon?">Delete</button><?php else: ?><span class="grow"></span><?php endif; ?>
    <button type="button" class="btn ghost" data-drawer-close>Cancel</button><button class="btn">Save coupon</button></div>
</form></div>
