<?php
use App\Services\Orders;
/** @var array $rows @var string $q @var string $st @var string $pay @var int $page @var int $pages @var int $total @var array $counts */
?>
<div class="tabs">
  <a href="<?= e(url('admin/orders')) ?>" class="<?= $st === '' ? 'on' : '' ?>">All <em><?= array_sum($counts) ?></em></a>
  <?php foreach (Orders::STATUSES as $k => $l): ?><a href="<?= e(url('admin/orders?status=' . $k)) ?>" class="<?= $st === $k ? 'on' : '' ?>"><?= e($l) ?> <em><?= (int) ($counts[$k] ?? 0) ?></em></a><?php endforeach; ?>
</div>
<form class="filters" method="get" style="margin-bottom:14px"><input type="hidden" name="status" value="<?= e($st) ?>">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Order #, email or name…">
  <select name="pay" onchange="this.form.submit()"><option value="">Any payment</option><option value="paid" <?= $pay === 'paid' ? 'selected' : '' ?>>Paid</option><option value="unpaid" <?= $pay === 'unpaid' ? 'selected' : '' ?>>Unpaid</option></select>
  <button class="btn sm ghost">Search</button></form>
<section class="apanel flush"><div class="tbl-wrap"><table class="tbl">
<thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th>Items</th><th class="r">Total</th><th>Date</th></tr></thead><tbody>
<?php foreach ($rows as $o): ?>
<tr><td><a href="<?= e(url('admin/orders/' . $o['id'])) ?>"><b><?= e($o['number']) ?></b></a></td>
<td><?= e($o['name']) ?><small><?= e($o['email']) ?></small></td>
<td><span class="badge s-<?= e($o['status']) ?>"><?= e(Orders::STATUSES[$o['status']] ?? $o['status']) ?></span></td>
<td><span class="badge s-<?= e($o['payment_status']) ?>"><?= e($o['payment_status']) ?></span><small><?= e($o['payment_method']) ?></small></td>
<td><?= $o['has_physical'] ? '📦' : '⚡' ?></td>
<td class="r"><b><?= money($o['total']) ?></b></td><td class="muted"><?= e(fmt_date($o['created_at'])) ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="7" class="muted">No orders match.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?= partial('admin/_pager', compact('page', 'pages')) ?>
