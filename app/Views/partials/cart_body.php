<?php
use App\Services\Cart;
use App\Services\Catalog;
/** @var array $sum @var array $cats */
if (!$sum['lines']): ?>
<div class="empty big">
  <div class="empty-art"><?= \App\Core\Art::svg('box', '#c6ff3d', 'Empty cart') ?></div>
  <h2 class="h2">Your cart is empty</h2>
  <p class="muted">Pick a department and start filling it.</p>
  <div class="pills"><?php foreach ($cats as $c): ?><a class="pill" style="--c:<?= e($c['color']) ?>;--ink:<?= e(\App\Core\Art::ink($c['color'])) ?>" href="<?= e(url('shop/' . $c['slug'])) ?>"><?= e($c['name']) ?></a><?php endforeach; ?></div>
</div>
<?php return; endif; ?>
<div class="cart-grid">
  <div class="cart-main">
    <?php if ($sum['has_physical'] && $sum['free_min'] > 0): ?>
    <div class="card ship-meter">
      <div class="ship-line"><b><?= $sum['free_left'] > 0 ? 'Add ' . money($sum['free_left']) . ' more for free shipping' : 'Free shipping unlocked' ?></b><span class="lime"><?= $sum['free_pct'] ?>%</span></div>
      <div class="bar"><i style="width:<?= $sum['free_pct'] ?>%"></i></div>
      <small class="muted">Free shipping counts physical items only. Digital keys never add shipping.</small>
    </div>
    <?php endif; ?>

    <?php foreach ($sum['lines'] as $l): $p = $l['p']; ?>
    <article class="card cline <?= $l['sold_out'] ? 'is-out' : '' ?>" style="--c:<?= e($p['color']) ?>">
      <a class="cline-media" href="<?= e(url('product/' . $p['slug'])) ?>"><?= Catalog::media($p, false) ?></a>
      <div class="cline-body">
        <span class="dot-label"><i></i><?= e($p['cat']['name'] ?? '') ?></span>
        <h3><a href="<?= e(url('product/' . $p['slug'])) ?>"><?= e($p['name']) ?></a></h3>
        <p class="muted"><?= e(Cart::optLabel($l['opts']) ? $p['tagline'] . ' · ' . Cart::optLabel($l['opts']) : $p['tagline']) ?></p>
        <?php if ($l['sold_out']): ?><span class="tag bad">Sold out — remove to continue</span>
        <?php elseif ($p['instant']): ?><span class="tag tag-key">Instant · key on screen + email</span>
        <?php else: ?><span class="tag"><?= e(setting('shipping_note')) ?></span><?php endif; ?>
        <div class="cline-ctl">
          <div class="qty">
            <form method="post" action="<?= e(url('cart/update')) ?>" data-cart-form><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($l['key']) ?>"><input type="hidden" name="qty" value="<?= $l['qty'] - 1 ?>"><button aria-label="Decrease">−</button></form>
            <span><?= $l['qty'] ?></span>
            <form method="post" action="<?= e(url('cart/update')) ?>" data-cart-form><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($l['key']) ?>"><input type="hidden" name="qty" value="<?= $l['qty'] + 1 ?>"><button aria-label="Increase">+</button></form>
          </div>
          <form method="post" action="<?= e(url('cart/remove')) ?>" data-cart-form><?= csrf_field() ?><input type="hidden" name="key" value="<?= e($l['key']) ?>"><button class="lnk">Remove</button></form>
        </div>
      </div>
      <div class="cline-price"><?= money($l['total']) ?></div>
    </article>
    <?php endforeach; ?>

    <div class="card outline more">
      <h3>Add one more from another department?</h3>
      <p class="muted">It all rides in this same order.</p>
      <div class="pills"><?php foreach ($cats as $c): ?><a class="pill" style="--c:<?= e($c['color']) ?>;--ink:<?= e(\App\Core\Art::ink($c['color'])) ?>" href="<?= e(url('shop/' . $c['slug'])) ?>"><?= e(ucfirst(strtolower(substr(strrchr(' ' . $c['name'], ' '), 1)))) ?></a><?php endforeach; ?></div>
    </div>
  </div>

  <aside class="card summary">
    <h2 class="h3">Summary</h2>
    <dl class="sumrows">
      <?php if ($sum['physical'] > 0): ?><div><dt>Physical items</dt><dd><?= money($sum['physical']) ?></dd></div><?php endif; ?>
      <?php if ($sum['digital'] > 0): ?><div><dt>Digital keys</dt><dd><?= money($sum['digital']) ?></dd></div><?php endif; ?>
      <?php if ($sum['discount'] > 0): ?><div class="disc"><dt>Discount · <?= e($sum['coupon']['code']) ?></dt><dd>−<?= money($sum['discount']) ?></dd></div><?php endif; ?>
      <div><dt>Shipping</dt><dd><?= $sum['has_physical'] ? ($sum['shipping'] > 0 ? money($sum['shipping']) : 'Free') : '—' ?></dd></div>
      <div><dt><?= e(setting('tax_label', 'Tax')) ?></dt><dd><?= (float) setting('tax_rate') > 0 ? money($sum['tax']) : 'At checkout' ?></dd></div>
    </dl>
    <form class="promo" method="post" action="<?= e(url('cart/coupon')) ?>" data-cart-form>
      <?= csrf_field() ?><label class="eyebrow-s" for="promo">Promo code</label>
      <div class="promo-row"><input id="promo" name="code" placeholder="Enter code" value="<?= e($sum['coupon']['code'] ?? '') ?>" autocomplete="off"><button class="btn btn-ghost" type="submit"><?= $sum['coupon'] ? 'Update' : 'Apply' ?></button></div>
    </form>
    <div class="total"><span>Total</span><b><?= money($sum['total']) ?></b></div>
    <?php if ($sum['has_sold_out']): ?><a class="btn btn-block disabled" aria-disabled="true">Remove sold-out items</a>
    <?php else: ?><a class="btn btn-block" href="<?= e(url('checkout')) ?>">Checkout →</a><?php endif; ?>
    <ul class="dots">
      <li style="--c:#8b7bff">Keys appear on screen and by email right after payment</li>
      <li style="--c:#ff7a2f">Physical items ship together in one parcel</li>
      <li style="--c:var(--accent)">One payment, one receipt</li>
    </ul>
  </aside>
</div>
