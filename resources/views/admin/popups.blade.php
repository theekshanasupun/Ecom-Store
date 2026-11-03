<?php /** @var array $rows */ ?>
<div class="toolbar"><label class="chk" style="margin:0"><span class="muted">Popups are <?= setting('popups_on') === '1' ? 'enabled' : 'switched off' ?> sitewide — change under <a href="<?= e(url('admin/settings?tab=bars')) ?>">Settings → Banners</a>.</span></label><a class="btn" href="<?= e(url('admin/popups/new')) ?>">+ New popup</a></div>
<div class="quick" style="margin-bottom:12px"><input type="search" data-tablefilter="#popup-table" placeholder="Filter popups…" aria-label="Filter popups"></div>
<section class="apanel flush"><div class="tbl-wrap"><table class="tbl" id="popup-table">
<thead><tr><th>Popup</th><th>Type</th><th>Trigger</th><th>Shows on</th><th class="r">Views</th><th class="r">Conversions</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $p): $rate = $p['views'] ? round($p['conversions'] / $p['views'] * 100, 1) : 0;
  $trig = ['delay' => 'After ' . $p['trigger_value'] . 's', 'exit' => 'Exit intent', 'scroll' => $p['trigger_value'] . '% scroll', 'click' => 'Link click (#popup-' . $p['id'] . ')', 'load' => 'Page load'][$p['trigger']] ?? $p['trigger']; ?>
<tr><td><a href="<?= e(url('admin/popups/' . $p['id'])) ?>"><b><?= e($p['name']) ?></b></a><small><?= e($p['title']) ?></small></td>
<td><?= e(ucfirst($p['layout'])) ?><?= $p['capture_email'] ? ' <span class="badge">email</span>' : '' ?></td><td><?= e($trig) ?></td><td><?= e(ucfirst($p['pages'])) ?> · <?= e($p['audience']) ?></td>
<td class="r"><?= (int) $p['views'] ?></td><td class="r"><?= (int) $p['conversions'] ?> <small><?= $rate ?>%</small></td>
<td><span class="badge s-<?= $p['status'] === 'active' ? 'completed' : 'pending' ?>"><?= e($p['status']) ?></span></td>
<td class="r"><div class="row-actions" style="justify-content:flex-end">
  <form method="post" action="<?= e(url('admin/popups/' . $p['id'] . '/toggle')) ?>"><?= csrf_field() ?><button class="btn sm ghost"><?= $p['status'] === 'active' ? 'Pause' : 'Publish' ?></button></form>
  <form method="post" action="<?= e(url('admin/popups/' . $p['id'] . '/duplicate')) ?>"><?= csrf_field() ?><button class="btn sm ghost">Duplicate</button></form>
  <form method="post" action="<?= e(url('admin/popups/' . $p['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this popup?')"><?= csrf_field() ?><button class="btn sm ghost danger">Delete</button></form></div></td></tr>
<?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="8" class="muted">No popups yet. Create a welcome offer, an exit-intent discount or a sale announcement.</td></tr><?php endif; ?>
</tbody></table></div></section>
