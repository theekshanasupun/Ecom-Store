<?php
$u = auth()->user();
$path = '/' . ltrim(request()->path(), '/');
$name = (string) setting('store_name');
$toProcess = \App\Models\Order::whereIn('status', ['pending', 'processing'])->where('payment_status', 'paid')->count();
$ic = [
    'home' => 'M3 12 12 3l9 9M5 10v10h14V10',
    'orders' => 'M6 2 3 6v14h18V6l-3-4zM3 6h18M16 10a4 4 0 0 1-8 0',
    'plus' => 'M12 5v14M5 12h14',
    'users' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8M22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
    'cart' => 'M6 6h15l-2 9H8zM6 6 5 3H2M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2M18 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2',
    'box' => 'M21 8 12 3 3 8v8l9 5 9-5zM3 8l9 5 9-5M12 13v8',
    'grid' => 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
    'gift' => 'M20 12v8H4v-8M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z',
    'tag' => 'M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8zM7 7h.01',
    'layers' => 'M12 2 2 7l10 5 10-5zM2 17l10 5 10-5M2 12l10 5 10-5',
    'wallet' => 'M3 7h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM3 7V5a2 2 0 0 1 2-2h12v4M16 14h.01',
    'mail' => 'M4 4h16v16H4zM4 8l8 5 8-5',
    'star' => 'M12 2l3 7 7 .6-5.3 4.7 1.6 7.2L12 17.8 5.7 21.5l1.6-7.2L2 9.6 9 9z',
    'shield' => 'M12 2l8 3v6c0 5-3.5 9-8 11-4.5-2-8-6-8-11V5z',
    'chart' => 'M4 20V10M10 20V4M16 20v-8M22 20H2',
    'news' => 'M4 4h13v16H6a2 2 0 0 1-2-2zM17 8h3v10a2 2 0 0 1-2 2M8 8h5M8 12h5M8 16h3',
    'bell' => 'M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0',
    'popup' => 'M4 5h16v11H4zM8 20h8M12 16v4M9 10h6',
    'builder' => 'M3 3h18v6H3zM3 13h8v8H3zM15 13h6v8h-6z',
    'page' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h8',
    'cog' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z',
    'team' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1',
    'palette' => 'M12 3a9 9 0 1 0 0 18c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3-.3-.4-.5-.8-.5-1.3 0-1.1.9-2 2-2H17a4 4 0 0 0 4-4c0-4.4-4-8-9-8zM7.5 12a1 1 0 1 0 0-2 1 1 0 0 0 0 2M10 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2M15 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2',
    'sys' => 'M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3',
];
// group key => [label, icon, [[path, label, icon, adminOnly]...]]
$groups = [
    'overview' => ['Overview', 'home', [['admin', 'Dashboard', 'home', false]]],
    'sales' => ['Sales', 'orders', [['admin/orders', 'Orders', 'orders', false], ['admin/orders/new', 'Create order', 'plus', false], ['admin/customers', 'Customers', 'users', false], ['admin/carts', 'Abandoned carts', 'cart', false]]],
    'catalog' => ['Catalog', 'box', [['admin/products', 'Products', 'box', false], ['admin/categories', 'Departments', 'grid', false]]],
    'promo' => ['Promotions', 'tag', [['admin/coupons', 'Coupons', 'gift', false], ['admin/discounts', 'Discounts', 'tag', false], ['admin/offers', 'Upsell & bundles', 'layers', false], ['admin/gift-cards', 'Gift cards', 'gift', false], ['admin/credit', 'Store credit', 'wallet', false]]],
    'loyalty' => ['Loyalty & Rewards', 'star', [['admin/loyalty', 'Overview', 'chart', false], ['admin/loyalty/members', 'Members', 'users', false], ['admin/loyalty/rewards', 'Reward catalogue', 'gift', true], ['admin/loyalty/redemptions', 'Redemptions', 'tag', false], ['admin/loyalty/tiers', 'Tiers', 'layers', true],
        ['admin/loyalty/campaigns', 'Campaigns & rules', 'tag', true], ['admin/loyalty/challenges', 'Gamification', 'star', true], ['admin/loyalty/referrals', 'Referrals', 'users', false], ['admin/loyalty/workflows', 'Automation', 'mail', true],
        ['admin/loyalty/segments', 'Segments', 'users', false], ['admin/loyalty/reports', 'Reports', 'chart', false], ['admin/loyalty/security', 'Security & fraud', 'shield', false], ['admin/loyalty/settings', 'Programme settings', 'cog', true]]],
    'marketing' => ['Marketing', 'mail', [['admin/automations', 'Email automations', 'mail', false], ['admin/newsletters', 'Newsletters', 'news', false], ['admin/subscribers', 'Subscribers', 'bell', false], ['admin/popups', 'Popups', 'popup', false]]],
    'content' => ['Content', 'page', [['admin/builder/home', 'Page builder', 'builder', false], ['admin/pages', 'Pages', 'page', false], ['admin/themes', 'Themes', 'palette', true]]],
    'system' => ['System', 'cog', [['admin/settings', 'Settings', 'cog', true], ['admin/users', 'Team', 'team', true], ['admin/system', 'System & updates', 'sys', true]]],
];
$allPaths = [];
foreach ($groups as $g) foreach ($g[2] as $it) $allPaths[] = $it[0];
// the most specific matching nav path wins (e.g. admin/orders/new beats admin/orders)
$best = '';
foreach ($allPaths as $p) {
    $full = '/' . $p;
    $m = $p === 'admin' ? $path === '/admin' : ($path === $full || str_starts_with($path, $full . '/'));
    if ($m && strlen($p) > strlen($best)) $best = $p;
}
$active = fn (string $p) => $p === $best;
$activeGroup = '';
foreach ($groups as $gk => $g) foreach ($g[2] as $it) if ($it[0] === $best) $activeGroup = $gk;
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e((\App\Support\Meta::$data['title'] ?? 'Admin') . ' · ' . $name . ' Admin') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Mono:wght@400;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<?php if (setting('favicon')): ?><link rel="icon" href="<?= e(upload_url(setting('favicon'))) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset_v('css/admin.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_v('css/admin26.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_v('css/admin-commerce26.css')) ?>">
<style>:root{--accent:<?= e(setting('accent', '#c6ff3d')) ?>}</style>
</head><body>
<div class="adm" id="adm">
  <aside class="side" id="side" data-sidebar>
    <div class="side-top"><a class="brand" href="<?= e(url('admin')) ?>"><?= e(strtoupper($name)) ?><i>.</i><small>ADMIN</small></a>
      <button type="button" class="pin" id="pin" aria-pressed="false" title="Keep the menu open (otherwise it folds away when not in use)"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17v5M9 3h6l-1 7 3 3v2H7v-2l3-3z"/></svg></button></div>
    <nav aria-label="Admin">
      <?php foreach ($groups as $gk => $g):
        $items = array_values(array_filter($g[2], fn ($it) => !$it[3] || $u->isAdmin()));
        if (!$items) continue;
        $single = count($g[2]) === 1;
        $open = $gk === $activeGroup; ?>
      <?php if ($single): $it = $items[0]; ?>
      <a href="<?= e(url($it[0])) ?>" class="nv <?= $active($it[0]) ? 'on' : '' ?>" title="<?= e($it[1]) ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $ic[$it[2]] ?>"/></svg><span><?= e($it[1]) ?></span></a>
      <?php else: $gOrders = $gk === 'sales' && $toProcess; ?>
      <div class="ng <?= $open ? 'open' : '' ?>" data-group="<?= e($gk) ?>" data-active="<?= $open ? '1' : '0' ?>">
        <button type="button" class="ng-h <?= $open ? 'has-on' : '' ?>" aria-expanded="<?= $open ? 'true' : 'false' ?>" aria-controls="ng-<?= e($gk) ?>" title="<?= e($g[0]) ?>"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $ic[$g[1]] ?>"/></svg><span><?= e($g[0]) ?></span><?php if ($gOrders): ?><em><?= $toProcess ?></em><?php endif; ?><i class="chev" aria-hidden="true"></i></button>
        <div class="ng-b" id="ng-<?= e($gk) ?>"><div class="ng-in">
          <?php foreach ($items as $it): ?>
          <a href="<?= e(url($it[0])) ?>" class="nv <?= $active($it[0]) ? 'on' : '' ?>" title="<?= e($it[1]) ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="<?= $ic[$it[2]] ?>"/></svg><span><?= e($it[1]) ?></span><?php if ($it[0] === 'admin/orders' && $toProcess): ?><em><?= $toProcess ?></em><?php endif; ?></a>
          <?php endforeach; ?>
        </div></div>
      </div>
      <?php endif; endforeach; ?>
    </nav>
    <div class="side-foot"><a href="<?= e(url('')) ?>" target="_blank" rel="noopener">View store ↗</a><small>v<?= e(config('kiosk.version')) ?></small></div>
  </aside>
  <div class="main">
    <header class="top">
      <button class="menu" id="menu" aria-label="Menu">☰</button>
      <h1><?= e(\App\Support\Meta::$data['title'] ?? '') ?></h1>
      <div class="gsearch" id="gsearch" data-url="<?= e(url('admin/search.json')) ?>" role="search">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
        <input type="search" id="gs-input" placeholder="Search products, orders, customers…" autocomplete="off" aria-label="Search admin" aria-controls="gs-pop" aria-expanded="false"><kbd>Ctrl K</kbd>
        <div class="gs-pop" id="gs-pop" role="listbox" hidden></div>
      </div>
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
<script src="<?= e(asset_v('js/admin26.js')) ?>" defer></script>
<script src="<?= e(asset_v('js/admin-commerce26.js')) ?>" defer></script>
</body></html>
