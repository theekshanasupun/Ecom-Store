<?php
use App\Services\Cart;
use App\Services\Catalog;

$cats = Catalog::categories();
$user = auth()->user();
$meta = \App\Support\Meta::$data;
$step = (int) ($meta['step'] ?? 0);
$path = '/' . ltrim(request()->path(), '/');
$name = (string) setting('store_name', 'KIOSK');
$pageTitle = ($meta['title'] ?? '') ?: $name;
$isHome = !empty($meta['home']);
$fullTitle = \App\Support\Seo::title($pageTitle, $isHome);
$cartCount = Cart::count();
$accent = preg_match('/^#[0-9a-f]{6}$/i', (string) setting('accent')) ? setting('accent') : '#c6ff3d';
$pagesFooter = \App\Models\Page::where('status', 'published')->where('in_footer', true)->orderBy('id')->get(['title', 'slug'])->all();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($meta['description'] ?? setting('meta_description')) ?>">
<meta name="theme-color" content="#0b0b0d">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:site_name" content="<?= e($name) ?>">
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(upload_url(setting('favicon'))) ?>"><link rel="apple-touch-icon" href="<?= e(upload_url(setting('favicon'))) ?>"><?php else: ?><link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="9" fill="#0b0b0d"/><circle cx="16" cy="16" r="7" fill="' . $accent . '"/></svg>') ?>"><?php endif; ?>
<?= \App\Support\Seo::head($meta, $pageTitle, $isHome) ?>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Mono:wght@400;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_v('css/store.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_v('css/store26.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_v('css/commerce26.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_v('css/loyalty27.css')) ?>">
<style>:root{--accent:<?= e($accent) ?>;--accent-ink:<?= e(\App\Support\Art::ink($accent)) ?>}</style>
</head>
<body class="<?= $step ? 'is-checkout' : '' ?>">
<?= \App\Support\Seo::bodyStart() ?>
<a class="skip" href="#main">Skip to content</a>
<?php
$annLines = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) setting('announce_text')))));
$annOk = setting('announce_on') === '1' && $annLines && !$step
    && (!setting('announce_start') || strtotime((string) setting('announce_start')) <= time())
    && (!setting('announce_end') || strtotime((string) setting('announce_end')) + 86399 >= time());
if ($annOk):
  $annStyle = in_array(setting('announce_style'), ['static', 'rotate', 'marquee'], true) ? setting('announce_style') : 'static';
  $annBg = preg_match('/^#[0-9a-f]{6}$/i', (string) setting('announce_bg')) ? setting('announce_bg') : '#c6ff3d';
  $annFg = preg_match('/^#[0-9a-f]{6}$/i', (string) setting('announce_fg')) ? setting('announce_fg') : '#0b0b0d';
  $annLink = trim((string) setting('announce_link'));
  $annHref = $annLink === '' ? '' : (preg_match('#^(https?:)?//#i', $annLink) ? $annLink : url(ltrim($annLink, '/')));
  if (preg_match('/^\s*(javascript|data):/i', $annLink)) $annHref = '';
?>
<div class="annbar ann-<?= e($annStyle) ?>" id="annbar" data-key="<?= e(substr(md5(implode('|', $annLines)), 0, 10)) ?>" data-close="<?= setting('announce_close') === '1' ? '1' : '0' ?>" style="background:<?= e($annBg) ?>;color:<?= e($annFg) ?>" role="region" aria-label="Announcement">
  <div class="ann-track"><?php foreach (($annStyle === 'static' ? [$annLines[0]] : $annLines) as $i => $ln): ?><span class="ann-msg<?= $i === 0 ? ' on' : '' ?>"><?php if ($annHref): ?><a href="<?= e($annHref) ?>" style="color:inherit"><?= e($ln) ?></a><?php else: ?><?= e($ln) ?><?php endif; ?></span><?php endforeach; ?></div>
  <?php if (setting('announce_close') === '1'): ?><button type="button" class="ann-x" aria-label="Dismiss" style="color:inherit">✕</button><?php endif; ?>
</div>
<?php endif; ?>
<?php if (setting('fd_on') === '1' && (int) setting('free_shipping_min') > 0 && !$step):
  $fb = Cart::freeBar();
  $fdt = fn ($t) => strtr((string) $t, ['{amount}' => $fb['left_txt'], '{min}' => $fb['min_txt']]);
  $fdText = $fb['empty'] ? $fdt(setting('fd_text_empty')) : ($fb['unlocked'] ? $fdt(setting('fd_text_done')) : $fdt(setting('fd_text_progress')));
  $col = fn ($k, $d) => preg_match('/^#[0-9a-f]{6}$/i', (string) setting($k)) ? setting($k) : $d;
?>
<div class="fdbar" id="fdbar" style="background:<?= e($col('fd_bg', '#141418')) ?>;color:<?= e($col('fd_fg', '#f3f1ec')) ?>;--fdc:<?= e($col('fd_bar', '#c6ff3d')) ?>" data-tpl-progress="<?= e(setting('fd_text_progress')) ?>" data-tpl-done="<?= e(setting('fd_text_done')) ?>" data-tpl-empty="<?= e(setting('fd_text_empty')) ?>">
  <span class="fd-txt"><?= e($fdText) ?></span><span class="fd-track"><i style="width:<?= $fb['empty'] ? 0 : $fb['pct'] ?>%"></i></span>
</div>
<?php endif; ?>
<?php if (setting('ticker_enabled') === '1' && trim((string) setting('ticker')) !== '' && !$step): ?>
<div class="ticker" aria-hidden="true"><div class="ticker-track"><?php $t = strtoupper((string) setting('ticker')); $t = preg_replace('/\s*·\s*/', ' &nbsp;·&nbsp; ', e($t)); for ($i = 0; $i < 4; $i++) echo '<span>' . $t . ' &nbsp;·&nbsp; </span>'; ?></div></div>
<?php endif; ?>

<header class="hdr" id="hdr">
  <a class="logo" href="<?= e(url('')) ?>" aria-label="<?= e($name) ?> home"><?php if (setting('logo')): ?><img src="<?= e(upload_url(setting('logo'))) ?>" alt="<?= e($name) ?>"><?php else: ?><?= e(strtoupper($name)) ?><?php endif; ?></a>
  <?php if ($step): ?>
  <ol class="steps" aria-label="Checkout steps">
    <li class="<?= $step >= 1 ? 'on' : '' ?>"><a href="<?= e(url('cart')) ?>"><b>1</b> Cart</a></li><li class="sep"></li>
    <li class="<?= $step >= 2 ? 'on' : '' ?>"><b>2</b> Details</li><li class="sep"></li>
    <li class="<?= $step >= 3 ? 'on' : '' ?>"><b>3</b> Payment</li>
  </ol>
  <nav class="nav nav-right"><?php foreach ($cats as $c): ?><a href="<?= e(url('shop/' . $c['slug'])) ?>"><?= e($c['name']) ?></a><?php endforeach; ?></nav>
  <?php else: ?>
  <nav class="nav" id="nav">
    <a href="<?= e(url('')) ?>" class="<?= $path === '/' ? 'on' : '' ?>">All</a>
    <?php foreach ($cats as $c): $on = $path === '/shop/' . $c['slug']; ?>
      <a href="<?= e(url('shop/' . $c['slug'])) ?>" class="<?= $on ? 'on' : '' ?>" style="--c:<?= e($c['color']) ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
    <a class="mob-only" href="<?= e(url($user ? 'account' : 'login')) ?>"><?= $user ? 'My account' : 'Sign in' ?></a>
    <?php if ($user && $user->isStaff()): ?><a class="mob-only" href="<?= e(url('admin')) ?>">Admin panel</a><?php endif; ?>
  </nav>
  <div class="hdr-actions">
    <button class="icon-btn" id="search-open" aria-label="Search"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></button>
    <?php if ($user): ?>
    <details class="acct desk" id="acct"><summary class="icon-btn" aria-label="Account menu"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/></svg></summary>
      <div class="acct-menu"><b><?= e($user->name) ?></b><small><?= e($user->email) ?></small>
        <a href="<?= e(url('account')) ?>">My account &amp; orders</a>
        <?php if ($user->isStaff()): ?><a class="acct-admin" href="<?= e(url('admin')) ?>">Admin panel →</a><?php endif; ?>
        <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button type="submit">Sign out</button></form>
      </div></details>
    <?php else: ?>
    <a class="icon-btn desk" href="<?= e(url('login')) ?>" aria-label="Sign in"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/></svg></a>
    <?php endif; ?>
    <button class="cart-btn" id="cart-open" aria-label="Open cart">Cart <span class="count" data-cart-count><?= $cartCount ?></span></button>
    <button class="icon-btn burger" id="burger" aria-label="Menu"><span></span><span></span></button>
  </div>
  <?php endif; ?>
</header>

<?php $fl = flashes(); if ($fl): ?><div class="flashes" role="status"><?php foreach ($fl as $f): ?><div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?></div><?php endif; ?>

<main id="main"><?= $content ?></main>

<footer class="ftr">
  <?php if (setting('newsletter_footer_on', '1') === '1' && !$step): ?>
  <section class="ftr-news" aria-label="Newsletter">
    <div><h3><?= e(setting('newsletter_title', 'Join the list')) ?></h3><p><?= e(setting('newsletter_text', 'New drops and member-only deals, straight to your inbox. No spam, unsubscribe any time.')) ?></p></div>
    <form class="news-form" data-news novalidate><?= \App\Http\Controllers\NewsletterController::fields() ?><label class="sr-only" for="ftr-news-email">Email address</label><input id="ftr-news-email" type="email" name="email" placeholder="you@example.com" required autocomplete="email"><button class="btn" type="submit">Subscribe</button><small class="sub-msg" role="status" hidden></small></form>
  </section>
  <?php endif; ?>
  <div class="ftr-ghost" aria-hidden="true"><?= e(strtoupper($name)) ?></div>
  <div class="ftr-row">
    <nav class="ftr-nav">
      <?php foreach ($cats as $c): ?><a href="<?= e(url('shop/' . $c['slug'])) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
      <a href="<?= e(url('cart')) ?>">Cart</a>
      <a href="<?= e(url('track')) ?>">Track order</a>
      <?php if (\App\Services\GiftCards::enabled()): ?><a href="<?= e(url('gift-cards')) ?>">Gift cards</a><?php endif; ?>
      <?php foreach ($pagesFooter as $pg): ?><a href="<?= e(url('p/' . $pg['slug'])) ?>"><?= e($pg['title']) ?></a><?php endforeach; ?>
    </nav>
    <p class="ftr-copy">© <?= date('Y') ?> <?= e($name) ?>. <?= e(setting('footer_text')) ?></p>
  </div>
</footer>

<!-- Cart drawer -->
<div class="scrim" id="scrim" hidden></div>
<aside class="drawer" id="drawer" aria-hidden="true" aria-label="Cart">
  <div class="drawer-head"><h2>Your cart</h2><button class="icon-btn" id="cart-close" aria-label="Close cart">✕</button></div>
  <div class="drawer-body" id="drawer-body"><?= partial('partials/cart_drawer', ['sum' => Cart::summary()]) ?></div>
</aside>

<!-- Search overlay (typeahead: assets/js/store26.js) -->
<div class="search" id="search" hidden role="dialog" aria-modal="true" aria-label="Search">
  <div class="search-box">
    <form action="<?= e(url('search')) ?>" method="get" role="search" autocomplete="off"><input id="search-input" name="q" type="search" placeholder="Search everything…" autocomplete="off" aria-label="Search" role="combobox" aria-expanded="false" aria-controls="search-results" aria-autocomplete="list" enterkeyhint="search"></form>
    <div class="search-results" id="search-results" role="listbox" aria-live="polite"></div>
    <button class="search-close" id="search-close" type="button">Esc</button>
  </div>
</div>
<div class="toasts" id="toasts" aria-live="polite"></div>
<?= empty($meta['builder_preview']) ? \App\Support\Popups::render(request()) : '' ?>

<script>window.KIOSK={base:<?= json_encode('') ?>,csrf:<?= json_encode(csrf_token()) ?>};</script>
<script src="<?= e(asset_v('js/store.js')) ?>" defer></script>
<script src="<?= e(asset_v('js/store26.js')) ?>" defer></script>
<script src="<?= e(asset_v('js/commerce26.js')) ?>" defer></script>
</body>
</html>
