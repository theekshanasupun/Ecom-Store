<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install KIOSK</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}body{margin:0;background:#0b0b0d;color:#f3f1ec;font:15px/1.55 Inter,system-ui,sans-serif;min-height:100vh}
.wrap{max-width:760px;margin:0 auto;padding:36px 20px 80px}
h1{font:800 clamp(38px,9vw,72px)/.95 Syne,Arial Black,sans-serif;letter-spacing:-.02em;margin:18px 0 6px}h1 em{font-style:normal;color:#c6ff3d}h2{font:700 24px Syne,sans-serif;margin:0 0 16px}
.logo{font:800 22px Syne,sans-serif;letter-spacing:.18em}.logo i{color:#c6ff3d;font-style:normal}
.steps{display:flex;gap:8px;margin:26px 0 22px;flex-wrap:wrap}.steps span{font:600 11px Inter;letter-spacing:.12em;text-transform:uppercase;padding:8px 14px;border-radius:99px;background:#16161b;color:#8a8a93}.steps .on{background:#c6ff3d;color:#0b0b0d}.steps .done{color:#c6ff3d}
.card{background:#141418;border:1px solid #23232a;border-radius:24px;padding:28px}.row{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #23232a;font-size:14px}.row:last-child{border:0}
.ok{color:#c6ff3d;font-weight:700}.bad{color:#ff5a6e;font-weight:700}.warn{color:#ffb020;font-weight:700}small,.muted{color:#8a8a93}
label{display:block;margin:0 0 16px}label>span{display:block;font:600 11px Inter;letter-spacing:.1em;text-transform:uppercase;color:#8a8a93;margin-bottom:6px}
input,select{width:100%;background:#0b0b0d;border:1px solid #2c2c35;border-radius:14px;padding:13px 16px;color:#f3f1ec;font:inherit}input:focus,select:focus{outline:2px solid #c6ff3d;outline-offset:1px;border-color:#c6ff3d}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}@@media(max-width:560px){.grid{grid-template-columns:1fr}}
.btn{display:inline-block;background:#c6ff3d;color:#0b0b0d;border:0;border-radius:99px;padding:15px 30px;font:700 15px Inter;cursor:pointer;text-decoration:none}.btn.ghost{background:transparent;color:#f3f1ec;border:1px solid #2c2c35}.btn[disabled]{opacity:.4;cursor:not-allowed}
.err{background:#2a1015;border:1px solid #5a1f2a;color:#ff9aa8;border-radius:14px;padding:14px 16px;margin-bottom:18px}
.seg{display:flex;gap:10px;margin-bottom:18px}.seg label{flex:1;margin:0}.seg input{display:none}.seg span{display:block;text-align:center;border:1px solid #2c2c35;border-radius:14px;padding:14px;text-transform:none;letter-spacing:0;font-size:14px;color:#f3f1ec;cursor:pointer}.seg input:checked+span{border-color:#c6ff3d;background:#1c2210;color:#c6ff3d}
.chk{display:flex;gap:10px;align-items:center}.chk input{width:auto}.chk span{margin:0;text-transform:none;letter-spacing:0;font-size:14px;color:#f3f1ec}
.actions{display:flex;gap:12px;justify-content:space-between;margin-top:24px;align-items:center}code{background:#0b0b0d;padding:2px 8px;border-radius:6px;color:#c6ff3d}
</style></head><body><div class="wrap">
<div class="logo">KIOSK<i>.</i></div>
@php $labels = ['1' => 'Requirements', '2' => 'Database', '3' => 'Store & admin', '4' => 'Install']; @endphp
@if ($step !== 'done')
<h1>Let's set up<br><em>your store.</em></h1><p class="muted">Four quick steps. Takes about a minute.</p>
<div class="steps">@foreach ($labels as $k => $l)<span class="{{ $k == $step ? 'on' : ($k < $step ? 'done' : '') }}">{{ $k }}. {{ $l }}</span>@endforeach</div>
@endif

@isset($errors2)@foreach ($errors2 as $e)<div class="err">{{ $e }}</div>@endforeach @endisset

@if ($step === 1)
<div class="card"><h2>Server requirements</h2>
@foreach ($reqs as [$name, $ok, $fatal, $note])
<div class="row"><span>{{ $name }} @if($note)<small>— {{ $note }}</small>@endif</span><span class="{{ $ok ? 'ok' : ($fatal ? 'bad' : 'warn') }}">{{ $ok ? '✓ OK' : ($fatal ? '✕ Missing' : '! Optional') }}</span></div>
@endforeach
<p class="muted" style="margin-top:16px;font-size:13px">Apache: <code>mod_rewrite</code> + <code>AllowOverride All</code>. Nginx: point the web root to <code>/public</code> (see README).</p>
<div class="actions"><span></span>@if($can)<a class="btn" href="{{ url('install/database') }}">Continue →</a>@else<button class="btn" disabled>Fix the red items first</button>@endif</div></div>

@elseif ($step === 2)
<div class="card"><h2>Database</h2>
<form method="post" action="{{ url('install/database') }}">@csrf
<div class="seg"><label><input type="radio" name="driver" value="mysql" {{ $d['driver'] === 'mysql' ? 'checked' : '' }}><span><b>MySQL / MariaDB</b><br><small>Recommended for production</small></span></label>
<label><input type="radio" name="driver" value="sqlite" {{ $d['driver'] === 'sqlite' ? 'checked' : '' }}><span><b>SQLite</b><br><small>No setup · single file</small></span></label></div>
<div id="mysql-fields"><div class="grid"><label><span>Host</span><input name="host" value="{{ $d['host'] }}"></label><label><span>Port</span><input name="port" value="{{ $d['port'] }}"></label></div>
<label><span>Database name</span><input name="name" value="{{ $d['name'] }}" autocomplete="off"></label>
<div class="grid"><label><span>Username</span><input name="user" value="{{ $d['user'] }}" autocomplete="off"></label><label><span>Password</span><input type="password" name="pass" autocomplete="new-password"></label></div></div>
<label class="chk"><input type="checkbox" name="overwrite" value="1"><span>I understand — reuse a non-empty database</span></label>
<div class="actions"><a class="btn ghost" href="{{ url('install') }}">Back</a><button class="btn">Test &amp; continue →</button></div></form></div>
<script>(function(){var r=document.querySelectorAll('[name=driver]'),f=document.getElementById('mysql-fields');function u(){f.style.display=document.querySelector('[name=driver]:checked').value==='sqlite'?'none':'block'}r.forEach(function(x){x.addEventListener('change',u)});u()})()</script>

@elseif ($step === 3)
@php $cur = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'AUD' => 'A$', 'CAD' => 'C$', 'INR' => '₹', 'LKR' => 'Rs', 'AED' => 'AED ', 'JPY' => '¥', 'SGD' => 'S$']; @endphp
<div class="card"><h2>Store &amp; administrator</h2>
<form method="post" action="{{ url('install/site') }}">@csrf
<div class="grid"><label><span>Store name</span><input name="store_name" value="{{ $a['store_name'] }}" required></label><label><span>Site URL</span><input name="url" value="{{ $a['url'] }}"></label></div>
<div class="grid"><label><span>Currency</span><select name="currency" id="cur">@foreach($cur as $c => $s)<option value="{{ $c }}" data-s="{{ $s }}" {{ $a['currency'] === $c ? 'selected' : '' }}>{{ $c }} ({{ trim($s) }})</option>@endforeach</select></label>
<label><span>Currency symbol</span><input name="currency_symbol" id="sym" value="{{ $a['currency_symbol'] }}"></label></div>
<label><span>Timezone</span><select name="timezone">@foreach(\DateTimeZone::listIdentifiers() as $tz)<option {{ $a['timezone'] === $tz ? 'selected' : '' }}>{{ $tz }}</option>@endforeach</select></label>
<h2 style="margin-top:28px">Administrator</h2>
<div class="grid"><label><span>Name</span><input name="admin_name" value="{{ $a['admin_name'] }}" required></label><label><span>Email</span><input type="email" name="admin_email" value="{{ $a['admin_email'] }}" required></label></div>
<div class="grid"><label><span>Password (8+ chars)</span><input type="password" name="admin_pass" autocomplete="new-password" required></label><label><span>Repeat password</span><input type="password" name="admin_pass2" autocomplete="new-password" required></label></div>
<label class="chk"><input type="checkbox" name="demo" value="1" {{ $a['demo'] ? 'checked' : '' }}><span>Install demo content (Toys, Clothing &amp; Digital keys) — you can delete it later</span></label>
<div class="actions"><a class="btn ghost" href="{{ url('install/database') }}">Back</a><button class="btn">Continue →</button></div></form></div>
<script>document.getElementById('cur').addEventListener('change',function(){document.getElementById('sym').value=this.selectedOptions[0].dataset.s})</script>

@elseif ($step === 4)
<div class="card"><h2>Ready to install</h2>
<div class="row"><span class="muted">Database</span><b>{{ $db['driver'] === 'sqlite' ? 'SQLite (database/database.sqlite)' : $db['user'].'@'.$db['host'].'/'.$db['name'] }}</b></div>
<div class="row"><span class="muted">Store</span><b>{{ $a['store_name'] }} · {{ $a['currency'] }}</b></div>
<div class="row"><span class="muted">Admin</span><b>{{ $a['admin_email'] }}</b></div>
<div class="row"><span class="muted">Demo content</span><b>{{ $a['demo'] ? 'Yes' : 'No' }}</b></div>
<form method="post" action="{{ url('install/run') }}" onsubmit="var b=this.querySelector('button');b.disabled=true;b.textContent='Installing…'">@csrf
<div class="actions"><a class="btn ghost" href="{{ url('install/site') }}">Back</a><button class="btn">Install now</button></div></form></div>

@else
<h1>You're<br><em>live.</em></h1><p class="muted">KIOSK is installed. Sign in with your admin account (then choose “Admin panel” in the account menu) to add products, set up payments and make it yours.</p>
<div class="card" style="margin-top:22px">
<div class="row"><span class="muted">Admin login</span><b>{{ $info['email'] }}</b></div>
<div class="row"><span class="muted">Installer files</span><b class="{{ $info['deletable'] ? 'ok' : 'warn' }}">{{ $info['deletable'] ? 'Removed automatically' : 'Please delete routes/install.php manually' }}</b></div>
<div class="actions"><a class="btn ghost" href="{{ url('/') }}">View store</a><a class="btn" href="{{ url('login') }}">Sign in →</a></div></div>
@endif
</div></body></html>
