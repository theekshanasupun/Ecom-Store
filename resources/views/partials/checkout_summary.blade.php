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
  <?php if (\App\Services\Loyalty::on()): $lo = $sum['loyalty'] ?? ['on' => false]; $lu = auth()->user(); $earn = \App\Services\Loyalty::estimateCart($sum, $lu); $lname = \App\Services\Loyalty::name();
    $goal = (!empty($lo['on']) && $earn > 0) ? \App\Services\Loyalty::goal($lu, $earn) : null; $pv = 100 / max(1, (float) setting('loyalty_redeem_rate', 100)); ?>
  <details class="loy-co" data-loy-box <?= (!empty($lo['on']) && ($lo['points'] > 0 || $lo['cash'] > 0)) ? 'open' : '' ?>>
    <summary><span class="lc-ttl"><i class="lc-star">★</i> <?= e($lname) ?></span>
      <span class="lc-pills"><?php if ($earn > 0): ?><em class="lc-earn">+<?= number_format($earn) ?> pts</em><?php endif; ?><?php if (!empty($lo['on']) && $lo['points'] > 0): ?><em class="lc-use">−<?= e(money($lo['points_value'], true)) ?></em><?php elseif (!$lu): ?><em class="lc-login">Log in to redeem</em><?php endif; ?></span></summary>
    <div class="loy-co-body">
      <div class="lc-card">
        <div><small>You'll earn on this order</small><b class="lc-big"><?= number_format($earn) ?> <span>points</span></b><?php if ($earn > 0): ?><small class="lc-val">≈ <?= e(money((int) floor($earn * $pv), true)) ?> off a future order · credited when completed</small><?php else: ?><small class="lc-val">Earn <?= e(rtrim(rtrim(number_format((float) setting('loyalty_earn_points', 1), 2), '0'), '.')) ?> point per <?= e(money((int) round((float) setting('loyalty_earn_spend', 100) * 100), true)) ?> spent.</small><?php endif; ?></div>
      </div>
      <?php if ($goal): ?><div class="lc-goal"><div class="le-bar"><i style="width:<?= (int) $goal['pct'] ?>%"></i></div><small><?= $goal['unlock'] ? '🎉 This order unlocks <b>' . e($goal['name']) . '</b>' : '<b>' . number_format($goal['left']) . '</b> pts to <b>' . e($goal['name']) . '</b> after this order' ?></small></div><?php endif; ?>
    <?php if (!$lu): ?>
      <div class="lc-cta"><p class="muted small">Members redeem points for money off at checkout.</p><a class="btn btn-ghost" href="<?= e(url('login')) ?>">Log in to redeem</a><a class="btn btn-ghost" href="<?= e(url('register')) ?>">Join free</a></div>
    <?php elseif (empty($lo['on'])): ?>
      <p class="muted small"><?= $lu->isStaff() && setting('loyalty_exclude_staff', '1') === '1' ? 'Staff accounts do not earn or redeem points — sign in with a customer account to try it.' : 'Your rewards account is suspended, so points can\'t be earned or redeemed. Contact us for help.' ?></p>
    <?php else: ?>
      <div class="lc-bal"><span>Your balance</span><b><?= number_format($lo['points_balance']) ?> pts</b><small>≈ <?= e(money(\App\Services\Loyalty::pointsValue($lo['points_balance']), true)) ?></small></div>
      <?php if ($lo['blocked']): ?><p class="muted small"><?= e($lo['blocked']) ?></p>
      <?php elseif ($lo['points_max'] > 0): $mx = (int) $lo['points_max']; ?>
        <div class="lc-redeem" data-pv="<?= e($pv) ?>" data-sym="<?= e(setting('currency_symbol', '$')) ?>">
          <div class="lc-row"><span>Redeem points</span><b data-lc-out><?= number_format($lo['points']) ?> pts · −<?= e(money($lo['points_value'], true)) ?></b></div>
          <input type="range" class="lc-range" min="0" max="<?= $mx ?>" step="1" value="<?= (int) $lo['points'] ?>" data-apply-points data-lc-range aria-label="Points to redeem">
          <div class="lc-chips"><?php foreach ([0 => 'None', 25 => '25%', 50 => '50%', 100 => 'Max'] as $pc => $lb): $val = $pc === 100 ? $mx : (int) floor($mx * $pc / 100); if ($pc > 0 && $pc < 100 && $val < max(1, $lo['min'])) continue; ?><button type="button" class="<?= (int) $lo['points'] === $val ? 'on' : '' ?>" data-points-set="<?= $val ?>"><?= $lb ?></button><?php endforeach; ?></div>
          <small class="muted">Up to <?= number_format($mx) ?> pts (<?= e(money(\App\Services\Loyalty::pointsValue($mx), true)) ?>) on this order</small>
        </div>
      <?php else: ?><p class="muted small">Minimum <?= number_format($lo['min']) ?> points to redeem<?= $lo['points_balance'] < $lo['min'] ? ' — you need ' . number_format($lo['min'] - $lo['points_balance']) . ' more.' : '.' ?></p><?php endif; ?>
      <?php if ($lo['cash_balance'] > 0): ?><label class="chk credit-chk" style="margin-top:6px"><input type="checkbox" data-apply-cashback <?= $lo['cash'] > 0 ? 'checked' : '' ?>><i></i><span>Use my cashback <small><?= money($lo['cash_balance']) ?> available<?= $lo['cash'] > 0 ? ' · applying ' . money($lo['cash']) : '' ?></small></span></label><?php endif; ?>
    <?php endif; ?>
    </div>
  </details>
  <?php endif; ?>
  <p class="apply-msg" data-apply-msg role="status"></p>
</div>

<?= partial('partials/sum_rows', compact('sum')) ?>
