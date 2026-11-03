<?php
use App\Services\Catalog;
use App\Services\Orders;
/** @var array $o @var array $items @var array $events @var ?array $customer @var array $carriers */
$paid = $o['payment_status'] === 'paid';
$awaiting = false;
foreach ($items as $it) if (in_array($it['type'], ['key', 'account']) && count($it['delivered']) < $it['qty']) $awaiting = true;
?>
<div class="toolbar"><div><span class="badge s-<?= e($o['status']) ?>"><?= e(Orders::STATUSES[$o['status']]) ?></span> <span class="badge s-<?= e($o['payment_status']) ?>"><?= e($o['payment_status']) ?></span> <?php if (($o['source'] ?? '') === 'admin'): ?><span class="badge">created by staff</span> <?php endif; ?><span class="muted"><?= e(fmt_date($o['created_at'])) ?> · <?= e($o['payment_method']) ?><?= $o['payment_ref'] ? ' · ' . e($o['payment_ref']) : '' ?></span></div>
<div class="row-actions">
  <a class="btn sm ghost" href="<?= e(url('admin/orders/' . $o['id'] . '/print')) ?>" target="_blank">Packing slip</a>
  <form method="post" action="<?= e(url('admin/orders/' . $o['id'] . '/resend')) ?>"><?= csrf_field() ?><button class="btn sm ghost">Resend email</button></form>
  <?php if ($o['status'] === 'shipped' || $o['tracking']): ?><form method="post" action="<?= e(url('admin/orders/' . $o['id'] . '/resend-shipping')) ?>"><?= csrf_field() ?><button class="btn sm ghost">Resend shipping email</button></form><?php endif; ?>
  <a class="btn sm ghost" href="<?= e($o->publicUrl()) ?>" target="_blank">Customer view ↗</a>
</div></div>

<div class="cols">
  <div class="stack wide">
    <section class="apanel flush"><div class="tbl-wrap"><table class="tbl">
      <thead><tr><th></th><th>Item</th><th class="r">Price</th><th class="r">Qty</th><th class="r">Total</th></tr></thead><tbody>
      <?php foreach ($items as $it): ?>
      <tr><td class="thumbcell" style="--c:<?= e($it['color'] ?: '#c6ff3d') ?>"><div class="thumb"><?= Catalog::media(['image' => $it['image'], 'art' => $it['art'], 'art_color' => $it['art_color'], 'color' => $it['color'] ?: '#c6ff3d', 'type' => $it['type'], 'name' => $it['name']], false) ?></div></td>
        <td><b><?= e($it['name']) ?></b><small><?= e($it['options']) ?> <span class="badge t-<?= e($it['type']) ?>"><?= e($it['type']) ?></span></small>
          <?php if ($it['delivered']): ?><div class="keylist ro"><?php foreach ($it['delivered'] as $k): ?><code><?= e($k) ?></code><?php endforeach; ?></div><?php elseif (in_array($it['type'], ['key', 'account']) && $paid): ?><small class="warn">⚠ Awaiting stock</small><?php endif; ?></td>
        <td class="r"><?= money($it['price']) ?></td><td class="r"><?= (int) $it['qty'] ?></td><td class="r"><b><?= money($it['total']) ?></b></td></tr>
      <?php endforeach; ?></tbody>
      <tfoot>
        <tr><td colspan="4" class="r muted">Subtotal</td><td class="r"><?= money($o['subtotal']) ?></td></tr>
        <?php if ($o['discount']): ?><tr><td colspan="4" class="r muted">Discount <?= e($o['coupon_code']) ?></td><td class="r">−<?= money($o['discount']) ?></td></tr><?php endif; ?>
        <tr><td colspan="4" class="r muted">Shipping<?= $o['ship_method'] ? ' · ' . e($o['ship_method']) : '' ?></td><td class="r"><?= money($o['shipping']) ?></td></tr>
        <?php if ($o['tax']): ?><tr><td colspan="4" class="r muted">Tax</td><td class="r"><?= money($o['tax']) ?></td></tr><?php endif; ?>
        <tr class="grand"><td colspan="4" class="r">Total</td><td class="r"><?= money($o['total']) ?></td></tr>
        <?php if ($o['giftcard_used']): ?><tr><td colspan="4" class="r muted">Paid with gift card <?= e($o['giftcard_code']) ?></td><td class="r">−<?= money($o['giftcard_used']) ?></td></tr><?php endif; ?>
        <?php if ($o['credit_used']): ?><tr><td colspan="4" class="r muted">Paid with store credit</td><td class="r">−<?= money($o['credit_used']) ?></td></tr><?php endif; ?>
        <?php if ($o['points_used']): ?><tr><td colspan="4" class="r muted">Paid with <?= number_format($o['points_used']) ?> loyalty points</td><td class="r">−<?= money($o['points_value']) ?></td></tr><?php endif; ?>
        <?php if ($o['cashback_used']): ?><tr><td colspan="4" class="r muted">Paid with cashback</td><td class="r">−<?= money($o['cashback_used']) ?></td></tr><?php endif; ?>
        <?php if ($o['tier_discount']): ?><tr><td colspan="4" class="r muted">Member discount (included in total)</td><td class="r">−<?= money($o['tier_discount']) ?></td></tr><?php endif; ?>
        <?php if ($o['giftcard_used'] || $o['credit_used'] || $o['points_used'] || $o['cashback_used']): ?><tr><td colspan="4" class="r muted">Due via <?= e($o['payment_method']) ?></td><td class="r"><b><?= money($o->due()) ?></b></td></tr><?php endif; ?>
        <?php if ($o['savings']): ?><tr><td colspan="4" class="r muted">Customer saved</td><td class="r" style="color:var(--accent)"><?= money($o['savings']) ?></td></tr><?php endif; ?>
      </tfoot></table></div></section>

    <section class="apanel"><h2>Timeline</h2>
      <form method="post" action="<?= e(url('admin/orders/' . $o['id'] . '/note')) ?>" class="inline-add"><?= csrf_field() ?><input name="note" placeholder="Add an internal note…"><button class="btn sm">Add</button></form>
      <ul class="timeline"><?php foreach ($events as $ev): ?><li><span><?= e($ev['message']) ?></span><small><?= e($ev['actor']) ?> · <?= e(fmt_date($ev['created_at'])) ?></small></li><?php endforeach; ?></ul>
    </section>
  </div>

  <div class="stack">
    <section class="apanel"><h2>Fulfilment</h2>
      <?php if (!$paid && !in_array($o['status'], ['cancelled', 'refunded'])): ?>
      <form method="post" action="<?= e(url('admin/orders/' . $o['id'] . '/paid')) ?>" onsubmit="return confirm('Mark as paid and deliver digital items?')"><?= csrf_field() ?>
        <?= field_text('ref', 'Payment reference (optional)', '', ['placeholder' => 'Bank ref, receipt #…']) ?><button class="btn block">✓ Mark as paid &amp; deliver</button></form><hr>
      <?php endif; ?>
      <?php if ($paid && $awaiting): ?>
      <form method="post" action="<?= e(url('admin/orders/' . $o['id'] . '/deliver')) ?>"><?= csrf_field() ?><p class="warn small">Some digital units are undelivered. Add keys to the product first.</p><button class="btn block">Deliver pending keys</button></form><hr>
      <?php endif; ?>
      <form method="post" action="<?= e(url('admin/orders/' . $o['id'] . '/status')) ?>"><?= csrf_field() ?>
        <?= field_select('status', 'Order status', Orders::STATUSES, $o['status']) ?>
        <?php if ($o['has_physical']): ?><?= field_text('tracking', 'Tracking number', $o['tracking']) ?><?php endif; ?>
        <?= field_check('notify', 'Email the customer about this change', true) ?>
        <button class="btn block ghost">Update status</button>
        <p class="muted small">Setting <b>Shipped</b> or <b>Completed</b> emails the customer. Cancelling restocks physical items.</p>
      </form>
    </section>
    <?php if ($o['has_physical']): ?>
    <section class="apanel"><div class="ph"><h2>Shipping details</h2><?php if ($o['shipped_at']): ?><small class="muted">Shipped <?= e(fmt_date($o['shipped_at'])) ?></small><?php endif; ?></div>
      <?php if ($o['carrier'] || $o['tracking']): ?><p><b><?= e($o['carrier'] ?: 'Tracking') ?></b> <?= e($o['tracking']) ?><?php if ($o->trackingLink()): ?> · <a href="<?= e($o->trackingLink()) ?>" target="_blank" rel="noopener">track ↗</a><?php endif; ?></p><?php endif; ?>
      <details class="qdet" <?= $o['status'] === 'processing' || $o['status'] === 'pending' ? 'open' : '' ?>><summary class="btn sm ghost">Edit shipping details</summary>
      <form method="post" action="<?= e(url('admin/orders/' . $o['id'] . '/shipping')) ?>" style="margin-top:14px"><?= csrf_field() ?>
        <div class="g2"><label class="fld"><span>Carrier</span><input name="carrier" list="carrier-list" value="<?= e($o['carrier']) ?>" placeholder="DHL, UPS…"><datalist id="carrier-list"><?php foreach ($carriers as $c): if ($c): ?><option value="<?= e($c) ?>"><?php endif; endforeach; ?></datalist></label>
          <?= field_text('tracking', 'Tracking number', $o['tracking']) ?></div>
        <?= field_text('tracking_url', 'Tracking link', $o['tracking_url'], ['placeholder' => 'https://…']) ?>
        <div class="g2"><?= field_text('ship_method', 'Delivery method', $o['ship_method'], ['placeholder' => 'Standard, Express…']) ?><?= field_text('shipping_cost', 'Shipping cost', from_cents((int) $o['shipping']), ['type' => 'number', 'step' => '0.01', 'min' => '0', 'hint' => 'Changing it updates the order total.']) ?></div>
        <h3>Ship to</h3>
        <?= field_text('ship_name', 'Recipient', $o['ship_name']) ?><?= field_text('ship_address', 'Address', $o['ship_address']) ?><?= field_text('ship_address2', 'Apartment, suite', $o['ship_address2']) ?>
        <div class="g3"><?= field_text('ship_city', 'City', $o['ship_city']) ?><?= field_text('ship_state', 'State', $o['ship_state']) ?><?= field_text('ship_zip', 'Postal code', $o['ship_zip']) ?></div>
        <?= field_text('ship_country', 'Country', $o['ship_country']) ?>
        <?php if (!in_array($o['status'], ['shipped', 'completed', 'cancelled', 'refunded'])): ?><?= field_check('mark_shipped', 'Mark the order as shipped', false) ?><?php endif; ?>
        <?= field_check('notify', 'Email the customer the shipping details', false) ?>
        <button class="btn block">Save shipping details</button>
      </form></details>
    </section>
    <?php endif; ?>
    <?php if ($customer && $o['payment_status'] === 'paid'): ?>
    <section class="apanel"><h2>Refund to store credit</h2>
      <form method="post" action="<?= e(url('admin/orders/' . $o['id'] . '/refund-credit')) ?>" onsubmit="return confirm('Add this amount to the customer\'s store credit?')"><?= csrf_field() ?>
        <?= field_text('amount', 'Amount', from_cents((int) $o['total']), ['type' => 'number', 'step' => '0.01', 'min' => '0.01', 'hint' => 'Credit can be spent on any future order.']) ?>
        <?= field_check('mark_refunded', 'Also mark the order as refunded (restocks physical items)', false) ?>
        <button class="btn ghost block">Refund to store credit</button></form>
    </section>
    <?php endif; ?>
    <section class="apanel"><h2>Customer</h2>
      <p><b><?= e($o['name']) ?></b><br><a href="mailto:<?= e($o['email']) ?>"><?= e($o['email']) ?></a><?= $o['phone'] ? '<br>' . e($o['phone']) : '' ?><?= $customer ? '<br><small class="muted">Account #' . (int) $customer['id'] . '</small>' : '<br><small class="muted">Guest checkout</small>' ?></p>
      <?php if ($o['has_physical']): ?><h3>Ship to</h3><p><?= e($o['ship_name']) ?><br><?= e($o['ship_address']) ?><?= $o['ship_address2'] ? '<br>' . e($o['ship_address2']) : '' ?><br><?= e(trim($o['ship_city'] . ', ' . $o['ship_state'] . ' ' . $o['ship_zip'], ', ')) ?><br><?= e($o['ship_country']) ?></p><?php endif; ?>
      <?php if ($o['notes']): ?><h3>Customer note</h3><p><?= e($o['notes']) ?></p><?php endif; ?>
      <small class="muted">IP <?= e($o['ip'] ?: '—') ?></small>
      <details class="qdet" style="margin-top:12px"><summary class="btn sm ghost">Edit contact details</summary>
        <form method="post" action="<?= e(url('admin/orders/' . $o['id'] . '/contact')) ?>" style="margin-top:12px"><?= csrf_field() ?>
          <?= field_text('name', 'Name', $o['name']) ?><?= field_text('email', 'Email', $o['email'], ['type' => 'email', 'required' => true]) ?><?= field_text('phone', 'Phone', $o['phone']) ?><button class="btn sm block">Save</button></form></details>
    </section>
  </div>
</div>
