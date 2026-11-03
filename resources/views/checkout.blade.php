<?php
use App\Support\Countries;
/** @var array $sum @var array $methods @var ?\App\Models\User $u @var ?\App\Models\Order $last @var array $bumps */
$v = fn (string $k, string $d = '') => old($k, $d);
$first = array_key_first($methods);
$phys = $sum['has_physical'];
$free = $sum['due'] <= 0;
$pwOn = setting('login_password_on', '1') === '1';
$otpOn = setting('login_otp_on', '1') === '1';
$stepNames = $phys ? ['Contact', 'Delivery', 'Payment'] : ['Contact', 'Payment'];
$country = $v('ship_country', $last->ship_country ?? (string) setting('geo_country'));
$icons = ['stripe' => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20M6 15h4"/></svg>',
  'paypal' => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M7 21 9 4h6a4 4 0 0 1 0 8H10"/></svg>',
  'bank' => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10 12 4l9 6M5 10v8M9 10v8M15 10v8M19 10v8M3 20h18"/></svg>',
  'cod' => '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/></svg>'];
?>
<section class="block narrow-top co26" id="co26" data-co data-due="<?= (int) $sum['due'] ?>" data-phys="<?= $phys ? 1 : 0 ?>">
  <div class="co26-top">
    <h1 class="h1">Checkout</h1>
    <span class="co26-secure"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg> Secure checkout</span>
  </div>
  <ol class="cs-steps" data-cs-steps aria-label="Checkout progress">
    <?php foreach ($stepNames as $i => $n): ?><li data-for="<?= $i ?>" class="<?= $i === 0 ? 'on' : '' ?>"><b><?= $i + 1 ?></b><span><?= e($n) ?></span></li><?php endforeach; ?>
  </ol>

  <?php if (!$methods && !$free): ?><div class="card"><p>No payment method is enabled yet. The store owner needs to enable one in Admin → Settings → Payments.</p></div><?php else: ?>

  <div class="co26-layout">
  <form method="post" action="<?= e(url('checkout')) ?>" class="co26-main" id="co-form" autocomplete="on">
    <?= csrf_field() ?>

    <!-- 1 · Contact -->
    <section class="cstep" data-step="0" data-name="contact">
      <header class="cstep-head"><span class="cstep-n">1</span><h2>Contact</h2><span class="cstep-sum" data-sum></span><button type="button" class="lnk" data-edit>Edit</button></header>
      <div class="cstep-body">
        <?php if (!$u): ?>
        <p class="co26-note">Returning customer? <a href="<?= e(url('login')) ?>" onclick="try{sessionStorage.setItem('k_after','checkout')}catch(e){}">Sign in</a><?= $otpOn ? ' or <a href="' . e(url('login?tab=code')) . '" onclick="try{sessionStorage.setItem(\'k_after\',\'checkout\')}catch(e){}">get a one-time email code</a>' : '' ?> for faster checkout.</p>
        <?php else: ?>
        <p class="co26-note">Signed in as <b><?= e($u['email']) ?></b>.</p>
        <?php endif; ?>
        <label class="fld"><span>Email <?= $sum['only_digital'] ? '· your keys are sent here' : '' ?></span><input type="email" name="email" value="<?= e($v('email', $u['email'] ?? '')) ?>" required autocomplete="email" inputmode="email" placeholder="you@example.com"></label>
        <div class="grid2">
          <label class="fld"><span>Full name</span><input name="name" value="<?= e($v('name', $u['name'] ?? '')) ?>" required autocomplete="name"></label>
          <label class="fld"><span>Phone <em>optional</em></span><input name="phone" type="tel" value="<?= e($v('phone', $u['phone'] ?? '')) ?>" autocomplete="tel" inputmode="tel"></label>
        </div>
        <label class="chk"><input type="checkbox" name="subscribe" value="1" <?= $v('subscribe', setting('checkout_subscribe_default', '0')) === '1' ? 'checked' : '' ?>><i></i><span>Email me news and offers <small>(unsubscribe any time)</small></span></label>
        <?php if (!$u && ($pwOn || $otpOn)): ?>
        <div class="acct-pick" role="radiogroup" aria-label="Account">
          <label class="acct-opt"><input type="radio" name="account_mode" value="guest" checked><span><b>Continue as guest</b><small>No account needed</small></span></label>
          <?php if ($pwOn): ?><label class="acct-opt"><input type="radio" name="account_mode" value="password"><span><b>Create an account</b><small>Track orders &amp; re-download keys · with a password</small></span></label><?php endif; ?>
          <?php if ($otpOn): ?><label class="acct-opt"><input type="radio" name="account_mode" value="code"><span><b>Create an account — no password</b><small>Sign in later with a code we email you</small></span></label><?php endif; ?>
        </div>
        <label class="fld" id="acct-pass" hidden><span>Choose a password <em>8+ characters</em></span><input type="password" name="password" autocomplete="new-password" minlength="8"></label>
        <?php endif; ?>
        <div class="cstep-actions"><button type="button" class="btn" data-next>Continue to <?= $phys ? 'delivery' : 'payment' ?> →</button></div>
      </div>
    </section>

    <?php if ($phys): ?>
    <!-- 2 · Delivery -->
    <section class="cstep" data-step="1" data-name="delivery">
      <header class="cstep-head"><span class="cstep-n">2</span><h2>Delivery</h2><span class="cstep-sum" data-sum></span><button type="button" class="lnk" data-edit>Edit</button></header>
      <div class="cstep-body">
        <?php if ($last && $last->ship_address): ?>
        <button type="button" class="saved-addr" data-fill='<?= e(json_encode(['ship_name' => $last->ship_name, 'ship_address' => $last->ship_address, 'ship_address2' => $last->ship_address2, 'ship_city' => $last->ship_city, 'ship_state' => $last->ship_state, 'ship_zip' => $last->ship_zip, 'ship_country' => $last->ship_country], JSON_HEX_APOS | JSON_HEX_QUOT)) ?>'>
          <b>Use my last address</b><span><?= e($last->ship_name) ?> · <?= e($last->ship_address) ?>, <?= e($last->ship_city) ?></span></button>
        <?php endif; ?>
        <label class="fld"><span>Recipient</span><input name="ship_name" value="<?= e($v('ship_name', $u['name'] ?? '')) ?>" autocomplete="shipping name"></label>
        <label class="fld"><span>Country / region</span>
          <select name="ship_country" required autocomplete="shipping country-name"><option value="">Select…</option><?php foreach (Countries::LIST as $c): ?><option <?= $country === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
        <label class="fld"><span>Address</span><input name="ship_address" value="<?= e($v('ship_address')) ?>" required autocomplete="shipping address-line1" placeholder="Street and number"></label>
        <label class="fld"><span>Apartment, suite <em>optional</em></span><input name="ship_address2" value="<?= e($v('ship_address2')) ?>" autocomplete="shipping address-line2"></label>
        <div class="grid3">
          <label class="fld"><span>City</span><input name="ship_city" value="<?= e($v('ship_city')) ?>" required autocomplete="shipping address-level2"></label>
          <label class="fld"><span>State / region</span><input name="ship_state" value="<?= e($v('ship_state')) ?>" autocomplete="shipping address-level1"></label>
          <label class="fld"><span>Postal code</span><input name="ship_zip" value="<?= e($v('ship_zip')) ?>" required autocomplete="shipping postal-code"></label>
        </div>
        <fieldset class="shipopts" data-shipopts><legend>Delivery method</legend>
          <?php foreach ($sum['ship_options'] as $id => $o): ?>
          <label class="shipopt"><input type="radio" name="ship" value="<?= e($id) ?>" data-label="<?= e($o['label']) ?>" <?= $sum['ship_id'] === $id ? 'checked' : '' ?>><span><b><?= e($o['label']) ?></b><small><?= e($o['eta']) ?></small><i><?= $o['price'] > 0 ? money($o['price']) : 'Free' ?></i></span></label>
          <?php endforeach; ?>
        </fieldset>
        <?php if ($sum['digital'] > 0 && !$sum['has_gift']): ?><p class="co26-note">⚡ Digital items in your order are delivered instantly by email — they don't wait for shipping.</p><?php endif; ?>
        <label class="fld"><span>Delivery notes <em>optional</em></span><input name="notes" value="<?= e($v('notes')) ?>" placeholder="Gate code, leave with neighbour…"></label>
        <div class="cstep-actions"><button type="button" class="btn" data-next>Continue to payment →</button></div>
      </div>
    </section>
    <?php endif; ?>

    <!-- Payment -->
    <section class="cstep" data-step="<?= $phys ? 2 : 1 ?>" data-name="payment">
      <header class="cstep-head"><span class="cstep-n"><?= $phys ? 3 : 2 ?></span><h2>Payment</h2></header>
      <div class="cstep-body">
        <?php if ($sum['only_digital']): ?><p class="co26-note">⚡ <?= $sum['has_gift'] && $sum['digital'] === $sum['gift_lines_total'] ? 'Gift cards are emailed right after payment.' : 'Keys, downloads and gift cards arrive on screen and by email right after payment.' ?></p><?php endif; ?>
        <div data-pay-section <?= $free ? 'hidden' : '' ?>>
          <div class="paymethods">
            <?php foreach ($methods as $k => $m): ?>
            <label class="paym"><input type="radio" name="method" value="<?= e($k) ?>" <?= $v('method', $first) === $k ? 'checked' : '' ?>><span><i class="pm-ic"><?= $icons[$k] ?? '' ?></i><b><?= e($m['label']) ?></b><small><?= e($m['hint']) ?></small></span></label>
            <?php endforeach; ?>
          </div>
          <?php if (isset($methods['bank'])): ?><div class="bankinfo" data-for="bank"><pre><?= e(setting('pay_bank_info')) ?></pre></div><?php endif; ?>
        </div>
        <div class="paid-by-balance" data-free-note <?= $free ? '' : 'hidden' ?>>✓ Your gift card / store credit covers this order — nothing to pay.</div>
        <?php if (!$phys): ?><label class="fld" style="margin-top:14px"><span>Order notes <em>optional</em></span><input name="notes" value="<?= e($v('notes')) ?>"></label><?php endif; ?>
        <div class="cstep-actions col">
          <button class="btn btn-block btn-lg" type="submit" id="co-submit"><span data-submit-label><?= $free ? 'Place order →' : 'Pay ' . money($sum['due']) . ' →' ?></span></button>
          <p class="fineprint">By ordering you agree to our <a href="<?= e(url('p/terms')) ?>">terms</a>. <a href="<?= e(url('cart')) ?>">← Back to cart</a></p>
          <ul class="trust"><li>🔒 Encrypted checkout</li><li>⚡ Instant digital delivery</li><li>↩ Easy support</li></ul>
        </div>
      </div>
    </section>
  </form>

  <aside class="co26-aside">
    <button type="button" class="co-sumtoggle" data-sumtoggle aria-expanded="false"><span>Order summary <i>▾</i></span><b data-sum-total><?= money($sum['due'] > 0 ? $sum['due'] : $sum['total']) ?></b></button>
    <div class="card summary sticky" id="co-summary"><?= partial('partials/checkout_summary', compact('sum')) ?></div>
    <?php foreach ($bumps as $of): ?><?= partial('partials/offer_block', ['o' => $of['offer'], 'prods' => $of['products'], 'current' => null, 'where' => 'checkout']) ?><?php endforeach; ?>
  </aside>
  </div>
  <?php endif; ?>
</section>
