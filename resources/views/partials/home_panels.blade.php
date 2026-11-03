<?php
use App\Services\Catalog;
$panels = Catalog::categories()->map(fn ($c) => ['cat' => $c, 'product' => Catalog::query()->where('category_id', $c->id)->orderByDesc('featured')->orderByDesc('sales')->first()])->all();
?>
<?php if ($panels): ?>
<section class="panels" id="panels" data-reveal>
  <?php foreach ($panels as $i => $pn): $c = $pn['cat']; $p = $pn['product']; ?>
  <div class="panel <?= $i === 0 ? 'open' : '' ?>" style="--c:<?= e($c['color']) ?>;--ink:<?= e(\App\Support\Art::ink($c['color'])) ?>" tabindex="0" role="button" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>">
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
