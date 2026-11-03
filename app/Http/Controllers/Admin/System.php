<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Updater;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class System extends Controller
{
    public function index()
    {
        $checks = [];
        foreach (['pdo', 'pdo_mysql', 'pdo_sqlite', 'mbstring', 'openssl', 'curl', 'zip', 'gd', 'fileinfo', 'json', 'tokenizer', 'xml'] as $e) $checks[$e] = extension_loaded($e);
        $dirs = [];
        foreach (['storage', 'storage/app', 'bootstrap/cache', 'public/uploads', 'app', 'resources', 'public'] as $d) $dirs[$d] = is_writable(base_path($d));
        $backups = [];
        foreach (glob(storage_path('app/backups/*.{zip,sql}'), GLOB_BRACE) ?: [] as $f) $backups[] = ['name' => basename($f), 'size' => filesize($f), 'time' => date('Y-m-d H:i', filemtime($f))];
        usort($backups, fn ($a, $b) => strcmp($b['time'], $a['time']));
        $history = array_slice(json_decode((string) setting('update_history', '[]'), true) ?: [], 0, Updater::KEEP);
        $driver = DB::connection()->getDriverName();
        $dbv = $driver === 'sqlite' ? 'SQLite '.DB::selectOne('select sqlite_version() v')->v : 'MySQL '.DB::selectOne('select version() v')->v;
        $info = [
            'version' => config('kiosk.version'), 'laravel' => app()->version(), 'php' => PHP_VERSION, 'db' => $dbv, 'server' => $_SERVER['SERVER_SOFTWARE'] ?? '—',
            'upload_max' => ini_get('upload_max_filesize'), 'post_max' => ini_get('post_max_size'), 'memory' => ini_get('memory_limit'),
            'disk_free' => function_exists('disk_free_space') ? round(@disk_free_space(base_path()) / 1048576).' MB' : '—',
        ];
        title('System');

        return admin_page('system', compact('checks', 'dirs', 'backups', 'history', 'info'));
    }

    public function update(Request $r)
    {
        $f = $r->file('package');
        if (! $f || ! $f->isValid()) {
            flash('error', $f && $f->getError() === UPLOAD_ERR_INI_SIZE ? 'The file is larger than the server limit (upload_max_filesize = '.ini_get('upload_max_filesize').').' : 'Choose an update.zip file first.');

            return redirect('/admin/system');
        }
        if (strtolower($f->getClientOriginalExtension()) !== 'zip') {
            flash('error', 'Please upload a .zip file.');

            return redirect('/admin/system');
        }
        File::ensureDirectoryExists(storage_path('app/tmp'));
        $tmp = storage_path('app/tmp/update-'.bin2hex(random_bytes(6)).'.zip');
        $f->move(dirname($tmp), basename($tmp));
        @set_time_limit(300);
        try {
            $res = Updater::apply($tmp);
        } finally {
            @unlink($tmp); // the package is always removed afterwards
        }
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? $res['msg'].'. '.implode(' · ', $res['log'] ?? []) : $res['msg']);

        return redirect('/admin/system');
    }

    public function backup()
    {
        File::ensureDirectoryExists(storage_path('app/backups'));
        $file = storage_path('app/backups/db-'.date('Ymd-His').'.sql');
        $pdo = DB::connection()->getPdo();
        $driver = DB::connection()->getDriverName();
        $out = '-- KIOSK database backup '.now().' (v'.config('kiosk.version').")\n-- driver: $driver\n\n";
        $tables = $driver === 'sqlite' ? array_column(DB::select("select name from sqlite_master where type='table' and name not like 'sqlite_%'"), 'name') : array_map(fn ($r) => array_values((array) $r)[0], DB::select('show tables'));
        foreach ($tables as $t) {
            if ($driver === 'sqlite') {
                $out .= DB::selectOne('select sql from sqlite_master where name = ?', [$t])->sql.";\n";
            } else {
                $out .= "DROP TABLE IF EXISTS `$t`;\n".array_values((array) DB::selectOne("show create table `$t`"))[1].";\n";
            }
            foreach (DB::table($t)->cursor() as $row) {
                $row = (array) $row;
                $out .= "INSERT INTO $t (".implode(',', array_keys($row)).') VALUES ('.implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row))).");\n";
            }
            $out .= "\n";
        }
        file_put_contents($file, $out);
        flash('success', 'Database backup created: '.basename($file));

        return redirect('/admin/system');
    }

    public function download(string $file)
    {
        $f = basename($file);
        if (! preg_match('/^[A-Za-z0-9._-]+\.(zip|sql)$/', $f) || ! is_file(storage_path('app/backups/'.$f))) abort(404);

        return response()->download(storage_path('app/backups/'.$f));
    }

    public function prune()
    {
        $r = Updater::prune();
        flash('success', $r['removed'] ? 'Cleaned up '.$r['removed'].' old update item(s), freed '.round($r['freed'] / 1048576, 1).' MB.' : 'Nothing to clean — only the last '.Updater::KEEP.' updates are kept.');

        return redirect('/admin/system');
    }

    public function cache()
    {
        Updater::clearCaches();
        Settings::reset();
        flash('success', 'Cache cleared.');

        return redirect('/admin/system');
    }
}
