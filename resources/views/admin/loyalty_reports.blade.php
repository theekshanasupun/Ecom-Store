<?php /** @var string $key @var array $rep @var int $total @var int $page @var int $pages @var string $from @var string $to */
$qs = ['report' => $key, 'from' => $from, 'to' => $to];
?>
<form class="filters" method="get" style="flex-wrap:wrap;margin-bottom:12px"><select name="report" onchange="this.form.submit()"><?php foreach (\App\Services\LoyaltyReports::REPORTS as $k => $l): ?><option value="<?= e($k) ?>" <?= $k === $key ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <input type="date" name="from" value="<?= e($from) ?>"><input type="date" name="to" value="<?= e($to) ?>"><button class="btn sm ghost">Apply</button>
  <a class="btn sm" href="<?= e(url('admin/loyalty/reports?' . http_build_query($qs + ['export' => 'csv']))) ?>">Export CSV</a><a class="btn sm" href="<?= e(url('admin/loyalty/reports?' . http_build_query($qs + ['export' => 'xls']))) ?>">Export Excel</a></form>
<p class="muted small"><?= number_format($total) ?> row(s). <?= $key === 'roi' ? 'ROI = (member revenue − cost of redeemed points and cashback) ÷ cost.' : '' ?></p>
<section class="apanel flush"><div class="tbl-wrap"><table class="tbl"><thead><tr><?php foreach ($rep['cols'] as $c): ?><th><?= e($c) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($rep['rows'] as $r): ?><tr><?php foreach ($r as $v): ?><td><?= e(is_scalar($v) || $v === null ? (string) $v : json_encode($v)) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
<?php if (!$rep['rows']): ?><tr><td colspan="<?= max(1, count($rep['cols'])) ?>" class="empty">No data for this period.</td></tr><?php endif; ?></tbody></table></div></section>
<?= partial('admin/_pager', compact('page', 'pages')) ?>
