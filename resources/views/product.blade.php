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
      <div class="pdp-price"><?= Catalog::priceHtml($p, $p['type'] !== 'key') ?><?php if ($disc): ?><span class="save">Save <?= $disc ?>%</span><?php endif; ?></div>

      <div class="pdp-tags">
        <?php if ($p['instant']): ?><span class="tag tag-key">⚡ Instant · key on screen + email</span>
        <?php elseif ($p['type'] === 'physical'): ?><span class="tag"><?= e(setting('shipping_note')) ?></span><?php endif; ?>
        <?php if (!$p['in_stock']): ?><span class="tag bad">Sold out</span>
        <?php elseif ($avail < PHP_INT_MAX && $avail <= (int) setting('low_stock', 3)): ?><span class="tag warn">Only <?= $avail ?> left</span><?php endif; ?>
        <?php if ($p['delivery_note'] && !$p['instant']): ?><span class="tag"><?= e($p['delivery_note']) ?></span><?php endif; ?>
      </div>

      <form method="post" action="<?= e(url('cart/add')) ?>" data-add-form class="pdp-form" data-feature>
        <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
        <?php foreach ($p['options_list'] as $o): $isCol = stripos($o['name'], 'col') === 0; ?>
        <fieldset class="optgroup"><legend><?= e($o['name']) ?></legend>
          <div class="optset <?= $isCol ? 'swatches' : '' ?>" data-opt="<?= e($o['name']) ?>">
          <?php foreach ($o['values'] as $i => $v): ?>
            <label class="opt <?= $isCol ? 'sw' : '' ?>" <?= $isCol ? 'style="--sw:' . e(strtolower($v)) . '" title="' . e($v) . '"' : '' ?>><input type="radio" name="opt[<?= e($o['name']) ?>]" value="<?= e($v) ?>" <?= $i === 0 ? 'checked' : '' ?> data-color="<?= $isCol ? e($v) : '' ?>"><span><?= $isCol ? '' : e($v) ?></span></label>
          <?php endforeach; ?>
          </div>
        </fieldset>
        <?php endforeach; ?>
        <?php if ($p['in_stock']): ?>
        <div class="pdp-buy">
          <div class="qty"><button type="button" data-q="-1" aria-label="Less">−</button><input type="number" name="qty" value="1" min="1" max="<?= $avail < 99 ? $avail : 99 ?>" aria-label="Quantity"><button type="button" data-q="1" aria-label="More">+</button></div>
          <button class="btn btn-cc" type="submit">Add to cart · <?= money($p['price']) ?></button>
        </div>
        <button class="btn btn-ghost btn-block" type="submit" name="buy_now" value="1"><?= $p['instant'] ? '⚡ Buy now · get key instantly' : 'Buy it now' ?></button>
        <?php else: ?><button class="btn btn-ghost btn-block" disabled>Sold out</button><?php endif; ?>
      </form>

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
