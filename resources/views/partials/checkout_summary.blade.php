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
  <?php if (\App\Services\Loyalty::on()): $lo = $sum['loyalty'] ?? ['on' => false]; $lu = auth()->user(); $earn = \App\Services\Loyalty::estimateCart($sum, $lu); $lname = \App\Services\Loyalty::name(); ?>
  <details class="loy-co" data-loy-box <?= (!empty($lo['on']) && ($lo['points'] > 0 || $lo['cash'] > 0)) ? 'open' : '' ?>>
    <summary><span>★ <?= e($lname) ?></span><small><?php if ($earn > 0): ?>Earn <b><?= number_format($earn) ?></b> pts<?php endif; ?><?php if (!empty($lo['on'])): ?> · <?= number_format($lo['points_balance']) ?> pts to redeem<?php elseif (!$lu): ?> · Log in to redeem<?php endif; ?></small></summary>
    <div class="loy-co-body">
    <?php if (!$lu): ?>
      <p class="muted small">You'll earn about <b><?= number_format($earn) ?></b> points on this order<?= $earn > 0 ? ' once you are a member' : '' ?>. <a href="<?= e(url('login')) ?>" data-keep-cart>Log in</a> or <a href="<?= e(url('register')) ?>">create an account</a> to earn and redeem points.</p>
    <?php elseif (empty($lo['on'])): ?>
      <p class="muted small">Your rewards account is not active, so points can't be earned or redeemed on this order.</p>
    <?php else: ?>
      <p class="small">🎯 This order earns about <b><?= number_format($earn) ?> points</b> (credited after it is completed).</p>
      <small class="muted">Balance: <b><?= number_format($lo['points_balance']) ?></b> points · worth <?= e(money(\App\Services\Loyalty::pointsValue($lo['points_balance']))) ?></small>
      <?php if ($lo['blocked']): ?><p class="muted small"><?= e($lo['blocked']) ?></p>
      <?php elseif ($lo['points_max'] > 0): ?>
        <div class="apply-input"><input type="number" min="0" max="<?= (int) $lo['points_max'] ?>" step="1" value="<?= (int) $lo['points'] ?>" data-apply-points aria-label="Points to redeem" placeholder="Points to use"><button type="button" class="btn btn-ghost" data-points-set="<?= (int) $lo['points_max'] ?>">Use max</button><?php if ($lo['points'] > 0): ?><button type="button" class="btn btn-ghost" data-points-set="0">Clear</button><?php endif; ?></div>
        <small class="muted">Redeem up to <?= number_format($lo['points_max']) ?> points (<?= e(money(\App\Services\Loyalty::pointsValue($lo['points_max']))) ?>)<?= $lo['points'] > 0 ? ' · applying ' . number_format($lo['points']) . ' = −' . e(money($lo['points_value'])) : '' ?></small>
      <?php else: ?><p class="muted small">Minimum <?= number_format($lo['min']) ?> points to redeem.</p><?php endif; ?>
      <?php if ($lo['cash_balance'] > 0): ?><label class="chk credit-chk" style="margin-top:8px"><input type="checkbox" data-apply-cashback <?= $lo['cash'] > 0 ? 'checked' : '' ?>><i></i><span>Use my cashback <small><?= money($lo['cash_balance']) ?> available<?= $lo['cash'] > 0 ? ' · applying ' . money($lo['cash']) : '' ?></small></span></label><?php endif; ?>
    <?php endif; ?>
    </div>
  </details>
  <?php endif; ?>
  <p class="apply-msg" data-apply-msg role="status"></p>
</div>

<?= partial('partials/sum_rows', compact('sum')) ?>
