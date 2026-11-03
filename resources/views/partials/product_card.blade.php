<?php
use App\Services\Catalog;
/** @var array $p */
$disc = $p->discountPct();
$badge = $p['badge'] ?: ($disc ? '-' . $disc . '%' : '');
$hasOpts = !empty($p['options_list']) && $p['type'] !== 'key';
$soldOut = !$p['in_stock'];
?>
<article class="pcard <?= $p['type'] === 'key' ? 'is-key' : '' ?> <?= $soldOut ? 'is-out' : '' ?>" style="--c:<?= e($p['color']) ?>" data-cat="<?= e($p['cat']['slug'] ?? '') ?>" data-tags="<?= e(strtolower(implode('|', $p['tag_list']))) ?>">
  <a class="plink" href="<?= e(url('product/' . $p['slug'])) ?>">
    <div class="pmedia">
      <?php if ($badge): ?><span class="pbadge"><?= e($badge) ?></span><?php endif; ?>
      <?php if ($soldOut): ?><span class="pbadge out">Sold out</span><?php endif; ?>
      <?= Catalog::media($p) ?>
    </div>
    <div class="pinfo">
      <div class="prow"><h3 class="pname"><?= e($p['name']) ?></h3><span class="pprice"><?= money($p['price'], $p['type'] !== 'key') ?></span></div>
      <div class="prow psub"><span><?= e($p['tagline']) ?></span><?php if ($disc && $p['compare_price']): ?><s><?= money($p['compare_price'], $p['type'] !== 'key') ?></s><?php endif; ?></div>
    </div>
  </a>
  <?php if ($soldOut): ?>
    <span class="qa qa-off">Sold out</span>
  <?php elseif ($hasOpts): ?>
    <a class="qa" href="<?= e(url('product/' . $p['slug'])) ?>">Choose options</a>
  <?php else: ?>
    <button class="qa" type="button" data-add="<?= (int) $p['id'] ?>"><?= $p['instant'] ? '<i>⚡</i> Instant delivery' : 'Add to cart <i>+</i>' ?></button>
  <?php endif; ?>
</article>
