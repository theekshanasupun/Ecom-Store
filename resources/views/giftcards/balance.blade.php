<?php /** @var ?\App\Models\GiftCard $card @var ?string $error */ ?>
<section class="block narrow center-form">
  <h1 class="h1">Gift card balance</h1>
  <form method="post" action="<?= e(url('gift-cards/balance')) ?>" class="card authcard"><?= csrf_field() ?>
    <label class="fld"><span>Gift card code</span><input name="code" value="<?= e(old('code', request('code'))) ?>" placeholder="XXXX-XXXX-XXXX-XXXX" required autocomplete="off" autofocus></label>
    <button class="btn btn-block">Check balance</button>
    <?php if ($error): ?><p class="bad" style="margin:14px 0 0"><?= e($error) ?></p><?php endif; ?>
    <?php if ($card): ?>
    <div class="gc-result"><span class="muted small">Balance</span><b><?= money($card->balance) ?></b><small class="muted">of <?= money($card->initial) ?><?= $card->expires_at ? ' · valid until ' . e($card->expires_at->format('M j, Y')) : '' ?></small></div>
    <?php endif; ?>
    <p class="muted small"><a href="<?= e(url('gift-cards')) ?>">Buy a gift card →</a></p>
  </form>
</section>
