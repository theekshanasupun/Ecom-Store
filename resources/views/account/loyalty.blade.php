<?php
/** @var \App\Models\User $u @var string $tab @var array $tabs @var ?object $tier @var array $progress */
use App\Services\Loyalty;
$pv = fn ($p) => money(Loyalty::pointsValue((int) $p));
$name = Loyalty::name();
$tc = $tier->color ?? '#c6ff3d';
$tabUrl = fn ($t, $x = '') => url('account/loyalty?tab=' . $t . $x);
?>
<section class="block narrow-top loy">
  <p class="eyebrow lime"><?= e($name) ?></p>
  <div class="loy-head"><h1 class="h1">Hi, <?= e(explode(' ', $u['name'])[0]) ?></h1><a class="btn btn-ghost" href="<?= e(url('account')) ?>">← My account</a></div>
  <?php if (!$active): ?><div class="card loy-warn">Your rewards account is currently <b>suspended</b>. You can still see your history. <a href="<?= e($tabUrl('settings')) ?>">Contact us</a> if this is a mistake.</div><?php endif; ?>
  <nav class="loy-tabs" aria-label="Rewards sections">
    <?php foreach ($tabs as $k => $l): ?><a href="<?= e($tabUrl($k)) ?>" class="<?= $tab === $k ? 'on' : '' ?>"><?= e($l) ?><?= $k === 'activity' && $unread ? ' <em>' . (int) $unread . '</em>' : '' ?></a><?php endforeach; ?>
  </nav>

<?php if ($tab === 'overview'): ?>
  <div class="loy-hero" style="--tc:<?= e($tc) ?>">
    <div class="loy-bal">
      <small>Available points</small>
      <b><?= number_format((int) $u->points) ?></b>
      <span class="muted">worth <?= e($pv($u->points)) ?><?= $pending > 0 ? ' · ' . number_format($pending) . ' pending' : '' ?></span>
      <?php if ($stats['soon'] > 0): ?><span class="warn">⏳ <?= number_format($stats['soon']) ?> expiring by <?= e(fmt_date($stats['soon_at'], false)) ?></span><?php endif; ?>
    </div>
    <div class="loy-tier">
      <span class="loy-badge-t" style="--tc:<?= e($tc) ?>">★ <?= e($tier->name ?? 'Member') ?></span>
      <?php if ($progress['next']): ?>
        <div class="loy-bar" role="progressbar" aria-valuenow="<?= (int) $progress['pct'] ?>" aria-valuemin="0" aria-valuemax="100"><i style="width:<?= (int) $progress['pct'] ?>%"></i></div>
        <small class="muted"><?= (int) $progress['pct'] ?>% to <b><?= e($progress['next']->name) ?></b><?= $progress['needs'] ? ' · ' . e(implode(' · ', $progress['needs'])) : '' ?></small>
      <?php else: ?><small class="muted">You are at the top tier 🎉</small><?php endif; ?>
      <?php if ($u->tier_until): ?><small class="muted">Tier valid until <?= e(fmt_date($u->tier_until, false)) ?></small><?php endif; ?>
    </div>
  </div>
  <div class="acct-stats loy-stats">
    <div class="card"><b><?= number_format($stats['lifetime']) ?></b><small>Lifetime points earned</small></div>
    <div class="card"><b><?= number_format($stats['redeemed']) ?></b><small>Points redeemed</small></div>
    <div class="card"><b><?= number_format($pending) ?></b><small>Pending points</small></div>
    <div class="card"><b><?= number_format($stats['expired']) ?></b><small>Expired points</small></div>
    <?php if (Loyalty::cashbackOn()): ?><div class="card"><b><?= money($u->cashback) ?></b><small>Cashback balance</small></div><?php endif; ?>
    <div class="card"><b><?= money($u->credit) ?></b><small>Store credit <span class="muted">(separate)</span></small></div>
  </div>

  <div class="loy-cols">
    <div class="card loy-card" id="loy-card" data-id="<?= e($u->loyalty_id) ?>" data-name="<?= e($u->name) ?>" data-tier="<?= e($tier->name ?? 'Member') ?>" data-color="<?= e($tc) ?>" data-since="<?= e(fmt_date($u->loyalty_joined_at, false)) ?>" data-brand="<?= e(setting('store_name')) ?>" data-url="<?= e(url('/') . '?member=' . $u->loyalty_id) ?>">
      <h2 class="h3">Digital membership card</h2>
      <div class="mcard" style="--tc:<?= e($tc) ?>"><div><small><?= e(setting('store_name')) ?> · <?= e($name) ?></small><b><?= e($tier->name ?? 'Member') ?></b><span><?= e($u->name) ?></span><code><?= e($u->loyalty_id) ?></code><small>Member since <?= e(fmt_date($u->loyalty_joined_at, false)) ?></small></div><div class="mqr" data-qr></div></div>
      <button type="button" class="btn btn-ghost" data-card-dl>Download card (PNG)</button>
    </div>
    <div class="card">
      <h2 class="h3">Daily check-in</h2>
      <?php if (setting('checkin_on', '1') === '1'): ?>
      <?php $st = \App\Services\LoyaltyEngagement::streak($u); ?>
      <p class="muted">🔥 <?= (int) $st ?>-day streak · +<?= (int) setting('checkin_points') ?> points a day<?= (int) setting('checkin_streak_every') ? ', bonus every ' . (int) setting('checkin_streak_every') . ' days' : '' ?></p>
      <form method="post" action="<?= e(url('account/loyalty/checkin')) ?>"><?= csrf_field() ?><button class="btn" <?= $active ? '' : 'disabled' ?>>Check in today</button></form>
      <?php else: ?><p class="muted">Check-ins are not enabled.</p><?php endif; ?>
      <h3 class="h3" style="margin-top:20px">Personalised for you</h3>
      <?php if ($rec['afford']): ?><p>You can already unlock <b><?= e($rec['afford']->name) ?></b> (<?= number_format($rec['afford']->points_cost) ?> pts). <a href="<?= e($tabUrl('rewards')) ?>">Redeem →</a></p><?php endif; ?>
      <?php if ($rec['next']): ?><p class="muted">Next goal: <b><?= e($rec['next']->name) ?></b> — <?= number_format($rec['next']->points_cost - $u->points) ?> points away.</p><?php endif; ?>
      <?php if (!$rec['afford'] && !$rec['next']): ?><p class="muted">Keep shopping to unlock rewards.</p><?php endif; ?>
    </div>
  </div>

  <?php if ($promos): ?><h2 class="h3">Active promotions</h2><div class="loy-grid"><?php foreach ($promos as $c): ?><div class="card loy-promo"><b><?= e($c->name) ?></b><small class="muted"><?= $c->type === 'multiplier' ? rtrim(rtrim(number_format((float) $c->value, 2), '0'), '.') . '× points' : ($c->type === 'cashback_pct' ? $c->value . '% extra cashback' : ($c->type === 'bonus_pct' ? '+' . $c->value . '% bonus points' : '+' . number_format($c->value) . ' bonus points')) ?><?= $c->ends_at ? ' · ends ' . e(fmt_date($c->ends_at, false)) : '' ?></small></div><?php endforeach; ?></div><?php endif; ?>

  <h2 class="h3">Badges</h2>
  <div class="loy-grid badges"><?php foreach ($badges as $b): ?><div class="card badge <?= $b->owned ? 'own' : '' ?>" title="<?= e($b->description) ?>"><span><?= e($b->icon) ?></span><b><?= e($b->name) ?></b><small class="muted"><?= e($b->description) ?></small></div><?php endforeach; ?><?php if (!$badges): ?><p class="muted">No badges yet.</p><?php endif; ?></div>

  <h2 class="h3">Recent activity</h2>
  <div class="card"><?php foreach ($recent as $r): ?><div class="loy-row"><span><b><?= e(\App\Services\Loyalty::TYPES[$r->type] ?? $r->type) ?></b> <small class="muted"><?= e($r->reason) ?> · <?= e(time_ago($r->created_at)) ?><?= $r->status === 'pending' ? ' · pending' : '' ?></small></span><b class="<?= $r->amount < 0 ? 'bad' : 'lime' ?>"><?= $r->amount > 0 ? '+' : '' ?><?= $r->wallet === 'cashback' ? money($r->amount) : number_format($r->amount) ?></b></div><?php endforeach; ?><?php if (!$recent): ?><p class="muted">Your activity will appear here.</p><?php endif; ?></div>

<?php elseif ($tab === 'wallet'): ?>
  <div class="loy-switch"><a class="<?= $w === 'points' ? 'on' : '' ?>" href="<?= e($tabUrl('wallet', '&wallet=points')) ?>">Points <b><?= number_format((int) $u->points) ?></b></a><?php if (Loyalty::cashbackOn()): ?><a class="<?= $w === 'cashback' ? 'on' : '' ?>" href="<?= e($tabUrl('wallet', '&wallet=cashback')) ?>">Cashback <b><?= money($u->cashback) ?></b></a><?php endif; ?><a href="#credit">Store credit <b><?= money($u->credit) ?></b></a></div>
  <p class="muted small">Points, cashback and store credit are three separate wallets with their own history. Points expire <?= (int) setting('loyalty_expiry_months') ? 'after ' . (int) setting('loyalty_expiry_months') . ' months' : 'never' ?>; cashback <?= (int) setting('cashback_expiry_months') ? 'after ' . (int) setting('cashback_expiry_months') . ' months' : 'does not expire' ?>.</p>
  <div class="loy-filters"><?php foreach (['' => 'All', 'earned' => 'Earned', 'redeemed' => 'Redeemed', 'expired' => 'Expired', 'reversed' => 'Reversed'] as $k => $l): ?><a class="<?= $f === $k ? 'on' : '' ?>" href="<?= e($tabUrl('wallet', '&wallet=' . $w . '&type=' . $k)) ?>"><?= e($l) ?></a><?php endforeach; ?></div>
  <div class="card"><?php foreach ($rows as $r): ?><div class="loy-row"><span><b><?= e(\App\Services\Loyalty::TYPES[$r->type] ?? $r->type) ?></b> <small class="muted"><?= e($r->reason) ?> · <?= e(fmt_date($r->created_at)) ?><?= $r->status === 'pending' ? ' · pending' : ($r->status === 'reversed' ? ' · cancelled' : '') ?><?= $r->expires_at && $r->remaining > 0 ? ' · expires ' . e(fmt_date($r->expires_at, false)) : '' ?></small></span><b class="<?= $r->amount < 0 ? 'bad' : 'lime' ?>"><?= $r->amount > 0 ? '+' : '' ?><?= $w === 'cashback' ? money($r->amount) : number_format($r->amount) ?></b></div><?php endforeach; ?><?php if (!count($rows)): ?><p class="muted">No transactions yet.</p><?php endif; ?></div>
  <?php if ($rows->hasPages()): ?><div class="loy-pager"><?php if ($rows->previousPageUrl()): ?><a class="btn btn-ghost" href="<?= e($rows->previousPageUrl()) ?>">← Newer</a><?php endif; ?><?php if ($rows->nextPageUrl()): ?><a class="btn btn-ghost" href="<?= e($rows->nextPageUrl()) ?>">Older →</a><?php endif; ?></div><?php endif; ?>
  <h2 class="h3" id="credit">Store credit history</h2><p class="muted small">Refund credit and promotional credit — separate from points and cashback. Spend it at checkout.</p>
  <div class="card"><?php foreach ($credit as $c): ?><div class="loy-row"><span><?= e($c->reason) ?> <small class="muted"><?= e(fmt_date($c->created_at, false)) ?></small></span><b class="<?= $c->amount < 0 ? 'bad' : 'lime' ?>"><?= ($c->amount > 0 ? '+' : '−') . money(abs($c->amount)) ?></b></div><?php endforeach; ?><?php if (!$credit): ?><p class="muted">No store credit activity.</p><?php endif; ?></div>

<?php elseif ($tab === 'tiers'): ?>
  <div class="loy-grid tiers"><?php foreach ($tiers as $t): $mine = $tier && $tier->id === $t->id; ?>
    <div class="card tier-card <?= $mine ? 'mine' : '' ?>" style="--tc:<?= e($t->color) ?>"><span class="loy-badge-t" style="--tc:<?= e($t->color) ?>">★ <?= e($t->name) ?></span><?= $mine ? '<em>Your tier</em>' : '' ?>
      <ul><li><?= rtrim(rtrim(number_format((float) $t->multiplier, 2), '0'), '.') ?>× points on every order</li>
      <?php if ($t->discount_pct > 0): ?><li><?= (float) $t->discount_pct ?>% member discount</li><?php endif; ?><?php if ($t->cashback_pct > 0): ?><li><?= (float) $t->cashback_pct ?>% cashback</li><?php endif; ?>
      <?php if ($t->free_delivery): ?><li>Free delivery</li><?php endif; ?><?php if ($t->early_access): ?><li>Early access to launches</li><?php endif; ?><?php if ($t->birthday_points): ?><li><?= number_format($t->birthday_points) ?> birthday points</li><?php endif; ?><?php if ($t->priority_support): ?><li>Priority support</li><?php endif; ?>
      <?php foreach (array_filter(array_map('trim', explode("\n", (string) $t->perks))) as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
      <small class="muted">Qualify: <?= $t->min_annual_spend ? money($t->min_annual_spend) . ' spend in ' . (int) setting('loyalty_tier_period', 12) . ' months' : '' ?><?= $t->min_lifetime_spend ? ' · ' . money($t->min_lifetime_spend) . ' lifetime' : '' ?><?= $t->min_points ? ' · ' . number_format($t->min_points) . ' points earned' : '' ?><?= !$t->min_annual_spend && !$t->min_lifetime_spend && !$t->min_points ? 'Joining is enough' : '' ?></small></div>
  <?php endforeach; ?></div>
  <div class="card"><b>Your progress</b><?php if ($progress['next']): ?><div class="loy-bar"><i style="width:<?= (int) $progress['pct'] ?>%"></i></div><small class="muted"><?= e(implode(' · ', $progress['needs']) ?: 'Almost there!') ?></small><?php else: ?><p class="muted">You hold the highest tier.</p><?php endif; ?>
    <small class="muted" style="display:block;margin-top:8px">Tier renews <?= $u->tier_until ? e(fmt_date($u->tier_until, false)) : 'never expires' ?> · history: <?php foreach (DB::table('loyalty_tier_history')->where('user_id', $u->id)->orderByDesc('id')->limit(3)->get() as $h): ?><?= e(fmt_date($h->created_at, false)) ?> → <?= e(\App\Services\LoyaltyTiers::find((int) $h->to_tier)->name ?? '?') ?>; <?php endforeach; ?></small></div>

<?php elseif ($tab === 'rewards'): ?>
  <form class="loy-search" method="get" action="<?= e(url('account/loyalty')) ?>"><input type="hidden" name="tab" value="rewards"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search rewards…"><select name="cat"><option value="">All categories</option><?php foreach ($cats as $c): ?><option <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select><select name="sort"><option value="points" <?= $sort === 'points' ? 'selected' : '' ?>>Points: low → high</option><option value="points_desc" <?= $sort === 'points_desc' ? 'selected' : '' ?>>Points: high → low</option></select><button class="btn btn-ghost">Filter</button></form>
  <p class="muted">You have <b><?= number_format((int) $u->points) ?></b> points.</p>
  <div class="loy-grid rewards"><?php foreach ($rewards as $rw): ?>
    <div class="card reward"><?php if ($rw->image): ?><img src="<?= e(upload_url($rw->image)) ?>" alt="" loading="lazy"><?php else: ?><div class="reward-ph">🎁</div><?php endif; ?>
      <div class="reward-b"><?php if ($rw->vip_only): ?><span class="pill">VIP</span><?php endif; ?><?php if ($rw->expires_at): ?><span class="pill">Ends <?= e(fmt_date($rw->expires_at, false)) ?></span><?php endif; ?><?php if ($rw->stock !== null): ?><span class="pill"><?= (int) $rw->stock ?> left</span><?php endif; ?>
        <h3><?= e($rw->name) ?></h3><p class="muted small"><?= e($rw->description) ?><?= $rw->min_order ? ' Min. order ' . money($rw->min_order) . '.' : '' ?></p>
        <div class="reward-f"><b><?= number_format($rw->points_cost) ?> pts</b><?php if ($rw->type === 'free_product' && $rw->cash_cost > 0): ?><small> + <?= money($rw->cash_cost) ?></small><?php endif; ?>
        <?php if ($rw->blocked): ?><button class="btn btn-ghost" disabled><?= e($rw->blocked) ?></button>
        <?php else: ?><form method="post" action="<?= e(url('account/loyalty/redeem')) ?>" onsubmit="return confirm('Redeem for <?= (int) $rw->points_cost ?> points?')"><?= csrf_field() ?><input type="hidden" name="reward_id" value="<?= (int) $rw->id ?>"><input type="hidden" name="token" value="<?= e($token . $rw->id) ?>"><button class="btn">Redeem</button></form><?php endif; ?></div></div></div>
  <?php endforeach; ?><?php if (!$rewards): ?><p class="muted">No rewards match.</p><?php endif; ?></div>
  <h2 class="h3">My rewards &amp; vouchers</h2>
  <div class="card"><?php foreach ($mine as $m): ?><div class="loy-row"><span><b><?= e($m->name) ?></b> <small class="muted"><?= e(fmt_date($m->created_at, false)) ?><?= $m->expires_at ? ' · expires ' . e(fmt_date($m->expires_at, false)) : '' ?></small><br><?php if ($m->code): ?><code class="vcode"><?= e($m->code) ?></code> <?php endif; ?><span class="pill <?= $m->status === 'issued' ? 'ok' : '' ?>"><?= e($m->status) ?></span></span>
    <?php if (in_array($m->status, ['issued', 'pending'], true)): ?><form method="post" action="<?= e(url('account/loyalty/redemption/' . $m->id . '/cancel')) ?>" onsubmit="return confirm('Cancel and get your points back?')"><?= csrf_field() ?><button class="btn btn-ghost">Cancel</button></form><?php endif; ?></div><?php endforeach; ?><?php if (!$mine): ?><p class="muted">Nothing redeemed yet.</p><?php endif; ?></div>
  <p class="muted small"><?= e(setting('loyalty_redeem_policy')) ?></p>

<?php elseif ($tab === 'challenges'): ?>
  <div class="loy-cols"><div class="card"><h2 class="h3">Daily check-in 🔥 <?= (int) $streak ?></h2><form method="post" action="<?= e(url('account/loyalty/checkin')) ?>"><?= csrf_field() ?><button class="btn" <?= $checked || !$active ? 'disabled' : '' ?>><?= $checked ? 'Checked in today ✓' : 'Check in' ?></button></form></div>
  <div class="card"><h2 class="h3">Games</h2>
    <?php if ($spin['on']): ?><div class="wheel-wrap"><canvas id="wheel" width="240" height="240" data-prizes='<?= e(json_encode(array_column($spin['prizes'], 'label'))) ?>'></canvas><div><button type="button" class="btn" data-spin <?= $spin['left'] > 0 && $active ? '' : 'disabled' ?>>Spin the wheel (<?= (int) $spin['left'] ?> left)</button><p class="muted small" data-game-msg></p></div></div><?php endif; ?>
    <?php if ($scratch['on']): ?><div class="scratch"><button type="button" class="btn btn-ghost" data-scratch <?= $scratch['left'] > 0 && $active ? '' : 'disabled' ?>>Scratch &amp; win (<?= (int) $scratch['left'] ?> left)</button><div class="scratch-card" data-scratch-card hidden>?</div></div><?php endif; ?>
    <?php if (!$spin['on'] && !$scratch['on']): ?><p class="muted">No games running right now.</p><?php endif; ?></div></div>
  <h2 class="h3">Active challenges</h2>
  <div class="loy-grid"><?php foreach ($challenges as $c): $pct = min(100, (int) floor($c->progress / max(1, $c->target) * 100)); ?>
    <div class="card chal <?= $c->done ? 'done' : '' ?>"><span class="pill"><?= e(ucfirst($c->period)) ?></span><h3><?= e($c->name) ?></h3><p class="muted small"><?= e($c->description) ?></p>
      <div class="loy-bar"><i style="width:<?= $pct ?>%"></i></div><small class="muted"><?= $c->kind === 'spend' ? money($c->progress) . ' / ' . money($c->target) : number_format($c->progress) . ' / ' . number_format($c->target) ?> · reward <?= $c->reward_points ? number_format($c->reward_points) . ' pts' : '' ?><?= $c->reward_cashback ? ' + ' . money($c->reward_cashback) : '' ?><?= $c->ends_at ? ' · ends ' . e(fmt_date($c->ends_at, false)) : '' ?></small><?= $c->done ? '<b class="lime"> ✓ Completed</b>' : '' ?></div>
  <?php endforeach; ?><?php if (!$challenges): ?><p class="muted">No active challenges.</p><?php endif; ?></div>
  <?php foreach ($comps as $c): ?><h2 class="h3">🏆 <?= e($c->name) ?></h2><div class="card"><?php foreach ($c->board as $i => $b): ?><div class="loy-row"><span>#<?= $i + 1 ?> <?= e($b['name']) ?></span><b><?= $c->metric === 'spend' ? money($b['score']) : number_format($b['score']) ?></b></div><?php endforeach; ?><?php if (!$c->board): ?><p class="muted">No scores yet.</p><?php endif; ?><small class="muted">Prizes (points): <?= e($c->prizes) ?><?= $c->ends_at ? ' · ends ' . e(fmt_date($c->ends_at, false)) : '' ?></small></div><?php endforeach; ?>
  <h2 class="h3">Top customers this month</h2><div class="card"><?php foreach ($month as $i => $b): ?><div class="loy-row"><span>#<?= $i + 1 ?> <?= e($b['name']) ?></span><b><?= money($b['score']) ?></b></div><?php endforeach; ?><?php if (!$month): ?><p class="muted">No data yet.</p><?php endif; ?></div>
  <h2 class="h3">Completed</h2><div class="card"><?php foreach ($hist as $h): ?><div class="loy-row"><span><?= e($h->name) ?> <small class="muted"><?= e(fmt_date($h->completed_at, false)) ?></small></span><b class="lime">+<?= number_format($h->reward_points) ?></b></div><?php endforeach; ?><?php if (!$hist): ?><p class="muted">Nothing completed yet.</p><?php endif; ?></div>

<?php elseif ($tab === 'referrals'): ?>
  <?php if (!$refOn): ?><div class="card"><p class="muted">The referral programme is paused.</p></div><?php else: ?>
  <div class="card"><h2 class="h3">Invite friends, earn rewards</h2>
    <p class="muted">You get <b><?= number_format((int) setting('ref_referrer_points')) ?> points</b><?= (int) setting('ref_referrer_cashback') ? ' + ' . money((int) setting('ref_referrer_cashback')) . ' cashback' : '' ?> when a friend makes a qualifying purchase. They get <?= (int) setting('ref_referee_pct') ? '<b>' . (int) setting('ref_referee_pct') . '% off</b> ' : '' ?><?= (int) setting('ref_referee_points') ? 'and ' . number_format((int) setting('ref_referee_points')) . ' welcome points' : '' ?>.</p>
    <div class="loy-copy"><input readonly value="<?= e($link) ?>" id="ref-link"><button type="button" class="btn" data-copy="#ref-link">Copy link</button></div>
    <p>Your code: <code class="vcode"><?= e($u->referral_code) ?></code></p>
    <div class="loy-share"><?php $txt = rawurlencode('Join me at ' . setting('store_name') . ' and get a welcome discount: ' . $link); ?>
      <a class="btn btn-ghost" target="_blank" rel="noopener" href="https://wa.me/?text=<?= $txt ?>">WhatsApp</a><a class="btn btn-ghost" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($link) ?>">Facebook</a><a class="btn btn-ghost" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=<?= $txt ?>">X</a><a class="btn btn-ghost" href="mailto:?subject=<?= rawurlencode('A gift from ' . setting('store_name')) ?>&body=<?= $txt ?>">Email</a></div>
    <?php if ($u->ambassador): ?><p class="lime">★ You are a brand ambassador — earn <?= (float) $u->ambassador_pct ?>% cashback on every order from your referrals.</p><?php endif; ?></div>
  <?php $tot = count($refs); $ok = count(array_filter($refs, fn ($x) => $x->status === 'rewarded')); $milestones = [3, 5, 10, 25]; $next = null; foreach ($milestones as $m) if ($ok < $m) { $next = $m; break; } ?>
  <div class="acct-stats"><div class="card"><b><?= $tot ?></b><small>Friends referred</small></div><div class="card"><b><?= $ok ?></b><small>Rewarded</small></div><div class="card"><b><?= number_format(array_sum(array_map(fn ($x) => (int) $x->reward_points, $refs))) ?></b><small>Points earned</small></div><?php if ($next): ?><div class="card"><b><?= $next - $ok ?></b><small>more for the <?= $next ?>-referral milestone</small></div><?php endif; ?></div>
  <h2 class="h3">Referral history</h2><div class="card"><?php foreach ($refs as $x): ?><div class="loy-row"><span><b><?= e(\App\Services\LoyaltyEngagement::maskName($x->referee_name)) ?></b> <small class="muted"><?= e(fmt_date($x->created_at, false)) ?></small></span><span class="pill <?= $x->status === 'rewarded' ? 'ok' : '' ?>"><?= $x->status === 'registered' ? 'Signed up' : ($x->status === 'qualified' ? 'Purchased · reward pending' : ($x->status === 'rewarded' ? 'Rewarded +' . number_format($x->reward_points) : 'Not eligible')) ?></span></div><?php endforeach; ?><?php if (!$refs): ?><p class="muted">Share your link to get started.</p><?php endif; ?></div>
  <?php endif; ?>

<?php elseif ($tab === 'activity'): ?>
  <form method="post" action="<?= e(url('account/loyalty/read')) ?>"><?= csrf_field() ?><button class="btn btn-ghost">Mark all as read</button></form>
  <div class="card" style="margin-top:12px"><?php foreach ($notes as $n): ?><div class="loy-row <?= $n->read_at ? '' : 'unread' ?>"><span><b><?= e($n->title) ?></b><br><small class="muted"><?= e($n->body) ?> · <?= e(time_ago($n->created_at)) ?></small></span><?php if (!$n->read_at): ?><form method="post" action="<?= e(url('account/loyalty/read')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $n->id ?>"><button class="btn btn-ghost">Read</button></form><?php endif; ?></div><?php endforeach; ?><?php if (!$notes): ?><p class="muted">No notifications.</p><?php endif; ?></div>

<?php elseif ($tab === 'settings'): $pr = Loyalty::prefs($u); ?>
  <div class="loy-cols"><div class="card"><h2 class="h3">Your account</h2>
    <dl class="loy-dl"><dt>Loyalty ID</dt><dd><code><?= e($u->loyalty_id) ?></code></dd><dt>Member since</dt><dd><?= e(fmt_date($u->loyalty_joined_at, false)) ?></dd><dt>Status</dt><dd><?= e(ucfirst($u->loyalty_status)) ?></dd><dt>Tier</dt><dd><?= e($tier->name ?? '—') ?></dd><dt>Birthday</dt><dd><?= $u->birthday ? e(fmt_date($u->birthday, false)) : '—' ?></dd></dl>
    <h3 class="h3">Notifications &amp; birthday</h3>
    <form method="post" action="<?= e(url('account/loyalty/prefs')) ?>"><?= csrf_field() ?>
      <?php foreach (['email' => 'Email', 'site' => 'On-site / in-account'] as $k => $l): ?><label class="chk"><input type="checkbox" name="<?= $k ?>" value="1" <?= $pr[$k] ? 'checked' : '' ?>><i></i><span><?= $l ?></span></label><?php endforeach; ?>
      <label class="chk"><input type="checkbox" name="push" value="1" <?= $pr['push'] ? 'checked' : '' ?>><i></i><span>Push (when supported)</span></label>
      <p class="muted small">Notify me about:</p>
      <?php foreach (['points' => 'Points & cashback', 'tier' => 'Tier changes', 'rewards' => 'Rewards', 'referral' => 'Referrals', 'challenge' => 'Challenges & badges', 'promo' => 'Promotions'] as $k => $l): ?><label class="chk"><input type="checkbox" name="t_<?= $k ?>" value="1" <?= $pr['types'][$k] ? 'checked' : '' ?>><i></i><span><?= $l ?></span></label><?php endforeach; ?>
      <?php if (!$u->birthday): ?><label class="fld"><span>Birthday (for birthday rewards — set once)</span><input type="date" name="birthday" max="<?= date('Y-m-d') ?>"></label><?php endif; ?>
      <button class="btn" style="margin-top:10px">Save</button></form></div>
  <div class="card"><h2 class="h3">Need help?</h2><form method="post" action="<?= e(url('account/loyalty/assist')) ?>"><?= csrf_field() ?><label class="fld"><span>Tell us what happened</span><textarea name="message" rows="4" required minlength="5"></textarea></label><button class="btn btn-ghost">Request assistance</button></form>
    <?php if ($tier && $tier->priority_support): ?><p class="lime small">★ Priority support is included in your tier.</p><?php endif; ?></div></div>
  <div class="card"><h2 class="h3">Programme terms</h2><p class="muted"><?= nl2br(e(setting('loyalty_terms'))) ?></p><h3 class="h3">Points expiry</h3><p class="muted"><?= nl2br(e(setting('loyalty_expiry_policy'))) ?></p><h3 class="h3">Redemption</h3><p class="muted"><?= nl2br(e(setting('loyalty_redeem_policy'))) ?> Points can be used from <?= number_format((int) setting('loyalty_min_redeem')) ?> points; 1.00 = <?= (float) setting('loyalty_redeem_rate') ?> points.</p></div>
<?php endif; ?>
</section>
<link rel="stylesheet" href="<?= e(asset_v('css/loyalty27.css')) ?>">
<script src="<?= e(asset_v('js/qr27.js')) ?>"></script>
<script src="<?= e(asset_v('js/loyalty27.js')) ?>" defer></script>
