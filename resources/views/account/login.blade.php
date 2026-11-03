<?php /** @var array $methods @var array $otp @var string $tab */
$both = $methods['password'] && $methods['otp'];
$tab = request('tab') === 'code' && $methods['otp'] ? 'code' : (request('tab') === 'password' && $methods['password'] ? 'password' : $tab);
?>
<section class="block narrow center-form">
  <h1 class="h1">Sign in</h1>
  <div class="card authcard" data-auth>
    <?php if ($both): ?>
    <div class="authtabs" role="tablist"><button type="button" role="tab" data-tab="password" aria-selected="<?= $tab === 'password' ? 'true' : 'false' ?>">Password</button><button type="button" role="tab" data-tab="code" aria-selected="<?= $tab === 'code' ? 'true' : 'false' ?>">Email me a code</button></div>
    <?php endif; ?>

    <?php if ($methods['password']): ?>
    <form method="post" action="<?= e(url('login')) ?>" class="authpane" data-pane="password" <?= $tab === 'password' ? '' : 'hidden' ?>><?= csrf_field() ?>
      <label class="fld"><span>Email</span><input type="email" name="email" value="<?= e(old('email')) ?>" required <?= $tab === 'password' ? 'autofocus' : '' ?> autocomplete="email"></label>
      <label class="fld"><span>Password</span><input type="password" name="password" required autocomplete="current-password"></label>
      <button class="btn btn-block">Sign in →</button>
    </form>
    <?php endif; ?>

    <?php if ($methods['otp']): ?>
    <div class="authpane" data-pane="code" <?= $tab === 'code' ? '' : 'hidden' ?>>
      <?php if ($otp['email']): ?>
      <form method="post" action="<?= e(url('login/verify')) ?>" data-verify><?= csrf_field() ?>
        <p class="authhint">We sent a 6-digit code to <b><?= e($otp['email']) ?></b>. It expires in <?= (int) \App\Services\LoginCodes::TTL_MIN ?> minutes.</p>
        <label class="fld"><span>Sign-in code</span><input class="otp-single" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" placeholder="••••••" required <?= $tab === 'code' ? 'autofocus' : '' ?>></label>
        <label class="fld"><span>Your name <small>(only if you are new here)</small></span><input name="name" value="<?= e($otp['name']) ?>" autocomplete="name" placeholder="Optional"></label>
        <button class="btn btn-block">Verify &amp; continue →</button>
      </form>
      <form method="post" action="<?= e(url('login/code')) ?>" style="margin-top:12px;display:flex;gap:14px;flex-wrap:wrap;align-items:center"><?= csrf_field() ?><input type="hidden" name="email" value="<?= e($otp['email']) ?>"><input type="hidden" name="name" value="<?= e($otp['name']) ?>">
        <button class="linkbtn" data-resend data-wait="<?= (int) $otp['wait'] ?>" <?= $otp['wait'] > 0 ? 'disabled' : '' ?>>Resend code</button>
        <a class="linkbtn" href="<?= e(url('login/reset-code')) ?>" data-change>Use a different email</a>
      </form>
      <?php else: ?>
      <form method="post" action="<?= e(url('login/code')) ?>"><?= csrf_field() ?>
        <p class="authhint">No password needed. Enter your email and we will send a one-time code. New here? We will create your account.</p>
        <label class="fld"><span>Email</span><input type="email" name="email" required <?= $tab === 'code' ? 'autofocus' : '' ?> autocomplete="email"></label>
        <button class="btn btn-block">Email me a code →</button>
        <p class="authhint" style="margin:12px 0 0">Team members sign in with their password.</p>
      </form>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?= partial('partials/social_buttons') ?>
    <p class="muted small" style="margin-top:14px">New here? <a href="<?= e(url('register')) ?>">Create an account</a> · <a href="<?= e(url('track')) ?>">Track a guest order</a></p>
  </div>
</section>
<script>
(function(){var root=document.querySelector('[data-auth]');if(!root)return;
var tabs=root.querySelectorAll('[data-tab]'),panes=root.querySelectorAll('[data-pane]');
function show(t){tabs.forEach(function(b){b.setAttribute('aria-selected',b.dataset.tab===t?'true':'false')});panes.forEach(function(p){p.hidden=p.dataset.pane!==t;var f=!p.hidden&&p.querySelector('input:not([type=hidden])');if(f&&!f.value)f.focus()})}
tabs.forEach(function(b){b.addEventListener('click',function(){show(b.dataset.tab)})});
var code=root.querySelector('.otp-single');if(code){code.addEventListener('input',function(){code.value=code.value.replace(/\D/g,'').slice(0,6);if(code.value.length===6&&!root.querySelector('[name=name]').value){/* give new users a moment to add a name, existing users sign straight in */}});}
var rs=root.querySelector('[data-resend]');if(rs){var w=parseInt(rs.dataset.wait,10)||0;if(w>0){var base='Resend code';var t=setInterval(function(){w--;if(w<=0){clearInterval(t);rs.disabled=false;rs.textContent=base}else rs.textContent=base+' ('+w+'s)'},1000);rs.textContent=base+' ('+w+'s)'}}
})();
</script>
