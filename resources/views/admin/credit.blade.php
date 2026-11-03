<?php /** @var array $rows @var int $page @var int $pages @var string $q @var bool $only @var array $stats @var array $recent @var array $who @var ?\App\Models\User $pick */
$sym = (string) setting('currency_symbol', '$');
$open = request()->boolean('new') || $pick || old('amount') !== null;
?>
<div class="kpis">
  <div class="kpi"><b><?= money($stats['outstanding']) ?></b><small>Outstanding credit (liability)</small></div>
  <div class="kpi"><b><?= (int) $stats['holders'] ?></b><small>Customers with a balance</small></div>
  <div class="kpi"><b><?= money($stats['granted30']) ?></b><small>Granted · last 30 days</small></div>
  <div class="kpi"><b><?= money($stats['spent30']) ?></b><small>Spent · last 30 days</small></div>
</div>
<div class="toolbar">
  <form class="filters" method="get"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search customers…"><label class="chk" style="margin:0"><input type="checkbox" name="with" value="1" <?= $only ? 'checked' : '' ?> onchange="this.form.submit()"><i></i><span>Only with a balance</span></label><button class="btn sm ghost">Search</button></form>
  <div class="row-actions"><form method="post" action="<?= e(url('admin/credit/settings')) ?>" class="inline-add" style="align-items:center"><?= csrf_field() ?><label class="chk" style="margin:0"><input type="hidden" name="credit_on" value="0"><input type="checkbox" name="credit_on" value="1" <?= \App\Services\StoreCredit::enabled() ? 'checked' : '' ?> onchange="this.form.submit()"><i></i><span>Customers can spend credit</span></label></form>
  <button type="button" class="btn" data-drawer-open="#credit-drawer">＋ Add / remove credit</button></div></div>

<section class="apanel flush"><div class="tbl-wrap"><table class="tbl">
<thead><tr><th>Customer</th><th class="r">Balance</th><th>Joined</th><th>Last login</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $u): ?>
<tr><td><b><a href="<?= e(url('admin/credit/' . $u->id)) ?>"><?= e($u->name) ?></a></b><small><?= e($u->email) ?></small></td><td class="r"><b class="<?= $u->credit > 0 ? 'lime' : 'muted' ?>"><?= money($u->credit) ?></b></td>
<td class="muted"><?= e(fmt_date($u->created_at, false)) ?></td><td class="muted"><?= e(time_ago($u->last_login_at)) ?></td>
<td class="r"><div class="rowmenu"><a class="btn sm ghost" href="<?= e(url('admin/credit?user=' . $u->id)) ?>">Adjust</a><a class="btn sm ghost" href="<?= e(url('admin/credit/' . $u->id)) ?>">Ledger</a></div></td></tr>
<?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="5" class="empty">No customers match.</td></tr><?php endif; ?>
</tbody></table></div></section>
<?= partial('admin/_pager', compact('page', 'pages')) ?>
<section class="apanel"><h2>Recent activity</h2><ul class="timeline"><?php foreach ($recent as $l): ?><li><span><a href="<?= e(url('admin/credit/' . $l->user_id)) ?>"><b><?= e($who[$l->user_id] ?? '#' . $l->user_id) ?></b></a> — <?= e($l->reason) ?> <b class="<?= $l->amount < 0 ? 'warn' : 'lime' ?>"><?= ($l->amount > 0 ? '+' : '−') . money(abs($l->amount)) ?></b></span><small><?= e($l->actor) ?> · <?= e(time_ago($l->created_at)) ?></small></li><?php endforeach; ?><?php if (!$recent): ?><li class="muted">No credit activity yet. Credit is also created when you refund an order to store credit.</li><?php endif; ?></ul></section>

<div class="drawer-panel" id="credit-drawer" hidden <?= $open ? 'data-autoopen' : '' ?>>
<form method="post" action="<?= e(url('admin/credit/adjust')) ?>"><?= csrf_field() ?>
  <div class="dp-head"><h2>Adjust store credit</h2><button type="button" data-drawer-close aria-label="Close">✕</button></div>
  <div class="dp-body">
    <?php if ($pick): ?><input type="hidden" name="user_id" value="<?= (int) $pick->id ?>"><div class="co-note"><b><?= e($pick->name) ?></b><br><span class="muted"><?= e($pick->email) ?> · balance <?= money($pick->credit) ?></span></div>
    <?php else: ?><?= field_text('email', 'Customer account email', old('email'), ['type' => 'email', 'required' => true, 'placeholder' => 'customer@example.com', 'autocomplete' => 'off']) ?><?php endif; ?>
    <div class="g2"><?= field_select('dir', 'Action', ['add' => 'Add credit', 'remove' => 'Remove credit'], old('dir', 'add')) ?><?= field_text('amount', 'Amount (' . $sym . ')', old('amount'), ['type' => 'number', 'step' => '0.01', 'min' => '0.01', 'required' => true]) ?></div>
    <?= field_text('reason', 'Reason (visible in the ledger)', old('reason'), ['placeholder' => 'Goodwill, loyalty reward, refund…']) ?>
    <?= field_check('notify', 'Email the customer about it', true) ?>
  </div>
  <div class="dp-foot"><span class="grow"></span><button type="button" class="btn ghost" data-drawer-close>Cancel</button><button class="btn">Apply</button></div>
</form></div>
