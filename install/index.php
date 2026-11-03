<?php
/**
 * KIOSK installer wizard. Deletes itself after a successful install.
 */
define('IN_INSTALLER', true);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Db;
use App\Core\Migrator;
use App\Core\Settings;

if (installed() && ($_GET['step'] ?? '') !== 'done') {
    header('Location: ' . BASE_PATH . '/');
    exit;
}

$step = $_GET['step'] ?? '1';
$S = &$_SESSION['inst'];
$S = $S ?? [];
$errors = [];

function inst_csrf_ok(): bool
{
    return isset($_POST['_csrf']) && hash_equals(csrf_token(), (string) $_POST['_csrf']);
}

function requirements(): array
{
    $r = [];
    $r[] = ['PHP 8.1 or newer', PHP_VERSION_ID >= 80100, true, 'Running ' . PHP_VERSION];
    $r[] = ['PDO extension', extension_loaded('pdo'), true, ''];
    $r[] = ['PDO MySQL or SQLite driver', extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'), true, (extension_loaded('pdo_mysql') ? 'MySQL ' : '') . (extension_loaded('pdo_sqlite') ? 'SQLite' : '')];
    foreach (['mbstring' => true, 'openssl' => true, 'json' => true, 'fileinfo' => false, 'zip' => false, 'curl' => false, 'gd' => false] as $e => $fatal) {
        $why = ['zip' => 'needed for one-click updates', 'curl' => 'needed for Stripe / PayPal', 'gd' => 'recommended for images', 'fileinfo' => 'recommended'][$e] ?? '';
        $r[] = ["PHP extension: $e", extension_loaded($e), $fatal, $why];
    }
    foreach (['storage', 'storage/files', 'storage/logs', 'storage/sessions', 'storage/backups', 'storage/tmp', 'uploads', 'uploads/products', 'uploads/site'] as $d) {
        if (!is_dir(ROOT . '/' . $d)) @mkdir(ROOT . '/' . $d, 0755, true);
        $r[] = ["Writable: /$d", is_writable(ROOT . '/' . $d), true, ''];
    }
    $r[] = ['Writable: site root (installer cleanup & updates)', is_writable(ROOT), false, 'if not, delete /install manually after setup'];
    $r[] = ['Writable: /app (one-click updates)', is_writable(APP), false, ''];
    return $r;
}

function can_continue(): bool
{
    foreach (requirements() as [$n, $ok, $fatal]) {
        if (!$ok && $fatal) return false;
    }
    return true;
}

// ---- POST handlers ---------------------------------------------------------------
if (is_post()) {
    if (!inst_csrf_ok()) {
        $errors[] = 'Session expired — please try again.';
    } elseif ($step === '2') {
        $d = [
            'driver' => ($_POST['driver'] ?? '') === 'sqlite' ? 'sqlite' : 'mysql',
            'host' => trim($_POST['host'] ?? 'localhost'), 'port' => (int) ($_POST['port'] ?? 3306) ?: 3306,
            'name' => trim($_POST['name'] ?? ''), 'user' => trim($_POST['user'] ?? ''), 'pass' => (string) ($_POST['pass'] ?? ''),
        ];
        try {
            if ($d['driver'] === 'sqlite') {
                if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('pdo_sqlite is not available on this server.');
                $file = STORAGE . '/database.sqlite';
                $d['file'] = $file;
                Db::connect($d);
                $has = (int) Db::val("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='users'");
            } else {
                if (!extension_loaded('pdo_mysql')) throw new RuntimeException('pdo_mysql is not available on this server.');
                if ($d['name'] === '' || $d['user'] === '') throw new RuntimeException('Database name and user are required.');
                try {
                    Db::connect($d);
                } catch (PDOException $e) {
                    if (str_contains($e->getMessage(), 'Unknown database')) {
                        $p = new PDO('mysql:host=' . $d['host'] . ';port=' . $d['port'], $d['user'], $d['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                        $p->exec('CREATE DATABASE `' . str_replace('`', '', $d['name']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                        Db::connect($d);
                    } else {
                        throw $e;
                    }
                }
                $has = (int) Db::val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'");
            }
            if ($has && empty($_POST['overwrite'])) {
                throw new RuntimeException('This database already contains KIOSK tables. Use an empty database, or tick "I understand — reuse it" to continue (existing tables are kept).');
            }
            $S['db'] = $d;
            header('Location: ?step=3');
            exit;
        } catch (Throwable $e) {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
        }
    } elseif ($step === '3') {
        $a = [
            'store_name' => trim($_POST['store_name'] ?? '') ?: 'KIOSK',
            'url' => rtrim(trim($_POST['url'] ?? ''), '/'),
            'currency' => strtoupper(trim($_POST['currency'] ?? 'USD')) ?: 'USD',
            'currency_symbol' => trim($_POST['currency_symbol'] ?? '$') ?: '$',
            'timezone' => in_array($_POST['timezone'] ?? '', DateTimeZone::listIdentifiers(), true) ? $_POST['timezone'] : 'UTC',
            'admin_name' => trim($_POST['admin_name'] ?? ''),
            'admin_email' => mb_strtolower(trim($_POST['admin_email'] ?? '')),
            'admin_pass' => (string) ($_POST['admin_pass'] ?? ''),
            'demo' => !empty($_POST['demo']),
        ];
        if ($a['admin_name'] === '') $errors[] = 'Enter the administrator name.';
        if (!filter_var($a['admin_email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid administrator email.';
        if (strlen($a['admin_pass']) < 8) $errors[] = 'Password must be at least 8 characters.';
        if ($a['admin_pass'] !== (string) ($_POST['admin_pass2'] ?? '')) $errors[] = 'Passwords do not match.';
        if (!$errors) {
            $S['site'] = $a;
            header('Location: ?step=4');
            exit;
        }
    } elseif ($step === '4') {
        if (empty($S['db']) || empty($S['site'])) {
            header('Location: ?step=1');
            exit;
        }
        $db = $S['db'];
        $a = $S['site'];
        $cfgFile = STORAGE . '/config.php';
        try {
            $conf = [
                'db' => $db, 'app_key' => bin2hex(random_bytes(32)), 'timezone' => $a['timezone'],
                'url' => $a['url'] ?: null, 'debug' => false, 'installed_at' => date('c'),
            ];
            if (@file_put_contents($cfgFile, "<?php\n// Generated by the KIOSK installer. Keep private.\nreturn " . var_export($conf, true) . ";\n") === false) {
                throw new RuntimeException('Cannot write storage/config.php — make /storage writable.');
            }
            Db::connect($db);
            Migrator::run();
            file_put_contents(STORAGE . '/installed.lock', date('c'));
            Settings::reset();
            Settings::setMany([
                'store_name' => $a['store_name'], 'store_email' => $a['admin_email'], 'currency' => $a['currency'],
                'currency_symbol' => $a['currency_symbol'], 'version' => APP_VERSION,
                'free_shipping_min' => (string) ($a['currency'] === 'USD' ? 7500 : 7500),
                'ticker' => 'Everything · Keys delivered in seconds · Free shipping on physical orders over ' . $a['currency_symbol'] . '75 · One cart for everything',
            ]);
            Db::insert('users', ['name' => $a['admin_name'], 'email' => $a['admin_email'], 'password' => Auth::hash($a['admin_pass']), 'role' => 'admin', 'status' => 'active', 'created_at' => now()]);
            if ($a['demo']) {
                (require ROOT . '/database/seed.php')();
            } else {
                foreach ([['About', 'about', '<p>Tell customers about your store.</p>'], ['Terms', 'terms', '<p>Add your terms here.</p>'], ['Privacy', 'privacy', '<p>Add your privacy policy here.</p>']] as [$t, $s, $b]) {
                    Db::insert('pages', ['title' => $t, 'slug' => $s, 'body' => $b, 'status' => 'published', 'in_footer' => 1, 'created_at' => now()]);
                }
            }
            $S = [];
            $_SESSION['inst_done'] = ['email' => $a['admin_email'], 'deletable' => is_writable(ROOT)];
            header('Location: ?step=done');
            exit;
        } catch (Throwable $e) {
            @unlink(STORAGE . '/installed.lock');
            @unlink($cfgFile);
            $errors[] = 'Installation failed: ' . $e->getMessage();
        }
    }
}

// ---- Views -------------------------------------------------------------------------
function ui_head(string $step): void
{
    $steps = ['1' => 'Requirements', '2' => 'Database', '3' => 'Store & admin', '4' => 'Install'];
    ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Install KIOSK</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}body{margin:0;background:#0b0b0d;color:#f3f1ec;font:15px/1.55 Inter,system-ui,sans-serif;min-height:100vh}
.wrap{max-width:760px;margin:0 auto;padding:36px 20px 80px}
h1{font:800 clamp(38px,9vw,72px)/.95 Syne,Arial Black,sans-serif;letter-spacing:-.02em;margin:18px 0 6px}
h1 em{font-style:normal;color:#c6ff3d}h2{font:700 24px Syne,sans-serif;margin:0 0 16px}
.logo{font:800 22px Syne,sans-serif;letter-spacing:.18em}.logo i{color:#c6ff3d;font-style:normal}
.steps{display:flex;gap:8px;margin:26px 0 22px;flex-wrap:wrap}.steps span{font:600 11px Inter;letter-spacing:.12em;text-transform:uppercase;padding:8px 14px;border-radius:99px;background:#16161b;color:#8a8a93}
.steps .on{background:#c6ff3d;color:#0b0b0d}.steps .done{color:#c6ff3d}
.card{background:#141418;border:1px solid #23232a;border-radius:24px;padding:28px}
.row{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #23232a;font-size:14px}.row:last-child{border:0}
.ok{color:#c6ff3d;font-weight:700}.bad{color:#ff5a6e;font-weight:700}.warn{color:#ffb020;font-weight:700}small,.muted{color:#8a8a93}
label{display:block;margin:0 0 16px}label>span{display:block;font:600 11px Inter;letter-spacing:.1em;text-transform:uppercase;color:#8a8a93;margin-bottom:6px}
input,select{width:100%;background:#0b0b0d;border:1px solid #2c2c35;border-radius:14px;padding:13px 16px;color:#f3f1ec;font:inherit}
input:focus,select:focus{outline:2px solid #c6ff3d;outline-offset:1px;border-color:#c6ff3d}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}@media(max-width:560px){.grid{grid-template-columns:1fr}}
.btn{display:inline-block;background:#c6ff3d;color:#0b0b0d;border:0;border-radius:99px;padding:15px 30px;font:700 15px Inter;cursor:pointer;text-decoration:none}
.btn.ghost{background:transparent;color:#f3f1ec;border:1px solid #2c2c35}.btn[disabled]{opacity:.4;cursor:not-allowed}
.err{background:#2a1015;border:1px solid #5a1f2a;color:#ff9aa8;border-radius:14px;padding:14px 16px;margin-bottom:18px}
.seg{display:flex;gap:10px;margin-bottom:18px}.seg label{flex:1;margin:0}.seg input{display:none}
.seg span{display:block;text-align:center;border:1px solid #2c2c35;border-radius:14px;padding:14px;text-transform:none;letter-spacing:0;font-size:14px;color:#f3f1ec;cursor:pointer}
.seg input:checked+span{border-color:#c6ff3d;background:#1c2210;color:#c6ff3d}
.chk{display:flex;gap:10px;align-items:center}.chk input{width:auto}.chk span{margin:0;text-transform:none;letter-spacing:0;font-size:14px;color:#f3f1ec}
.actions{display:flex;gap:12px;justify-content:space-between;margin-top:24px;align-items:center}
code{background:#0b0b0d;padding:2px 8px;border-radius:6px;color:#c6ff3d}
</style></head><body><div class="wrap">
<div class="logo">KIOSK<i>.</i></div>
<?php if ($step !== 'done'): ?>
<h1>Let's set up<br><em>your store.</em></h1>
<p class="muted">Four quick steps. Takes about a minute.</p>
<div class="steps"><?php foreach ($steps as $k => $l): ?><span class="<?= $k == $step ? 'on' : ($k < $step ? 'done' : '') ?>"><?= $k ?>. <?= $l ?></span><?php endforeach; ?></div>
<?php endif;
}

function ui_foot(): void
{
    echo '</div></body></html>';
}

function ui_errors(array $errors): void
{
    foreach ($errors as $e) echo '<div class="err">' . e($e) . '</div>';
}

if ($step === '2') {
    $d = $S['db'] ?? ['driver' => 'mysql', 'host' => 'localhost', 'port' => 3306, 'name' => '', 'user' => '', 'pass' => ''];
    $d = array_merge($d, array_intersect_key($_POST, $d));
    ui_head('2'); ?>
<div class="card"><h2>Database</h2><?php ui_errors($errors); ?>
<form method="post"><?= csrf_field() ?>
<div class="seg">
<label><input type="radio" name="driver" value="mysql" <?= $d['driver'] === 'mysql' ? 'checked' : '' ?>><span><b>MySQL / MariaDB</b><br><small>Recommended for production</small></span></label>
<label><input type="radio" name="driver" value="sqlite" <?= $d['driver'] === 'sqlite' ? 'checked' : '' ?>><span><b>SQLite</b><br><small>No setup · single file</small></span></label>
</div>
<div id="mysql-fields">
<div class="grid"><label><span>Host</span><input name="host" value="<?= e($d['host']) ?>"></label><label><span>Port</span><input name="port" value="<?= e($d['port']) ?>"></label></div>
<label><span>Database name</span><input name="name" value="<?= e($d['name']) ?>" autocomplete="off"></label>
<div class="grid"><label><span>Username</span><input name="user" value="<?= e($d['user']) ?>" autocomplete="off"></label><label><span>Password</span><input type="password" name="pass" autocomplete="new-password"></label></div>
</div>
<label class="chk"><input type="checkbox" name="overwrite" value="1"><span>I understand — reuse a non-empty database</span></label>
<div class="actions"><a class="btn ghost" href="?step=1">Back</a><button class="btn">Test &amp; continue →</button></div>
</form></div>
<script>(function(){var r=document.querySelectorAll('[name=driver]'),f=document.getElementById('mysql-fields');function u(){var s=document.querySelector('[name=driver]:checked').value==='sqlite';f.style.display=s?'none':'block'}r.forEach(function(x){x.addEventListener('change',u)});u()})()</script>
<?php ui_foot();
    exit;
}

if ($step === '3') {
    if (empty($S['db'])) { header('Location: ?step=2'); exit; }
    $a = array_merge(['store_name' => 'KIOSK', 'url' => '', 'currency' => 'USD', 'currency_symbol' => '$', 'timezone' => 'UTC', 'admin_name' => '', 'admin_email' => '', 'demo' => true], $S['site'] ?? [], array_intersect_key($_POST, ['store_name' => 1, 'admin_name' => 1, 'admin_email' => 1]));
    $detect = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_PATH;
    $cur = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'AUD' => 'A$', 'CAD' => 'C$', 'INR' => '₹', 'LKR' => 'Rs', 'AED' => 'AED ', 'JPY' => '¥', 'SGD' => 'S$'];
    ui_head('3'); ?>
<div class="card"><h2>Store &amp; administrator</h2><?php ui_errors($errors); ?>
<form method="post"><?= csrf_field() ?>
<div class="grid"><label><span>Store name</span><input name="store_name" value="<?= e($a['store_name']) ?>" required></label>
<label><span>Site URL</span><input name="url" value="<?= e($a['url'] ?: $detect) ?>"></label></div>
<div class="grid"><label><span>Currency</span><select name="currency" id="cur"><?php foreach ($cur as $c => $s): ?><option value="<?= $c ?>" data-s="<?= e($s) ?>" <?= $a['currency'] === $c ? 'selected' : '' ?>><?= $c ?> (<?= e(trim($s)) ?>)</option><?php endforeach; ?></select></label>
<label><span>Currency symbol</span><input name="currency_symbol" id="sym" value="<?= e($a['currency_symbol']) ?>"></label></div>
<label><span>Timezone</span><select name="timezone"><?php foreach (DateTimeZone::listIdentifiers() as $tz): ?><option <?= $a['timezone'] === $tz ? 'selected' : '' ?>><?= e($tz) ?></option><?php endforeach; ?></select></label>
<h2 style="margin-top:28px">Administrator</h2>
<div class="grid"><label><span>Name</span><input name="admin_name" value="<?= e($a['admin_name']) ?>" required></label><label><span>Email</span><input type="email" name="admin_email" value="<?= e($a['admin_email']) ?>" required></label></div>
<div class="grid"><label><span>Password (8+ chars)</span><input type="password" name="admin_pass" autocomplete="new-password" required></label><label><span>Repeat password</span><input type="password" name="admin_pass2" autocomplete="new-password" required></label></div>
<label class="chk"><input type="checkbox" name="demo" value="1" <?= $a['demo'] ? 'checked' : '' ?>><span>Install demo content (Toys, Clothing &amp; Digital keys) — you can delete it later</span></label>
<div class="actions"><a class="btn ghost" href="?step=2">Back</a><button class="btn">Continue →</button></div>
</form></div>
<script>document.getElementById('cur').addEventListener('change',function(){document.getElementById('sym').value=this.selectedOptions[0].dataset.s})</script>
<?php ui_foot();
    exit;
}

if ($step === '4') {
    if (empty($S['db']) || empty($S['site'])) { header('Location: ?step=1'); exit; }
    ui_head('4'); ?>
<div class="card"><h2>Ready to install</h2><?php ui_errors($errors); ?>
<div class="row"><span class="muted">Database</span><b><?= e($S['db']['driver'] === 'sqlite' ? 'SQLite (storage/database.sqlite)' : $S['db']['user'] . '@' . $S['db']['host'] . '/' . $S['db']['name']) ?></b></div>
<div class="row"><span class="muted">Store</span><b><?= e($S['site']['store_name']) ?> · <?= e($S['site']['currency']) ?></b></div>
<div class="row"><span class="muted">Admin</span><b><?= e($S['site']['admin_email']) ?></b></div>
<div class="row"><span class="muted">Demo content</span><b><?= $S['site']['demo'] ? 'Yes' : 'No' ?></b></div>
<form method="post" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Installing…'"><?= csrf_field() ?>
<div class="actions"><a class="btn ghost" href="?step=3">Back</a><button class="btn">Install now</button></div></form></div>
<?php ui_foot();
    exit;
}

if ($step === 'done') {
    $info = $_SESSION['inst_done'] ?? null;
    if (!installed() || !$info) { header('Location: ' . BASE_PATH . '/'); exit; }
    $deleted = false;
    ob_start();
    ui_head('done'); ?>
<h1>You're<br><em>live.</em></h1>
<p class="muted">KIOSK is installed. Sign in to the admin to add products, set up payments and make it yours.</p>
<div class="card" style="margin-top:22px">
<div class="row"><span class="muted">Admin login</span><b><?= e($info['email']) ?></b></div>
<div class="row"><span class="muted">Installer folder</span><b id="inst-state" class="<?= $info['deletable'] ? 'ok' : 'warn' ?>"><?= $info['deletable'] ? 'Removed automatically' : 'Please delete the /install folder manually' ?></b></div>
<div class="actions"><a class="btn ghost" href="<?= e(url('')) ?>">View store</a><a class="btn" href="<?= e(url('admin')) ?>">Open admin →</a></div></div>
<?php ui_foot();
    $html = ob_get_clean();
    unset($_SESSION['inst_done']);
    header('Content-Length: ' . strlen($html));
    header('Connection: close');
    echo $html;
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    else { @ob_flush(); flush(); }
    register_shutdown_function(function () {
        @unlink(STORAGE . '/sessions/.keep');
        rrmdir(__DIR__);
    });
    exit;
}

// ---- Step 1 ---------------------------------------------------------------------------
$reqs = requirements();
ui_head('1'); ?>
<div class="card"><h2>Server requirements</h2>
<?php foreach ($reqs as [$name, $ok, $fatal, $note]): ?>
<div class="row"><span><?= e($name) ?> <?= $note ? '<small>— ' . e($note) . '</small>' : '' ?></span><span class="<?= $ok ? 'ok' : ($fatal ? 'bad' : 'warn') ?>"><?= $ok ? '✓ OK' : ($fatal ? '✕ Missing' : '! Optional') ?></span></div>
<?php endforeach; ?>
<p class="muted" style="margin-top:16px;font-size:13px">Apache needs <code>mod_rewrite</code> + <code>AllowOverride All</code>. On Nginx see <code>README.md</code> for the one-line <code>try_files</code> rule.</p>
<div class="actions"><span></span><?php if (can_continue()): ?><a class="btn" href="?step=2">Continue →</a><?php else: ?><button class="btn" disabled>Fix the red items first</button><?php endif; ?></div>
</div>
<?php ui_foot();
