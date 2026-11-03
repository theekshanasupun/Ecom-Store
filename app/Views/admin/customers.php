<form class="filters" method="get" style="margin-bottom:14px"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search customers…"><button class="btn sm ghost">Search</button><span class="muted"><?= (int) $total ?> customers</span></form>
<section class="apanel flush"><div class="tbl-wrap"><table class="tbl">
<thead><tr><th>Customer</th><th>Phone</th><th class="r">Orders</th><th class="r">Spent</th><th>Joined</th><th>Last login</th></tr></thead><tbody>
<?php foreach ($rows as $u): ?>
<tr><td><b><?= e($u['name']) ?></b><small><?= e($u['email']) ?></small></td><td><?= e($u['phone']) ?></td><td class="r"><a href="<?= e(url('admin/orders?q=' . urlencode($u['email']))) ?>"><?= (int) $u['orders_n'] ?></a></td><td class="r"><b><?= money($u['spent']) ?></b></td><td class="muted"><?= e(fmt_date($u['created_at'], false)) ?></td><td class="muted"><?= e(time_ago($u['last_login'])) ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6" class="muted">No customer accounts yet. Guest orders appear under Orders.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?= partial('admin/_pager', compact('page', 'pages')) ?>
