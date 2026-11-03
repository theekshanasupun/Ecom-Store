<?php use App\Services\GiftCards;
/** @var array $rows @var int $page @var int $pages @var string $q @var string $st @var array $stats @var array $activity @var array $codes */
$sym = (string) setting('currency_symbol', '$');
$open = request()->boolean('new') || old('amount') !== null;
$tab = request('tab') === 'settings' ? 'settings' : (request('tab') === 'activity' ? 'activity' : 'cards');
$dsgOn = array_keys(GiftCards::designs());
$badge = ['active' => 'completed', 'used' => 'pending', 'void' => 'cancelled', 'pending' => 'pending'];
?>
<div class="kpis">
  <div class="kpi"><b><?= money($stats['outstanding']) ?></b><small>Outstanding balance (liability)</small></div>
  <div class="kpi"><b><?= money($stats['issued']) ?></b><small>Total issued · <?= (int) $stats['count'] ?> cards</small></div>
  <div class="kpi"><b><?= money($stats['redeemed']) ?></b><small>Redeemed so far</small></div>
  <div class="kpi"><b><?= (int) $stats['active'] ?></b><small>Active cards · <?= (int) $stats['scheduled'] ?> scheduled</small></div>
</div>
<div class="toolbar"><div class="quick"><span class="muted small">Customers buy gift cards at <a href="<?= e(url('gift-cards')) ?>" target="_blank">/gift-cards</a> · balance checker at <a href="<?= e(url('gift-cards/balance')) ?>" target="_blank">/gift-cards/balance</a></span></div>
  <div class="row-actions"><a class="btn ghost" href="<?= e(url('admin/gift-cards/export')) ?>">Export CSV</a><button type="button" class="btn" data-drawer-open="#gc-drawer">＋ Issue gift card</button></div></div>

<div class="tabs2" data-tabs><button type="button" data-tab="cards" class="<?= $tab === 'cards' ? 'on' : '' ?>">Cards</button><button type="button" data-tab="settings" class="<?= $tab === 'settings' ? 'on' : '' ?>">Setup &amp; designs</button><button type="button" data-tab="activity" class="<?= $tab === 'activity' ? 'on' : '' ?>">Recent activity</button></div>

<section data-pane="cards" <?= $tab === 'cards' ? '' : 'hidden' ?>>
  <form class="filters" method="get" style="margin-bottom:14px"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Code, recipient or sender…">
    <select name="status" onchange="this.form.submit()"><option value="">Any status</option><?php foreach (['active' => 'Active', 'used' => 'Fully used', 'void' => 'Voided'] as $k => $l): ?><option value="<?= $k ?>" <?= $st === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select><button class="btn sm ghost">Search</button></form>
  <section class="apanel flush"><div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>Card</th><th>Recipient</th><th class="r">Balance</th><th>Status</th><th>Expires</th><th>Source</th><th>Created</th></tr></thead><tbody>
    <?php foreach ($rows as $g): $d = GiftCards::DESIGNS[$g->design] ?? GiftCards::DESIGNS['classic']; ?>
    <tr><td><a href="<?= e(url('admin/gift-cards/' . $g->id)) ?>" class="gcmini"><i style="background:linear-gradient(135deg,<?= e($d[1]) ?>,<?= e($d[2]) ?>)"></i><b><?= e($g->code) ?></b></a></td>
    <td><?= e($g->recipient_name ?: '—') ?><small><?= e($g->recipient_email) ?><?= $g->deliver_at && !$g->sent_at ? ' · scheduled ' . e($g->deliver_at->format('M j')) : '' ?></small></td>
    <td class="r"><b><?= money($g->balance) ?></b><small>of <?= money($g->initial) ?></small></td>
    <td><span class="badge s-<?= $badge[$g->status] ?? 'pending' ?>"><?= e($g->isExpired() && $g->status === 'active' ? 'expired' : $g->status) ?></span></td>
    <td class="muted"><?= $g->expires_at ? e($g->expires_at->format('Y-m-d')) : 'Never' ?></td><td class="muted"><?= e($g->created_via) ?><?= $g->order_id ? ' · <a href="' . e(url('admin/orders/' . $g->order_id)) . '">order</a>' : '' ?></td>
    <td class="muted"><?= e(fmt_date($g->created_at, false)) ?></td></tr>
    <?php endforeach; ?><?php if (!$rows): ?><tr><td colspan="7" class="empty">No gift cards yet. <a href="#gc-drawer" data-drawer-open="#gc-drawer">Issue one</a> or wait for the first purchase.</td></tr><?php endif; ?>
    </tbody></table></div></section>
  <?= partial('admin/_pager', compact('page', 'pages')) ?>
</section>

<section data-pane="settings" <?= $tab === 'settings' ? '' : 'hidden' ?>>
<form method="post" action="<?= e(url('admin/gift-cards/settings')) ?>" class="gcsetup"><?= csrf_field() ?>
  <div class="cols"><div class="stack wide">
    <section class="apanel"><h2>Selling gift cards</h2>
      <?= field_check('gc_on', 'Gift cards are for sale in the store', GiftCards::enabled(), 'Turns the /gift-cards page and the redeem box at checkout on or off.') ?>
      <?= field_text('gc_amounts', 'Preset amounts', implode(', ', array_map(fn ($c) => rtrim(rtrim(number_format($c / 100, 2, '.', ''), '0'), '.'), GiftCards::amounts())), ['hint' => 'Comma separated, in ' . $sym . ' — e.g. 25, 50, 100, 250']) ?>
      <?= field_check('gc_custom', 'Also let customers type their own amount', setting('gc_custom', '1') === '1') ?>
      <div class="g2"><?= field_text('gc_min', 'Custom amount minimum', setting('gc_min', 10), ['type' => 'number', 'step' => '1', 'min' => '1']) ?><?= field_text('gc_max', 'Custom amount maximum', setting('gc_max', 500), ['type' => 'number', 'step' => '1', 'min' => '1']) ?></div>
      <?= field_text('gc_expiry_months', 'Expire after (months, 0 = never)', setting('gc_expiry_months', 0), ['type' => 'number', 'min' => '0', 'hint' => 'Applies to newly issued cards. Check your local gift card laws before setting an expiry.']) ?>
    </section>
    <section class="apanel"><h2>Email &amp; terms</h2>
      <?= field_text('gc_email_subject', 'Email subject', setting('gc_email_subject', 'You received a {store} gift card'), ['hint' => 'Use {store}, {amount} and {from}.']) ?>
      <?= field_area('gc_terms', 'Terms shown under the code in the email', setting('gc_terms', ''), ['rows' => 3, 'placeholder' => 'Valid for online purchases. Not redeemable for cash.']) ?>
    </section>
  </div>
  <div class="stack"><section class="apanel"><h2>Card designs</h2><p class="muted small" style="margin-top:0">Tick the designs customers can choose from.</p>
    <div class="gcdesigns"><?php foreach (GiftCards::DESIGNS as $k => $d): ?><label class="gcdesign"><input type="checkbox" name="gc_designs[]" value="<?= e($k) ?>" <?= in_array($k, $dsgOn, true) ? 'checked' : '' ?>><span style="background:linear-gradient(135deg,<?= e($d[1]) ?>,<?= e($d[2]) ?>);color:<?= e($d[3]) ?>"><b><?= e($d[0]) ?></b><small><?= e($sym) ?>50</small></span></label><?php endforeach; ?></div>
    <div class="actions"><button class="btn block">Save gift card settings</button></div></section></div></div>
</form>
</section>

<section data-pane="activity" <?= $tab === 'activity' ? '' : 'hidden' ?>><section class="apanel"><ul class="timeline">
  <?php foreach ($activity as $a): ?><li><span><a href="<?= e(url('admin/gift-cards/' . $a->gift_card_id)) ?>"><b><?= e($codes[$a->gift_card_id] ?? '#' . $a->gift_card_id) ?></b></a> — <?= e($a->reason) ?> <b class="<?= $a->amount < 0 ? 'warn' : '' ?>"><?= $a->amount ? ($a->amount > 0 ? '+' : '−') . money(abs($a->amount)) : '' ?></b></span><small><?= e($a->actor) ?> · <?= e(time_ago($a->created_at)) ?></small></li><?php endforeach; ?>
  <?php if (!$activity): ?><li class="muted">Nothing yet.</li><?php endif; ?></ul></section></section>

<div class="drawer-panel dp-wide" id="gc-drawer" hidden <?= $open ? 'data-autoopen' : '' ?>>
<form method="post" action="<?= e(url('admin/gift-cards/issue')) ?>" id="gc-issue" data-sym="<?= e($sym) ?>" data-store="<?= e(strtoupper(setting('store_name'))) ?>"><?= csrf_field() ?>
  <div class="dp-head"><h2>Issue a gift card</h2><button type="button" data-drawer-close aria-label="Close">✕</button></div>
  <div class="dp-body"><div class="dp-split">
    <div>
      <div class="g2"><?= field_text('amount', 'Amount (' . $sym . ')', old('amount'), ['type' => 'number', 'step' => '0.01', 'min' => '1', 'required' => true, 'placeholder' => '50']) ?>
        <?= field_text('qty', 'How many cards', old('qty', 1), ['type' => 'number', 'min' => '1', 'max' => '100', 'hint' => 'More than 1 = bulk codes without a recipient (for promotions / print).']) ?></div>
      <div class="gcdesigns sm"><?php foreach (GiftCards::DESIGNS as $k => $d): ?><label class="gcdesign"><input type="radio" name="design" value="<?= e($k) ?>" data-c1="<?= e($d[1]) ?>" data-c2="<?= e($d[2]) ?>" data-fg="<?= e($d[3]) ?>" <?= old('design', 'classic') === $k ? 'checked' : '' ?>><span style="background:linear-gradient(135deg,<?= e($d[1]) ?>,<?= e($d[2]) ?>);color:<?= e($d[3]) ?>"><b><?= e($d[0]) ?></b></span></label><?php endforeach; ?></div>
      <div data-single>
        <div class="g2"><?= field_text('recipient_name', 'Recipient name', old('recipient_name')) ?><?= field_text('recipient_email', 'Recipient email', old('recipient_email'), ['type' => 'email', 'placeholder' => 'Leave empty to just create the code']) ?></div>
        <div class="g2"><?= field_text('sender_name', 'From', old('sender_name', setting('store_name'))) ?><?= field_text('deliver_at', 'Send on (optional)', old('deliver_at'), ['type' => 'date', 'hint' => 'Empty = right away']) ?></div>
        <?= field_area('message', 'Personal message', old('message'), ['rows' => 3]) ?>
        <?= field_check('send', 'Email the card to the recipient', true) ?>
      </div>
      <div class="g2"><?= field_text('expires_at', 'Expires on (optional)', old('expires_at'), ['type' => 'date', 'hint' => 'Empty = ' . ((int) setting('gc_expiry_months', 0) ? 'default ' . (int) setting('gc_expiry_months') . ' months' : 'never')]) ?><?= field_text('note', 'Internal note', old('note'), ['placeholder' => 'Competition winner, refund…']) ?></div>
    </div>
    <aside class="livepane"><h4>Live preview</h4>
      <div class="gc-card gcp" id="gcp" style="--g1:#c6ff3d;--g2:#7ad100;--gfg:#0b0b0d"><div class="gc-card-top"><b><?= e(strtoupper(setting('store_name'))) ?></b><span>GIFT CARD</span></div><div class="gc-card-amt" data-gcp-amt><?= $sym ?>50</div><div class="gc-card-code">XXXX-XXXX-XXXX-XXXX</div><div class="gc-card-to" data-gcp-to></div></div></aside>
  </div></div>
  <div class="dp-foot"><span class="grow"></span><button type="button" class="btn ghost" data-drawer-close>Cancel</button><button class="btn">Create gift card</button></div>
</form></div>
