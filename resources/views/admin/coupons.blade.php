<?php $e0 = $edit ?? null; $v = fn($k, $d = '') => $e0[$k] ?? $d; ?>
<div class="cols">
<section class="apanel wide flush"><div class="tbl-wrap"><table class="tbl">
<thead><tr><th>Code</th><th>Discount</th><th>Min spend</th><th class="r">Used</th><th>Valid</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $c): ?>
<tr><td><b><?= e($c['code']) ?></b></td><td><?= $c['type'] === 'percent' ? (int) $c['value'] . '%' : money($c['value']) ?></td><td><?= $c['min_total'] ? money($c['min_total']) : '—' ?></td><td class="r"><?= (int) $c['used'] ?><?= $c['max_uses'] ? ' / ' . (int) $c['max_uses'] : '' ?></td>
<td class="muted"><?= $c['starts_at'] || $c['ends_at'] ? e(($c['starts_at'] ? substr($c['starts_at'], 0, 10) : '…') . ' → ' . ($c['ends_at'] ? substr($c['ends_at'], 0, 10) : '…')) : 'Always' ?></td>
<td><span class="badge s-<?= $c['status'] === 'active' ? 'completed' : 'pending' ?>"><?= e($c['status']) ?></span></td>
<td class="r"><a class="btn sm ghost" href="<?= e(url('admin/coupons?edit=' . $c['id'])) ?>">Edit</a></td></tr>
<?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="7" class="muted">No coupons yet.</td></tr><?php endif; ?>
</tbody></table></div></section>
<section class="apanel"><h2><?= $e0 ? 'Edit coupon' : 'New coupon' ?></h2>
<form method="post" action="<?= e(url('admin/coupons/save')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($e0['id'] ?? 0) ?>">
<?= field_text('code', 'Code', $v('code'), ['required' => true, 'placeholder' => 'WELCOME10']) ?>
<div class="g2"><?= field_select('type', 'Type', ['percent' => 'Percent %', 'fixed' => 'Fixed amount'], $v('type', 'percent')) ?>
<?= field_text('value', 'Value', ($e0 && $e0['type'] === 'fixed') ? from_cents((int) $e0['value']) : $v('value'), ['required' => true, 'type' => 'number', 'step' => '0.01', 'min' => '0']) ?></div>
<div class="g2"><?= field_text('min_total', 'Min. spend', $e0 && $e0['min_total'] ? from_cents((int) $e0['min_total']) : '', ['type' => 'number', 'step' => '0.01']) ?>
<?= field_text('max_uses', 'Max uses (0 = ∞)', $v('max_uses', 0), ['type' => 'number', 'min' => '0']) ?></div>
<div class="g2"><?= field_text('starts_at', 'Starts', $e0 && $e0['starts_at'] ? substr($e0['starts_at'], 0, 10) : '', ['type' => 'date']) ?>
<?= field_text('ends_at', 'Ends', $e0 && $e0['ends_at'] ? substr($e0['ends_at'], 0, 10) : '', ['type' => 'date']) ?></div>
<?= field_select('status', 'Status', ['active' => 'Active', 'disabled' => 'Disabled'], $v('status', 'active')) ?>
<button class="btn block">Save coupon</button></form>
<?php if ($e0): ?><form method="post" action="<?= e(url('admin/coupons/' . $e0['id'] . '/delete')) ?>" onsubmit="return confirm('Delete coupon?')" style="margin-top:10px"><?= csrf_field() ?><button class="btn sm ghost danger">Delete</button> <a class="btn sm ghost" href="<?= e(url('admin/coupons')) ?>">Cancel</a></form><?php endif; ?>
</section></div>
