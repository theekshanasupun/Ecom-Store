<?php
use App\Support\Theme;
$s = fn (string $k, string $d = '') => (string) setting($k, $d);
$bg = Theme::hex('theme_light_bg', Theme::DEFAULTS['bg']);
$sf = Theme::hex('theme_light_surface', Theme::DEFAULTS['surface']);
$tx = Theme::hex('theme_light_text', Theme::DEFAULTS['text']);
$custom = Theme::hex('theme_light_accent', '') !== '';
$acc = $custom ? Theme::hex('theme_light_accent', '#b4501e') : Theme::lightAccent();
$font = Theme::font();
$rad = Theme::radius();
$allFonts = 'https://fonts.googleapis.com/css2?' . implode('&', array_map(fn ($f) => $f[3], Theme::FONTS)) . '&display=swap';
?>
<link href="<?= e($allFonts) ?>" rel="stylesheet">
<style>
.th-wrap{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);gap:18px;align-items:start}
@media(max-width:1100px){.th-wrap{grid-template-columns:1fr}}
.th-opts{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin:6px 0 16px}
.th-opt{position:relative;display:block;cursor:pointer}.th-opt input{position:absolute;opacity:0;inset:0}
.th-opt span{display:block;border:1px solid var(--line,#2a2a32);border-radius:12px;padding:12px 14px;font-size:13px;line-height:1.3}
.th-opt b{display:block;font-size:14px;margin-bottom:2px}.th-opt small{opacity:.7}
.th-opt input:checked+span{border-color:var(--accent,#c6ff3d);box-shadow:0 0 0 1px var(--accent,#c6ff3d)}
.th-opt input:focus-visible+span{outline:2px solid var(--accent,#c6ff3d);outline-offset:2px}
.th-colors{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:0 14px}
.th-prev-wrap{position:sticky;top:14px}
.th-tabs{display:flex;gap:6px;margin-bottom:10px}.th-tabs button{border:1px solid var(--line,#2a2a32);background:transparent;color:inherit;border-radius:99px;padding:7px 16px;cursor:pointer;font:600 12.5px system-ui}.th-tabs button.on{background:var(--accent,#c6ff3d);color:#0b0b0d;border-color:transparent}
/* mini storefront */
.pv{--p-bg:#0b0b0d;--p-card:#141418;--p-line:#26262d;--p-tx:#f3f1ec;--p-mut:#8a8a93;--p-ac:#c6ff3d;--p-ink:#0b0b0d;--p-fd:'Syne',system-ui,sans-serif;--p-fb:'Inter',system-ui,sans-serif;--p-r:26px;--p-rb:99px;--p-hw:800;--p-tt:uppercase;
 background:var(--p-bg);color:var(--p-tx);border-radius:16px;border:1px solid var(--p-line);overflow:hidden;font:14px/1.5 var(--p-fb);transition:background .25s,color .25s}
.pv-h{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid var(--p-line)}
.pv-logo{font:var(--p-hw) 17px var(--p-fd);letter-spacing:.08em;text-transform:uppercase}
.pv-nav{display:flex;gap:14px;font-size:12px;color:var(--p-mut)}.pv-cart{background:var(--p-tx);color:var(--p-bg);border-radius:var(--p-rb);padding:7px 14px;font-weight:700;font-size:12px}
.pv-hero{padding:26px 18px 8px}.pv-eye{font:600 10px var(--p-fb);letter-spacing:.16em;text-transform:uppercase;color:var(--p-ac);margin-bottom:8px}
.pv-hero h3{margin:0 0 8px;font:var(--p-hw) 36px/1 var(--p-fd);letter-spacing:-.02em;text-transform:var(--p-tt)}
.pv-hero p{margin:0 0 16px;color:var(--p-mut);max-width:30em}
.pv-btns{display:flex;gap:8px;flex-wrap:wrap}.pv-b{background:var(--p-ac);color:var(--p-ink);border-radius:var(--p-rb);padding:10px 18px;font-weight:700;font-size:13px}.pv-b.g{background:transparent;color:var(--p-tx);border:1px solid var(--p-line)}
.pv-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;padding:16px 18px 20px}
.pv-c{background:var(--p-card);border:1px solid var(--p-line);border-radius:var(--p-r);padding:8px 8px 12px}
.pv-i{aspect-ratio:1;border-radius:calc(var(--p-r) - 6px);display:grid;place-items:center;font-size:28px;margin-bottom:8px}
.pv-c b{display:block;font:var(--p-hw) 13px var(--p-fd);padding:0 6px}.pv-c small{color:var(--p-mut);padding:0 6px;display:block}
.pv-in{margin:0 18px 18px;display:flex;gap:8px}.pv-in i{flex:1;border:1px solid var(--p-line);background:var(--p-card);border-radius:var(--p-rb);padding:9px 14px;color:var(--p-mut);font-style:normal;font-size:12px}
.pv.light{--p-bg:var(--l-bg);--p-card:var(--l-sf);--p-line:var(--l-line);--p-tx:var(--l-tx);--p-mut:var(--l-mut);--p-ac:var(--l-ac);--p-ink:var(--l-ink);--p-fd:var(--l-fd);--p-fb:var(--l-fb);--p-r:var(--l-r);--p-rb:var(--l-rb);--p-hw:600;--p-tt:none}
.pv.light .pv-hero h3{font-size:34px}
</style>

<form method="post" action="<?= e(url('admin/themes')) ?>" enctype="multipart/form-data" id="theme-form"><?= csrf_field() ?>
<div class="th-wrap">
<div>
  <section class="apanel"><h2>Mode</h2>
    <p class="muted">The <b>dark</b> theme is the original KIOSK look. The <b>light</b> theme is a different design — warm cream, serif headings, softer corners.</p>
    <div class="th-opts"><?php foreach (Theme::MODES as $k => $lab): ?>
      <label class="th-opt"><input type="radio" name="theme_mode" value="<?= e($k) ?>" <?= Theme::mode() === $k ? 'checked' : '' ?>><span><b><?= e($lab) ?></b><small><?= ['auto' => 'Light or dark by the visitor\'s device setting', 'dark' => 'Everyone starts on dark', 'light' => 'Everyone starts on light'][$k] ?></small></span></label>
    <?php endforeach; ?></div>
    <?= field_check('theme_toggle', 'Show the light / dark toggle in the store header', Theme::toggleOn(), 'When off, every visitor sees the default above and the button is hidden.') ?>
  </section>

  <section class="apanel"><h2>Light theme colours</h2>
    <div class="th-colors">
      <label class="fld"><span>Page background</span><input type="color" name="theme_light_bg" value="<?= e($bg) ?>" data-l="bg"></label>
      <label class="fld"><span>Cards &amp; fields</span><input type="color" name="theme_light_surface" value="<?= e($sf) ?>" data-l="sf"></label>
      <label class="fld"><span>Text</span><input type="color" name="theme_light_text" value="<?= e($tx) ?>" data-l="tx"></label>
      <label class="fld"><span>Accent</span><input type="color" name="theme_light_accent" value="<?= e($acc) ?>" data-l="ac" id="th-ac"></label>
    </div>
    <label class="chk"><input type="checkbox" name="theme_light_auto" value="1" <?= $custom ? '' : 'checked' ?> id="th-auto"><i></i><span>Derive the accent from the store accent (<?= e($s('accent', '#c6ff3d')) ?>) automatically <small class="hint">The bright dark-mode accent is darkened until it is readable on the light background.</small></span></label>
    <p class="small muted" id="th-contrast" style="margin:10px 0 0"></p>
  </section>

  <section class="apanel"><h2>Light theme fonts</h2>
    <div class="th-opts"><?php foreach (Theme::FONTS as $k => $f): ?>
      <label class="th-opt"><input type="radio" name="theme_light_font" value="<?= e($k) ?>" <?= $font === $k ? 'checked' : '' ?> data-fd="<?= e($f[1]) ?>" data-fb="<?= e($f[2]) ?>"><span style="font-family:<?= e($f[2]) ?>"><b style="font-family:<?= e($f[1]) ?>;font-size:17px;font-weight:600"><?= e(explode(' + ', $f[0])[0]) ?></b><small><?= e($f[0]) ?></small></span></label>
    <?php endforeach; ?></div>
  </section>

  <section class="apanel"><h2>Corner roundness</h2>
    <div class="th-opts"><?php foreach (Theme::RADII as $k => $r): ?>
      <label class="th-opt"><input type="radio" name="theme_light_radius" value="<?= e($k) ?>" <?= $rad === $k ? 'checked' : '' ?> data-r="<?= e($r[1]) ?>" data-rb="<?= e($r[2]) ?>"><span><b><?= e($r[0]) ?></b><small>Cards <?= e($r[1]) ?> · buttons <?= e($r[2] === '99px' ? 'pill' : $r[2]) ?></small></span></label>
    <?php endforeach; ?></div>
  </section>

  <section class="apanel"><h2>Light theme logo <small class="muted">(optional)</small></h2>
    <p class="muted small">Upload a second logo for the light theme (e.g. a dark wordmark). If empty, your normal logo is used on both themes.</p>
    <label class="fld"><span>Logo image</span><input type="file" name="theme_light_logo" accept="image/*"></label>
    <?php if ($s('theme_light_logo')): ?><div class="imgprev"><img src="<?= e(upload_url($s('theme_light_logo'))) ?>" alt="" style="background:#fff;padding:10px"><label class="chk"><input type="checkbox" name="remove_light_logo" value="1"><i></i><span>Remove light-theme logo</span></label></div><?php endif; ?>
  </section>

  <div class="row-actions" style="margin-top:4px">
    <button class="btn" type="submit">Save theme</button>
    <button class="btn ghost danger" type="submit" name="action" value="reset" onclick="return confirm('Reset all light-theme options to the defaults?')">Reset to defaults</button>
    <a class="btn ghost" href="<?= e(url('')) ?>" target="_blank" rel="noopener">View store ↗</a>
  </div>
</div>

<div class="th-prev-wrap">
  <div class="th-tabs" role="tablist"><button type="button" class="on" data-pv="light">Light preview</button><button type="button" data-pv="dark">Dark preview</button></div>
  <div class="pv light" id="pv" aria-label="Theme preview">
    <div class="pv-h"><span class="pv-logo"><?= e(strtoupper($s('store_name', 'KIOSK'))) ?></span><span class="pv-nav"><span>All</span><span>Toys</span><span>Clothing</span></span><span class="pv-cart">Cart 2</span></div>
    <div class="pv-hero"><div class="pv-eye">New season</div><h3><?= e($s('hero_line1', 'One store.')) ?> <?= e($s('hero_line2', 'Every want.')) ?></h3><p>Toys, clothing and digital keys, side by side.</p><div class="pv-btns"><span class="pv-b">Shop now</span><span class="pv-b g">Track order</span></div></div>
    <div class="pv-grid"><?php foreach ([['#ffb347', '🧸', 'Plush bear', '$24'], ['#7fd1ff', '👕', 'Cotton tee', '$32'], ['#c6a4ff', '🎮', 'Game key', '$19']] as $p): ?><div class="pv-c"><div class="pv-i" style="background:<?= $p[0] ?>"><?= $p[1] ?></div><b><?= e($p[2]) ?></b><small><?= e($p[3]) ?></small></div><?php endforeach; ?></div>
    <div class="pv-in"><i>you@example.com</i><span class="pv-b">Subscribe</span></div>
  </div>
</div>
</div>
</form>

<script>
(function(){
  var $=function(s){return document.querySelector(s)},$$=function(s){return [].slice.call(document.querySelectorAll(s))};
  var pv=$('#pv'),dark=<?= json_encode((string) $s('accent', '#c6ff3d')) ?>;
  function rgb(h){h=h.replace('#','');return [0,2,4].map(function(i){return parseInt(h.substr(i,2),16)})}
  function hex(a){return '#'+a.map(function(n){return ('0'+Math.max(0,Math.min(255,Math.round(n))).toString(16)).slice(-2)}).join('')}
  function mix(a,b,t){var x=rgb(a),y=rgb(b);return hex(x.map(function(v,i){return v+(y[i]-v)*t}))}
  function lum(h){var c=rgb(h).map(function(n){n/=255;return n<=.03928?n/12.92:Math.pow((n+.055)/1.055,2.4)});return .2126*c[0]+.7152*c[1]+.0722*c[2]}
  function cr(a,b){var p=lum(a),q=lum(b);return (Math.max(p,q)+.05)/(Math.min(p,q)+.05)}
  function ink(h){var c=rgb(h);return (.299*c[0]+.587*c[1]+.114*c[2])>150?'#0b0b0d':'#ffffff'}
  function derive(bg){for(var t=0;t<=.9;t+=.05){var c=mix(dark,'#000000',t);if(cr(c,bg)>=4.5)return c}return '#000000'}
  function update(){
    var bg=$('[data-l=bg]').value,sf=$('[data-l=sf]').value,tx=$('[data-l=tx]').value,auto=$('#th-auto').checked,acEl=$('#th-ac');
    var ac=auto?derive(bg):acEl.value; if(auto)acEl.value=ac; acEl.disabled=auto;
    var f=$('[name=theme_light_font]:checked'),r=$('[name=theme_light_radius]:checked'),s=pv.style;
    s.setProperty('--l-bg',bg);s.setProperty('--l-sf',sf);s.setProperty('--l-tx',tx);s.setProperty('--l-ac',ac);s.setProperty('--l-ink',ink(ac));
    s.setProperty('--l-line',mix(bg,tx,.12));s.setProperty('--l-mut',mix(tx,bg,.42));
    if(f){s.setProperty('--l-fd',f.dataset.fd);s.setProperty('--l-fb',f.dataset.fb)}
    if(r){s.setProperty('--l-r',r.dataset.r);s.setProperty('--l-rb',r.dataset.rb)}
    var c1=cr(tx,bg),c2=cr(ac,bg),el=$('#th-contrast');
    el.textContent='Contrast on background — text '+c1.toFixed(1)+':1'+(c1>=4.5?' ✓':' ✗ (aim for 4.5:1)')+' · accent '+c2.toFixed(1)+':1'+(c2>=4.5?' ✓':' ✗ (low for small text)');
  }
  $$('#theme-form input').forEach(function(i){i.addEventListener('input',update);i.addEventListener('change',update)});
  $$('.th-tabs button').forEach(function(b){b.addEventListener('click',function(){
    $$('.th-tabs button').forEach(function(x){x.classList.toggle('on',x===b)});
    pv.classList.toggle('light',b.dataset.pv==='light');
    if(b.dataset.pv==='dark'){pv.style.setProperty('--p-ac',dark);pv.style.setProperty('--p-ink',ink(dark))}else{pv.style.removeProperty('--p-ac');pv.style.removeProperty('--p-ink')}
  })});
  update();
})();
</script>
