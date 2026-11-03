<?php /** @var array $rows @var array $coupons @var array $stats @var array $recent */
$tags = ['{first_name}', '{name}', '{store}', '{cart_items}', '{cart_button}', '{cart_total}', '{coupon}', '{coupon_line}', '{shop_button}', '{order_number}', '{unsubscribe_url}']; ?>
<div class="kpis">
  <div class="kpi"><b><?= (int) $stats['open'] ?></b><small>Open carts with email · <?= money($stats['open_value']) ?></small></div>
  <div class="kpi"><b><?= (int) $stats['recovered'] ?></b><small>Recovered carts · <?= money($stats['recovered_value']) ?></small></div>
  <div class="kpi"><b><?= (int) $stats['sent30'] ?></b><small>Emails sent (30 days)</small></div>
  <div class="kpi"><b><?= (int) $stats['subs'] ?></b><small>Subscribers</small></div>
</div>

<section class="apanel"><h2>How it runs</h2>
  <p class="muted small">Emails use <a href="<?= e(url('admin/settings?tab=email')) ?>">your email settings</a>. Carts are captured once a visitor's email is known (signed in, typed at checkout, or via a popup). Customers can opt out with the link in every email.</p>
  <form method="post" action="<?= e(url('admin/automations/settings')) ?>" class="inline-add" style="align-items:center"><?= csrf_field() ?>
    <label class="chk" style="margin:0"><input type="checkbox" name="autorun" value="1" <?= setting('automations_autorun') === '1' ? 'checked' : '' ?>><i></i><span>Run automatically on visitor traffic (no cron needed)</span></label>
    <button class="btn sm ghost">Save</button>
  </form>
  <form method="post" action="<?= e(url('admin/automations/run')) ?>" style="margin-top:8px"><?= csrf_field() ?><button class="btn sm">Run due automations now</button> <span class="muted small">Last run: <?= e(setting('automations_last_run') ? time_ago(setting('automations_last_run')) : 'never') ?> · For exact timing add a cron: <code>* * * * * php <?= e(base_path('artisan')) ?> schedule:run</code></span></form>
</section>

<?php foreach ($rows as $a): ?>
<details class="flow" id="a<?= (int) $a['id'] ?>">
  <summary><span class="dot <?= $a['enabled'] ? 'on' : '' ?>"></span><b><?= e($a['name']) ?></b><span class="muted small"><?= e($a['trigger_label']) ?></span>
    <span class="meta"><span><?= (int) $a['sent'] ?> sent</span><?php if (str_starts_with($a['key'], 'abandoned')): ?><span><?= (int) $a['recovered'] ?> recovered · <?= money($a['revenue']) ?></span><?php endif; ?><span class="badge s-<?= $a['enabled'] ? 'completed' : 'pending' ?>"><?= $a['enabled'] ? 'ON' : 'OFF' ?></span></span></summary>
  <div class="flow-body">
    <form method="post" action="<?= e(url('admin/automations/' . $a['id'])) ?>" style="margin-top:16px"><?= csrf_field() ?>
      <?= field_check('enabled', 'Enabled', (bool) $a['enabled']) ?>
      <div class="g2"><?= field_text('delay_hours', str_starts_with($a['key'], 'abandoned') ? 'Send after cart inactivity (hours)' : 'Send after (hours)', $a['delay_hours'], ['type' => 'number', 'min' => '0']) ?>
        <label class="fld"><span>Discount code to include (optional)</span><select name="coupon_code"><option value="">— none —</option><?php foreach ($coupons as $c): ?><option value="<?= e($c) ?>" <?= $a['coupon_code'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select><small class="hint">Create codes under Coupons. Use {coupon} in the text.</small></label></div>
      <?= field_text('subject', 'Subject', $a['subject']) ?>
      <?= field_area('body', 'Message (blank line = new paragraph)', $a['body'], ['rows' => 9]) ?>
      <div class="tags"><span class="muted small">Click to insert:</span> <?php foreach ($tags as $t): ?><code><?= e($t) ?></code><?php endforeach; ?></div>
      <div class="actions"><button class="btn">Save</button></div>
    </form>
    <form method="post" action="<?= e(url('admin/automations/' . $a['id'] . '/test')) ?>" class="inline-add" style="margin-top:14px"><?= csrf_field() ?><input type="email" name="to" placeholder="Send a test to…" value="<?= e(auth()->user()->email) ?>"><button class="btn ghost sm">Send test</button></form>
  </div>
</details>
<?php endforeach; ?>

<section class="apanel"><h2>Recent sends</h2><ul class="list"><?php foreach ($recent as $l): ?><li><span><b><?= e($l['automation']) ?></b> → <?= e($l['email']) ?><small><?= e($l['subject']) ?></small></span><span class="muted"><?= $l['ok'] ? '' : '<span class="warn">failed</span> ' ?><?= e(time_ago($l['created_at'])) ?></span></li><?php endforeach; ?><?php if (!$recent): ?><li class="muted">Nothing sent yet.</li><?php endif; ?></ul></section>
