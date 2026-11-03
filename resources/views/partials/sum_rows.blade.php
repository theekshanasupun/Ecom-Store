<?php
/** @var array $sum  Price rows shared by the cart page and checkout. */
$saved = $sum['savings'] + $sum['discount'] + $sum['offer_discount'];
?>
<dl class="sumrows">
  <div><dt>Subtotal <small><?= (int) $sum['count'] ?> item<?= $sum['count'] == 1 ? '' : 's' ?></small></dt><dd><?= money($sum['subtotal']) ?></dd></div>
  <?php if ($sum['savings'] > 0): ?><div class="disc sub"><dt>Sale prices included</dt><dd>−<?= money($sum['savings']) ?></dd></div><?php endif; ?>
  <?php if ($sum['discount'] > 0): ?><div class="disc"><dt>Discount<?= !empty($sum['coupon']) ? ' · <code>' . e($sum['coupon']['code']) . '</code>' : '' ?><?= $sum['coupon_auto'] ? ' <small>automatic</small>' : '' ?></dt><dd>−<?= money($sum['discount']) ?></dd></div><?php endif; ?>
  <?php foreach ($sum['offer_rows'] as $r): ?><div class="disc"><dt><?= e($r['label']) ?></dt><dd>−<?= money($r['amount']) ?></dd></div><?php endforeach; ?>
  <?php if (!empty($sum['tier_discount'])): ?><div class="disc"><dt>Member discount</dt><dd>−<?= money($sum['tier_discount']) ?></dd></div><?php endif; ?>
  <?php if ($sum['has_physical']): ?><div><dt>Shipping<?= $sum['ship_label'] ? ' <small>' . e($sum['ship_label']) . '</small>' : '' ?></dt><dd><?= $sum['shipping'] > 0 ? money($sum['shipping']) : '<span class="free">Free</span>' ?></dd></div><?php endif; ?>
  <?php if ((float) setting('tax_rate') > 0): ?><div><dt><?= e(setting('tax_label', 'Tax')) ?></dt><dd><?= money($sum['tax']) ?></dd></div><?php endif; ?>
</dl>
<?php if ($saved > 0): ?><div class="saving-pill"><span>✦</span> You're saving <b><?= money($saved) ?></b> on this order</div><?php endif; ?>
<div class="total"><span>Total <small><?= e(currency_code()) ?></small></span><b><?= money($sum['total']) ?></b></div>
<?php if ($sum['gift'] > 0 || $sum['credit'] > 0 || ($sum['lpoints_value'] ?? 0) > 0 || ($sum['lcash'] ?? 0) > 0): ?>
<dl class="sumrows tender">
  <?php if ($sum['gift'] > 0): ?><div class="disc"><dt>Gift card <code><?= e($sum['gift_card']->masked()) ?></code></dt><dd>−<?= money($sum['gift']) ?></dd></div><?php endif; ?>
  <?php if ($sum['credit'] > 0): ?><div class="disc"><dt>Store credit</dt><dd>−<?= money($sum['credit']) ?></dd></div><?php endif; ?>
  <?php if (($sum['lpoints_value'] ?? 0) > 0): ?><div class="disc"><dt>Points <small><?= number_format($sum['lpoints']) ?></small></dt><dd>−<?= money($sum['lpoints_value']) ?></dd></div><?php endif; ?>
  <?php if (($sum['lcash'] ?? 0) > 0): ?><div class="disc"><dt>Cashback</dt><dd>−<?= money($sum['lcash']) ?></dd></div><?php endif; ?>
  <div class="due"><dt>Due today</dt><dd><?= money($sum['due']) ?></dd></div>
</dl>
<?php endif; ?>
