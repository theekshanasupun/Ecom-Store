<?php /** @var array $rows @var ?\App\Models\DiscountRule $edit @var array $cats @var array $names @var array $init @var array $stats @var ?\App\Models\Product $sample */
$e0 = $edit ?? null;
$v = fn ($k, $d = '') => old($k, $e0[$k] ?? $d);
$open = $e0 || request()->boolean('new') || old('name') !== null;
$sym = (string) setting('currency_symbol', '$');
$selC = $e0 ? $e0->categoryIds() : [];
$scopeLbl = ['all' => 'Everything in the store', 'products' => 'Specific products', 'categories' => 'Departments'];
$catNames = array_column($cats, 'name', 'id');
?>
<div class="kpis">
  <div class="kpi"><b><?= (int) $stats['total'] ?></b><small>Discount rules</small></div>
  <div class="kpi"><b><?= (int) $stats['active'] ?></b><small>Live right now</small></div>
  <div class="kpi"><b><?= (int) $stats['scheduled'] ?></b><small>Scheduled</small></div>
  <div class="kpi"><b><?= (int) $stats['tiers'] ?></b><small>Quantity tiers</small></div>
</div>
<div class="toolbar">
  <div class="quick"><input type="search" data-tablefilter="#disc-table" placeholder="Filter discounts…" aria-label="Filter"><span class="muted small">Prices drop automatically on the storefront — no codes. The best rule wins per product (they don't stack).</span></div>
  <button type="button" class="btn" data-drawer-open="#disc-drawer">＋ New discount</button>
</div>

<section class="apanel flush"><div class="tbl-wrap"><table class="tbl" id="disc-table">
<thead><tr><th>Name</th><th>Applies to</th><th>Discount</th><th>Rules</th><th>Schedule</th><th>On</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $x): $ids = $x->scope === 'categories' ? array_map(fn ($i) => $catNames[$i] ?? '#' . $i, $x->categoryIds()) : []; $rules = array_filter([$x->min_qty > 1 ? 'Buy ' . $x->min_qty . '+' : '', $x->audience !== 'all' ? ucfirst($x->audience) . ' only' : '', $x->skip_sale ? 'skips sale items' : '', $x->priority ? 'priority ' . $x->priority : '']);
  $live = $x->status === 'active' && (!$x->starts_at || $x->starts_at->isPast()) && (!$x->ends_at || $x->ends_at->isFuture()); ?>
<tr><td><b><a href="<?= e(url('admin/discounts?edit=' . $x->id)) ?>"><?= e($x->name) ?></a></b><?php if ($x->label): ?><small>Badge: <?= e($x->label) ?></small><?php endif; ?></td>
<td><?= e($scopeLbl[$x->scope]) ?><?php if ($x->scope === 'products'): ?> <small>(<?= count($x->productIds()) ?>)</small><?php elseif ($ids): ?><small><?= e(implode(', ', array_slice($ids, 0, 3))) ?><?= count($ids) > 3 ? '…' : '' ?></small><?php endif; ?></td>
<td><b><?= $x->type === 'percent' ? (int) $x->value . '% off' : money($x->value) . ' off' ?></b></td>
<td class="muted small"><?= e($rules ? implode(' · ', $rules) : '—') ?></td>
<td class="muted"><?= $x->starts_at || $x->ends_at ? e(($x->starts_at ? $x->starts_at->format('Y-m-d') : '…') . ' → ' . ($x->ends_at ? $x->ends_at->format('Y-m-d') : '…')) : 'Always' ?><?php if ($x->status === 'active' && $x->ends_at && $x->ends_at->isPast()): ?> <span class="badge s-cancelled">ended</span><?php elseif ($x->status === 'active' && $x->starts_at && $x->starts_at->isFuture()): ?> <span class="badge s-pending">scheduled</span><?php elseif ($live): ?> <span class="badge s-completed">live</span><?php endif; ?></td>
<td><label class="switch"><input type="checkbox" data-switch-url="<?= e(url('admin/discounts/' . $x->id . '/toggle')) ?>" <?= $x->status === 'active' ? 'checked' : '' ?> aria-label="Enabled"><i></i></label></td>
<td class="r"><div class="rowmenu"><a class="btn sm ghost" href="<?= e(url('admin/discounts?edit=' . $x->id)) ?>">Edit</a><button type="button" class="btn sm ghost" data-post-url="<?= e(url('admin/discounts/' . $x->id . '/duplicate')) ?>">Duplicate</button></div></td></tr>
<?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="7" class="empty">No automatic discounts yet — <a href="#disc-drawer" data-drawer-open="#disc-drawer">set up your first sale</a>. Ideas: 20% off a department, a bulk-buy tier, a members-only price.</td></tr><?php endif; ?>
</tbody></table></div></section>

<div class="drawer-panel dp-wide" id="disc-drawer" hidden <?= $open ? 'data-autoopen' : '' ?>>
<form method="post" action="<?= e(url('admin/discounts/save')) ?>" id="disc-form" data-sym="<?= e($sym) ?>" data-sample="<?= e($sample ? json_encode(['name' => $sample->name, 'price' => $sample->getRawOriginal('price')]) : '') ?>"><?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) ($e0['id'] ?? 0) ?>">
  <div class="dp-head"><h2><?= $e0 ? 'Edit discount' : 'New discount' ?></h2><button type="button" data-drawer-close aria-label="Close">✕</button></div>
  <div class="dp-body"><div class="dp-split">
    <div>
      <div class="tabs2" data-tabs><button type="button" data-tab="what" class="on">1 · Applies to</button><button type="button" data-tab="how">2 · Discount</button><button type="button" data-tab="rules">3 · Rules &amp; schedule</button></div>
      <section data-pane="what">
        <?= field_text('name', 'Name (internal)', $v('name'), ['required' => true, 'placeholder' => 'Summer toys sale']) ?>
        <div class="typecards">
          <?php foreach (['all' => ['🏬', 'Everything', 'Sitewide sale on all products'], 'categories' => ['🗂️', 'Departments', 'Pick one or more departments'], 'products' => ['🏷️', 'Products', 'Hand-pick products']] as $k => [$ic, $t, $s]): ?>
          <label class="typecard <?= $v('scope', 'all') === $k ? 'on' : '' ?>" data-radio="scope"><input type="radio" name="scope" value="<?= $k ?>" <?= $v('scope', 'all') === $k ? 'checked' : '' ?> hidden><span class="ti"><?= $ic ?></span><b><?= $t ?></b><small><?= $s ?></small></label>
          <?php endforeach; ?>
        </div>
        <div data-showwhen="scope=categories" <?= $v('scope', 'all') === 'categories' ? '' : 'hidden' ?>>
          <div class="sp" data-search-pick><div class="sp-top"><input type="search" data-pick-filter placeholder="Search departments…"></div>
            <div class="pick"><?php foreach ($cats as $c): ?><label><input type="checkbox" name="category_ids[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $selC, true) ? 'checked' : '' ?>><span><?= e($c['name']) ?></span></label><?php endforeach; ?></div></div>
        </div>
        <div data-showwhen="scope=products" <?= $v('scope', 'all') === 'products' ? '' : 'hidden' ?>>
          <div class="prodpick" data-prodpick data-url="<?= e(url('admin/products/search.json')) ?>" data-init="<?= e(json_encode($init)) ?>"><input type="hidden" name="product_ids" value="<?= e($e0 ? implode(',', $e0->productIds()) : old('product_ids')) ?>">
            <input type="search" placeholder="Search products by name or SKU…" autocomplete="off"><div class="pp-results" data-results></div><div class="pp-chips" data-chips></div></div>
        </div>
      </section>
      <section data-pane="how" hidden>
        <div class="g2"><?= field_select('type', 'Type', ['percent' => 'Percent off', 'fixed' => 'Fixed amount off'], $v('type', 'percent')) ?>
          <?= field_text('value', 'Value', old('value', $e0 ? ($e0->type === 'fixed' ? from_cents((int) $e0->value) : $e0->value) : ''), ['type' => 'number', 'step' => '0.01', 'min' => '0', 'required' => true, 'placeholder' => '20']) ?></div>
        <div class="g2"><?= field_text('min_qty', 'Quantity tier — buy at least', $v('min_qty', 1), ['type' => 'number', 'min' => '1', 'hint' => '1 = always. Use 3 for “buy 3 or more, save X”.']) ?>
          <?= field_text('label', 'Badge text', $v('label'), ['maxlength' => 40, 'placeholder' => 'Summer sale', 'hint' => 'Shown on the product when it has no badge of its own.']) ?></div>
      </section>
      <section data-pane="rules" hidden>
        <?= field_select('audience', 'Who gets it?', ['all' => 'Everyone', 'customers' => 'Signed-in customers only (members price)', 'guests' => 'Guests only'], $v('audience', 'all')) ?>
        <label class="chk"><input type="hidden" name="skip_sale" value="0"><input type="checkbox" name="skip_sale" value="1" <?= $v('skip_sale', 0) ? 'checked' : '' ?>><i></i><span>Skip items that already show a compare-at (sale) price</span></label>
        <div class="g2"><?= field_text('starts_at', 'Starts', old('starts_at', $e0 && $e0->starts_at ? $e0->starts_at->format('Y-m-d') : ''), ['type' => 'date']) ?>
          <?= field_text('ends_at', 'Ends', old('ends_at', $e0 && $e0->ends_at ? $e0->ends_at->format('Y-m-d') : ''), ['type' => 'date']) ?></div>
        <div class="g2"><?= field_text('priority', 'Priority', $v('priority', 0), ['type' => 'number', 'min' => '0', 'hint' => 'Only used to break ties.']) ?>
          <?= field_select('status', 'Status', ['active' => 'Active', 'draft' => 'Draft (off)'], $v('status', 'active')) ?></div>
      </section>
    </div>
    <aside class="livepane"><h4>Live preview</h4>
      <div class="pricetag" id="disc-tag"><small>Example product</small><b data-ex-name>Sample</b><div class="pt-prices"><strong data-ex-now>$0</strong><s data-ex-was></s><em data-ex-save></em></div></div>
      <h4 style="margin-top:16px">In plain words</h4><div class="cpn-sum" id="disc-sum" style="margin:0"></div></aside>
  </div></div>
  <div class="dp-foot"><?php if ($e0): ?><button type="submit" class="btn ghost danger grow" formaction="<?= e(url('admin/discounts/' . $e0['id'] . '/delete')) ?>" formnovalidate data-confirm="Delete this discount?">Delete</button><?php else: ?><span class="grow"></span><?php endif; ?>
    <button type="button" class="btn ghost" data-drawer-close>Cancel</button><button class="btn">Save discount</button></div>
</form></div>
