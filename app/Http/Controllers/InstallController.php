<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\DemoData;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/** Web installer. This controller, its views and routes/install.php delete themselves after a successful install. */
class InstallController extends Controller
{
    public static function checks(): array
    {
        $r = [['PHP 8.3 or newer', PHP_VERSION_ID >= 80300, true, 'Running '.PHP_VERSION]];
        $r[] = ['PDO MySQL or SQLite driver', extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite'), true, trim((extension_loaded('pdo_mysql') ? 'MySQL ' : '').(extension_loaded('pdo_sqlite') ? 'SQLite' : ''))];
        foreach (['pdo' => true, 'mbstring' => true, 'openssl' => true, 'json' => true, 'tokenizer' => true, 'xml' => true, 'ctype' => true, 'fileinfo' => true, 'zip' => false, 'curl' => false, 'gd' => false] as $e => $fatal) {
            $why = ['zip' => 'needed for one-click updates', 'curl' => 'recommended for payments', 'gd' => 'recommended for images'][$e] ?? '';
            $r[] = ["PHP extension: $e", extension_loaded($e), $fatal, $why];
        }
        foreach (['storage', 'storage/app', 'storage/framework', 'storage/logs', 'bootstrap/cache', 'public/uploads', 'database'] as $d) {
            File::ensureDirectoryExists(base_path($d));
            $r[] = ["Writable: /$d", is_writable(base_path($d)), true, ''];
        }
        $r[] = ['Writable: .env', is_writable(base_path('.env')), true, ''];
        $r[] = ['Writable: site root (installer cleanup & updates)', is_writable(base_path()), false, 'otherwise delete the installer files manually'];

        return $r;
    }

    private function ok(): bool
    {
        foreach (self::checks() as [$n, $ok, $fatal]) if (! $ok && $fatal) return false;

        return true;
    }

    public function requirements()
    {
        return view('install.wizard', ['step' => 1, 'reqs' => self::checks(), 'can' => $this->ok()]);
    }

    public function database(Request $r)
    {
        return view('install.wizard', ['step' => 2, 'd' => session('inst.db', ['driver' => 'mysql', 'host' => 'localhost', 'port' => 3306, 'name' => '', 'user' => '', 'pass' => ''])]);
    }

    public function databasePost(Request $r)
    {
        $d = [
            'driver' => $r->input('driver') === 'sqlite' ? 'sqlite' : 'mysql', 'host' => trim((string) $r->input('host', 'localhost')),
            'port' => (int) $r->input('port', 3306) ?: 3306, 'name' => trim((string) $r->input('name')), 'user' => trim((string) $r->input('user')), 'pass' => (string) $r->input('pass'),
        ];
        try {
            if ($d['driver'] === 'sqlite') {
                $file = database_path('database.sqlite');
                if (! is_file($file)) touch($file);
                $pdo = new \PDO('sqlite:'.$file, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                $has = (int) $pdo->query("select count(*) from sqlite_master where type='table' and name='users'")->fetchColumn();
            } else {
                if ($d['name'] === '' || $d['user'] === '') throw new \RuntimeException('Database name and user are required.');
                $opts = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];
                try {
                    $pdo = new \PDO("mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset=utf8mb4", $d['user'], $d['pass'], $opts);
                } catch (\PDOException $e) {
                    if (! str_contains($e->getMessage(), 'Unknown database')) throw $e;
                    $p = new \PDO("mysql:host={$d['host']};port={$d['port']}", $d['user'], $d['pass'], $opts);
                    $p->exec('CREATE DATABASE `'.str_replace('`', '', $d['name']).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
                    $pdo = new \PDO("mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset=utf8mb4", $d['user'], $d['pass'], $opts);
                }
                $has = (int) $pdo->query("select count(*) from information_schema.tables where table_schema = database() and table_name = 'users'")->fetchColumn();
            }
            if ($has && ! $r->boolean('overwrite')) throw new \RuntimeException('This database already contains KIOSK tables. Use an empty database, or tick "reuse it" to continue.');
        } catch (\Throwable $e) {
            return view('install.wizard', ['step' => 2, 'd' => $d, 'errors2' => ['Database connection failed: '.$e->getMessage()]]);
        }
        session(['inst.db' => $d]);

        return redirect('/install/site');
    }

    public function site()
    {
        if (! session('inst.db')) return redirect('/install/database');
        $a = array_merge(['store_name' => 'KIOSK', 'url' => request()->getSchemeAndHttpHost().request()->getBasePath(), 'currency' => 'USD', 'currency_symbol' => '$', 'timezone' => 'UTC', 'admin_name' => '', 'admin_email' => '', 'demo' => true], session('inst.site', []));

        return view('install.wizard', ['step' => 3, 'a' => $a]);
    }

    public function sitePost(Request $r)
    {
        $a = [
            'store_name' => trim((string) $r->input('store_name')) ?: 'KIOSK', 'url' => rtrim(trim((string) $r->input('url')), '/'),
            'currency' => strtoupper(trim((string) $r->input('currency', 'USD'))) ?: 'USD', 'currency_symbol' => trim((string) $r->input('currency_symbol', '$')) ?: '$',
            'timezone' => in_array($r->input('timezone'), \DateTimeZone::listIdentifiers(), true) ? $r->input('timezone') : 'UTC',
            'admin_name' => trim((string) $r->input('admin_name')), 'admin_email' => mb_strtolower(trim((string) $r->input('admin_email'))), 'demo' => $r->boolean('demo'),
        ];
        $pass = (string) $r->input('admin_pass');
        $err = [];
        if ($a['admin_name'] === '') $err[] = 'Enter the administrator name.';
        if (! filter_var($a['admin_email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Enter a valid administrator email.';
        if (strlen($pass) < 8) $err[] = 'Password must be at least 8 characters.';
        if ($pass !== (string) $r->input('admin_pass2')) $err[] = 'Passwords do not match.';
        if ($err) return view('install.wizard', ['step' => 3, 'a' => $a, 'errors2' => $err]);
        session(['inst.site' => $a, 'inst.pass' => encrypt($pass)]);

        return redirect('/install/review');
    }

    public function review()
    {
        if (! session('inst.db') || ! session('inst.site')) return redirect('/install');

        return view('install.wizard', ['step' => 4, 'db' => session('inst.db'), 'a' => session('inst.site')]);
    }

    private function env(array $pairs): void
    {
        $path = base_path('.env');
        $txt = is_file($path) ? file_get_contents($path) : '';
        foreach ($pairs as $k => $v) {
            $line = $k.'='.(preg_match('/[\s#"\']/', (string) $v) ? '"'.addcslashes((string) $v, '"\\').'"' : $v);
            $re = '/^'.preg_quote($k, '/').'=.*$/m';
            $txt = preg_match($re, $txt) ? preg_replace($re, str_replace('\\', '\\\\', $line), $txt) : rtrim($txt)."\n".$line."\n";
        }
        file_put_contents($path, $txt);
    }

    public function run()
    {
        $db = session('inst.db');
        $a = session('inst.site');
        if (! $db || ! $a) return redirect('/install');
        $pass = decrypt(session('inst.pass'));
        $oldEnv = is_file(base_path('.env')) ? file_get_contents(base_path('.env')) : '';
        try {
            $key = 'base64:'.base64_encode(random_bytes(32));
            $env = ['APP_NAME' => $a['store_name'], 'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_URL' => $a['url'] ?: url('/'), 'APP_KEY' => $key,
                'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'file', 'QUEUE_CONNECTION' => 'sync', 'LOG_LEVEL' => 'warning'];
            if ($db['driver'] === 'sqlite') {
                $env += ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => database_path('database.sqlite')];
                config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => database_path('database.sqlite')]);
            } else {
                $env += ['DB_CONNECTION' => 'mysql', 'DB_HOST' => $db['host'], 'DB_PORT' => $db['port'], 'DB_DATABASE' => $db['name'], 'DB_USERNAME' => $db['user'], 'DB_PASSWORD' => $db['pass']];
                config(['database.default' => 'mysql', 'database.connections.mysql.host' => $db['host'], 'database.connections.mysql.port' => $db['port'],
                    'database.connections.mysql.database' => $db['name'], 'database.connections.mysql.username' => $db['user'], 'database.connections.mysql.password' => $db['pass']]);
            }
            $this->env($env);
            // use the new app key straight away (demo keys are encrypted with it)
            config(['app.key' => $key]);
            app()->forgetInstance('encrypter');
            DB::purge();

            Artisan::call('migrate', ['--force' => true]);
            file_put_contents(storage_path('app/installed.lock'), date('c'));
            Settings::reset();
            Settings::setMany([
                'store_name' => $a['store_name'], 'store_email' => $a['admin_email'], 'currency' => $a['currency'], 'currency_symbol' => $a['currency_symbol'], 'timezone' => $a['timezone'],
                'ticker' => 'Everything · Keys delivered in seconds · Free shipping on physical orders over '.$a['currency_symbol'].'75 · One cart for everything',
            ]);
            User::create(['name' => $a['admin_name'], 'email' => $a['admin_email'], 'password' => $pass, 'role' => 'admin', 'status' => 'active']);
            $a['demo'] ? DemoData::seed() : DemoData::seedPages(false);
        } catch (\Throwable $e) {
            @unlink(storage_path('app/installed.lock'));
            file_put_contents(base_path('.env'), $oldEnv);
            report($e);

            return view('install.wizard', ['step' => 4, 'db' => $db, 'a' => $a, 'errors2' => ['Installation failed: '.$e->getMessage()]]);
        }
        session()->forget(['inst.db', 'inst.site', 'inst.pass']);
        // the app key was rotated, so the session is not reusable — hand over via a small file
        file_put_contents(storage_path('app/install-done.json'), json_encode(['email' => $a['admin_email'], 'deletable' => is_writable(base_path('routes')) && is_writable(app_path('Http/Controllers'))]));

        return redirect('/install/done');
    }

    public function done()
    {
        $file = storage_path('app/install-done.json');
        $info = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! installed() || ! $info) return redirect('/');
        @unlink($file);
        $html = view('install.wizard', ['step' => 'done', 'info' => $info])->render();
        // remove the installer once the response has been sent
        register_shutdown_function(function () {
            @unlink(base_path('routes/install.php'));
            @unlink(app_path('Http/Controllers/InstallController.php'));
            File::deleteDirectory(resource_path('views/install'));
        });

        return response($html);
    }
}
