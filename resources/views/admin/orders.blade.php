<?php
use App\Services\Orders;
/** @var array $rows @var string $q @var string $st @var string $pay @var int $page @var int $pages @var int $total @var array $counts @var array $carriers */
$exportQs = http_build_query(array_filter(['q' => $q, 'status' => $st, 'pay' => $pay, 'source' => request('source')]));
?>
<div class="toolbar">
  <div class="tabs" style="margin:0;border:0;padding:0">
    <a href="<?= e(url('admin/orders')) ?>" class="<?= $st === '' ? 'on' : '' ?>">All <em><?= array_sum($counts) ?></em></a>
    <?php foreach (Orders::STATUSES as $k => $l): ?><a href="<?= e(url('admin/orders?status=' . $k)) ?>" class="<?= $st === $k ? 'on' : '' ?>"><?= e($l) ?> <em><?= (int) ($counts[$k] ?? 0) ?></em></a><?php endforeach; ?>
  </div>
  <div class="row-actions"><a class="btn ghost sm" href="<?= e(url('admin/orders/export' . ($exportQs ? '?' . $exportQs : ''))) ?>">Export CSV</a><a class="btn" href="<?= e(url('admin/orders/new')) ?>">＋ Create order</a></div>
</div>
<form class="filters" method="get" style="margin-bottom:14px"><input type="hidden" name="status" value="<?= e($st) ?>">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Order #, email or name…">
  <select name="pay" onchange="this.form.submit()"><option value="">Any payment</option><option value="paid" <?= $pay === 'paid' ? 'selected' : '' ?>>Paid</option><option value="unpaid" <?= $pay === 'unpaid' ? 'selected' : '' ?>>Unpaid</option></select>
  <select name="source" onchange="this.form.submit()"><option value="">All sources</option><option value="admin" <?= request('source') === 'admin' ? 'selected' : '' ?>>Created by staff</option></select>
  <button class="btn sm ghost">Search</button></form>

<form method="post" action="<?= e(url('admin/orders/bulk')) ?>" data-bulk id="orders-form"><?= csrf_field() ?>
<?= partial('admin/_bulkbar', ['cats' => [], 'needs' => [], 'actions' => ['status_processing' => 'Set status: Processing', 'status_shipped' => 'Mark as shipped (emails customers)', 'status_completed' => 'Mark as completed', 'status_cancelled' => 'Cancel orders', 'paid' => 'Mark as paid & deliver', 'export' => 'Export selected to CSV', 'delete' => 'Delete (cancelled orders only)']]) ?>
<section class="apanel flush"><div class="tbl-wrap"><table class="tbl" id="orders-table">
<thead><tr><th class="chkc"><input type="checkbox" data-selall aria-label="Select all"></th><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th>Ship</th><th class="r">Total</th><th>Date</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $o):
  $meta = ['id' => $o['id'], 'number' => $o['number'], 'status' => $o['status'], 'paid' => $o['payment_status'] === 'paid', 'physical' => (bool) $o['has_physical'], 'carrier' => (string) $o['carrier'], 'tracking' => (string) $o['tracking'], 'tracking_url' => (string) $o['tracking_url'],
    'ship_name' => (string) $o['ship_name'], 'ship_address' => (string) $o['ship_address'], 'ship_address2' => (string) $o['ship_address2'], 'ship_city' => (string) $o['ship_city'], 'ship_state' => (string) $o['ship_state'], 'ship_zip' => (string) $o['ship_zip'], 'ship_country' => (string) $o['ship_country'], 'name' => $o['name']]; ?>
<tr data-id="<?= (int) $o['id'] ?>" data-order="<?= e(json_encode($meta, JSON_UNESCAPED_UNICODE)) ?>">
<td class="chkc"><input type="checkbox" name="ids[]" value="<?= (int) $o['id'] ?>" class="rowchk" aria-label="Select <?= e($o['number']) ?>"></td>
<td><a href="<?= e(url('admin/orders/' . $o['id'])) ?>"><b><?= e($o['number']) ?></b></a><?php if (($o['source'] ?? '') === 'admin'): ?> <span class="badge" title="Created by staff">staff</span><?php endif; ?></td>
<td><?= e($o['name']) ?><small><?= e($o['email']) ?></small></td>
<td><span class="badge s-<?= e($o['status']) ?> js-status"><?= e(Orders::STATUSES[$o['status']] ?? $o['status']) ?></span></td>
<td><span class="badge s-<?= e($o['payment_status']) ?> js-pay"><?= e($o['payment_status']) ?></span><small><?= e($o['payment_method']) ?></small></td>
<td class="js-ship"><?= $o['has_physical'] ? ($o['tracking'] ? '📦 ' . e($o['carrier'] ?: 'Tracking') . '<small>' . e($o['tracking']) . '</small>' : '📦<small>no tracking</small>') : '⚡' ?></td>
<td class="r"><b><?= money($o['total']) ?></b></td><td class="muted"><?= e(fmt_date($o['created_at'])) ?></td>
<td class="r"><button type="button" class="qa-btn" data-qa aria-haspopup="true" aria-label="Quick actions for <?= e($o['number']) ?>">⋯</button></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="9" class="empty">No orders match. <a href="<?= e(url('admin/orders/new')) ?>">Create one</a> for a phone or in-store sale.</td></tr><?php endif; ?>
</tbody></table></div></section></form>
<?= partial('admin/_pager', compact('page', 'pages')) ?>
<script>window.ORDERS26 = <?= json_encode(['quick' => url('admin/orders'), 'carriers' => $carriers, 'statuses' => Orders::STATUSES], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<script src="<?= e(asset_v('js/admin-orders26.js')) ?>" defer></script>
