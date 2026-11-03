<?php
$u = auth()->user();
$path = '/' . ltrim(request()->path(), '/');
$name = (string) setting('store_name');
$toProcess = \App\Models\Order::whereIn('status', ['pending', 'processing'])->where('payment_status', 'paid')->count();
$nav = [
    ['admin', 'Dashboard', 'M3 12 12 3l9 9M5 10v10h14V10', false],
    ['admin/orders', 'Orders', 'M6 2 3 6v14h18V6l-3-4zM3 6h18M16 10a4 4 0 0 1-8 0', false],
    ['admin/products', 'Products', 'M21 8 12 3 3 8v8l9 5 9-5zM3 8l9 5 9-5M12 13v8', false],
    ['admin/categories', 'Departments', 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z', false],
    ['admin/customers', 'Customers', 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8', false],
    ['admin/coupons', 'Coupons', 'M20 12v8H4v-8M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z', false],
    ['admin/popups', 'Popups', 'M4 5h16v11H4zM8 20h8M12 16v4M9 10h6', false],
    ['admin/automations', 'Email automations', 'M4 4h16v16H4zM4 8l8 5 8-5', false],
    ['admin/carts', 'Abandoned carts', 'M6 6h15l-2 9H8zM6 6 5 3H2M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2M18 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2', false],
    ['admin/subscribers', 'Subscribers', 'M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0', false],
    ['admin/builder/home', 'Page builder', 'M3 3h18v6H3zM3 13h8v8H3zM15 13h6v8h-6z', false],
    ['admin/pages', 'Pages', 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h8', false],
    ['admin/settings', 'Settings', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z', true],
    ['admin/users', 'Team', 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1', true],
    ['admin/system', 'System & updates', 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3', true],
];
$active = function (string $p) use ($path) {
    $full = '/' . $p;
    return $p === 'admin' ? $path === '/admin' : str_starts_with($path, $full);
};
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e((\App\Support\Meta::$data['title'] ?? 'Admin') . ' · ' . $name . ' Admin') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Mono:wght@400;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(upload_url(setting('favicon'))) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset_v('css/admin.css')) ?>">
<style>:root{--accent:<?= e(setting('accent', '#c6ff3d')) ?>}</style>
</head><body>
<div class="adm">
  <aside class="side" id="side">
    <a class="brand" href="<?= e(url('admin')) ?>"><?= e(strtoupper($name)) ?><i>.</i><small>ADMIN</small></a>
    <nav>
      <?php foreach ($nav as [$p, $label, $icon, $adminOnly]): if ($adminOnly && !$u->isAdmin()) continue; ?>
      <a href="<?= e(url($p)) ?>" class="<?= $active($p) ? 'on' : '' ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $icon ?>"/></svg><span><?= e($label) ?></span><?php if ($p === 'admin/orders' && $toProcess): ?><em><?= $toProcess ?></em><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot"><a href="<?= e(url('')) ?>" target="_blank" rel="noopener">View store ↗</a><small>v<?= e(config('kiosk.version')) ?></small></div>
  </aside>
  <div class="main">
    <header class="top">
      <button class="menu" id="menu" aria-label="Menu">☰</button>
      <h1><?= e(\App\Support\Meta::$data['title'] ?? '') ?></h1>
      <div class="top-r">
        <a class="user" href="<?= e(url('admin/profile')) ?>"><span class="av"><?= e(strtoupper(mb_substr($u['name'], 0, 1))) ?></span><span class="un"><?= e($u['name']) ?></span></a>
        <form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button class="btn sm ghost">Sign out</button></form>
      </div>
    </header>
    <div class="content">
      <?php foreach (flashes() as $f): ?><div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
      <?= $content ?>
    </div>
  </div>
</div>
<script src="<?= e(asset_v('js/admin.js')) ?>" defer></script>
</body></html>
