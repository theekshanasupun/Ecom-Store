<?php use App\Services\GiftCards;
/** @var \App\Models\GiftCard $g @var array $log */
$d = GiftCards::DESIGNS[$g->design] ?? GiftCards::DESIGNS['classic'];
$badge = ['active' => 'completed', 'used' => 'pending', 'void' => 'cancelled'];
?>
<div class="toolbar"><div><span class="badge s-<?= $badge[$g->status] ?? 'pending' ?>"><?= e($g->isExpired() && $g->status === 'active' ? 'expired' : $g->status) ?></span> <span class="muted">Created <?= e(fmt_date($g->created_at)) ?> · <?= e($g->created_via) ?><?= $g->order_id ? ' · <a href="' . e(url('admin/orders/' . $g->order_id)) . '">order #' . (int) $g->order_id . '</a>' : '' ?></span></div>
  <div class="row-actions"><a class="btn sm ghost" href="<?= e(url('admin/gift-cards')) ?>">← All cards</a>
  <?php if ($g->status === 'void'): ?><form method="post" action="<?= e(url('admin/gift-cards/' . $g->id . '/status')) ?>"><?= csrf_field() ?><input type="hidden" name="to" value="active"><button class="btn sm">Re-activate</button></form>
  <?php else: ?><form method="post" action="<?= e(url('admin/gift-cards/' . $g->id . '/status')) ?>" data-confirm="Void this card? The balance can no longer be spent."><?= csrf_field() ?><input type="hidden" name="to" value="void"><button class="btn sm ghost danger">Void card</button></form><?php endif; ?></div></div>
<div class="cols">
  <div class="stack wide">
    <section class="apanel"><div class="gc-card gcp" style="--g1:<?= e($d[1]) ?>;--g2:<?= e($d[2]) ?>;--gfg:<?= e($d[3]) ?>;max-width:440px"><div class="gc-card-top"><b><?= e(strtoupper(setting('store_name'))) ?></b><span>GIFT CARD</span></div><div class="gc-card-amt"><?= money($g->balance) ?></div><div class="gc-card-code"><?= e($g->code) ?></div><div class="gc-card-to"><?= $g->recipient_name ? 'For ' . e($g->recipient_name) : '' ?></div></div>
      <p class="muted small" style="margin:12px 0 0">Original value <b><?= money($g->initial) ?></b><?= $g->expires_at ? ' · expires ' . e($g->expires_at->format('M j, Y')) : ' · never expires' ?> <button type="button" class="btn sm ghost" data-copy="<?= e($g->code) ?>">Copy</button></p></section>
    <section class="apanel"><h2>History</h2><ul class="timeline"><?php foreach ($log as $l): ?><li><span><?= e($l->reason) ?> <b class="<?= $l->amount < 0 ? 'warn' : '' ?>"><?= $l->amount ? ($l->amount > 0 ? '+' : '−') . money(abs($l->amount)) : '' ?></b> <span class="muted">→ balance <?= money($l->balance) ?></span><?= $l->order_id ? ' · <a href="' . e(url('admin/orders/' . $l->order_id)) . '">order</a>' : '' ?></span><small><?= e($l->actor) ?> · <?= e(fmt_date($l->created_at)) ?></small></li><?php endforeach; ?></ul></section>
  </div>
  <div class="stack">
    <section class="apanel"><h2>Adjust balance</h2>
      <form method="post" action="<?= e(url('admin/gift-cards/' . $g->id . '/adjust')) ?>"><?= csrf_field() ?>
        <div class="g2"><?= field_select('dir', 'Action', ['add' => 'Add funds', 'remove' => 'Remove funds'], 'add') ?><?= field_text('amount', 'Amount', '', ['type' => 'number', 'step' => '0.01', 'min' => '0.01', 'required' => true]) ?></div>
        <?= field_text('reason', 'Reason', '', ['placeholder' => 'Goodwill, correction…']) ?><button class="btn block">Update balance</button></form></section>
    <section class="apanel"><h2>Recipient</h2><p><b><?= e($g->recipient_name ?: '—') ?></b><br><?= e($g->recipient_email ?: 'No email') ?><br><small class="muted">From <?= e($g->sender_name ?: '—') ?><?= $g->message ? '<br>“' . e($g->message) . '”' : '' ?></small></p>
      <p class="muted small"><?= $g->sent_at ? 'Emailed ' . e(fmt_date($g->sent_at)) : ($g->deliver_at ? 'Scheduled for ' . e(fmt_date($g->deliver_at, false)) : 'Not emailed yet') ?></p>
      <form method="post" action="<?= e(url('admin/gift-cards/' . $g->id . '/resend')) ?>"><?= csrf_field() ?><?= field_text('email', 'Send to', $g->recipient_email ?: '', ['type' => 'email', 'required' => true]) ?><button class="btn block ghost"><?= $g->sent_at ? 'Resend email' : 'Send email now' ?></button></form></section>
    <?php if ($g->note): ?><section class="apanel"><h2>Note</h2><p class="muted"><?= e($g->note) ?></p></section><?php endif; ?>
  </div>
</div>
