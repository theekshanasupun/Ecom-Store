<?php /** @var array $rows @var int $page @var int $pages @var int $total @var array $tiers @var array $rewards @var $tierMap */
$q = request()->query();
?>
<form class="filters" method="get" style="flex-wrap:wrap;margin-bottom:12px">
  <input type="search" name="q" value="<?= e($q['q'] ?? '') ?>" placeholder="Name, email, phone or loyalty ID">
  <select name="tier"><option value="">All tiers</option><?php foreach ($tiers as $t): ?><option value="<?= (int) $t->id ?>" <?= ($q['tier'] ?? '') == $t->id ? 'selected' : '' ?>><?= e($t->name) ?></option><?php endforeach; ?></select>
  <select name="status"><option value="">Any status</option><option value="active" <?= ($q['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option><option value="suspended" <?= ($q['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option></select>
  <select name="buyers"><option value="">Purchase activity</option><option value="yes" <?= ($q['buyers'] ?? '') === 'yes' ? 'selected' : '' ?>>Has purchased</option><option value="no" <?= ($q['buyers'] ?? '') === 'no' ? 'selected' : '' ?>>Never purchased</option></select>
  <input type="number" name="min_points" value="<?= e($q['min_points'] ?? '') ?>" placeholder="Min points" style="width:110px"><input type="number" name="max_points" value="<?= e($q['max_points'] ?? '') ?>" placeholder="Max points" style="width:110px">
  <input type="date" name="joined_from" value="<?= e($q['joined_from'] ?? '') ?>" title="Joined from"><input type="date" name="joined_to" value="<?= e($q['joined_to'] ?? '') ?>" title="Joined to">
  <button class="btn sm ghost">Filter</button><a class="btn sm ghost" href="<?= e(url('admin/loyalty/members?' . http_build_query($q + ['export' => 'csv']))) ?>">CSV</a><a class="btn sm ghost" href="<?= e(url('admin/loyalty/members?' . http_build_query($q + ['export' => 'xls']))) ?>">Excel</a></form>
<p class="muted small"><?= number_format($total) ?> member(s)</p>
<form method="post" action="<?= e(url('admin/loyalty/bulk')) ?>" id="bulk-form"><?= csrf_field() ?>
<section class="apanel flush"><div class="tbl-wrap"><table class="tbl"><thead><tr><?php if (auth()->user()->isAdmin()): ?><th><input type="checkbox" onclick="document.querySelectorAll('.bsel').forEach(c=>c.checked=this.checked)" aria-label="Select all"></th><?php endif; ?><th>Member</th><th>Tier</th><th class="r">Points</th><th class="r">Cashback</th><th class="r">Store credit</th><th>Status</th><th>Joined</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $u): $t = $tierMap[$u->tier_id] ?? null; ?><tr>
<?php if (auth()->user()->isAdmin()): ?><td><input class="bsel" type="checkbox" name="ids[]" value="<?= (int) $u->id ?>"></td><?php endif; ?>
<td><b><a href="<?= e(url('admin/loyalty/members/' . $u->id)) ?>"><?= e($u->name) ?></a></b><small><?= e($u->email) ?> · <?= e($u->loyalty_id) ?></small></td>
<td><?= $t ? '<span class="pill" style="border-color:' . e($t->color) . '">' . e($t->name) . '</span>' : '—' ?></td><td class="r"><b><?= number_format((int) $u->points) ?></b></td><td class="r"><?= money($u->cashback) ?></td><td class="r"><?= money($u->credit) ?></td>
<td><span class="pill <?= $u->loyalty_status === 'active' ? 'ok' : '' ?>"><?= e($u->loyalty_status) ?></span></td><td class="muted"><?= e(fmt_date($u->loyalty_joined_at, false)) ?></td><td class="r"><a class="btn sm ghost" href="<?= e(url('admin/loyalty/members/' . $u->id)) ?>">Open</a></td></tr>
<?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="9" class="empty">No members match.</td></tr><?php endif; ?></tbody></table></div></section>
<?php if (auth()->user()->isAdmin()): ?>
<section class="apanel"><h2>Bulk actions on selected members</h2><div class="g3">
  <?= field_select('act', 'Action', ['award' => 'Award points', 'deduct' => 'Deduct points', 'reward' => 'Assign a reward'], 'award') ?>
  <?= field_text('amount', 'Points', '', ['type' => 'number', 'min' => '1']) ?><?= field_select('reward_id', 'Reward (for "assign")', ['' => '—'] + array_column(array_map(fn ($r) => (array) $r, $rewards), 'name', 'id'), '') ?></div>
  <?= field_text('reason', 'Reason (required for points)', '', ['placeholder' => 'Campaign / goodwill…']) ?><button class="btn" data-confirm="Apply this action to all selected members?">Apply to selected</button></section>
<?php endif; ?>
</form>
<?= partial('admin/_pager', compact('page', 'pages')) ?>
