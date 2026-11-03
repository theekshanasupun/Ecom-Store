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
  <label class="fld"><span>Favicon (browser tab icon — PNG, ICO, WEBP; square, 64×64 or larger)</span><input type="file" name="favicon" accept=".ico,.png,.webp,.jpg,.jpeg,.gif,image/*"></label>
  <?php if ($s('favicon')): ?><div class="imgprev"><img src="<?= e(upload_url($s('favicon'))) ?>" alt="" style="width:48px;height:48px;object-fit:contain;background:#0b0b0d;padding:6px"><label class="chk"><input type="checkbox" name="remove_favicon" value="1"><i></i><span>Remove favicon</span></label></div><?php endif; ?>
  <h2>Behaviour</h2>
  <?= field_text('low_stock', 'Low-stock alert at', $s('low_stock'), ['type' => 'number', 'min' => '0']) ?>
  <?= field_check('guest_checkout', 'Allow guest checkout', $s('guest_checkout') === '1') ?>
  <?= field_check('notify_admin', 'Email me when an order is placed', $s('notify_admin') === '1') ?>
  <?= field_check('maintenance', 'Maintenance mode (store hidden from visitors, admin still works)', $s('maintenance') === '1') ?>

<?php elseif ($tab === 'storefront'): ?>
  <h2>Home page hero</h2>
  <div class="g2"><?= field_text('hero_line1', 'Headline · line 1', $s('hero_line1')) ?><?= field_text('hero_line2', 'Headline · line 2 (accent colour)', $s('hero_line2')) ?></div>
  <?= field_area('hero_text', 'Intro text', $s('hero_text'), ['rows' => 2]) ?>
  <p class="muted small">The announcement bar, free-delivery bar and ticker are under <a href="<?= e(url('admin/settings?tab=bars')) ?>">Banners &amp; delivery bar</a>.</p>
  <h2>Brand</h2>
  <label class="fld"><span>Accent colour</span><input type="color" name="accent" value="<?= e($s('accent')) ?>"></label>
  <h2>Social</h2>
  <div class="g3"><?= field_text('social_instagram', 'Instagram URL', $s('social_instagram')) ?><?= field_text('social_x', 'X / Twitter URL', $s('social_x')) ?><?= field_text('social_tiktok', 'TikTok URL', $s('social_tiktok')) ?></div>
  <p class="muted small">Department colours, headlines and order are edited under <a href="<?= e(url('admin/categories')) ?>">Departments</a>.</p>

<?php elseif ($tab === 'bars'): ?>
  <h2>Upper banner bar</h2>
  <p class="muted small">A slim announcement strip above the header — great for sales, shipping cut-offs or a promo code.</p>
  <?= field_check('announce_on', 'Show announcement bar', $s('announce_on') === '1') ?>
  <?= field_area('announce_text', 'Message(s) — one per line', $s('announce_text'), ['rows' => 3, 'hint' => 'With “Rotating” or “Scrolling” style every line is shown in turn.']) ?>
  <div class="g2"><?= field_text('announce_link', 'Link (optional)', $s('announce_link'), ['placeholder' => '/shop/toys or https://…']) ?>
  <?= field_select('announce_style', 'Style', ['static' => 'Static (first line)', 'rotate' => 'Rotating messages', 'marquee' => 'Scrolling ticker'], $s('announce_style')) ?></div>
  <div class="g3"><label class="fld"><span>Background</span><input type="color" name="announce_bg" value="<?= e($s('announce_bg')) ?>"></label><label class="fld"><span>Text colour</span><input type="color" name="announce_fg" value="<?= e($s('announce_fg')) ?>"></label>
  <div><?= field_check('announce_close', 'Visitors can dismiss it', $s('announce_close') === '1') ?></div></div>
  <div class="g2"><?= field_text('announce_start', 'Show from (optional)', $s('announce_start'), ['type' => 'date']) ?><?= field_text('announce_end', 'Hide after (optional)', $s('announce_end'), ['type' => 'date']) ?></div>
  <h2>Free delivery bar</h2>
  <p class="muted small">A live progress bar under the banner: “You’re $12.50 away from free delivery”. It updates instantly as customers add items.</p>
  <?= field_check('fd_on', 'Show free delivery bar', $s('fd_on') === '1') ?>
  <?= field_text('free_shipping_min', 'Free delivery threshold', from_cents((int) $s('free_shipping_min')), ['type' => 'number', 'step' => '0.01', 'min' => '0', 'hint' => 'Physical items only. 0 turns free delivery off. Same value as Shipping & tax.']) ?>
  <?= field_text('fd_text_empty', 'Message — empty cart', $s('fd_text_empty'), ['hint' => 'Tags: {min} = threshold']) ?>
  <?= field_text('fd_text_progress', 'Message — in progress', $s('fd_text_progress'), ['hint' => 'Tags: {amount} = still to spend, {min} = threshold']) ?>
  <?= field_text('fd_text_done', 'Message — unlocked', $s('fd_text_done')) ?>
  <div class="g3"><label class="fld"><span>Background</span><input type="color" name="fd_bg" value="<?= e($s('fd_bg')) ?>"></label><label class="fld"><span>Text colour</span><input type="color" name="fd_fg" value="<?= e($s('fd_fg')) ?>"></label><label class="fld"><span>Progress colour</span><input type="color" name="fd_bar" value="<?= e($s('fd_bar')) ?>"></label></div>
  <?= field_check('fd_in_drawer', 'Also show the progress meter inside the cart drawer', $s('fd_in_drawer') !== '0') ?>
  <h2>Scrolling ticker (classic)</h2>
  <?= field_check('ticker_enabled', 'Show scrolling ticker', $s('ticker_enabled') === '1') ?>
  <?= field_text('ticker', 'Ticker text', $s('ticker'), ['hint' => 'Separate phrases with " · "']) ?>
  <h2>Popups</h2>
  <p class="muted small">Create popups under <a href="<?= e(url('admin/popups')) ?>">Popups</a>. Master switch:</p>
  <?= field_check('popups_on', 'Enable popups sitewide', $s('popups_on') === '1') ?>

<?php elseif ($tab === 'seo'): ?>
  <h2>Search appearance</h2>
  <div class="seo-prev" aria-hidden="true"><div class="u"><?= e(preg_replace('#^https?://#', '', url('/'))) ?></div><div class="t"><?= e(\App\Support\Seo::title((string) $s('store_name') . ' home', false)) ?></div><div class="d"><?= e($s('meta_description')) ?></div></div>
  <?= field_text('seo_title_tpl', 'Page title template', $s('seo_title_tpl'), ['hint' => 'Tags: {title} {site} {tagline}. Example: <code>{title} | {site}</code>']) ?>
  <?= field_text('seo_home_title', 'Home page title (optional override)', $s('seo_home_title'), ['data-count-x' => 1]) ?>
  <?= field_area('meta_description', 'Default meta description', $s('meta_description'), ['rows' => 2, 'hint' => 'Used when a page has no description of its own (aim for 120–155 characters).']) ?>
  <?= field_text('seo_keywords', 'Meta keywords (optional)', $s('seo_keywords')) ?>
  <label class="fld"><span>Default social share image (1200×630)</span><input type="file" name="seo_og_image" accept="image/*"></label>
  <?php if ($s('seo_og_image')): ?><div class="imgprev"><img src="<?= e(upload_url($s('seo_og_image'))) ?>" alt=""></div><?php endif; ?>
  <?= field_text('seo_twitter', 'X / Twitter handle', $s('seo_twitter'), ['placeholder' => 'yourstore']) ?>
  <p class="muted small">Every product, department and page also has its own SEO title / description / noindex fields in its editor.</p>

  <h2>Indexing &amp; crawlers</h2>
  <?= field_check('seo_noindex_site', 'Discourage search engines from indexing the whole site (use while building)', $s('seo_noindex_site') === '1') ?>
  <?= field_check('seo_canonical', 'Add canonical URLs', $s('seo_canonical') === '1') ?>
  <?= field_check('seo_ai_allow', 'Allow AI crawlers & assistants (GPTBot, ClaudeBot, PerplexityBot, Google-Extended…)', $s('seo_ai_allow') === '1', 'Untick to block them in robots.txt') ?>
  <?= field_check('seo_llms_on', 'Publish /llms.txt — a plain-language guide to your store for AI answer engines', $s('seo_llms_on') === '1') ?>
  <?= field_area('seo_llms_extra', 'Extra context for AI (shown at the top of llms.txt)', $s('seo_llms_extra'), ['rows' => 3, 'hint' => 'Who you are, what you sell, shipping & returns in plain sentences — this is what generative engines quote.']) ?>
  <?= field_area('seo_robots_extra', 'Extra robots.txt rules', $s('seo_robots_extra'), ['rows' => 3, 'placeholder' => "User-agent: *\nDisallow: /private/"]) ?>
  <p class="muted small">Your files: <a target="_blank" rel="noopener" href="<?= e(url('sitemap.xml')) ?>">sitemap.xml</a> · <a target="_blank" rel="noopener" href="<?= e(url('robots.txt')) ?>">robots.txt</a> · <a target="_blank" rel="noopener" href="<?= e(url('llms.txt')) ?>">llms.txt</a></p>

  <h2>Structured data (rich results)</h2>
  <?= field_check('seo_schema_org', 'Organization / LocalBusiness schema', $s('seo_schema_org') === '1') ?>
  <?= field_check('seo_schema_product', 'Product schema (price, stock, images) on product pages', $s('seo_schema_product') === '1') ?>
  <?= field_check('seo_schema_search', 'Sitelinks search box schema on the home page', $s('seo_schema_search') === '1') ?>
  <?= field_check('seo_breadcrumbs', 'Breadcrumb schema', $s('seo_breadcrumbs') === '1') ?>

  <h2>Verification &amp; analytics</h2>
  <div class="g3"><?= field_text('seo_google_verify', 'Google Search Console token', $s('seo_google_verify')) ?><?= field_text('seo_bing_verify', 'Bing Webmaster token', $s('seo_bing_verify')) ?><?= field_text('seo_pinterest_verify', 'Pinterest token', $s('seo_pinterest_verify')) ?></div>
  <div class="g3"><?= field_text('ga4_id', 'Google Analytics 4 ID', $s('ga4_id'), ['placeholder' => 'G-XXXXXXXXXX']) ?><?= field_text('gtm_id', 'Google Tag Manager ID', $s('gtm_id'), ['placeholder' => 'GTM-XXXXXXX']) ?><?= field_text('fb_pixel', 'Meta (Facebook) Pixel ID', $s('fb_pixel')) ?></div>
  <?php if (auth()->user()->isAdmin()): ?>
  <?= field_area('seo_head_code', 'Custom code — inside <head>', $s('seo_head_code'), ['rows' => 3, 'hint' => 'Administrators only. Scripts here run on every storefront page.']) ?>
  <?= field_area('seo_body_code', 'Custom code — after <body>', $s('seo_body_code'), ['rows' => 3]) ?>
  <?php endif; ?>

<?php elseif ($tab === 'geo'): ?>
  <h2>GEO / local presence</h2>
  <p class="muted small">Tell search engines and AI assistants where you operate. This adds LocalBusiness structured data, geo meta tags and location info in llms.txt — it helps “near me” searches and local answers.</p>
  <?= field_check('geo_on', 'Enable local business markup', $s('geo_on') === '1') ?>
  <div class="g2"><?= field_text('geo_name', 'Business name (if different)', $s('geo_name'), ['placeholder' => $s('store_name')]) ?>
  <?= field_select('geo_type', 'Business type', ['Store' => 'Store', 'OnlineStore' => 'Online store', 'ToyStore' => 'Toy store', 'ClothingStore' => 'Clothing store', 'ElectronicsStore' => 'Electronics store', 'LocalBusiness' => 'Local business (generic)', 'HobbyShop' => 'Hobby shop', 'GiftShop' => 'Gift shop'], $s('geo_type')) ?></div>
  <?= field_text('geo_street', 'Street address', $s('geo_street')) ?>
  <div class="g3"><?= field_text('geo_city', 'City', $s('geo_city')) ?><?= field_text('geo_region', 'State / region', $s('geo_region')) ?><?= field_text('geo_postal', 'Postal code', $s('geo_postal')) ?></div>
  <div class="g3"><?= field_text('geo_country', 'Country (2-letter code)', $s('geo_country'), ['maxlength' => 2, 'placeholder' => 'US']) ?><?= field_text('geo_lat', 'Latitude', $s('geo_lat'), ['placeholder' => '40.7128']) ?><?= field_text('geo_lng', 'Longitude', $s('geo_lng'), ['placeholder' => '-74.0060']) ?></div>
  <?= field_area('geo_hours', 'Opening hours — one per line', $s('geo_hours'), ['rows' => 3, 'placeholder' => "Mon-Fri 09:00-17:00\nSat 10:00-14:00", 'hint' => 'Format: Day or Day-Day, then HH:MM-HH:MM']) ?>
  <div class="g2"><?= field_text('geo_price_range', 'Price range', $s('geo_price_range'), ['placeholder' => '$$']) ?><?= field_text('geo_areas', 'Service areas (comma-separated)', $s('geo_areas'), ['placeholder' => 'Colombo, Kandy, Galle']) ?></div>
  <?= field_area('geo_same_as', 'Other profiles (Google Business, Facebook, Maps…) — one URL per line', $s('geo_same_as'), ['rows' => 3]) ?>

<?php elseif ($tab === 'social'): ?>
  <h2>Social login</h2>
  <p class="muted small">Let customers sign in with one click. Create an OAuth app with each provider and paste its credentials here. For each app use the redirect URL shown below.</p>
  <?php foreach (\App\Services\Social::PROVIDERS as $k => $pv): ?>
  <h3><?= e($pv['name']) ?></h3>
  <?= field_check("login_{$k}_on", 'Enable ' . $pv['name'] . ' login', $s("login_{$k}_on") === '1') ?>
  <div class="g2"><?= field_text("{$k}_id", 'Client ID', $s("{$k}_id"), ['autocomplete' => 'off']) ?><?= field_text("{$k}_secret", 'Client secret', $s("{$k}_secret"), ['type' => 'password', 'autocomplete' => 'new-password']) ?></div>
  <small class="hint">Redirect URL: <code><?= e(\App\Services\Social::callback($k)) ?></code></small>
  <?php endforeach; ?>
  <p class="muted small" style="margin-top:14px">An existing account is only linked when the provider confirms the email address; otherwise the customer is asked to sign in with their password first.</p>

<?php elseif ($tab === 'shipping'): ?>
  <h2>Shipping (physical products only)</h2>
  <div class="g2"><?= field_text('shipping_flat', 'Flat shipping rate', from_cents((int) $s('shipping_flat')), ['type' => 'number', 'step' => '0.01', 'min' => '0']) ?>
  <?= field_text('free_shipping_min', 'Free shipping over', from_cents((int) $s('free_shipping_min')), ['type' => 'number', 'step' => '0.01', 'min' => '0', 'hint' => '0 disables free shipping. Drives the progress bar in the cart.']) ?></div>
  <?= field_text('shipping_note', 'Delivery promise label', $s('shipping_note'), ['hint' => 'Shown on physical items, e.g. “Ships in 2 days”']) ?>
  <h2>Delivery options</h2>
  <?= field_text('ship_std_label', 'Name of the standard option', $s('ship_std_label') ?: 'Standard delivery', ['hint' => 'Uses the flat rate / free-shipping threshold above.']) ?>
  <?= field_area('shipping_methods', 'Extra delivery options (one per line)', $s('shipping_methods'), ['rows' => 4, 'placeholder' => "Express | 14.99 | 1-2 business days\nNext-day courier | 24.99 | Order before 2pm", 'hint' => 'Format: <code>Name | price | delivery promise</code>. Customers pick one at checkout; the price is added to the order.']) ?>
  <h2>Tax</h2>
  <div class="g2"><?= field_text('tax_rate', 'Tax rate %', $s('tax_rate'), ['type' => 'number', 'step' => '0.01', 'min' => '0', 'max' => '100', 'hint' => 'Added on top of prices. 0 = no tax line.']) ?><?= field_text('tax_label', 'Tax label', $s('tax_label'), ['placeholder' => 'VAT / GST / Tax']) ?></div>

<?php elseif ($tab === 'accounts'): ?>
  <h2>Sign-in methods</h2>
  <?= field_check('login_password_on', 'Allow sign-in with a password', setting('login_password_on', '1') === '1') ?>
  <?= field_check('login_otp_on', 'Allow sign-in / sign-up with a one-time email code (no password)', setting('login_otp_on', '1') === '1', 'Customers type their email, receive a 6-digit code and are signed in — accounts are created automatically.') ?>
  <?= field_check('login_otp_staff', 'Let staff and admins use email codes too', setting('login_otp_staff', '0') === '1', 'Off by default: admin and staff accounts must use their password.') ?>
  <h2>Checkout</h2>
  <?= field_check('guest_checkout', 'Allow guest checkout', setting('guest_checkout', '1') === '1') ?>
  <?= field_check('checkout_subscribe_default', 'Pre-tick “email me news and offers” at checkout', setting('checkout_subscribe_default', '0') === '1', 'Leave off where consent must be explicit (GDPR).') ?>
  <?= field_check('credit_on', 'Customers can spend store credit at checkout', \App\Services\StoreCredit::enabled(), 'Manage balances under Promotions → Store credit.') ?>
  <h2>Newsletter signup</h2>
  <?= field_check('newsletter_footer_on', 'Show the newsletter signup in the footer', setting('newsletter_footer_on', '1') === '1') ?>
  <?= field_text('newsletter_title', 'Footer title', setting('newsletter_title', 'Join the list'), ['placeholder' => 'Join the list']) ?>
  <?= field_text('newsletter_text', 'Footer text', setting('newsletter_text', 'New drops and deals, straight to your inbox.')) ?>

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
  <?= field_text('stripe_whsec', 'Webhook signing secret (optional, recommended)', $s('stripe_whsec'), ['placeholder' => 'whsec_…', 'hint' => 'Webhook URL: <code>' . e(url('pay/stripe/webhook')) . '</code> · event: <code>checkout.session.completed</code>']) ?>
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
