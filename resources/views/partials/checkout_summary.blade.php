<?php
use App\Services\Catalog;
use App\Services\Cart;
/** @var array $sum  Contents of the sticky checkout summary (re-rendered by /checkout/apply). */
$coupon = $sum['coupon'] ?? null;
?>
<h2 class="h3">Your order</h2>
<ul class="mini">
  <?php foreach ($sum['lines'] as $l): $p = $l['p']; ?>
  <li style="--c:<?= e($p['color']) ?>"><div class="mini-media"><?= Catalog::media($p, false) ?><em><?= (int) $l['qty'] ?></em></div>
    <div><b><?= e($p['name']) ?></b><small><?= e(Cart::optLabel($l['opts'])) ?: (!empty($l['gc']) ? 'Gift card' . ($l['gc']['to_name'] ? ' for ' . e($l['gc']['to_name']) : '') : ($p['instant'] ? 'Instant delivery' : '')) ?></small></div>
    <span><?= money($l['total']) ?><?php if (!empty($l['orig']) && $l['orig'] > $l['unit']): ?><s><?= money($l['orig'] * $l['qty']) ?></s><?php endif; ?></span></li>
  <?php endforeach; ?>
</ul>

<?php if ($sum['has_physical'] && $sum['free_min'] > 0): ?>
<div class="co-ship-meter"><div><span><?= $sum['free_left'] > 0 ? 'Add <b>' . money($sum['free_left']) . '</b> for free shipping' : '🎉 Free shipping unlocked' ?></span></div><div class="bar"><i style="width:<?= (int) $sum['free_pct'] ?>%"></i></div></div>
<?php endif; ?>

<div class="co-apply" data-apply-box>
  <div class="co-apply-row">
    <form data-apply="coupon" class="apply-form" autocomplete="off">
      <label><span>Discount code</span></label>
      <?php if ($coupon && !$sum['coupon_auto']): ?>
        <div class="chip-applied"><b><?= e($coupon['code']) ?></b> <span>−<?= money($sum['discount']) ?></span><button type="button" data-apply-clear="coupon" aria-label="Remove code">✕</button></div>
      <?php else: ?>
        <div class="apply-input"><input name="code" placeholder="Code" aria-label="Discount code"><button class="btn btn-ghost">Apply</button></div>
      <?php endif; ?>
    </form>
  </div>
  <?php if (\App\Services\GiftCards::enabled()): ?>
  <div class="co-apply-row">
    <form data-apply="gift" class="apply-form" autocomplete="off">
      <label><span>Gift card</span></label>
      <?php if ($sum['gift_card']): ?>
        <div class="chip-applied"><b><?= e($sum['gift_card']->masked()) ?></b> <span>balance <?= money($sum['gift_card']->balance) ?></span><button type="button" data-apply-clear="gift" aria-label="Remove gift card">✕</button></div>
      <?php else: ?>
        <div class="apply-input"><input name="code" placeholder="XXXX-XXXX-XXXX-XXXX" aria-label="Gift card code"><button class="btn btn-ghost">Apply</button></div>
      <?php endif; ?>
    </form>
  </div>
  <?php endif; ?>
  <?php if ($sum['credit_balance'] > 0): ?>
  <div class="co-apply-row"><label class="chk credit-chk"><input type="checkbox" data-apply-credit <?= $sum['use_credit'] ? 'checked' : '' ?>><i></i><span>Use my store credit <small><?= money($sum['credit_balance']) ?> available<?= $sum['credit'] > 0 ? ' · applying ' . money($sum['credit']) : '' ?></small></span></label></div>
  <?php endif; ?>
  <p class="apply-msg" data-apply-msg role="status"></p>
</div>

<?= partial('partials/sum_rows', compact('sum')) ?>
