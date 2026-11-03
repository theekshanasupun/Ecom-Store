<div class="login">
  <div class="brand big"><?= e(strtoupper(setting('store_name'))) ?><i>.</i></div>
  <h1>Admin sign in</h1>
  <form method="post" action="<?= e(url('admin/login')) ?>" class="apanel"><?= csrf_field() ?>
    <label class="fld"><span>Email</span><input type="email" name="email" value="<?= e(old('email')) ?>" required autofocus autocomplete="username"></label>
    <label class="fld"><span>Password</span><input type="password" name="password" required autocomplete="current-password"></label>
    <button class="btn block">Sign in →</button>
  </form>
  <p class="muted"><a href="<?= e(url('')) ?>">← Back to store</a></p>
</div>
