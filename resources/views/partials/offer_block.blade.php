<?php
use App\Services\Catalog;
use App\Services\Offers;
/** @var \App\Models\Offer $o @var array $prods @var ?\App\Models\Product $current @var string $where */
$where = $where ?? 'product';
$isBundle = $o->kind === 'bundle';
$title = $o->headline ?: ($isBundle ? 'Bundle & save' : ($o->kind === 'upsell' ? 'Upgrade your pick' : 'Frequently bought together'));
$items = $prods;
if (!$isBundle && $current) array_unshift($items, $current);
$cur = fn ($x) => $x->priceInfo()['price'];
$sum = 0; foreach ($items as $x) $sum += $cur($x);
$bundlePrice = $sum;
if ($isBundle) $bundlePrice = $sum - ($o->discount_type === 'percent' ? (int) round($sum * min(100, (int) $o->discount_value) / 100) : ($o->discount_type === 'fixed' ? min($sum, (int) $o->discount_value) : 0));
?>
<section class="offer offer-<?= e($o->kind) ?> offer-<?= e($where) ?>" data-offer="<?= (int) $o->id ?>">
  <header><h3><?= e($title) ?></h3><?php if ($o->subtext): ?><p><?= e($o->subtext) ?></p><?php endif; ?></header>
  <ul class="offer-items">
    <?php foreach ($items as $i => $x): $isCur = $current && $x->id === $current->id; $price = $cur($x); $disc = (!$isBundle && !$isCur) ? Offers::priceWith($o, $price) : $price; ?>
    <li style="--c:<?= e($x['color']) ?>">
      <?php if (!$isBundle): ?><label class="offer-chk"><input type="checkbox" data-id="<?= (int) $x->id ?>" data-price="<?= (int) $disc ?>" checked <?= $isCur ? 'disabled' : '' ?>><i></i></label><?php endif; ?>
      <a class="offer-thumb" href="<?= e(url('product/' . $x['slug'])) ?>"><?= Catalog::media($x, true) ?></a>
      <div class="offer-info"><a href="<?= e(url('product/' . $x['slug'])) ?>"><?= e($x['name']) ?></a><?php if ($x['options_list']): ?><small>Default options</small><?php endif; ?></div>
      <div class="offer-price"><b><?= money($disc, true) ?></b><?php if ($disc < $price): ?><s><?= money($price, true) ?></s><?php endif; ?></div>
    </li>
    <?php if ($isBundle && $i < count($items) - 1): ?><li class="offer-plus" aria-hidden="true">+</li><?php endif; ?>
    <?php endforeach; ?>
  </ul>
  <footer class="offer-foot">
    <div class="offer-total"><?php if ($isBundle): ?><span>Bundle price</span><b><?= money($bundlePrice, true) ?></b><?php if ($bundlePrice < $sum): ?><s><?= money($sum, true) ?></s><em>Save <?= money($sum - $bundlePrice, true) ?></em><?php endif; ?><?php else: ?><span>Selected total</span><b data-offer-total><?= money($sum, true) ?></b><?php endif; ?></div>
    <button type="button" class="btn btn-cc" data-addmany data-offer="<?= (int) $o->id ?>" <?= $isBundle ? 'data-ids="' . e(implode(',', array_map(fn ($x) => $x->id, $items))) . '"' : '' ?>><?= $isBundle ? 'Add bundle to cart' : 'Add selected to cart' ?></button>
  </footer>
</section>
