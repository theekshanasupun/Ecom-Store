<?php $x = $edit ?? null; ?>
<div class="cols">
<section class="apanel wide flush"><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><b><?= e($r['name']) ?></b><small><?= e($r['email']) ?></small></td><td><span class="badge"><?= e($r['role']) ?></span></td><td><span class="badge s-<?= $r['status'] === 'active' ? 'completed' : 'pending' ?>"><?= e($r['status']) ?></span></td><td class="muted"><?= e(time_ago($r['last_login_at'])) ?></td><td class="r"><a class="btn sm ghost" href="<?= e(url('admin/users?edit=' . $r['id'])) ?>">Edit</a></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="apanel"><h2><?= $x ? 'Edit member' : 'Add team member' ?></h2>
<form method="post" action="<?= e(url('admin/users/save')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($x['id'] ?? 0) ?>">
<?= field_text('name', 'Name', $x['name'] ?? '', ['required' => true]) ?>
<?= field_text('email', 'Email', $x['email'] ?? '', ['type' => 'email', 'required' => true]) ?>
<?= field_text('password', $x ? 'New password (leave blank to keep)' : 'Password (8+ chars)', '', ['type' => 'password', 'autocomplete' => 'new-password']) ?>
<?= field_select('role', 'Role', ['staff' => 'Staff — orders, products, customers', 'admin' => 'Admin — everything'], $x['role'] ?? 'staff') ?>
<?= field_select('status', 'Status', ['active' => 'Active', 'disabled' => 'Disabled'], $x['status'] ?? 'active') ?>
<button class="btn block">Save</button></form>
<?php if ($x && (int) $x['id'] !== auth()->id()): ?><form method="post" action="<?= e(url('admin/users/' . $x['id'] . '/delete')) ?>" onsubmit="return confirm('Remove this person?')" style="margin-top:10px"><?= csrf_field() ?><button class="btn sm ghost danger">Remove</button> <a class="btn sm ghost" href="<?= e(url('admin/users')) ?>">Cancel</a></form><?php endif; ?>
</section></div>
