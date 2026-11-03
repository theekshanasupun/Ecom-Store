<?php /** @var array $checks @var array $dirs @var array $backups @var array $history @var array $info */ ?>
<div class="cols">
<div class="stack wide">
  <section class="apanel updbox">
    <div class="ph"><h2>System update</h2><span class="badge s-completed">Installed v<?= e($info['version']) ?></span></div>
    <p class="muted">Upload the <b>update.zip</b> you received. KIOSK will back up the files it replaces, apply the update, run any database migrations and then <b>delete the zip automatically</b>. Your uploads, settings and database content are never touched.</p>
    <form method="post" action="<?= e(url('admin/system/update')) ?>" enctype="multipart/form-data" class="dropzone" id="dz"><?= csrf_field() ?>
      <input type="file" name="package" accept=".zip" id="pkg" required>
      <div><b id="dz-name">Drop update.zip here</b><small>or click to browse · max <?= e($info['upload_max']) ?></small></div>
      <button class="btn" id="dz-go" disabled>Install update</button>
    </form>
    <p class="muted small">Tip: take a <b>database backup</b> first. Updates are applied instantly; the store shows a short “updating” notice while files are replaced.</p>
  </section>

  <section class="apanel"><h2>Update history</h2>
    <?php if (!$history): ?><p class="muted">No updates applied yet.</p><?php endif; ?>
    <ul class="timeline"><?php foreach ($history as $h): ?><li><span><b>v<?= e($h['version']) ?></b> <span class="muted">from v<?= e($h['from']) ?></span><?= $h['notes'] ? '<br>' . e($h['notes']) : '' ?></span><small><?= e(fmt_date($h['at'])) ?></small></li><?php endforeach; ?></ul>
  </section>

  <section class="apanel"><div class="ph"><h2>Backups</h2>
    <div class="row-actions"><form method="post" action="<?= e(url('admin/system/backup')) ?>"><?= csrf_field() ?><button class="btn sm">Create database backup</button></form></div></div>
    <?php if (!$backups): ?><p class="muted">No backups yet.</p><?php endif; ?>
    <ul class="list"><?php foreach ($backups as $b): ?><li><a href="<?= e(url('admin/system/backups/' . $b['name'])) ?>"><?= e($b['name']) ?></a><span class="muted"><?= e(round($b['size'] / 1024)) ?> KB · <?= e($b['time']) ?></span></li><?php endforeach; ?></ul>
  </section>
</div>

<div class="stack">
  <section class="apanel"><h2>Server</h2>
    <dl class="kv"><?php foreach (['version' => 'KIOSK', 'php' => 'PHP', 'db' => 'Database', 'server' => 'Web server', 'upload_max' => 'Upload limit', 'post_max' => 'POST limit', 'memory' => 'Memory', 'disk_free' => 'Disk free'] as $k => $l): ?><div><dt><?= $l ?></dt><dd><?= e($info[$k]) ?></dd></div><?php endforeach; ?></dl>
    <form method="post" action="<?= e(url('admin/system/cache')) ?>"><?= csrf_field() ?><button class="btn sm ghost block">Clear cache</button></form>
  </section>
  <section class="apanel"><h2>Extensions</h2><div class="chipset"><?php foreach ($checks as $k => $ok): ?><span class="badge <?= $ok ? 's-completed' : 's-cancelled' ?>"><?= $ok ? '✓' : '✕' ?> <?= e($k) ?></span><?php endforeach; ?></div></section>
  <section class="apanel"><h2>Permissions</h2><div class="chipset"><?php foreach ($dirs as $k => $ok): ?><span class="badge <?= $ok ? 's-completed' : 's-cancelled' ?>"><?= $ok ? '✓' : '✕' ?> /<?= e($k) ?></span><?php endforeach; ?></div>
  <p class="muted small">Red items must be writable by the PHP user for uploads and updates.</p></section>
</div>
</div>
