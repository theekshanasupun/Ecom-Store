<?php /** @var array $rows @var array $coupons @var array $stats @var array $recent @var array $queue */
$tags = ['{first_name}', '{name}', '{store}', '{cart_items}', '{cart_button}', '{cart_total}', '{coupon}', '{coupon_line}', '{shop_button}', '{order_number}', '{unsubscribe_url}'];
$custom = array_values(array_filter($rows, fn ($a) => $a['type'] === 'custom'));
$system = array_values(array_filter($rows, fn ($a) => $a['type'] !== 'custom'));
?>
<div class="kpis">
  <div class="kpi"><b><?= (int) $stats['open'] ?></b><small>Open carts with email · <?= money($stats['open_value']) ?></small></div>
  <div class="kpi"><b><?= (int) $stats['recovered'] ?></b><small>Recovered carts · <?= money($stats['recovered_value']) ?></small></div>
  <div class="kpi"><b><?= (int) $stats['sent30'] ?></b><small>Emails sent (30 days)</small></div>
  <div class="kpi"><b><?= (int) $stats['pending'] ?></b><small>Queued emails waiting</small></div>
</div>

<div class="toolbar">
  <div><h2 style="margin:0">Your automations</h2><span class="muted small">Pick any action in your store as the trigger, build the email visually, choose when it goes out.</span></div>
  <div class="row-actions"><a class="btn ghost" href="<?= e(url('admin/newsletters')) ?>">Newsletters</a><a class="btn" href="<?= e(url('admin/automations/new')) ?>">+ New automation</a></div>
</div>

<section class="apanel flush"><div class="tbl-wrap"><table class="tbl">
<thead><tr><th>Automation</th><th>Trigger</th><th>Steps</th><th class="r">Queued</th><th class="r">Sent</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($custom as $a): $q = $queue[$a['id']] ?? []; $steps = count($a->steps()); ?>
<tr><td><b><a href="<?= e(url('admin/automations/' . $a['id'] . '/edit')) ?>"><?= e($a['name']) ?></a></b><?php if ($a['coupon_code']): ?><small>coupon <?= e($a['coupon_code']) ?></small><?php endif; ?></td>
<td><?= e(\App\Support\Triggers::EVENTS[$a['trigger_event']] ?? $a['trigger_event']) ?></td>
<td><?= $steps ?> email<?= $steps === 1 ? '' : 's' ?><?= $a->hasDesign() ? '' : ' <small class="warn">· not designed</small>' ?></td>
<td class="r"><?= (int) ($q['pending'] ?? 0) ?></td><td class="r"><?= (int) $a['sent'] ?></td>
<td><form method="post" action="<?= e(url('admin/automations/' . $a['id'] . '/toggle')) ?>"><?= csrf_field() ?><button class="badge s-<?= $a['enabled'] ? 'completed' : 'pending' ?>" style="border:0;cursor:pointer"><?= $a['enabled'] ? 'ON' : 'OFF' ?></button></form></td>
<td class="r"><a class="btn sm ghost" href="<?= e(url('admin/automations/' . $a['id'] . '/edit')) ?>">Edit</a></td></tr>
<?php endforeach; ?>
<?php if (!$custom): ?><tr><td colspan="7" class="muted">No custom automations yet. Try “When an order is shipped → send a thank-you with a coupon after 7 days”.</td></tr><?php endif; ?>
</tbody></table></div></section>

<section class="apanel"><h2>How it runs</h2>
  <p class="muted small">Emails use <a href="<?= e(url('admin/settings?tab=email')) ?>">your email settings</a>. Carts are captured once a visitor's email is known (signed in, typed at checkout, or via a popup). Customers can opt out with the link in every marketing email (order emails are always sent).</p>
  <form method="post" action="<?= e(url('admin/automations/settings')) ?>" class="inline-add" style="align-items:center"><?= csrf_field() ?>
    <label class="chk" style="margin:0"><input type="checkbox" name="autorun" value="1" <?= setting('automations_autorun') === '1' ? 'checked' : '' ?>><i></i><span>Run automatically on visitor traffic (no cron needed)</span></label>
    <button class="btn sm ghost">Save</button>
  </form>
  <form method="post" action="<?= e(url('admin/automations/run')) ?>" style="margin-top:8px"><?= csrf_field() ?><button class="btn sm">Run due automations now</button> <span class="muted small">Last run: <?= e(setting('automations_last_run') ? time_ago(setting('automations_last_run')) : 'never') ?> · For exact timing add a cron: <code>* * * * * php <?= e(base_path('artisan')) ?> schedule:run</code></span></form>
</section>

<h2 style="margin:26px 0 12px">Built-in flows</h2>
<?php foreach ($system as $a): ?>
<details class="flow" id="a<?= (int) $a['id'] ?>">
  <summary><span class="dot <?= $a['enabled'] ? 'on' : '' ?>"></span><b><?= e($a['name']) ?></b><span class="muted small"><?= e($a['trigger_label']) ?></span>
    <span class="meta"><span><?= (int) $a['sent'] ?> sent</span><?php if (str_starts_with($a['key'], 'abandoned')): ?><span><?= (int) $a['recovered'] ?> recovered · <?= money($a['revenue']) ?></span><?php endif; ?><?php if ($a->hasDesign()): ?><span class="badge s-processing">designed</span><?php endif; ?><span class="badge s-<?= $a['enabled'] ? 'completed' : 'pending' ?>"><?= $a['enabled'] ? 'ON' : 'OFF' ?></span></span></summary>
  <div class="flow-body">
    <form method="post" action="<?= e(url('admin/automations/' . $a['id'])) ?>" style="margin-top:16px"><?= csrf_field() ?>
      <?= field_check('enabled', 'Enabled', (bool) $a['enabled']) ?>
      <div class="g2"><?= field_text('delay_hours', str_starts_with($a['key'], 'abandoned') ? 'Send after cart inactivity (hours)' : 'Send after (hours)', $a['delay_hours'], ['type' => 'number', 'min' => '0']) ?>
        <label class="fld"><span>Discount code to include (optional)</span><select name="coupon_code"><option value="">— none —</option><?php foreach ($coupons as $c): ?><option value="<?= e($c) ?>" <?= $a['coupon_code'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select><small class="hint">Create codes under Coupons. Use {coupon} in the text.</small></label></div>
      <?= field_text('subject', 'Subject', $a['subject']) ?>
      <?php if ($a->hasDesign()): ?>
        <p class="muted small" style="margin:6px 0 12px">This flow uses a <b>designed email</b>. The plain-text message below is only used if you reset the design.</p>
      <?php endif; ?>
      <?= field_area('body', 'Plain message (blank line = new paragraph)', $a['body'], ['rows' => 7]) ?>
      <div class="tags"><span class="muted small">Merge tags:</span> <?php foreach ($tags as $t): ?><code><?= e($t) ?></code><?php endforeach; ?></div>
      <div class="actions"><button class="btn">Save</button> <a class="btn ghost" href="<?= e(url('admin/automations/' . $a['id'] . '/design')) ?>">🎨 <?= $a->hasDesign() ? 'Edit design' : 'Design with the visual builder' ?></a></div>
    </form>
    <div class="row-actions" style="margin-top:14px;gap:10px;flex-wrap:wrap">
      <form method="post" action="<?= e(url('admin/automations/' . $a['id'] . '/test')) ?>" class="inline-add"><?= csrf_field() ?><input type="email" name="to" placeholder="Send a test to…" value="<?= e(auth()->user()->email) ?>"><button class="btn ghost sm">Send test</button></form>
      <?php if ($a->hasDesign()): ?><form method="post" action="<?= e(url('admin/automations/' . $a['id'] . '/reset-design')) ?>" onsubmit="return confirm('Go back to the plain-text version?')"><?= csrf_field() ?><button class="btn ghost sm danger">Reset to text</button></form><?php endif; ?>
    </div>
  </div>
</details>
<?php endforeach; ?>

<section class="apanel"><h2>Recent sends</h2><ul class="list"><?php foreach ($recent as $l): ?><li><span><b><?= e($l['automation']) ?></b> → <?= e($l['email']) ?><small><?= e($l['subject']) ?></small></span><span class="muted"><?= $l['ok'] ? '' : '<span class="warn">failed</span> ' ?><?= e(time_ago($l['created_at'])) ?></span></li><?php endforeach; ?><?php if (!$recent): ?><li class="muted">Nothing sent yet.</li><?php endif; ?></ul></section>
