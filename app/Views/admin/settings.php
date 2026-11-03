<?php
/** @var string $tab @var array $tabs */
$s = fn(string $k) => setting($k);
?>
<div class="tabs"><?php foreach ($tabs as $k => $t): ?><a href="<?= e(url('admin/settings?tab=' . $k)) ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($t['label']) ?></a><?php endforeach; ?></div>

<form method="post" action="<?= e(url('admin/settings')) ?>" enctype="multipart/form-data" class="apanel narrow"><?= csrf_field() ?><input type="hidden" name="tab" value="<?= e($tab) ?>">

<?php if ($tab === 'general'): ?>
  <h2>Store</h2>
  <div class="g2"><?= field_text('store_name', 'Store name', $s('store_name')) ?><?= field_text('store_tagline', 'Tagline', $s('store_tagline')) ?></div>
  <div class="g2"><?= field_text('store_email', 'Store email (order alerts)', $s('store_email'), ['type' => 'email']) ?><?= field_text('store_phone', 'Phone', $s('store_phone')) ?></div>
  <?= field_text('store_address', 'Address', $s('store_address')) ?>
  <div class="g2"><?= field_text('currency', 'Currency code', $s('currency'), ['hint' => 'ISO code, e.g. USD, EUR, GBP — used by Stripe / PayPal']) ?><?= field_text('currency_symbol', 'Currency symbol', $s('currency_symbol')) ?></div>
  <?= field_text('meta_description', 'SEO description', $s('meta_description')) ?>
  <?= field_text('footer_text', 'Footer text', $s('footer_text')) ?>
  <label class="fld"><span>Logo (optional — replaces the text wordmark)</span><input type="file" name="logo" accept="image/*"></label>
  <?php if ($s('logo')): ?><div class="imgprev"><img src="<?= e(upload_url($s('logo'))) ?>" alt="" style="background:#0b0b0d;padding:10px"><label class="chk"><input type="checkbox" name="remove_logo" value="1"><i></i><span>Remove logo</span></label></div><?php endif; ?>
  <h2>Behaviour</h2>
  <?= field_text('low_stock', 'Low-stock alert at', $s('low_stock'), ['type' => 'number', 'min' => '0']) ?>
  <?= field_check('guest_checkout', 'Allow guest checkout', $s('guest_checkout') === '1') ?>
  <?= field_check('notify_admin', 'Email me when an order is placed', $s('notify_admin') === '1') ?>
  <?= field_check('maintenance', 'Maintenance mode (store hidden from visitors, admin still works)', $s('maintenance') === '1') ?>

<?php elseif ($tab === 'storefront'): ?>
  <h2>Home page hero</h2>
  <div class="g2"><?= field_text('hero_line1', 'Headline · line 1', $s('hero_line1')) ?><?= field_text('hero_line2', 'Headline · line 2 (accent colour)', $s('hero_line2')) ?></div>
  <?= field_area('hero_text', 'Intro text', $s('hero_text'), ['rows' => 2]) ?>
  <h2>Top ticker</h2>
  <?= field_check('ticker_enabled', 'Show scrolling ticker', $s('ticker_enabled') === '1') ?>
  <?= field_text('ticker', 'Ticker text', $s('ticker'), ['hint' => 'Separate phrases with " · "']) ?>
  <h2>Brand</h2>
  <label class="fld"><span>Accent colour</span><input type="color" name="accent" value="<?= e($s('accent')) ?>"></label>
  <h2>Social</h2>
  <div class="g3"><?= field_text('social_instagram', 'Instagram URL', $s('social_instagram')) ?><?= field_text('social_x', 'X / Twitter URL', $s('social_x')) ?><?= field_text('social_tiktok', 'TikTok URL', $s('social_tiktok')) ?></div>
  <p class="muted small">Department colours, headlines and order are edited under <a href="<?= e(url('admin/categories')) ?>">Departments</a>.</p>

<?php elseif ($tab === 'shipping'): ?>
  <h2>Shipping (physical products only)</h2>
  <div class="g2"><?= field_text('shipping_flat', 'Flat shipping rate', from_cents((int) $s('shipping_flat')), ['type' => 'number', 'step' => '0.01', 'min' => '0']) ?>
  <?= field_text('free_shipping_min', 'Free shipping over', from_cents((int) $s('free_shipping_min')), ['type' => 'number', 'step' => '0.01', 'min' => '0', 'hint' => '0 disables free shipping. Drives the progress bar in the cart.']) ?></div>
  <?= field_text('shipping_note', 'Delivery promise label', $s('shipping_note'), ['hint' => 'Shown on physical items, e.g. “Ships in 2 days”']) ?>
  <h2>Tax</h2>
  <div class="g2"><?= field_text('tax_rate', 'Tax rate %', $s('tax_rate'), ['type' => 'number', 'step' => '0.01', 'min' => '0', 'max' => '100', 'hint' => 'Added on top of prices. 0 = no tax line.']) ?><?= field_text('tax_label', 'Tax label', $s('tax_label'), ['placeholder' => 'VAT / GST / Tax']) ?></div>

<?php elseif ($tab === 'payments'): ?>
  <h2>Bank transfer / manual</h2>
  <?= field_check('pay_bank_on', 'Enable', $s('pay_bank_on') === '1') ?>
  <?= field_text('pay_bank_label', 'Label', $s('pay_bank_label')) ?>
  <?= field_area('pay_bank_info', 'Instructions shown to customer', $s('pay_bank_info'), ['rows' => 4]) ?>
  <h2>Cash on delivery</h2>
  <?= field_check('pay_cod_on', 'Enable (physical orders only)', $s('pay_cod_on') === '1') ?>
  <?= field_text('pay_cod_label', 'Label', $s('pay_cod_label')) ?>
  <h2>Stripe</h2>
  <?= field_check('pay_stripe_on', 'Enable Stripe Checkout', $s('pay_stripe_on') === '1') ?>
  <?= field_text('pay_stripe_label', 'Label', $s('pay_stripe_label')) ?>
  <div class="g2"><?= field_text('stripe_pk', 'Publishable key', $s('stripe_pk'), ['placeholder' => 'pk_live_…']) ?><?= field_text('stripe_sk', 'Secret key', $s('stripe_sk'), ['placeholder' => 'sk_live_…', 'autocomplete' => 'off']) ?></div>
  <?= field_text('stripe_whsec', 'Webhook signing secret (optional, recommended)', $s('stripe_whsec'), ['placeholder' => 'whsec_…', 'hint' => 'Webhook URL: <code>' . e(abs_url('pay/stripe/webhook')) . '</code> · event: <code>checkout.session.completed</code>']) ?>
  <h2>PayPal</h2>
  <?= field_check('pay_paypal_on', 'Enable PayPal', $s('pay_paypal_on') === '1') ?>
  <?= field_text('pay_paypal_label', 'Label', $s('pay_paypal_label')) ?>
  <div class="g2"><?= field_text('paypal_client', 'Client ID', $s('paypal_client')) ?><?= field_text('paypal_secret', 'Secret', $s('paypal_secret'), ['autocomplete' => 'off']) ?></div>
  <?= field_check('paypal_sandbox', 'Sandbox (test) mode', $s('paypal_sandbox') === '1') ?>

<?php elseif ($tab === 'email'): ?>
  <h2>Outgoing email</h2>
  <?= field_select('mail_driver', 'Method', ['mail' => 'PHP mail() — simplest', 'smtp' => 'SMTP — most reliable', 'log' => 'Log only (testing)'], $s('mail_driver')) ?>
  <div class="g2"><?= field_text('mail_from', 'From address', $s('mail_from'), ['type' => 'email', 'placeholder' => 'orders@yourdomain.com']) ?><?= field_text('mail_from_name', 'From name', $s('mail_from_name')) ?></div>
  <div class="g3"><?= field_text('smtp_host', 'SMTP host', $s('smtp_host')) ?><?= field_text('smtp_port', 'Port', $s('smtp_port')) ?><?= field_select('smtp_enc', 'Encryption', ['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None'], $s('smtp_enc')) ?></div>
  <div class="g2"><?= field_text('smtp_user', 'SMTP username', $s('smtp_user'), ['autocomplete' => 'off']) ?><?= field_text('smtp_pass', 'SMTP password', $s('smtp_pass'), ['type' => 'password', 'autocomplete' => 'new-password']) ?></div>
<?php endif; ?>

  <div class="actions"><button class="btn">Save <?= e($tabs[$tab]['label']) ?></button></div>
</form>

<?php if ($tab === 'email'): ?>
<form method="post" action="<?= e(url('admin/settings/test-mail')) ?>" class="apanel narrow inline-add" style="margin-top:14px"><?= csrf_field() ?>
  <input type="email" name="to" placeholder="Send a test email to…" value="<?= e($s('store_email')) ?>"><button class="btn ghost">Send test</button></form>
<?php endif; ?>
