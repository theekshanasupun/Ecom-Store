<?php
use App\Services\Catalog;
use App\Services\Orders;
/** @var array $o @var array $items @var array $events @var ?array $customer */
$paid = $o['payment_status'] === 'paid';
$awaiting = false;
foreach ($items as $it) if (in_array($it['type'], ['key', 'account']) && count($it['delivered']) < $it['qty']) $awaiting = true;
?>
<div class="toolbar"><div><span class="badge s-<?= e($o['status']) ?>"><?= e(Orders::STATUSES[$o['status']]) ?></span> <span class="badge s-<?= e($o['payment_status']) ?>"><?= e($o['payment_status']) ?></span> <span class="muted"><?= e(fmt_date($o['created_at'])) ?> · <?= e($o['payment_method']) ?><?= $o['payment_ref'] ? ' · ' . e($o['payment_ref']) : '' ?></span></div>
<div class="row-actions">
  <form method="post" action="<?= e(url('admin/orders/' . $o['id'] . '/resend')) ?>"><?= csrf_field() ?><button class="btn sm ghost">Resend email</button></form>
  <a class="btn sm ghost" href="<?= e(Orders::publicUrl($o)) ?>" target="_blank">Customer view ↗</a>
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
        <tr><td colspan="4" class="r muted">Shipping</td><td class="r"><?= money($o['shipping']) ?></td></tr>
        <?php if ($o['tax']): ?><tr><td colspan="4" class="r muted">Tax</td><td class="r"><?= money($o['tax']) ?></td></tr><?php endif; ?>
        <tr class="grand"><td colspan="4" class="r">Total</td><td class="r"><?= money($o['total']) ?></td></tr>
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
        <button class="btn block ghost">Update status</button>
        <p class="muted small">Setting <b>Shipped</b> or <b>Completed</b> emails the customer. Cancelling restocks physical items.</p>
      </form>
    </section>
    <section class="apanel"><h2>Customer</h2>
      <p><b><?= e($o['name']) ?></b><br><a href="mailto:<?= e($o['email']) ?>"><?= e($o['email']) ?></a><?= $o['phone'] ? '<br>' . e($o['phone']) : '' ?><?= $customer ? '<br><small class="muted">Account #' . (int) $customer['id'] . '</small>' : '<br><small class="muted">Guest checkout</small>' ?></p>
      <?php if ($o['has_physical']): ?><h3>Ship to</h3><p><?= e($o['ship_name']) ?><br><?= e($o['ship_address']) ?><?= $o['ship_address2'] ? '<br>' . e($o['ship_address2']) : '' ?><br><?= e(trim($o['ship_city'] . ', ' . $o['ship_state'] . ' ' . $o['ship_zip'], ', ')) ?><br><?= e($o['ship_country']) ?></p><?php endif; ?>
      <?php if ($o['notes']): ?><h3>Customer note</h3><p><?= e($o['notes']) ?></p><?php endif; ?>
      <small class="muted">IP <?= e($o['ip']) ?></small>
    </section>
  </div>
</div>
