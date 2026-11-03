<?php $x = $edit ?? null; $open = $x || request()->boolean('new'); ?>
<div class="toolbar"><div class="quick"><input type="search" data-tablefilter="#team-table" placeholder="Filter team…" aria-label="Filter team"><span class="muted small"><?= count($rows) ?> member(s)</span></div>
  <button type="button" class="btn" data-drawer-open="#team-drawer">＋ Add team member</button></div>
<section class="apanel flush"><div class="tbl-wrap"><table class="tbl" id="team-table"><thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><b><?= e($r['name']) ?></b><small><?= e($r['email']) ?></small></td><td><span class="badge"><?= e($r['role']) ?></span></td><td><span class="badge s-<?= $r['status'] === 'active' ? 'completed' : 'pending' ?>"><?= e($r['status']) ?></span></td><td class="muted"><?= e(time_ago($r['last_login_at'])) ?></td><td class="r"><a class="btn sm ghost" href="<?= e(url('admin/users?edit=' . $r['id'])) ?>">Edit</a></td></tr><?php endforeach; ?>
</tbody></table></div></section>

<div class="drawer-panel" id="team-drawer" hidden <?= $open ? 'data-autoopen' : '' ?>>
<form method="post" action="<?= e(url('admin/users/save')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($x['id'] ?? 0) ?>">
  <div class="dp-head"><h2><?= $x ? 'Edit member' : 'Add team member' ?></h2><button type="button" data-drawer-close aria-label="Close">✕</button></div>
  <div class="dp-body">
    <?= field_text('name', 'Name', $x['name'] ?? '', ['required' => true]) ?>
    <?= field_text('email', 'Email', $x['email'] ?? '', ['type' => 'email', 'required' => true]) ?>
    <?= field_text('password', $x ? 'New password (leave blank to keep)' : 'Password (8+ chars)', '', ['type' => 'password', 'autocomplete' => 'new-password']) ?>
    <?= field_select('role', 'Role', ['staff' => 'Staff — orders, products, customers', 'admin' => 'Admin — everything'], $x['role'] ?? 'staff') ?>
    <?= field_select('status', 'Status', ['active' => 'Active', 'disabled' => 'Disabled'], $x['status'] ?? 'active') ?>
  </div>
  <div class="dp-foot"><?php if ($x && (int) $x['id'] !== auth()->id()): ?><button type="submit" class="btn ghost danger grow" formaction="<?= e(url('admin/users/' . $x['id'] . '/delete')) ?>" formnovalidate data-confirm="Remove this person?">Remove</button><?php else: ?><span class="grow"></span><?php endif; ?>
    <button type="button" class="btn ghost" data-drawer-close>Cancel</button><button class="btn">Save</button></div>
</form></div>
