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
$fullTitle = str_contains($pageTitle, $name) ? $pageTitle : $pageTitle . ' — ' . $name;
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
<?php if (!empty($meta['og_image'])): ?><meta property="og:image" content="<?= e($meta['og_image']) ?>"><?php endif; ?>
<link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="9" fill="#0b0b0d"/><circle cx="16" cy="16" r="7" fill="' . $accent . '"/></svg>') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Mono:wght@400;700&family=Syne:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_v('css/store.css')) ?>">
<style>:root{--accent:<?= e($accent) ?>;--accent-ink:<?= e(\App\Support\Art::ink($accent)) ?>}</style>
</head>
<body class="<?= $step ? 'is-checkout' : '' ?>">
<a class="skip" href="#main">Skip to content</a>
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
  </nav>
  <div class="hdr-actions">
    <button class="icon-btn" id="search-open" aria-label="Search"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></button>
    <a class="icon-btn desk" href="<?= e(url($user ? 'account' : 'login')) ?>" aria-label="Account"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4-6 8-6s7 2 8 6"/></svg></a>
    <button class="cart-btn" id="cart-open" aria-label="Open cart">Cart <span class="count" data-cart-count><?= $cartCount ?></span></button>
    <button class="icon-btn burger" id="burger" aria-label="Menu"><span></span><span></span></button>
  </div>
  <?php endif; ?>
</header>

<?php $fl = flashes(); if ($fl): ?><div class="flashes" role="status"><?php foreach ($fl as $f): ?><div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?></div><?php endif; ?>

<main id="main"><?= $content ?></main>

<footer class="ftr">
  <div class="ftr-ghost" aria-hidden="true"><?= e(strtoupper($name)) ?></div>
  <div class="ftr-row">
    <nav class="ftr-nav">
      <?php foreach ($cats as $c): ?><a href="<?= e(url('shop/' . $c['slug'])) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
      <a href="<?= e(url('cart')) ?>">Cart</a>
      <a href="<?= e(url('track')) ?>">Track order</a>
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

<!-- Search overlay -->
<div class="search" id="search" hidden>
  <div class="search-box">
    <form action="<?= e(url('search')) ?>" method="get" role="search"><input id="search-input" name="q" type="search" placeholder="Search everything…" autocomplete="off" aria-label="Search"></form>
    <div class="search-results" id="search-results"></div>
    <button class="search-close" id="search-close" type="button">Esc</button>
  </div>
</div>
<div class="toasts" id="toasts" aria-live="polite"></div>

<script>window.KIOSK={base:<?= json_encode('') ?>,csrf:<?= json_encode(csrf_token()) ?>};</script>
<script src="<?= e(asset_v('js/store.js')) ?>" defer></script>
</body>
</html>
