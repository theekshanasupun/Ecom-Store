<?php
use App\Services\Catalog;
/** @var ?array $props */
$pr = $props ?? [];
$mix = Catalog::categories()->map(fn ($c) => Catalog::query()->where('category_id', $c->id)->orderByDesc('featured')->orderByDesc('sales')->first())->filter()->take(3)->values()->all();
$eyebrow = (string) ($pr['eyebrow'] ?? '') !== '' ? $pr['eyebrow'] : 'One cart, one checkout';
$head = (string) ($pr['title'] ?? '') !== '' ? $pr['title'] : "Mix it\nall up.";
$text = (string) ($pr['text'] ?? '') !== '' ? $pr['text'] : 'A toy, a tee and a game key can ride in the same order. Keys arrive instantly, physical items ship, and you pay once.';
$btn = (string) ($pr['btn'] ?? '') !== '' ? $pr['btn'] : 'See your cart →';
$burl = (string) ($pr['url'] ?? '') !== '' ? $pr['url'] : 'cart';
$href = preg_match('#^(https?:)?//#i', $burl) ? $burl : url(ltrim($burl, '/'));
?>
<?php if (count($mix) >= 2): ?>
<section class="mixit" data-reveal>
  <div class="mixit-l">
    <p class="eyebrow"><?= e($eyebrow) ?></p>
    <h2 class="h2"><?= nl2br(e($head)) ?></h2>
    <p class="muted"><?= e($text) ?></p>
    <a class="btn btn-ghost" href="<?= e($href) ?>"><?= e($btn) ?></a>
  </div>
  <ul class="mixit-r">
    <?php foreach ($mix as $p): ?>
    <li style="--c:<?= e($p['color']) ?>"><i></i><div><b><?= e($p['name']) ?></b><small><?= $p['instant'] ? 'Instant · emailed and on screen' : e(setting('shipping_note')) ?></small></div><span><?= money($p['price']) ?></span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
