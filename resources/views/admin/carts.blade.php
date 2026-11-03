<?php /** @var array $rows @var string $status @var int $page @var int $pages */ ?>
<div class="tabs"><?php foreach (['' => 'All', 'open' => 'Open (abandoned)', 'recovered' => 'Recovered', 'ordered' => 'Converted'] as $k => $l): ?><a href="<?= e(url('admin/carts' . ($k ? '?status=' . $k : ''))) ?>" class="<?= $status === $k ? 'on' : '' ?>"><?= e($l) ?></a><?php endforeach; ?></div>
<section class="apanel flush"><div class="tbl-wrap"><table class="tbl">
<thead><tr><th>Customer</th><th>Items</th><th class="r">Value</th><th>Last activity</th><th class="r">Reminders</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $c): $names = []; foreach (($c->lines()['lines'] ?? []) as $l) { $pr = \App\Models\Product::find($l['pid'] ?? 0); if ($pr) $names[] = $pr->name . ' ×' . (int) $l['qty']; } ?>
<tr><td><b><?= e($c['name'] ?: '—') ?></b><small><?= e($c['email']) ?></small></td><td class="small"><?= e(str_limit(implode(', ', $names), 90)) ?></td><td class="r"><b><?= money($c['total']) ?></b></td>
<td class="muted"><?= e(time_ago($c['last_activity_at'])) ?></td><td class="r"><?= (int) $c['reminders'] ?><?= $c['last_reminder_at'] ? '<small>' . e(time_ago($c['last_reminder_at'])) . '</small>' : '' ?></td>
<td><span class="badge s-<?= $c['status'] === 'open' ? 'pending' : 'completed' ?>"><?= e($c['status']) ?></span></td>
<td class="r"><div class="row-actions" style="justify-content:flex-end">
<?php if ($c['status'] === 'open'): ?><form method="post" action="<?= e(url('admin/carts/' . $c['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="do" value="remind"><button class="btn sm ghost">Send reminder</button></form><?php endif; ?>
<form method="post" action="<?= e(url('admin/carts/' . $c['id'])) ?>" onsubmit="return confirm('Remove this cart?')"><?= csrf_field() ?><input type="hidden" name="do" value="delete"><button class="btn sm ghost danger">✕</button></form></div></td></tr>
<?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="7" class="muted">No saved carts yet. Carts appear here once a visitor with a known email adds something to their cart.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?= partial('admin/_pager', compact('page', 'pages')) ?>
