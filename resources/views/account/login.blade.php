<section class="block narrow center-form">
  <h1 class="h1">Sign in</h1>
  <form method="post" action="<?= e(url('login')) ?>" class="card authcard"><?= csrf_field() ?>
    <label class="fld"><span>Email</span><input type="email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="email"></label>
    <label class="fld"><span>Password</span><input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn btn-block">Sign in →</button>
    <?= partial('partials/social_buttons') ?>
    <p class="muted small">New here? <a href="<?= e(url('register')) ?>">Create an account</a> · <a href="<?= e(url('track')) ?>">Track a guest order</a></p>
  </form>
</section>
