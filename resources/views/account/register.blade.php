<?php /** @var array $methods */ $both = $methods['password'] && $methods['otp']; $m = old('method', $methods['password'] ? 'password' : 'code'); ?>
<section class="block narrow center-form">
  <h1 class="h1">Create account</h1>
  <form method="post" action="<?= e(url('register')) ?>" class="card authcard" data-reg><?= csrf_field() ?>
    <?php if ($both): ?>
    <div class="methods">
      <label><input type="radio" name="method" value="password" <?= $m === 'password' ? 'checked' : '' ?>><span><b>Create with a password</b><small>Sign in with your email and password</small></span></label>
      <label><input type="radio" name="method" value="code" <?= $m === 'code' ? 'checked' : '' ?>><span><b>No password — email me codes</b><small>We send a one-time code each time you sign in</small></span></label>
    </div>
    <?php else: ?><input type="hidden" name="method" value="<?= $methods['password'] ? 'password' : 'code' ?>"><?php endif; ?>
    <label class="fld"><span>Name</span><input name="name" value="<?= e(old('name')) ?>" required autocomplete="name"></label>
    <label class="fld"><span>Email</span><input type="email" name="email" value="<?= e(old('email')) ?>" required autocomplete="email"></label>
    <?php if ($methods['password']): ?><label class="fld" data-pw><span>Password (8+ characters)</span><input type="password" name="password" autocomplete="new-password" minlength="8" <?= $m === 'password' ? 'required' : '' ?>></label><?php endif; ?>
    <button class="btn btn-block" data-go><?= $m === 'code' ? 'Email me a code →' : 'Create account →' ?></button>
    <p class="muted small">Already have one? <a href="<?= e(url('login')) ?>">Sign in</a></p>
    <?= partial('partials/social_buttons') ?>
  </form>
</section>
<script>
(function(){var f=document.querySelector('[data-reg]');if(!f)return;var pw=f.querySelector('[data-pw]'),go=f.querySelector('[data-go]');
function sync(){var r=f.querySelector('[name=method]:checked')||f.querySelector('[name=method]');var code=r&&r.value==='code';if(pw){pw.hidden=code;var i=pw.querySelector('input');i.required=!code;if(code)i.value=''}go.textContent=code?'Email me a code →':'Create account →'}
f.querySelectorAll('[name=method]').forEach(function(r){r.addEventListener('change',sync)});sync()})();
</script>
