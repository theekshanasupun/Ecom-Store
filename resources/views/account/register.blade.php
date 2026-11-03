<section class="block narrow center-form">
  <h1 class="h1">Create account</h1>
  <form method="post" action="<?= e(url('register')) ?>" class="card authcard"><?= csrf_field() ?>
    <label class="fld"><span>Name</span><input name="name" value="<?= e(old('name')) ?>" required autocomplete="name"></label>
    <label class="fld"><span>Email</span><input type="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email"></label>
    <label class="fld"><span>Password (8+ characters)</span><input type="password" name="password" required autocomplete="new-password"></label>
    <button class="btn btn-block">Create account →</button>
    <p class="muted small">Already have one? <a href="<?= e(url('login')) ?>">Sign in</a></p>
  </form>
</section>
