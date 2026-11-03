<?php
use App\Services\Cart;
use App\Services\Catalog;
/** @var array $sum @var array $methods @var ?array $u */
$v = fn(string $k, string $d = '') => old($k, $d);
$first = array_key_first($methods);
?>
<section class="block narrow-top">
  <h1 class="h1">Your details</h1>
  <?php if (!$methods): ?><div class="card"><p>No payment method is enabled yet. The store owner needs to enable one in Admin → Settings → Payments.</p></div><?php else: ?>
  <form method="post" action="<?= e(url('checkout')) ?>" class="co-grid" id="co-form">
    <?= csrf_field() ?>
    <div class="co-main">
      <div class="card">
        <h2 class="h3">Contact</h2>
        <?php if (!$u): ?><p class="muted small">Have an account? <a href="<?= e(url('login')) ?>" onclick="try{sessionStorage.setItem('k_after','checkout')}catch(e){}">Sign in</a> for faster checkout.</p><?php endif; ?>
        <div class="grid2">
          <label class="fld"><span>Full name</span><input name="name" value="<?= e($v('name', $u['name'] ?? '')) ?>" required autocomplete="name"></label>
          <label class="fld"><span>Email <?= $sum['only_digital'] ? '(your keys are sent here)' : '' ?></span><input type="email" name="email" value="<?= e($v('email', $u['email'] ?? '')) ?>" required autocomplete="email"></label>
        </div>
        <label class="fld"><span>Phone (optional)</span><input name="phone" value="<?= e($v('phone', $u['phone'] ?? '')) ?>" autocomplete="tel"></label>
      </div>

      <?php if ($sum['has_physical']): ?>
      <div class="card">
        <h2 class="h3">Shipping address</h2>
        <label class="fld"><span>Recipient</span><input name="ship_name" value="<?= e($v('ship_name', $u['name'] ?? '')) ?>" autocomplete="shipping name"></label>
        <label class="fld"><span>Address</span><input name="ship_address" value="<?= e($v('ship_address')) ?>" required autocomplete="shipping address-line1"></label>
        <label class="fld"><span>Apartment, suite (optional)</span><input name="ship_address2" value="<?= e($v('ship_address2')) ?>" autocomplete="shipping address-line2"></label>
        <div class="grid3">
          <label class="fld"><span>City</span><input name="ship_city" value="<?= e($v('ship_city')) ?>" required autocomplete="shipping address-level2"></label>
          <label class="fld"><span>State / region</span><input name="ship_state" value="<?= e($v('ship_state')) ?>" autocomplete="shipping address-level1"></label>
          <label class="fld"><span>Postal code</span><input name="ship_zip" value="<?= e($v('ship_zip')) ?>" required autocomplete="shipping postal-code"></label>
        </div>
        <label class="fld"><span>Country</span><input name="ship_country" value="<?= e($v('ship_country')) ?>" required autocomplete="shipping country-name"></label>
        <label class="fld"><span>Order notes (optional)</span><input name="notes" value="<?= e($v('notes')) ?>"></label>
      </div>
      <?php endif; ?>

      <div class="card">
        <h2 class="h3">Payment</h2>
        <div class="paymethods">
          <?php foreach ($methods as $k => $m): ?>
          <label class="paym"><input type="radio" name="method" value="<?= e($k) ?>" <?= $v('method', $first) === $k ? 'checked' : '' ?>><span><b><?= e($m['label']) ?></b><small><?= e($m['hint']) ?></small></span></label>
          <?php endforeach; ?>
        </div>
        <?php if (isset($methods['bank'])): ?><div class="bankinfo" data-for="bank"><pre><?= e(setting('pay_bank_info')) ?></pre></div><?php endif; ?>
        <?php if (!$u): ?>
        <label class="chk" style="margin-top:18px"><input type="checkbox" name="create_account" value="1" id="mkacct"><i></i><span>Create an account to track orders & re-download keys</span></label>
        <label class="fld" id="acct-pass" hidden><span>Choose a password (8+ characters)</span><input type="password" name="password" autocomplete="new-password"></label>
        <?php endif; ?>
      </div>
    </div>

    <aside class="card summary sticky">
      <h2 class="h3">Order</h2>
      <ul class="mini">
        <?php foreach ($sum['lines'] as $l): $p = $l['p']; ?>
        <li style="--c:<?= e($p['color']) ?>"><div class="mini-media"><?= Catalog::media($p, false) ?><em><?= $l['qty'] ?></em></div><div><b><?= e($p['name']) ?></b><small><?= e(Cart::optLabel($l['opts'])) ?: ($p['instant'] ? 'Instant delivery' : '') ?></small></div><span><?= money($l['total']) ?></span></li>
        <?php endforeach; ?>
      </ul>
      <dl class="sumrows">
        <div><dt>Subtotal</dt><dd><?= money($sum['subtotal']) ?></dd></div>
        <?php if ($sum['discount'] > 0): ?><div class="disc"><dt>Discount · <?= e($sum['coupon']['code']) ?></dt><dd>−<?= money($sum['discount']) ?></dd></div><?php endif; ?>
        <?php if ($sum['has_physical']): ?><div><dt>Shipping</dt><dd><?= $sum['shipping'] > 0 ? money($sum['shipping']) : 'Free' ?></dd></div><?php endif; ?>
        <?php if ($sum['tax'] > 0): ?><div><dt><?= e(setting('tax_label', 'Tax')) ?></dt><dd><?= money($sum['tax']) ?></dd></div><?php endif; ?>
      </dl>
      <div class="total"><span>Total</span><b><?= money($sum['total']) ?></b></div>
      <button class="btn btn-block" type="submit" id="co-submit">Place order →</button>
      <p class="fineprint">By ordering you agree to our <a href="<?= e(url('p/terms')) ?>">terms</a>.</p>
      <a class="lnk" href="<?= e(url('cart')) ?>">← Back to cart</a>
    </aside>
  </form>
  <?php endif; ?>
</section>
