<?php
use App\Services\Catalog;
use App\Services\Orders;
/** @var array $o @var array $items */
$paid = $o['payment_status'] === 'paid';
$tok = '?t=' . $o['token'];
$headline = $paid ? 'Thank you.' : ($o['status'] === 'cancelled' ? 'Cancelled.' : 'Order placed.');
?>
<section class="block narrow-top">
  <p class="eyebrow lime">Order <?= e($o['number']) ?> · <?= e(fmt_date($o['created_at'])) ?></p>
  <h1 class="mega" style="font-size:clamp(46px,11vw,120px)"><?= e($headline) ?></h1>
  <div class="statusline">
    <span class="badge b-<?= e($o['payment_status']) ?>"><?= $paid ? 'Paid' : ucfirst($o['payment_status']) ?></span>
    <span class="badge b-<?= e($o['status']) ?>"><?= e(Orders::STATUSES[$o['status']] ?? $o['status']) ?></span>
    <?php if ($o['tracking']): ?><span class="badge">Tracking: <?= e($o['tracking']) ?></span><?php endif; ?>
  </div>

  <?php if (!$paid && $o['status'] !== 'cancelled'): ?>
  <div class="card callout">
    <?php if ($o['payment_method'] === 'bank'): ?>
      <h3>Pay by bank transfer</h3><p class="muted">Use <b><?= e($o['number']) ?></b> as the payment reference. We deliver as soon as the payment lands.</p><pre class="bankpre"><?= e(setting('pay_bank_info')) ?></pre>
    <?php elseif ($o['payment_method'] === 'cod'): ?>
      <h3>Pay on delivery</h3><p class="muted">Have <b><?= money($o['total']) ?></b> ready when your parcel arrives.</p>
    <?php else: ?>
      <h3>Payment not completed</h3><p class="muted">Your order is saved. Finish paying to receive it.</p>
      <form method="post" action="<?= e(url('order/' . $o['number'] . '/pay' . $tok)) ?>"><?= csrf_field() ?><button class="btn">Pay <?= money($o['total']) ?> now →</button></form>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="order-grid">
    <div class="order-main">
      <?php foreach ($items as $it): $cc = $it['color'] ?: '#c6ff3d'; ?>
      <article class="card oitem" style="--c:<?= e($cc) ?>">
        <div class="oitem-top">
          <div class="oitem-media"><?= Catalog::media(['image' => $it['image'], 'art' => $it['art'], 'art_color' => $it['art_color'], 'color' => $cc, 'type' => $it['type'], 'name' => $it['name']], false) ?></div>
          <div class="oitem-info"><span class="dot-label"><i></i><?= e($it['category']) ?></span><h3><?= e($it['name']) ?></h3><p class="muted"><?= e($it['options']) ?> × <?= (int) $it['qty'] ?></p></div>
          <b><?= money($it['total']) ?></b>
        </div>
        <?php if (in_array($it['type'], ['key', 'account'], true)): ?>
          <?php if ($paid && $it['delivered']): ?>
            <div class="keys"><?php foreach ($it['delivered'] as $k): ?>
              <div class="keybox"><code data-secret><?= e($k) ?></code><button type="button" class="chip" data-copy>Copy</button></div>
            <?php endforeach; ?></div>
            <?php if (count($it['delivered']) < $it['qty']): ?><p class="tag warn">Part of this order is waiting for stock — we'll email you the rest.</p><?php endif; ?>
          <?php elseif ($paid): ?><p class="tag warn">Awaiting stock — we'll email your key as soon as it's ready.</p>
          <?php else: ?><p class="tag">Your key appears here the moment payment is confirmed.</p><?php endif; ?>
        <?php elseif ($it['type'] === 'file'): ?>
          <?php if ($paid): ?><a class="btn btn-cc" href="<?= e(url('download/' . $it['id'] . $tok)) ?>">⬇ Download file</a><?php else: ?><p class="tag">Download unlocks after payment.</p><?php endif; ?>
        <?php endif; ?>
      </article>
      <?php endforeach; ?>
    </div>
    <aside class="card summary">
      <h2 class="h3">Summary</h2>
      <dl class="sumrows">
        <div><dt>Subtotal</dt><dd><?= money($o['subtotal']) ?></dd></div>
        <?php if ($o['discount'] > 0): ?><div class="disc"><dt>Discount <?= e($o['coupon_code']) ?></dt><dd>−<?= money($o['discount']) ?></dd></div><?php endif; ?>
        <?php if ($o['has_physical']): ?><div><dt>Shipping</dt><dd><?= $o['shipping'] > 0 ? money($o['shipping']) : 'Free' ?></dd></div><?php endif; ?>
        <?php if ($o['tax'] > 0): ?><div><dt>Tax</dt><dd><?= money($o['tax']) ?></dd></div><?php endif; ?>
      </dl>
      <div class="total"><span>Total</span><b><?= money($o['total']) ?></b></div>
      <?php if ($o['has_physical']): ?>
      <div class="addr"><small class="eyebrow-s">Ships to</small><p><?= e($o['ship_name']) ?><br><?= e($o['ship_address']) ?><?= $o['ship_address2'] ? '<br>' . e($o['ship_address2']) : '' ?><br><?= e(trim($o['ship_city'] . ' ' . $o['ship_zip'])) ?><br><?= e($o['ship_country']) ?></p></div>
      <?php endif; ?>
      <div class="addr"><small class="eyebrow-s">Receipt sent to</small><p><?= e($o['email']) ?></p></div>
      <a class="btn btn-ghost btn-block" href="<?= e(url('')) ?>">Keep shopping</a>
    </aside>
  </div>
</section>
