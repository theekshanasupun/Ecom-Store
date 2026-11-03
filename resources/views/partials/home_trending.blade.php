<?php
use App\Services\Catalog;
/** @var ?array $props */
$pr = $props ?? [];
$cats = Catalog::categories();
$n = max(1, min(24, (int) ($pr['count'] ?? 8)));
$ttl = (string) ($pr['title'] ?? '') !== '' ? $pr['title'] : "Trending\nright now";
$trending = Catalog::query()->orderByDesc('featured')->orderByDesc('sales')->orderByDesc('products.id')->limit($n)->get()->all();
?>
<section class="block" id="trending">
  <div class="block-head" data-reveal>
    <h2 class="h2"><?= nl2br(e($ttl)) ?></h2>
    <div class="chips" data-filter-group="#trending-grid">
      <button class="chip on" data-filter="*">All</button>
      <?php foreach ($cats as $c): ?><button class="chip" data-filter="<?= e($c['slug']) ?>" style="--c:<?= e($c['color']) ?>"><?= e($c['name']) ?></button><?php endforeach; ?>
    </div>
  </div>
  <?php if ($trending): ?>
  <div class="pgrid" id="trending-grid"><?php foreach ($trending as $p) echo partial('partials/product_card', ['p' => $p]); ?></div>
  <?php else: ?><p class="muted">No products yet. Add some from the admin panel.</p><?php endif; ?>
</section>
