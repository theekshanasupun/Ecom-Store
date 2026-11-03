<?php /** @var ?\App\Models\Automation $a @var array $products @var array $cats @var array $coupons @var array $queue @var array $qstats @var array $events */
$c = $a ? $a->conditionList() : [];
$steps = $a ? $a->steps() : [['delay_minutes' => 0, 'subject' => '', 'preheader' => '', 'blocks' => []]];
if (!$steps) $steps = [['delay_minutes' => 0, 'subject' => '', 'preheader' => '', 'blocks' => []]];
$selP = array_map('intval', (array) ($c['product_ids'] ?? [])); $selC = array_map('intval', (array) ($c['category_ids'] ?? []));
$v = fn ($k, $d = '') => old($k, $a[$k] ?? $d);
$split = function (int $min): array { if ($min > 0 && $min % 1440 === 0) return [$min / 1440, 'days']; if ($min > 0 && $min % 60 === 0) return [$min / 60, 'hours']; return [$min, 'minutes']; };
?>
<form method="post" action="<?= e(url($a ? 'admin/automations/' . $a['id'] : 'admin/automations/create')) ?>" class="pform" id="autoform"><?= csrf_field() ?>
<div class="cols">
 <div class="stack wide">
  <section class="apanel"><h2>1 · What starts it?</h2>
    <?= field_text('name', 'Automation name', $v('name'), ['required' => true, 'placeholder' => 'e.g. Thank-you + coupon 7 days after shipping']) ?>
    <label class="fld"><span>Trigger — when this happens in your store…</span><select name="trigger_event" id="trg">
      <?php foreach ($events as $k => $l): ?><option value="<?= e($k) ?>" <?= $v('trigger_event', 'order.paid') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
      <small class="hint">Abandoned-cart, welcome, post-purchase and win-back flows are built in (below on the automations page).</small></label>
    <?= field_check('enabled', 'Automation is ON', (bool) ($a['enabled'] ?? false)) ?>
  </section>

  <section class="apanel" id="conds"><h2>2 · Only when… <small class="muted">(optional conditions — apply to order events)</small></h2>
    <div class="g2"><?= field_text('min_total', 'Order total at least', !empty($c['min_total']) ? from_cents((int) $c['min_total']) : '', ['type' => 'number', 'step' => '0.01', 'min' => '0']) ?>
    <?= field_text('max_total', 'Order total at most', !empty($c['max_total']) ? from_cents((int) $c['max_total']) : '', ['type' => 'number', 'step' => '0.01', 'min' => '0']) ?></div>
    <div class="g2"><?= field_select('payment_method', 'Payment method', ['' => 'Any', 'stripe' => 'Card (Stripe)', 'paypal' => 'PayPal', 'bank' => 'Bank transfer', 'cod' => 'Cash on delivery'], $c['payment_method'] ?? '') ?>
    <?= field_text('country', 'Ships to country contains', $c['country'] ?? '', ['placeholder' => 'e.g. Sri Lanka']) ?></div>
    <?= field_check('first_order', 'Only the customer\'s first order', !empty($c['first_order'])) ?>
    <div class="g2">
      <div><span class="muted small">Order contains any of these products:</span><input type="search" class="pickfilter" placeholder="Filter products…"><div class="pick"><?php foreach ($products as $p): ?><label><input type="checkbox" name="product_ids[]" value="<?= (int) $p['id'] ?>" <?= in_array((int) $p['id'], $selP, true) ? 'checked' : '' ?>><span><?= e($p['name']) ?></span></label><?php endforeach; ?></div></div>
      <div><span class="muted small">…or products from these departments:</span><div class="pick"><?php foreach ($cats as $k): ?><label><input type="checkbox" name="category_ids[]" value="<?= (int) $k['id'] ?>" <?= in_array((int) $k['id'], $selC, true) ? 'checked' : '' ?>><span><?= e($k['name']) ?></span></label><?php endforeach; ?></div></div>
    </div>
  </section>

  <section class="apanel"><div class="ph"><h2>3 · The emails <small class="muted">(1–5 in a sequence)</small></h2><button type="button" class="btn sm ghost" id="addstep">+ Add email</button></div>
    <p class="muted small">Each email is sent its own delay <b>after the trigger</b>. Click “Design email” to open the drag &amp; drop builder — save this form first for new emails.</p>
    <div id="steps">
    <?php foreach ($steps as $i => $s): [$dv, $du] = $split((int) ($s['delay_minutes'] ?? 0)); $designed = !empty($s['blocks']); ?>
      <div class="stepbox" data-step>
        <div class="stephead"><b class="sn">Email <?= $i + 1 ?></b><span class="muted small"><?= $designed ? '✓ designed' : 'not designed yet' ?></span>
          <span class="grow"></span>
          <?php if ($a): ?><a class="btn sm ghost" href="<?= e(url('admin/automations/' . $a['id'] . '/design?step=' . $i)) ?>">🎨 Design email</a><?php endif; ?>
          <button type="button" class="btn sm ghost danger" data-del title="Remove">✕</button></div>
        <input type="hidden" name="steps[<?= $i ?>][orig]" value="<?= $a ? $i : '' ?>">
        <div class="g3">
          <?= field_text("steps[$i][delay]", 'Send after', $dv, ['type' => 'number', 'min' => '0', 'step' => '1']) ?>
          <label class="fld"><span>Unit</span><select name="steps[<?= $i ?>][unit]"><?php foreach (['minutes', 'hours', 'days'] as $u): ?><option value="<?= $u ?>" <?= $du === $u ? 'selected' : '' ?>><?= ucfirst($u) ?></option><?php endforeach; ?></select></label>
          <span></span>
        </div>
        <?= field_text("steps[$i][subject]", 'Subject', $s['subject'] ?? '', ['placeholder' => 'Thanks for your order, {first_name}!']) ?>
        <?= field_text("steps[$i][preheader]", 'Preview text', $s['preheader'] ?? '', ['placeholder' => 'Shown next to the subject in the inbox']) ?>
      </div>
    <?php endforeach; ?>
    </div>
  </section>

  <section class="apanel"><h2>4 · Extras <small class="muted">(optional)</small></h2>
    <div class="g2">
      <label class="fld"><span>Include a coupon code ({coupon} merge tag)</span><select name="coupon_code"><option value="">— none —</option><?php foreach ($coupons as $cc): ?><option value="<?= e($cc) ?>" <?= $v('coupon_code') === $cc ? 'selected' : '' ?>><?= e($cc) ?></option><?php endforeach; ?></select></label>
      <?= field_text('credit', 'Also add store credit to the customer', !empty($c['credit_cents']) ? from_cents((int) $c['credit_cents']) : '', ['type' => 'number', 'step' => '0.01', 'min' => '0', 'hint' => 'Needs the customer to have an account. Added once per trigger.']) ?>
    </div>
  </section>
 </div>

 <div class="stack">
  <section class="apanel"><h2>Save</h2>
    <button class="btn block"><?= $a ? 'Save automation' : 'Create automation' ?></button>
    <p class="muted small" style="margin-top:10px">Emails queue the moment the trigger happens and go out on schedule (visitor traffic or cron).</p>
    <p><a href="<?= e(url('admin/automations')) ?>">← All automations</a></p>
  </section>
  <?php if ($a): ?>
  <section class="apanel"><h2>Stats</h2>
    <dl class="kv"><div><dt>Sent</dt><dd><?= (int) $a['sent'] ?></dd></div><div><dt>Queued</dt><dd><?= (int) ($qstats['pending'] ?? 0) ?></dd></div><div><dt>Failed</dt><dd><?= (int) ($qstats['failed'] ?? 0) ?></dd></div><div><dt>Skipped</dt><dd><?= (int) ($qstats['skipped'] ?? 0) ?></dd></div></dl>
  </section>
  <?php endif; ?>
 </div>
</div>
</form>

<?php if ($a): ?>
<div class="cols"><section class="apanel wide"><h2>Test &amp; manage</h2>
  <div class="row-actions" style="gap:10px;flex-wrap:wrap">
    <form method="post" action="<?= e(url('admin/automations/' . $a['id'] . '/test')) ?>" class="inline-add"><?= csrf_field() ?>
      <input type="email" name="to" value="<?= e(auth()->user()->email) ?>"><select name="step"><?php foreach ($steps as $i => $_): ?><option value="<?= $i ?>">Email <?= $i + 1 ?></option><?php endforeach; ?></select><button class="btn ghost sm">Send test</button></form>
    <form method="post" action="<?= e(url('admin/automations/run')) ?>"><?= csrf_field() ?><button class="btn sm">Run due now</button></form>
    <form method="post" action="<?= e(url('admin/automations/' . $a['id'] . '/duplicate')) ?>"><?= csrf_field() ?><button class="btn sm ghost">Duplicate</button></form>
    <form method="post" action="<?= e(url('admin/automations/' . $a['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this automation and its queue?')"><?= csrf_field() ?><button class="btn sm ghost danger">Delete</button></form>
  </div>
  <h3 style="margin-top:20px">Last 20 queue entries</h3>
  <ul class="list"><?php foreach ($queue as $q): ?><li><span><b><?= e($q['email']) ?></b> <small>email <?= (int) $q['step'] + 1 ?> · <?= e($q['ref']) ?><?= $q['error'] ? ' · ' . e($q['error']) : '' ?></small></span><span class="muted"><span class="badge s-<?= $q['status'] === 'sent' ? 'completed' : ($q['status'] === 'failed' ? 'cancelled' : 'pending') ?>"><?= e($q['status']) ?></span> <?= e($q['status'] === 'pending' ? 'due ' . fmt_date($q['run_at']) : time_ago($q['sent_at'] ?: $q['updated_at'])) ?></span></li><?php endforeach; ?><?php if (!$queue): ?><li class="muted">Nothing queued yet.</li><?php endif; ?></ul>
</section></div>
<?php endif; ?>

<style>.stepbox{border:1px solid var(--line);border-radius:14px;padding:14px 16px;margin-bottom:12px;background:var(--card2)}.stephead{display:flex;align-items:center;gap:10px;margin-bottom:8px}.stephead .grow{flex:1}</style>
<script>
(function(){
  var wrap=document.getElementById('steps'),add=document.getElementById('addstep');
  function renumber(){var boxes=wrap.querySelectorAll('[data-step]');boxes.forEach(function(b,i){b.querySelector('.sn').textContent='Email '+(i+1);b.querySelectorAll('[name]').forEach(function(el){el.name=el.name.replace(/steps\[\d+\]/,'steps['+i+']')});});add.disabled=boxes.length>=5;}
  add.addEventListener('click',function(){var boxes=wrap.querySelectorAll('[data-step]');if(boxes.length>=5)return;var n=boxes[boxes.length-1].cloneNode(true);n.querySelectorAll('input[type=text],input[type=number]').forEach(function(i){i.value=''});n.querySelector('[name$="[orig]"]').value='';var d=n.querySelector('a.btn');if(d)d.remove();var m=n.querySelector('.stephead .muted');if(m)m.textContent='save, then design';wrap.appendChild(n);renumber();});
  wrap.addEventListener('click',function(e){var b=e.target.closest('[data-del]');if(!b)return;if(wrap.querySelectorAll('[data-step]').length<2){alert('An automation needs at least one email.');return}b.closest('[data-step]').remove();renumber();});
  renumber();
})();
</script>
