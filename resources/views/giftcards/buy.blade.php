<?php
/** @var int[] $amounts @var array $designs */
$min = (float) setting('gc_min', 10); $max = (float) setting('gc_max', 500);
$custom = setting('gc_custom', '1') === '1';
$pick = (int) old('amount', $amounts[1] ?? ($amounts[0] ?? 5000));
$dsg = old('design', array_key_first($designs));
?>
<section class="block narrow-top gc-page">
  <div class="gc-head"><p class="eyebrow">Gift cards</p><h1 class="h1">Give the gift of choice</h1><p class="lede">Send a gift card by email — instantly or on the day it matters. They never expire<?= (int) setting('gc_expiry_months', 0) > 0 ? ' for ' . (int) setting('gc_expiry_months') . ' months' : '' ?> and can be used on anything in the store.</p></div>
  <div class="gc-grid">
    <form method="post" action="<?= e(url('gift-cards/add')) ?>" class="card gc-form" id="gc-form"><?= csrf_field() ?>
      <h2 class="h3">1 · Pick an amount</h2>
      <div class="gc-amounts"><?php foreach ($amounts as $a): ?><label class="gc-amt"><input type="radio" name="amount" value="<?= (int) $a ?>" <?= $pick === $a && !old('custom') ? 'checked' : '' ?>><span><?= money($a, true) ?></span></label><?php endforeach; ?>
        <?php if ($custom): ?><label class="gc-amt gc-other"><span>Other</span><input type="number" name="custom" min="<?= $min ?>" max="<?= $max ?>" step="1" placeholder="<?= money($min * 100, true) ?>–<?= money($max * 100, true) ?>" value="<?= e(old('custom')) ?>"></label><?php endif; ?></div>
      <h2 class="h3">2 · Choose a design</h2>
      <div class="gc-designs"><?php foreach ($designs as $k => $d): ?><label class="gc-dsg"><input type="radio" name="design" value="<?= e($k) ?>" data-c1="<?= e($d[1]) ?>" data-c2="<?= e($d[2]) ?>" data-fg="<?= e($d[3]) ?>" <?= $dsg === $k ? 'checked' : '' ?>><span style="background:linear-gradient(135deg,<?= e($d[1]) ?>,<?= e($d[2]) ?>)" title="<?= e($d[0]) ?>"></span></label><?php endforeach; ?></div>
      <h2 class="h3">3 · Who is it for?</h2>
      <div class="grid2"><label class="fld"><span>Recipient name</span><input name="to_name" value="<?= e(old('to_name')) ?>" maxlength="100" placeholder="Alex"></label>
        <label class="fld"><span>Recipient email</span><input type="email" name="to_email" value="<?= e(old('to_email')) ?>" placeholder="alex@example.com"></label></div>
      <div class="grid2"><label class="fld"><span>From</span><input name="from" value="<?= e(old('from', auth()->user()->name ?? '')) ?>" maxlength="100"></label>
        <label class="fld"><span>Send on <em>optional</em></span><input type="date" name="deliver_at" value="<?= e(old('deliver_at')) ?>" min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>"><small class="hint">Leave empty to email it right after payment.</small></label></div>
      <label class="fld"><span>Message <em>optional</em></span><textarea name="msg" rows="3" maxlength="500" placeholder="Happy birthday! Treat yourself."><?= e(old('msg')) ?></textarea></label>
      <p class="muted small">Leave the recipient email empty and we'll send the card to you so you can pass it on yourself.</p>
      <div class="gc-actions"><button class="btn" type="submit">Add to cart</button><button class="btn btn-ghost" type="submit" name="buy_now" value="1">Buy now</button><a class="lnk" href="<?= e(url('gift-cards/balance')) ?>">Check a balance</a></div>
    </form>
    <aside class="gc-preview-wrap"><div class="gc-card" id="gc-card" style="--g1:<?= e($designs[$dsg][1] ?? '#c6ff3d') ?>;--g2:<?= e($designs[$dsg][2] ?? '#7ad100') ?>;--gfg:<?= e($designs[$dsg][3] ?? '#0b0b0d') ?>">
        <div class="gc-card-top"><b><?= e(strtoupper(setting('store_name'))) ?></b><span>GIFT CARD</span></div>
        <div class="gc-card-amt" data-gc-amt><?= money($pick, true) ?></div>
        <div class="gc-card-code">XXXX-XXXX-XXXX-XXXX</div>
        <div class="gc-card-to" data-gc-to></div></div>
      <ul class="dots"><li style="--c:var(--accent)">Delivered by email — instantly or scheduled</li><li style="--c:#8b7bff">Works on every product, shipped or digital</li><li style="--c:#ff7a2f">Balance carries over until it's used up</li></ul></aside>
  </div>
</section>
