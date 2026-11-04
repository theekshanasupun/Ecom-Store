<?php
use App\Services\Catalog;
/** @var array $p @var array $related */
$cat = $p['cat'];
$disc = $p->discountPct();
$avail = $p->available();
$ink = \App\Support\Art::ink($p['color']);
$details = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $p['details']))));
$gallery = array_merge($p['image'] ? [$p['image']] : [], $p['gallery_list']);
$cardArt = !$p['image'] && in_array($p['art'] ?: $p['type'], ['key', 'gift', 'account'], true);
// ---- v2.6: variant-aware pricing, quantity tiers and offers ----
$opts = [];
foreach ($p['options_list'] as $o) $opts[$o['name']] = $o['values'][0] ?? '';
$vlist = $p->variantList();
$start = $p->priceInfo($opts);
$tiers = \App\Services\Pricing::tiers($p);
$offers = \App\Services\Offers::forPlacement('product', [], [], $p);
\App\Services\Offers::seen($offers);
$loyRate = \App\Services\Loyalty::productRate($p, auth()->user());
$loyGuest = !auth()->check();
$vjson = [
  'loy' => $loyRate ? ['rate' => $loyRate, 'mode' => (string) setting('loyalty_round', 'floor'), 'guest' => $loyGuest, 'login' => url('login'), 'name' => \App\Services\Loyalty::name()] : null,
  'base' => ['price' => $start['price'], 'compare' => $start['compare'], 'sku' => $start['sku'], 'avail' => $avail >= PHP_INT_MAX ? -1 : $avail, 'instant' => (bool) $p['instant']],
  'sym' => (string) setting('currency_symbol', '$'), 'low' => (int) setting('low_stock', 3),
  'options' => array_map(fn ($o) => ['name' => $o['name'], 'values' => $o['values']], $p['options_list']),
  'variants' => array_map(function ($v) use ($p) { $i = $p->priceInfo($v['opts']); return ['opts' => $v['opts'], 'price' => $i['price'], 'compare' => $i['compare'], 'stock' => $p->available($v['opts']) >= PHP_INT_MAX ? -1 : $p->available($v['opts']), 'sku' => $i['sku'], 'image' => $v['image'] ? upload_url($v['image']) : '', 'active' => $v['active']]; }, $vlist),
];
$hexOf = fn ($o, $v) => ($c = $o['colors'][$v] ?? '') && preg_match('/^#[0-9a-f]{3,8}$/i', $c) ? $c : '';
?>
<section class="pdp" style="--c:<?= e($p['color']) ?>;--ink:<?= e($ink) ?>">
  <nav class="crumbs"><a href="<?= e(url('')) ?>"><?= e(setting('store_name')) ?></a> / <a href="<?= e(url('shop/' . $cat['slug'])) ?>"><?= e($cat['name']) ?></a> / <span><?= e($p['name']) ?></span></nav>
  <div class="pdp-grid">
    <div class="pdp-media" data-gallery>
      <div class="pdp-stage <?= $cardArt ? 'is-card' : '' ?>" data-art id="stage">
        <?php if ($p['badge'] || $disc): ?><span class="pbadge"><?= e($p['badge'] ?: '-' . $disc . '%') ?></span><?php endif; ?>
        <?= Catalog::media($p, false) ?>
      </div>
      <?php if (count($gallery) > 1): ?>
      <div class="thumbs"><?php foreach ($gallery as $i => $g): ?><button type="button" class="<?= $i === 0 ? 'on' : '' ?>" data-src="<?= e(upload_url($g)) ?>"><img src="<?= e(upload_url($g)) ?>" alt="" loading="lazy"></button><?php endforeach; ?></div>
      <?php endif; ?>
    </div>

    <div class="pdp-info">
      <p class="eyebrow cc"><?= e($cat['name']) ?><?= $p['sku'] ? ' · ' . e($p['sku']) : '' ?></p>
      <h1 class="h1"><?= e($p['name']) ?></h1>
      <p class="lede"><?= e($p['tagline']) ?></p>
      <div class="pbox" data-pbox>
        <div class="pbox-main">
          <b class="pbox-now" data-now><?= money($start['price'], $p['type'] !== 'key') ?></b>
          <s class="pbox-was" data-was <?= $start['compare'] ? '' : 'hidden' ?>><?= $start['compare'] ? money($start['compare'], $p['type'] !== 'key') : '' ?></s>
          <span class="pbox-save" data-save <?= $start['compare'] ? '' : 'hidden' ?>><?= $start['compare'] ? 'Save ' . money($start['save'], true) . ' · ' . $start['pct'] . '%' : '' ?></span>
        </div>
        <?php if ($loyRate): $ep = \App\Services\Loyalty::estimate($start['price'], $loyRate); ?>
        <div class="loy-earn" data-earn <?= $ep > 0 ? '' : 'hidden' ?>><span>★</span> <span data-earn-text><?= $ep > 0 ? ($loyGuest ? 'Earn <b>' . number_format($ep) . '</b> ' . e(\App\Services\Loyalty::name()) . ' points with this purchase · <a href="' . e(url('login')) . '">sign in</a>' : 'You\'ll earn <b>' . number_format($ep) . '</b> points with this purchase') : '' ?></span></div>
        <?php endif; ?>
        <div class="pbox-sub"><span data-sku><?= $start['sku'] ? 'SKU ' . e($start['sku']) : '' ?></span><?php if (setting('tax_rate') > 0): ?><span><?= (float) setting('tax_rate') ?>% <?= e(strtolower(setting('tax_label', 'tax'))) ?> added at checkout</span><?php endif; ?></div>
        <?php if ($tiers): ?><ul class="pbox-tiers"><?php foreach ($tiers as $t): ?><li><b>Buy <?= (int) $t['qty'] ?>+</b> · <?= $t['type'] === 'percent' ? (int) $t['value'] . '% off each' : money($t['value'], true) . ' off each' ?></li><?php endforeach; ?></ul><?php endif; ?>
      </div>

      <div class="pdp-tags">
        <?php if ($p['instant']): ?><span class="tag tag-key">⚡ Instant · key on screen + email</span>
        <?php elseif ($p['type'] === 'physical'): ?><span class="tag"><?= e(setting('shipping_note')) ?></span><?php endif; ?>
        <span data-stocktag><?php if (!$p['in_stock']): ?><span class="tag bad">Sold out</span>
        <?php elseif ($avail < PHP_INT_MAX && $avail <= (int) setting('low_stock', 3)): ?><span class="tag warn">Only <?= $avail ?> left</span><?php endif; ?></span>
        <?php if ($p['delivery_note'] && !$p['instant']): ?><span class="tag"><?= e($p['delivery_note']) ?></span><?php endif; ?>
      </div>

      <form method="post" action="<?= e(url('cart/add')) ?>" data-add-form class="pdp-form" data-feature>
        <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
        <?php foreach ($p['options_list'] as $o): $isCol = ($o['type'] ?? '') === 'color'; $isSel = ($o['type'] ?? '') === 'select'; ?>
        <fieldset class="optgroup"><legend><?= e($o['name']) ?><b class="optpick" data-optpick="<?= e($o['name']) ?>"><?= e($o['values'][0] ?? '') ?></b></legend>
          <?php if ($isSel): ?>
          <select class="optsel" name="opt[<?= e($o['name']) ?>]" data-optsel="<?= e($o['name']) ?>"><?php foreach ($o['values'] as $v): ?><option value="<?= e($v) ?>"><?= e($v) ?></option><?php endforeach; ?></select>
          <?php else: ?>
          <div class="optset <?= $isCol ? 'swatches' : '' ?>" data-opt="<?= e($o['name']) ?>">
          <?php foreach ($o['values'] as $i => $v): $hex = $hexOf($o, $v); ?>
            <label class="opt <?= $isCol ? 'sw' : '' ?>" <?= $isCol ? 'style="--sw:' . e($hex ?: strtolower($v)) . '" title="' . e($v) . '"' : '' ?>><input type="radio" name="opt[<?= e($o['name']) ?>]" value="<?= e($v) ?>" <?= $i === 0 ? 'checked' : '' ?> data-color="<?= $isCol ? e($hex ?: $v) : '' ?>"><span><?= $isCol ? '' : e($v) ?></span></label>
          <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </fieldset>
        <?php endforeach; ?>
        <?php if ($p['in_stock']): ?>
        <div class="pdp-buy">
          <div class="qty"><button type="button" data-q="-1" aria-label="Less">−</button><input type="number" name="qty" value="1" min="1" max="<?= $avail < 99 ? $avail : 99 ?>" aria-label="Quantity"><button type="button" data-q="1" aria-label="More">+</button></div>
          <button class="btn btn-cc" type="submit" data-addbtn>Add to cart · <span data-btnprice><?= money($start['price']) ?></span></button>
        </div>
        <button class="btn btn-ghost btn-block" type="submit" name="buy_now" value="1"><?= $p['instant'] ? '⚡ Buy now · get key instantly' : 'Buy it now' ?></button>
        <?php else: ?><button class="btn btn-ghost btn-block" disabled>Sold out</button><?php endif; ?>
      </form>

      <script type="application/json" id="pdp-data"><?= json_encode($vjson, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
      <?php foreach ($offers as $of): $o = $of['offer']; $prods = $of['products']; ?>
      <?= partial('partials/offer_block', ['o' => $o, 'prods' => $prods, 'current' => $p]) ?>
      <?php endforeach; ?>

      <?php if ($p['description']): ?><div class="prose"><?= nl2br(e($p['description'])) ?></div><?php endif; ?>
      <?php if ($details): ?><ul class="checks"><?php foreach ($details as $d): ?><li><?= e($d) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
  </div>
</section>

<?php if ($related): ?>
<section class="block" style="--c:<?= e($p['color']) ?>">
  <div class="block-head"><h2 class="h2">More <?= e(strtolower($cat['name'])) ?></h2></div>
  <div class="pgrid"><?php foreach ($related as $r) echo partial('partials/product_card', ['p' => $r]); ?></div>
</section>
<?php endif; ?>
