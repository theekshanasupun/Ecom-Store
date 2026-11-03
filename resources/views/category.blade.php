<?php
use App\Services\Catalog;
/** @var array $cat @var array $products @var ?array $feature @var array $tags @var array $others @var string $sort */
$ink = \App\Support\Art::ink($cat['color']);
?>
<section class="dept" style="--c:<?= e($cat['color']) ?>;--ink:<?= e($ink) ?>">
  <nav class="crumbs"><a href="<?= e(url('')) ?>"><?= e(setting('store_name')) ?></a> / <span><?= e($cat['name']) ?></span></nav>
  <?php if ($cat['eyebrow']): ?><p class="eyebrow cc"><?= e($cat['eyebrow']) ?></p><?php endif; ?>
  <h1 class="mega cc" data-reveal><?= e($cat['headline'] ?: strtoupper($cat['name'])) ?></h1>
  <p class="lede" data-reveal><?= e($cat['tagline']) ?></p>

  <?php if ($feature): $f = $feature; ?>
  <div class="feature" data-reveal data-feature>
    <div class="feature-top"><b><?= e($f['name']) ?></b><span class="mono"><?= e($f['instant'] ? 'Instant key' : ($f['tag_list'][0] ?? $cat['name'])) ?></span></div>
    <div class="feature-art <?= in_array($f['art'], ['key', 'gift', 'account']) || ($f['type'] === 'key' && !$f['image']) ? 'is-card' : '' ?>" data-art><?= Catalog::media($f, false) ?></div>
    <form class="feature-bar" method="post" action="<?= e(url('cart/add')) ?>" data-add-form>
      <?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int) $f['id'] ?>">
      <div class="feature-info"><b><?= e($f['name']) ?></b><small><?= e($f['tagline']) ?></small></div>
      <div class="feature-opts">
        <?php foreach ($f['options_list'] as $o): $isCol = stripos($o['name'], 'col') === 0; if ($f['type'] === 'key' && count($o['values']) < 2) continue; ?>
          <div class="optset <?= $isCol ? 'swatches' : '' ?>" data-opt="<?= e($o['name']) ?>">
            <?php foreach ($o['values'] as $i => $v): ?>
              <label class="opt <?= $isCol ? 'sw' : '' ?>" <?= $isCol ? 'style="--sw:' . e(strtolower($v)) . '" title="' . e($v) . '"' : '' ?>><input type="radio" name="opt[<?= e($o['name']) ?>]" value="<?= e($v) ?>" <?= $i === 0 ? 'checked' : '' ?> data-color="<?= $isCol ? e($v) : '' ?>"><span><?= $isCol ? '' : e($v) ?></span></label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="feature-buy"><span class="price"><?= money($f['price'], $f['type'] !== 'key') ?></span>
        <?php if ($f['in_stock']): ?><button class="btn btn-cc" type="submit"><?= $f['instant'] ? 'Buy now · get key instantly' : 'Add to cart' ?></button><?php else: ?><span class="btn btn-ghost">Sold out</span><?php endif; ?>
      </div>
      <?php if ($f['instant']): ?><input type="hidden" name="buy_now" value="1"><p class="fineprint">Delivered on screen and by email right after payment.</p><?php endif; ?>
    </form>
  </div>
  <?php endif; ?>
</section>

<section class="block" id="shelf" style="--c:<?= e($cat['color']) ?>;--ink:<?= e($ink) ?>">
  <div class="block-head" data-reveal>
    <h2 class="h2">All <?= e(strtolower($cat['name'])) ?></h2>
    <div class="shelf-tools">
      <?php if ($tags): ?>
      <div class="chips chips-cc" data-filter-group="#shelf-grid" data-mode="tags">
        <button class="chip on" data-filter="*">All</button>
        <?php foreach (array_keys($tags) as $t): ?><button class="chip" data-filter="<?= e(strtolower($t)) ?>"><?= e($t) ?></button><?php endforeach; ?>
      </div>
      <?php endif; ?>
      <form method="get" class="sortf"><select name="sort" onchange="this.form.submit()" aria-label="Sort">
        <?php foreach (['new' => 'Newest', 'popular' => 'Popular', 'price-asc' => 'Price ↑', 'price-desc' => 'Price ↓'] as $k => $v): ?><option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
      </select></form>
    </div>
  </div>
  <?php if ($products): ?>
  <div class="pgrid" id="shelf-grid"><?php foreach ($products as $p) echo partial('partials/product_card', ['p' => $p]); ?></div>
  <p class="count muted"><?= count($products) ?> <?= e(strtolower($cat['name'])) ?> · <span id="shown"></span></p>
  <?php else: ?><p class="muted">Nothing here yet — check back soon.</p><?php endif; ?>
</section>

<?php if ($others): ?>
<section class="also">
  <p class="eyebrow lime">Also in <?= e(setting('store_name')) ?></p>
  <div class="also-grid">
    <?php foreach (array_slice($others, 0, 2) as $o): ?>
    <a class="also-card" href="<?= e(url('shop/' . $o['slug'])) ?>" style="--c:<?= e($o['color']) ?>;--ink:<?= e(\App\Support\Art::ink($o['color'])) ?>">
      <span class="eyebrow-s"><?= e($o['name']) ?></span><b><?= e($o['headline'] ? ucfirst(strtolower($o['headline'])) : $o['name']) ?> <i>→</i></b>
    </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
