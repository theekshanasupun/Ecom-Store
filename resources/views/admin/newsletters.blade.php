<?php /** @var array $rows @var int $subs @var int $sent30 */
$badge = ['draft' => 'pending', 'scheduled' => 'processing', 'sending' => 'processing', 'sent' => 'completed']; ?>
<div class="kpis">
  <div class="kpi"><b><?= (int) $subs ?></b><small>Active subscribers</small></div>
  <div class="kpi"><b><?= count($rows) ?></b><small>Newsletters</small></div>
  <div class="kpi"><b><?= (int) $sent30 ?></b><small>Newsletter emails (30 days)</small></div>
</div>
<div class="toolbar"><div><span class="muted">Design with the drag &amp; drop builder, send now or schedule it to a segment.</span></div>
  <div class="row-actions"><a class="btn ghost" href="<?= e(url('admin/subscribers')) ?>">Subscribers</a><a class="btn" href="<?= e(url('admin/newsletters/new')) ?>">+ New newsletter</a></div></div>
<section class="apanel flush"><div class="tbl-wrap"><table class="tbl">
<thead><tr><th>Newsletter</th><th>Segment</th><th>Status</th><th class="r">Recipients</th><th class="r">Sent</th><th>When</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $n): ?>
<tr><td><b><a href="<?= e(url('admin/newsletters/' . $n['id'])) ?>"><?= e($n['name']) ?></a></b><small><?= e($n['subject']) ?></small></td>
<td class="muted small"><?= e(\App\Models\Newsletter::SEGMENTS[$n['segment']] ?? $n['segment']) ?></td>
<td><span class="badge s-<?= e($badge[$n['status']] ?? 'pending') ?>"><?= e($n['status']) ?></span></td>
<td class="r"><?= (int) $n['recipients'] ?></td><td class="r"><?= (int) $n['sent'] ?></td>
<td class="muted"><?= $n['status'] === 'sent' ? e(fmt_date($n['sent_at'])) : ($n['scheduled_at'] ? e(fmt_date($n['scheduled_at'])) : '—') ?></td>
<td class="r"><a class="btn sm ghost" href="<?= e(url('admin/newsletters/' . $n['id'])) ?>"><?= in_array($n['status'], ['sent', 'sending']) ? 'View' : 'Edit' ?></a></td></tr>
<?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="7" class="muted">No newsletters yet — create your first one.</td></tr><?php endif; ?>
</tbody></table></div></section>
