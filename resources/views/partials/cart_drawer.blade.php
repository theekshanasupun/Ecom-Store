<?php
use App\Services\Cart;
use App\Services\Catalog;
/** @var array $sum */
if (!$sum['lines']): ?>
  <div class="empty">
    <div class="empty-art"><?= \App\Support\Art::svg('box', '#c6ff3d', 'Empty cart') ?></div>
    <h3>Nothing here yet</h3>
    <p>Add a toy, a tee or a key — it all rides in one cart.</p>
    <a class="btn" href="<?= e(url('')) ?>">Start shopping</a>
  </div>
<?php else: ?>
  <?php if ($sum['has_physical'] && $sum['free_min'] > 0 && setting('fd_in_drawer') !== '0'): ?>
  <div class="ship-meter">
    <div class="ship-line"><span><?= $sum['free_left'] > 0 ? 'Add ' . money($sum['free_left']) . ' more for free shipping' : 'Free shipping unlocked' ?></span><b><?= $sum['free_pct'] ?>%</b></div>
    <div class="bar"><i style="width:<?= $sum['free_pct'] ?>%"></i></div>
  </div>
  <?php endif; ?>
  <ul class="dlines">
  <?php foreach ($sum['lines'] as $l): $p = $l['p']; ?>
    <li class="dline" style="--c:<?= e($p['color']) ?>">
      <a class="dthumb" href="<?= e(url($p['slug'] ? 'product/' . $p['slug'] : 'gift-cards')) ?>"><?= Catalog::media($p, false) ?></a>
      <div class="dinfo">
        <b><?= e($p['name']) ?></b>
        <small><?= e(Cart::optLabel($l['opts']) ?: $p['tagline']) ?></small>
        <?php if ($l['sold_out']): ?><small class="bad">Sold out</small><?php endif; ?>
        <div class="qty sm">
          <button type="button" data-cart="set" data-key="<?= e($l['key']) ?>" data-qty="<?= $l['qty'] - 1 ?>" aria-label="Decrease">−</button>
          <span><?= $l['qty'] ?></span>
          <button type="button" data-cart="set" data-key="<?= e($l['key']) ?>" data-qty="<?= $l['qty'] + 1 ?>" aria-label="Increase">+</button>
        </div>
      </div>
      <div class="dprice"><?= money($l['total']) ?><?php if (!empty($l['orig']) && $l['orig'] > $l['unit'] && $l['qty'] > 0): ?><s><?= money($l['orig'] * $l['qty']) ?></s><?php endif; ?><button type="button" class="lnk" data-cart="remove" data-key="<?= e($l['key']) ?>">Remove</button></div>
    </li>
  <?php endforeach; ?>
  </ul>
  <div class="dfoot">
    <?php if ($sum['savings'] + $sum['offer_discount'] > 0): ?><div class="saving-pill sm"><span>✦</span> Saving <b><?= money($sum['savings'] + $sum['offer_discount']) ?></b></div><?php endif; ?>
    <div class="dtotal"><span>Subtotal</span><b><?= money($sum['subtotal'] - $sum['offer_discount']) ?></b></div>
    <small class="muted">Shipping &amp; tax calculated at checkout.</small>
    <a class="btn btn-block" href="<?= e(url('checkout')) ?>">Checkout →</a>
    <a class="btn btn-ghost btn-block" href="<?= e(url('cart')) ?>">View full cart</a>
  </div>
<?php endif; ?>
