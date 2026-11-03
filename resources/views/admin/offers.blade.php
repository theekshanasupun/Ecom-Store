<?php /** @var array $rows @var ?\App\Models\Offer $edit @var array $cats @var array $initTrig @var array $initOffer @var array $stats */
$e0 = $edit ?? null;
$v = fn ($k, $d = '') => old($k, $e0[$k] ?? $d);
$open = $e0 || request()->boolean('new') || old('name') !== null;
$sym = (string) setting('currency_symbol', '$');
$kind = $v('kind', 'crosssell');
$selCat = $e0 ? $e0->triggerCategories() : [];
$pl = old('placement', $e0 ? $e0->placements() : ['product', 'cart']);
$kinds = ['crosssell' => ['🛍️', 'Cross-sell', 'Frequently bought together — related add-ons'], 'upsell' => ['⬆️', 'Upsell', 'A better / bigger version to upgrade to'], 'bundle' => ['📦', 'Bundle', 'A fixed set sold together at a discount']];
$names = [];
foreach ($rows as $x) foreach (array_merge($x->offerProducts(), $x->triggerProducts()) as $i) $names[$i] = true;
$nm = $names ? \App\Models\Product::withoutEvents(fn () => \App\Models\Product::whereIn('id', array_keys($names))->pluck('name', 'id')->all()) : [];
?>
<div class="kpis">
  <div class="kpi"><b><?= (int) $stats['active'] ?> / <?= (int) $stats['total'] ?></b><small>Offers live</small></div>
  <div class="kpi"><b><?= (int) $stats['views'] ?></b><small>Times shown</small></div>
  <div class="kpi"><b><?= (int) $stats['adds'] ?></b><small>Added to cart</small></div>
  <div class="kpi"><b><?= $stats['views'] ? round($stats['adds'] / $stats['views'] * 100, 1) : 0 ?>%</b><small>Conversion</small></div>
</div>
<div class="toolbar">
  <div class="quick"><input type="search" data-tablefilter="#offer-table" placeholder="Filter offers…" aria-label="Filter"><span class="muted small">Show recommendations on product pages, in the cart and at checkout.</span></div>
  <button type="button" class="btn" data-drawer-open="#offer-drawer">＋ New offer</button>
</div>

<section class="apanel flush"><div class="tbl-wrap"><table class="tbl" id="offer-table">
<thead><tr><th>Offer</th><th>Type</th><th>When</th><th>Recommends</th><th>Discount</th><th>Where</th><th>Shown → added</th><th>On</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $x): $trig = array_merge(array_map(fn ($i) => $nm[$i] ?? '#' . $i, $x->triggerProducts()), array_map(fn ($i) => 'dept #' . $i, $x->triggerCategories())); ?>
<tr><td><b><a href="<?= e(url('admin/offers?edit=' . $x->id)) ?>"><?= e($x->name) ?></a></b><?php if ($x->headline): ?><small><?= e($x->headline) ?></small><?php endif; ?></td>
<td><span class="badge t-<?= $x->kind === 'bundle' ? 'key' : 'physical' ?>"><?= e($kinds[$x->kind][1]) ?></span></td>
<td class="small"><?= $x->kind === 'bundle' ? '<span class="muted">All items in the cart</span>' : ($trig ? e(implode(', ', array_slice($trig, 0, 2))) . (count($trig) > 2 ? '…' : '') : '<span class="muted">Everywhere</span>') ?></td>
<td class="small"><?= e(implode(', ', array_slice(array_map(fn ($i) => $nm[$i] ?? '#' . $i, $x->offerProducts()), 0, 3))) ?><?= count($x->offerProducts()) > 3 ? '…' : '' ?></td>
<td><?= $x->discount_type === 'none' ? '<span class="muted">—</span>' : ($x->discount_type === 'percent' ? (int) $x->discount_value . '%' : money($x->discount_value)) ?></td>
<td class="small muted"><?= e(implode(' · ', $x->placements())) ?></td>
<td><?= (int) $x->views ?> → <b><?= (int) $x->adds ?></b></td>
<td><label class="switch"><input type="checkbox" data-switch-url="<?= e(url('admin/offers/' . $x->id . '/toggle')) ?>" <?= $x->status === 'active' ? 'checked' : '' ?> aria-label="Enabled"><i></i></label></td>
<td class="r"><div class="rowmenu"><a class="btn sm ghost" href="<?= e(url('admin/offers?edit=' . $x->id)) ?>">Edit</a><button type="button" class="btn sm ghost" data-post-url="<?= e(url('admin/offers/' . $x->id . '/duplicate')) ?>">Duplicate</button></div></td></tr>
<?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="9" class="empty">No offers yet — <a href="#offer-drawer" data-drawer-open="#offer-drawer">create a bundle or a cross-sell</a>.</td></tr><?php endif; ?>
</tbody></table></div></section>

<div class="drawer-panel dp-wide" id="offer-drawer" hidden <?= $open ? 'data-autoopen' : '' ?>>
<form method="post" action="<?= e(url('admin/offers/save')) ?>" id="offer-form" data-sym="<?= e($sym) ?>"><?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) ($e0['id'] ?? 0) ?>">
  <div class="dp-head"><h2><?= $e0 ? 'Edit offer' : 'New offer' ?></h2><button type="button" data-drawer-close aria-label="Close">✕</button></div>
  <div class="dp-body"><div class="dp-split">
    <div>
      <div class="tabs2" data-tabs><button type="button" data-tab="type" class="on">1 · Type</button><button type="button" data-tab="prods">2 · Products</button><button type="button" data-tab="disc">3 · Discount &amp; placement</button></div>
      <section data-pane="type">
        <?= field_text('name', 'Name (internal)', $v('name'), ['required' => true, 'placeholder' => 'Phone case cross-sell']) ?>
        <div class="typecards">
          <?php foreach ($kinds as $k => [$ic, $t, $s]): ?><label class="typecard <?= $kind === $k ? 'on' : '' ?>" data-radio="kind"><input type="radio" name="kind" value="<?= $k ?>" <?= $kind === $k ? 'checked' : '' ?> hidden><span class="ti"><?= $ic ?></span><b><?= $t ?></b><small><?= $s ?></small></label><?php endforeach; ?>
        </div>
        <div class="g2"><?= field_text('headline', 'Headline shown to customers', $v('headline'), ['maxlength' => 120, 'placeholder' => 'Complete the look']) ?><?= field_text('subtext', 'Sub-text', $v('subtext'), ['maxlength' => 190, 'placeholder' => 'Customers also bought…']) ?></div>
        <?= field_select('status', 'Status', ['active' => 'Active', 'draft' => 'Draft (off)'], $v('status', 'active')) ?>
      </section>
      <section data-pane="prods" hidden>
        <div data-showwhen="kind=upsell,crosssell">
          <h3 class="sec">When does it show?</h3>
          <p class="muted small" style="margin-top:0">Show this recommendation when the shopper views / has one of these products or departments in the cart. Leave both empty to show it everywhere.</p>
          <div class="prodpick" data-prodpick data-url="<?= e(url('admin/products/search.json')) ?>" data-init="<?= e(json_encode($initTrig)) ?>"><input type="hidden" name="trigger_product_ids" value="<?= e($e0 ? implode(',', $e0->triggerProducts()) : old('trigger_product_ids')) ?>">
            <input type="search" placeholder="Trigger products — search by name or SKU…" autocomplete="off"><div class="pp-results" data-results></div><div class="pp-chips" data-chips></div></div>
          <div class="sp" data-search-pick style="margin-top:10px"><div class="sp-top"><input type="search" data-pick-filter placeholder="…or whole departments"></div>
            <div class="pick"><?php foreach ($cats as $c): ?><label><input type="checkbox" name="trigger_category_ids[]" value="<?= (int) $c['id'] ?>" <?= in_array((int) $c['id'], $selCat, true) ? 'checked' : '' ?>><span><?= e($c['name']) ?></span></label><?php endforeach; ?></div></div>
        </div>
        <h3 class="sec"><span data-showwhen="kind=bundle">Products in the bundle (2 or more)</span><span data-showwhen="kind=upsell,crosssell">Products to recommend</span></h3>
        <div class="prodpick" data-prodpick data-url="<?= e(url('admin/products/search.json')) ?>" data-init="<?= e(json_encode($initOffer)) ?>"><input type="hidden" name="offer_product_ids" value="<?= e($e0 ? implode(',', $e0->offerProducts()) : old('offer_product_ids')) ?>">
          <input type="search" placeholder="Search products by name or SKU…" autocomplete="off"><div class="pp-results" data-results></div><div class="pp-chips" data-chips></div></div>
      </section>
      <section data-pane="disc" hidden>
        <div class="g2"><?= field_select('discount_type', 'Discount', ['none' => 'No discount — just recommend', 'percent' => 'Percent off', 'fixed' => 'Fixed amount off'], $v('discount_type', 'none')) ?>
          <?= field_text('discount_value', 'Value', old('discount_value', $e0 ? ($e0->discount_type === 'fixed' ? from_cents((int) $e0->discount_value) : $e0->discount_value) : ''), ['type' => 'number', 'step' => '0.01', 'min' => '0', 'placeholder' => '10']) ?></div>
        <p class="muted small" data-showwhen="kind=bundle">Bundle discount applies automatically whenever every product of the bundle is in the cart (per complete set).</p>
        <p class="muted small" data-showwhen="kind=upsell,crosssell">The discount applies to the recommended product while a trigger item is in the cart.</p>
        <h3 class="sec">Show it on</h3>
        <div class="chkrow"><?php foreach (['product' => 'Product pages', 'cart' => 'Cart', 'checkout' => 'Checkout (order bump)'] as $k => $l): ?><label class="chk"><input type="checkbox" name="placement[]" value="<?= $k ?>" <?= in_array($k, (array) $pl, true) ? 'checked' : '' ?>><i></i><span><?= $l ?></span></label><?php endforeach; ?></div>
        <?= field_text('sort', 'Order (lower shows first)', $v('sort', 0), ['type' => 'number', 'min' => '0']) ?>
      </section>
    </div>
    <aside class="livepane"><h4>In plain words</h4><div class="cpn-sum" id="offer-sum" style="margin:0"></div>
      <h4 style="margin-top:16px">How it looks</h4><div class="offer-demo" id="offer-demo"></div></aside>
  </div></div>
  <div class="dp-foot"><?php if ($e0): ?><button type="submit" class="btn ghost danger grow" formaction="<?= e(url('admin/offers/' . $e0['id'] . '/delete')) ?>" formnovalidate data-confirm="Delete this offer?">Delete</button><?php else: ?><span class="grow"></span><?php endif; ?>
    <button type="button" class="btn ghost" data-drawer-close>Cancel</button><button class="btn">Save offer</button></div>
</form></div>
