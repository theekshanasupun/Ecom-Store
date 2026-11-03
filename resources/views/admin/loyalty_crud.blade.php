<?php /** @var string $e @var array $s @var iterable $rows @var ?object $edit @var array $tiersAll */
$open = $edit || request()->boolean('new') || old('name') !== null;
$sym = (string) setting('currency_symbol', '$');
$val = function ($name, $f) use ($edit, $s) {
    $d = $s['defaults'][$name] ?? '';
    $v = old($name, $edit->$name ?? $d);
    return match ($f[1]) {
        'money' => $v === '' || $v === null ? '' : (is_numeric($v) && !old($name) ? from_cents((int) $v) : $v),
        'valuefield' => ($edit && in_array($edit->type, ['discount_fixed', 'voucher'], true) && !old($name)) ? from_cents((int) $v) : $v,
        'datetime' => $v ? date('Y-m-d\TH:i', strtotime((string) $v)) : '', default => $v,
    };
};
$typeNames = \App\Services\LoyaltyRewards::TYPES;
?>
<?= partial('admin/loyalty_nav', ['cur' => $e]) ?>
<div class="toolbar"><div class="quick"><input type="search" data-tablefilter="#crud-table" placeholder="Filter…" aria-label="Filter"><span class="muted small"><?= e($s['hint']) ?></span></div>
  <button type="button" class="btn" data-drawer-open="#crud-drawer">＋ New <?= e($s['single']) ?></button></div>
<section class="apanel flush"><div class="tbl-wrap"><table class="tbl" id="crud-table"><thead><tr><?php foreach ($s['cols'] as $c => $l): ?><th><?= e($l) ?></th><?php endforeach; ?><th>On</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $x): ?><tr>
<?php foreach ($s['cols'] as $c => $l): $v = $x->$c ?? ''; ?><td><?php
  if ($c === 'name') echo '<b><a href="' . e(url("admin/loyalty/$e?edit=" . $x->id)) . '">' . e($v) . '</a></b>';
  elseif ($c === 'type' && $e === 'rewards') echo e($typeNames[$v] ?? $v);
  elseif (in_array($c, ['min_annual_spend', 'min_lifetime_spend']) ) echo $v ? money($v) : '—';
  elseif ($c === 'reward_cashback') echo $v ? money($v) : '—';
  elseif (in_array($c, ['starts_at', 'ends_at', 'expires_at', 'settled_at'])) echo $v ? e(fmt_date($v, false)) : '—';
  elseif ($c === 'stock') echo $v === null ? '∞' : (int) $v;
  elseif ($c === 'spent' && $e === 'campaigns') echo number_format((int) $v);
  else echo e($v === null || $v === '' ? '—' : $v);
?></td><?php endforeach; ?>
<?php $tg = $s['toggle'] ?? 'active'; $isOn = str_contains($tg, ':') ? ($x->{explode(':', $tg)[0]} === explode(':', $tg)[1]) : (bool) $x->$tg; ?>
<td><label class="switch"><input type="checkbox" data-switch-url="<?= e(url("admin/loyalty/$e/" . $x->id . '/toggle')) ?>" <?= $isOn ? 'checked' : '' ?> aria-label="Enabled"><i></i></label></td>
<td class="r"><a class="btn sm ghost" href="<?= e(url("admin/loyalty/$e?edit=" . $x->id)) ?>">Edit</a></td></tr>
<?php endforeach; ?><?php if (!count($rows)): ?><tr><td colspan="<?= count($s['cols']) + 2 ?>" class="empty">Nothing here yet — create the first <?= e($s['single']) ?>.</td></tr><?php endif; ?></tbody></table></div></section>

<div class="drawer-panel dp-wide" id="crud-drawer" hidden <?= $open ? 'data-autoopen' : '' ?>>
<form method="post" action="<?= e(url("admin/loyalty/$e/save")) ?>" enctype="multipart/form-data"><?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) ($edit->id ?? 0) ?>">
  <div class="dp-head"><h2><?= $edit ? 'Edit' : 'New' ?> <?= e($s['single']) ?></h2><button type="button" data-drawer-close aria-label="Close">✕</button></div>
  <div class="dp-body"><div class="g2">
  <?php foreach ($s['fields'] as $name => $f): $t = $f[1]; $label = $f[0] . (!empty($f['req']) ? ' *' : ''); $o = ['hint' => $f['hint'] ?? null] + (!empty($f['req']) ? ['required' => true] : []);
    if (!$o['hint']) unset($o['hint']);
    if (in_array($t, ['textarea'])) echo '<div style="grid-column:1/-1">' . field_area($name, $label, $val($name, $f), $o + ['rows' => 3]) . '</div>';
    elseif ($t === 'bool') echo '<div style="padding-top:22px">' . field_check($name, $f[0], (bool) $val($name, $f)) . '</div>';
    elseif ($t === 'select') echo field_select($name, $label, $f['options'], $val($name, $f), $o);
    elseif ($t === 'color') echo field_text($name, $label, $val($name, $f) ?: '#c6ff3d', ['type' => 'color']);
    elseif ($t === 'image') echo '<label class="fld"><span>' . e($f[0]) . '</span><input type="file" name="' . e($name) . '" accept="image/*">' . (!empty($edit->$name) ? '<small class="hint"><img src="' . e(upload_url($edit->$name)) . '" alt="" style="height:40px;border-radius:8px"> current image kept unless you upload a new one</small>' : '') . '</label>';
    elseif ($t === 'tiers') { $sel = array_map('intval', array_filter(explode(',', (string) $val($name, $f)))); echo '<div class="fld" style="grid-column:1/-1"><span>' . e($f[0]) . '</span><div style="display:flex;gap:12px;flex-wrap:wrap">'; foreach ($tiersAll as $tt) echo '<label class="chk"><input type="checkbox" name="' . e($name) . '[]" value="' . (int) $tt->id . '" ' . (in_array((int) $tt->id, $sel, true) ? 'checked' : '') . '><i></i><span>' . e($tt->name) . '</span></label>'; echo '</div><small class="hint">None selected = everyone</small></div>'; }
    elseif ($t === 'datetime') echo field_text($name, $label, $val($name, $f), $o + ['type' => 'datetime-local']);
    elseif (in_array($t, ['number', 'nullint'])) echo field_text($name, $label, $val($name, $f), $o + ['type' => 'number', 'min' => '0', 'step' => '1']);
    elseif (in_array($t, ['decimal'])) echo field_text($name, $label, $val($name, $f), $o + ['type' => 'number', 'min' => '0', 'step' => '0.01']);
    elseif (in_array($t, ['money', 'valuefield'])) echo field_text($name, $label, $val($name, $f), $o + ['type' => 'number', 'min' => '0', 'step' => '0.01']);
    else echo field_text($name, $label, $val($name, $f), $o);
  endforeach; ?>
  </div><?php if ($e === 'rewards'): ?><p class="muted small">Amounts are in <?= e($sym) ?>. For percentage rewards enter 10 for 10%.</p><?php endif; ?></div>
  <div class="dp-foot"><?php if ($edit): ?><button type="submit" class="btn ghost danger grow" formaction="<?= e(url("admin/loyalty/$e/" . $edit->id . '/delete')) ?>" formnovalidate data-confirm="Delete this <?= e($s['single']) ?>? <?= in_array($e, ['tiers', 'rewards']) ? 'If it is in use it will be deactivated instead.' : '' ?>">Delete</button><?php else: ?><span class="grow"></span><?php endif; ?>
    <button type="button" class="btn ghost" data-drawer-close>Cancel</button><button class="btn">Save</button></div>
</form></div>
