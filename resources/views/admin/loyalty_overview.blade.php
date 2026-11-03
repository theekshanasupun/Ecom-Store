<?php /** @var array $o @var array $trend @var int $unenrolled */
$max = max(1, ...array_map(fn ($t) => max($t['earned'], $t['redeemed']), $trend));
?>
<form class="filters" method="get" style="margin-bottom:14px"><label class="fld" style="margin:0"><span>From</span><input type="date" name="from" value="<?= e($from) ?>"></label><label class="fld" style="margin:0"><span>To</span><input type="date" name="to" value="<?= e($to) ?>"></label><button class="btn sm ghost">Apply</button><a class="btn sm ghost" href="<?= e(url('admin/loyalty/reports')) ?>">Reports &amp; exports →</a>
<?php if (auth()->user()->isAdmin()): ?><button class="btn sm ghost" formmethod="post" formaction="<?= e(url('admin/loyalty/run')) ?>"><?= csrf_field() ?>Run maintenance now</button><?php endif; ?></form>
<?php if ($unenrolled > 0 && auth()->user()->isAdmin()): ?><div class="flash flash-info"><?= (int) $unenrolled ?> existing customer(s) are not enrolled yet. <form method="post" action="<?= e(url('admin/loyalty/enrol')) ?>" style="display:inline"><?= csrf_field() ?><button class="btn sm">Enrol them now</button></form></div><?php endif; ?>
<?php if (!\App\Services\Loyalty::on()): ?><div class="flash flash-error">The loyalty programme is switched <b>off</b> (Programme settings).</div><?php endif; ?>
<div class="kpis">
  <?php foreach ([['members', 'Loyalty members'], ['active', 'Active (90 days)'], ['inactive', 'Inactive'], ['new', 'New registrations']] as [$k, $l]): ?><div class="kpi"><b><?= number_format($o[$k]) ?></b><small><?= $l ?></small></div><?php endforeach; ?>
  <div class="kpi"><b><?= number_format($o['issued']) ?></b><small>Points issued</small></div><div class="kpi"><b><?= number_format($o['redeemed']) ?></b><small>Points redeemed</small></div>
  <div class="kpi"><b><?= number_format($o['outstanding']) ?></b><small>Outstanding points</small></div><div class="kpi"><b><?= number_format($o['expired']) ?></b><small>Expired points</small></div>
  <div class="kpi"><b><?= money($o['liability']) ?></b><small>Points liability</small></div><div class="kpi"><b><?= money($o['cb_issued']) ?></b><small>Cashback issued</small></div>
  <div class="kpi"><b><?= money($o['cb_liability']) ?></b><small>Cashback liability</small></div><div class="kpi"><b><?= number_format($o['rewards_redeemed']) ?></b><small>Rewards redeemed</small></div>
  <div class="kpi"><b><?= money($o['revenue']) ?></b><small>Member revenue (<?= number_format($o['orders']) ?> orders)</small></div><div class="kpi"><b><?= $o['repeat_rate'] ?>%</b><small>Repeat purchase rate</small></div>
  <div class="kpi"><b><?= money($o['aov']) ?></b><small>Member AOV <span class="muted">(non-member <?= money($o['non_aov']) ?>)</span></small></div><div class="kpi"><b><?= money($o['clv']) ?></b><small>Avg customer lifetime value</small></div>
  <div class="kpi"><b><?= $o['roi'] === null ? '—' : $o['roi'] . '%' ?></b><small>Programme ROI (cost <?= money($o['cost']) ?>)</small></div>
</div>
<div class="cols">
  <section class="apanel wide"><h2>Points earned vs redeemed · 30 days</h2>
    <div style="display:flex;align-items:flex-end;gap:3px;height:150px" role="img" aria-label="Daily points earned and redeemed"><?php foreach ($trend as $t): ?><div title="<?= e($t['label']) ?>: +<?= (int) $t['earned'] ?> / −<?= (int) $t['redeemed'] ?>" style="flex:1;display:flex;align-items:flex-end;gap:1px;height:100%"><i style="flex:1;background:var(--accent);height:<?= max(1, (int) ($t['earned'] / $max * 100)) ?>%"></i><i style="flex:1;background:#6ea8ff;height:<?= max(1, (int) ($t['redeemed'] / $max * 100)) ?>%"></i></div><?php endforeach; ?></div>
    <p class="muted small"><span style="color:var(--accent)">■</span> earned &nbsp; <span style="color:#6ea8ff">■</span> redeemed</p></section>
  <div class="stack">
    <section class="apanel"><h2>Tier distribution</h2><?php foreach ($o['tiers'] as $t): ?><div class="ph" style="margin:0 0 8px"><span><i style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= e($t['color']) ?>"></i> <?= e($t['name']) ?></span><b><?= (int) $t['n'] ?></b></div><?php endforeach; ?></section>
    <section class="apanel"><h2>Most popular rewards</h2><?php foreach ($o['popular'] as $p): ?><div class="ph" style="margin:0 0 8px"><span><?= e($p['name']) ?></span><b><?= (int) $p['n'] ?></b></div><?php endforeach; ?><?php if (!$o['popular']): ?><p class="muted">No redemptions yet.</p><?php endif; ?></section>
    <section class="apanel"><h2>Referrals &amp; engagement</h2><p class="muted small"><?= (int) $o['referrals']['total'] ?> referred · <?= (int) $o['referrals']['rewarded'] ?> rewarded · <?= (int) $o['referrals']['pending'] ?> pending<br>30 days: <?= (int) $o['engagement']['checkins30'] ?> check-ins · <?= (int) $o['engagement']['plays30'] ?> game plays · <?= (int) $o['engagement']['challenges30'] ?> challenges completed · <?= (int) $o['engagement']['badges'] ?> badges earned</p></section>
  </div>
</div>
