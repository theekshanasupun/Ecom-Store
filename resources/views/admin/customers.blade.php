<?php /** @var array $rows @var string $q @var int $total @var int $page @var int $pages */ $adding = request()->boolean('add'); ?>
<div class="toolbar">
  <form class="filters" method="get"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email or phone…"><button class="btn sm ghost">Search</button><span class="muted"><?= (int) $total ?> customers</span></form>
  <div class="row-actions"><a class="btn ghost" href="<?= e(url('admin/orders/new')) ?>">Create order</a><button type="button" class="btn" data-drawer-open="#cust-drawer">＋ Add customer</button></div>
</div>



<form method="post" action="<?= e(url('admin/customers/bulk')) ?>" data-bulk><?= csrf_field() ?>
<?= partial('admin/_bulkbar', ['cats' => [], 'needs' => [], 'actions' => ['subscribe' => 'Add to mailing list', 'suspend' => 'Suspend login', 'activate' => 'Re-activate', 'delete' => 'Delete (orders are kept)']]) ?>
<section class="apanel flush"><div class="tbl-wrap"><table class="tbl">
<thead><tr><th class="chkc"><input type="checkbox" data-selall aria-label="Select all"></th><th>Customer</th><th>Phone</th><th class="r">Orders</th><th class="r">Spent</th><th>Status</th><th>Joined</th><th>Last login</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $u): ?>
<tr><td class="chkc"><input type="checkbox" name="ids[]" value="<?= (int) $u['id'] ?>" class="rowchk" aria-label="Select <?= e($u['name']) ?>"></td>
<td><b><?= e($u['name']) ?></b><small><?= e($u['email']) ?></small></td><td><?= e($u['phone']) ?></td>
<td class="r"><a href="<?= e(url('admin/orders?q=' . urlencode($u['email']))) ?>"><?= (int) $u['orders_n'] ?></a></td>
<td class="r"><b><?= money($u['spent'] ?? 0) ?></b></td>
<td><span class="badge s-<?= $u['status'] === 'active' ? 'completed' : 'pending' ?>"><?= e($u['status']) ?></span></td>
<td class="muted"><?= e(fmt_date($u['created_at'], false)) ?></td><td class="muted"><?= e(time_ago($u['last_login_at'])) ?></td><td class="r"><a class="btn sm ghost" href="<?= e(url('admin/orders/new?customer=' . $u['id'])) ?>">Create order</a></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="9" class="muted">No customer accounts yet. Guest orders appear under Orders.</td></tr><?php endif; ?>
</tbody></table></div></section>
</form>
<?= partial('admin/_pager', compact('page', 'pages')) ?>
<div class="drawer-panel" id="cust-drawer" hidden <?= $adding ? 'data-autoopen' : '' ?>>
<form method="post" action="<?= e(url('admin/customers/save')) ?>"><?= csrf_field() ?>
  <div class="dp-head"><h2>Add customer</h2><button type="button" data-drawer-close aria-label="Close">✕</button></div>
  <div class="dp-body">
    <?= field_text('name', 'Full name', old('name'), ['required' => true, 'autocomplete' => 'off']) ?><?= field_text('email', 'Email', old('email'), ['type' => 'email', 'required' => true, 'autocomplete' => 'off']) ?><?= field_text('phone', 'Phone (optional)', old('phone')) ?>
    <?= field_text('password', 'Password', '', ['hint' => 'Leave empty to generate a strong password automatically.', 'autocomplete' => 'new-password']) ?>
    <?= field_check('send_email', 'Email the login details to the customer', true) ?>
    <?= field_check('subscribe', 'Add to the mailing list (they consent to marketing emails)', false) ?>
    <p class="muted small">Past guest orders placed with the same email are linked to the new account automatically.</p>
  </div>
  <div class="dp-foot"><span class="grow"></span><button type="button" class="btn ghost" data-drawer-close>Cancel</button><button class="btn">Create customer</button></div>
</form></div>
