<?php
use App\Services\Orders;
/** @var array $stats @var array $series @var array $recent @var array $top @var array $low */
$max = max(1, ...array_column($series, 'total'));
?>
<div class="stats">
  <div class="stat hi"><small>Today</small><b><?= money($stats['today']) ?></b></div>
  <div class="stat"><small>Last 7 days</small><b><?= money($stats['week']) ?></b></div>
  <div class="stat"><small>Last 30 days</small><b><?= money($stats['month']) ?></b></div>
  <div class="stat"><small>All time</small><b><?= money($stats['all']) ?></b></div>
</div>
<div class="stats s4">
  <a class="stat link" href="<?= e(url('admin/orders?status=processing')) ?>"><small>To process</small><b><?= $stats['to_process'] ?></b></a>
  <a class="stat link" href="<?= e(url('admin/orders')) ?>"><small>Orders</small><b><?= $stats['orders'] ?></b></a>
  <a class="stat link" href="<?= e(url('admin/customers')) ?>"><small>Customers</small><b><?= $stats['customers'] ?></b></a>
  <a class="stat link" href="<?= e(url('admin/products')) ?>"><small>Live products</small><b><?= $stats['products'] ?></b></a>
</div>

<div class="cols">
  <section class="apanel wide">
    <h2>Revenue · 14 days</h2>
    <div class="chart" role="img" aria-label="Revenue chart">
      <?php foreach ($series as $s): $h = $s['total'] ? max(4, round($s['total'] / $max * 100)) : 2; ?>
      <div class="col" title="<?= e($s['label'] . ': ' . money($s['total']) . ' · ' . $s['n'] . ' orders') ?>"><i style="height:<?= $h ?>%"></i><span><?= e(substr($s['label'], -2) === $s['label'] ? $s['label'] : explode(' ', $s['label'])[1]) ?></span></div>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="apanel">
    <h2>Needs attention</h2>
    <?php if (!$low): ?><p class="muted">Stock levels look healthy ✓</p><?php endif; ?>
    <ul class="list"><?php foreach ($low as $r): ?><li><a href="<?= e(url('admin/products/' . $r['id'])) ?>"><?= e($r['name']) ?></a><span class="badge <?= $r['left'] === 0 ? 'bad' : 'warn' ?>"><?= $r['left'] === 0 ? 'Out' : $r['left'] . ' left' ?><?= in_array($r['type'], ['key', 'account']) ? ' (keys)' : '' ?></span></li><?php endforeach; ?></ul>
    <h2 style="margin-top:22px">Best sellers</h2>
    <ul class="list"><?php foreach ($top as $r): ?><li><a href="<?= e(url('admin/products/' . $r['id'])) ?>"><?= e($r['name']) ?></a><span class="muted"><?= (int) $r['sales'] ?> sold</span></li><?php endforeach; ?><?php if (!$top): ?><li class="muted">No sales yet</li><?php endif; ?></ul>
  </section>
</div>

<section class="apanel">
  <div class="ph"><h2>Recent orders</h2><a class="btn sm ghost" href="<?= e(url('admin/orders')) ?>">All orders</a></div>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th class="r">Total</th><th>When</th></tr></thead>
    <tbody>
    <?php foreach ($recent as $o): ?>
    <tr><td><a href="<?= e(url('admin/orders/' . $o['id'])) ?>"><b><?= e($o['number']) ?></b></a></td><td><?= e($o['name']) ?><small><?= e($o['email']) ?></small></td>
      <td><span class="badge s-<?= e($o['status']) ?>"><?= e(Orders::STATUSES[$o['status']] ?? $o['status']) ?></span></td>
      <td><span class="badge s-<?= e($o['payment_status']) ?>"><?= e($o['payment_status']) ?></span> <small><?= e($o['payment_method']) ?></small></td>
      <td class="r"><b><?= money($o['total']) ?></b></td><td class="muted"><?= e(time_ago($o['created_at'])) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$recent): ?><tr><td colspan="6" class="muted">No orders yet. They will appear here.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
