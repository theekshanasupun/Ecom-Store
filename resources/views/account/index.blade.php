<?php use App\Services\Orders;
/** @var \App\Models\User $u @var mixed $orders @var bool $newsletter @var array $social @var array $stats */
$credit = (int) ($u->credit ?? 0);
?>
<section class="block narrow-top">
  <p class="eyebrow lime">My account</p>
  <h1 class="h1">Hi, <?= e(explode(' ', $u['name'])[0]) ?></h1>
  <?php if ($u->isStaff()): ?><p><a class="btn" href="<?= e(url('admin')) ?>">Open admin panel →</a></p><?php endif; ?>
  <div class="acct-stats">
    <div class="card"><b><?= (int) $stats['orders'] ?></b><small>Orders</small></div>
    <div class="card"><b><?= money($stats['spent'], true) ?></b><small>Total spent</small></div>
    <?php if (\App\Services\Loyalty::on() && !$u->isStaff()): $lt = \App\Services\LoyaltyTiers::of($u); ?><a class="card" href="<?= e(url('account/loyalty')) ?>" style="text-decoration:none"><b>★ <?= number_format((int) $u->points) ?></b><small><?= e(\App\Services\Loyalty::name()) ?> points<?= $lt ? ' · ' . e($lt->name) : '' ?> →</small></a><?php endif; ?>
    <?php if ($credit > 0 || \App\Services\StoreCredit::enabled()): ?><div class="card"><b><?= money($credit) ?></b><small>Store credit</small></div><?php endif; ?>
    <?php if (\App\Services\GiftCards::enabled()): ?><div class="card"><b>🎁</b><small><a href="<?= e(url('gift-cards')) ?>">Buy a gift card</a> · <a href="<?= e(url('gift-cards/balance')) ?>">Check a balance</a></small></div><?php endif; ?>
  </div>
  <div class="order-grid">
    <div class="order-main orow2">
      <h2 class="h3">Orders</h2>
      <?php if (!count($orders)): ?><div class="card"><p class="muted">No orders yet.</p><a class="btn" href="<?= e(url('')) ?>">Start shopping</a></div><?php endif; ?>
      <?php foreach ($orders as $o): ?>
      <div class="card orow">
        <a href="<?= e(url('order/' . $o['number'] . '?t=' . $o['token'])) ?>" style="text-decoration:none;display:grid;flex:1;min-width:0"><b><?= e($o['number']) ?></b><small class="muted"><?= e(fmt_date($o['created_at'], false)) ?></small>
          <?php if ($o['tracking']): ?><span class="trk">📦 <?= e($o['carrier'] ?: 'Tracking') ?> · <?= e($o['tracking']) ?></span><?php endif; ?></a>
        <?php if ($o['tracking'] && $o['tracking_url']): ?><a class="btn btn-ghost" style="padding:9px 16px;font-size:13px" href="<?= e($o['tracking_url']) ?>" target="_blank" rel="noopener">Track ↗</a><?php endif; ?>
        <span class="badge b-<?= e($o['status']) ?>"><?= e(Orders::STATUSES[$o['status']] ?? $o['status']) ?></span>
        <b><?= money($o['total']) ?></b>
      </div>
      <?php endforeach; ?>
    </div>
    <aside class="card summary">
      <h2 class="h3">Profile</h2>
      <form method="post" action="<?= e(url('account/profile')) ?>"><?= csrf_field() ?>
        <label class="fld"><span>Name</span><input name="name" value="<?= e($u['name']) ?>"></label>
        <label class="fld"><span>Phone</span><input name="phone" value="<?= e($u['phone']) ?>"></label>
        <label class="fld"><span>Email <?= $u->email_verified_at ? '<span class="pill ok">✓ verified</span>' : '' ?></span><input value="<?= e($u['email']) ?>" disabled></label>
        <button class="btn btn-block">Save profile</button>
      </form>

      <div class="secpanel" id="security">
        <h3>Security &amp; sign-in</h3>
        <p class="authhint" style="margin:0">Sign-in methods: <span class="pill <?= $u->has_password ? 'ok' : '' ?>"><?= $u->has_password ? '✓' : '–' ?> Password</span> <?php if (setting('login_otp_on', '1') === '1'): ?><span class="pill ok">✓ Email code</span><?php endif; ?> <?php foreach ($social as $s): ?><span class="pill ok">✓ <?= e(ucfirst($s)) ?></span><?php endforeach; ?></p>
        <form method="post" action="<?= e(url('account/password')) ?>"><?= csrf_field() ?>
          <?php if ($u->has_password): ?><label class="fld"><span>Current password</span><input type="password" name="current_password" autocomplete="current-password" required></label><?php endif; ?>
          <label class="fld"><span><?= $u->has_password ? 'New password' : 'Set a password (optional)' ?></span><input type="password" name="new_password" autocomplete="new-password" minlength="8" required></label>
          <button class="btn btn-ghost btn-block"><?= $u->has_password ? 'Change password' : 'Set password' ?></button>
        </form>
      </div>

      <div class="secpanel">
        <h3>Newsletter</h3>
        <form method="post" action="<?= e(url('account/newsletter')) ?>"><?= csrf_field() ?>
          <label class="chk"><input type="checkbox" name="newsletter" value="1" <?= $newsletter ? 'checked' : '' ?>><i></i><span>Send me news and offers</span></label>
          <button class="btn btn-ghost btn-block" style="margin-top:10px">Save preference</button>
        </form>
      </div>
      <form method="post" action="<?= e(url('logout')) ?>" style="margin-top:14px"><?= csrf_field() ?><button class="btn btn-ghost btn-block">Sign out</button></form>
    </aside>
  </div>
</section>
