<?php use App\Services\Orders; ?>
<section class="block narrow-top">
  <p class="eyebrow lime">My account</p>
  <h1 class="h1">Hi, <?= e(explode(' ', $u['name'])[0]) ?></h1>
  <?php if ($u->isStaff()): ?><p><a class="btn" href="<?= e(url('admin')) ?>">Open admin panel →</a></p><?php endif; ?>
  <div class="order-grid">
    <div class="order-main">
      <h2 class="h3">Orders</h2>
      <?php if (!$orders): ?><div class="card"><p class="muted">No orders yet.</p><a class="btn" href="<?= e(url('')) ?>">Start shopping</a></div><?php endif; ?>
      <?php foreach ($orders as $o): ?>
      <a class="card orow" href="<?= e(url('order/' . $o['number'] . '?t=' . $o['token'])) ?>">
        <div><b><?= e($o['number']) ?></b><small class="muted"><?= e(fmt_date($o['created_at'], false)) ?></small></div>
        <span class="badge b-<?= e($o['status']) ?>"><?= e(Orders::STATUSES[$o['status']] ?? $o['status']) ?></span>
        <b><?= money($o['total']) ?></b>
      </a>
      <?php endforeach; ?>
    </div>
    <aside class="card summary">
      <h2 class="h3">Profile</h2>
      <form method="post" action="<?= e(url('account/profile')) ?>"><?= csrf_field() ?>
        <label class="fld"><span>Name</span><input name="name" value="<?= e($u['name']) ?>"></label>
        <label class="fld"><span>Phone</span><input name="phone" value="<?= e($u['phone']) ?>"></label>
        <label class="fld"><span>Email</span><input value="<?= e($u['email']) ?>" disabled></label>
        <label class="fld"><span>Current password</span><input type="password" name="current_password" autocomplete="current-password"></label>
        <label class="fld"><span>New password</span><input type="password" name="new_password" autocomplete="new-password"></label>
        <button class="btn btn-block">Save</button>
      </form>
      <form method="post" action="<?= e(url('logout')) ?>" style="margin-top:14px"><?= csrf_field() ?><button class="btn btn-ghost btn-block">Sign out</button></form>
    </aside>
  </div>
</section>
