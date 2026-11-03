<?php
use App\Services\Catalog;
/** @var array $cats @var array $trending @var array $panels @var array $mix */
$l1 = (string) setting('hero_line1');
$l2 = (string) setting('hero_line2');
?>
<section class="hero">
  <h1 class="mega hero-h" data-reveal><span><?= e($l1) ?></span> <em><?= e($l2) ?></em></h1>
  <p class="lede" data-reveal><?= e(setting('hero_text')) ?></p>
</section>

<?php if ($panels): ?>
<section class="panels" id="panels" data-reveal>
  <?php foreach ($panels as $i => $pn): $c = $pn['cat']; $p = $pn['product']; ?>
  <div class="panel <?= $i === 0 ? 'open' : '' ?>" style="--c:<?= e($c['color']) ?>;--ink:<?= e(\App\Core\Art::ink($c['color'])) ?>" tabindex="0" role="button" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>">
    <div class="panel-top"><span class="eyebrow-s"><?= e($c['name']) ?></span><span class="panel-plus" aria-hidden="true"></span></div>
    <h2 class="panel-title"><?= e(ucfirst(strtolower($c['headline'] ?: $c['name']))) ?></h2>
    <?php if ($p): ?>
    <div class="panel-art"><?= Catalog::media($p, false) ?></div>
    <div class="panel-bottom">
      <div><b><?= e($p['name']) ?></b><span><?= e($p['tagline']) ?> · <?= money($p['price'], true) ?></span></div>
      <a class="btn btn-dark" href="<?= e(url('shop/' . $c['slug'])) ?>">Enter <?= e(strtolower($c['name'])) ?> →</a>
    </div>
    <?php else: ?>
    <div class="panel-bottom"><a class="btn btn-dark" href="<?= e(url('shop/' . $c['slug'])) ?>">Enter →</a></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="block" id="trending">
  <div class="block-head" data-reveal>
    <h2 class="h2">Trending<br>right now</h2>
    <div class="chips" data-filter-group="#trending-grid">
      <button class="chip on" data-filter="*">All</button>
      <?php foreach ($cats as $c): ?><button class="chip" data-filter="<?= e($c['slug']) ?>" style="--c:<?= e($c['color']) ?>"><?= e($c['name']) ?></button><?php endforeach; ?>
    </div>
  </div>
  <?php if ($trending): ?>
  <div class="pgrid" id="trending-grid"><?php foreach ($trending as $p) echo partial('partials/product_card', ['p' => $p]); ?></div>
  <?php else: ?><p class="muted">No products yet. Add some from the admin panel.</p><?php endif; ?>
</section>

<?php if (count($mix) >= 2): ?>
<section class="mixit" data-reveal>
  <div class="mixit-l">
    <p class="eyebrow">One cart, one checkout</p>
    <h2 class="h2">Mix it<br>all up.</h2>
    <p class="muted">A toy, a tee and a game key can ride in the same order. Keys arrive instantly, physical items ship, and you pay once.</p>
    <a class="btn btn-ghost" href="<?= e(url('cart')) ?>">See your cart →</a>
  </div>
  <ul class="mixit-r">
    <?php foreach ($mix as $p): ?>
    <li style="--c:<?= e($p['color']) ?>"><i></i><div><b><?= e($p['name']) ?></b><small><?= $p['instant'] ? 'Instant · emailed and on screen' : e(setting('shipping_note')) ?></small></div><span><?= money($p['price']) ?></span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
