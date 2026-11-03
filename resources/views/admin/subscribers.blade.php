<?php /** @var array $rows @var string $q @var int $total @var int $page @var int $pages */ ?>
<div class="toolbar"><form class="filters" method="get"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search email or name…"><button class="btn sm ghost">Search</button><span class="muted"><?= (int) $total ?> contacts</span></form>
  <div class="row-actions"><a class="btn ghost" href="<?= e(url('admin/subscribers/export')) ?>">Export CSV</a><button type="button" class="btn" data-drawer-open="#sub-drawer">＋ Add contacts</button></div></div>
<form method="post" action="<?= e(url('admin/subscribers/bulk')) ?>" data-bulk><?= csrf_field() ?>
<?= partial('admin/_bulkbar', ['cats' => [], 'needs' => [], 'actions' => ['subscribe' => 'Mark subscribed', 'unsubscribe' => 'Mark unsubscribed', 'delete' => 'Delete']]) ?>
<section class="apanel flush"><div class="tbl-wrap"><table class="tbl"><thead><tr><th class="chkc"><input type="checkbox" data-selall aria-label="Select all"></th><th>Email</th><th>Source</th><th>Status</th><th>Joined</th></tr></thead><tbody>
<?php foreach ($rows as $s): ?><tr><td class="chkc"><input type="checkbox" name="ids[]" value="<?= (int) $s['id'] ?>" class="rowchk" aria-label="Select"></td><td><b><?= e($s['email']) ?></b><small><?= e($s['name']) ?></small></td><td class="muted"><?= e($s['source']) ?></td>
<td><span class="badge s-<?= $s['status'] === 'subscribed' ? 'completed' : 'pending' ?>"><?= e($s['status']) ?></span></td><td class="muted"><?= e(fmt_date($s['created_at'], false)) ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="muted">No subscribers yet. Add a popup with “Collect an email” or an Email-signup block in the page builder.</td></tr><?php endif; ?>
</tbody></table></div></section></form>
<?= partial('admin/_pager', compact('page', 'pages')) ?>

<div class="drawer-panel" id="sub-drawer" hidden <?= request()->boolean('add') ? 'data-autoopen' : '' ?>>
<form method="post" action="<?= e(url('admin/subscribers/add')) ?>"><?= csrf_field() ?>
<div class="dp-head"><h2>Add contacts</h2><button type="button" data-drawer-close aria-label="Close">✕</button></div>
<div class="dp-body"><?= field_area('emails', 'Email addresses', '', ['rows' => 9, 'placeholder' => "one@example.com\ntwo@example.com", 'hint' => 'One per line or comma-separated. Only add people who agreed to hear from you.']) ?></div>
<div class="dp-foot"><span class="grow"></span><button type="button" class="btn ghost" data-drawer-close>Cancel</button><button class="btn">Add contacts</button></div></form></div>
