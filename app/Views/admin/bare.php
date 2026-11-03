<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Sign in · <?= e(setting('store_name')) ?> Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<style>:root{--accent:<?= e(setting('accent', '#c6ff3d')) ?>}</style>
</head><body class="bare">
<?php foreach (flashes() as $f): ?><div class="flash flash-<?= e($f['type']) ?>" style="max-width:420px;margin:16px auto"><?= e($f['msg']) ?></div><?php endforeach; ?>
<?= $content ?>
</body></html>
