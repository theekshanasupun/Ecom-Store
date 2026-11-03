<?php
namespace App\Controllers\Admin;

use App\Core\Db;
use App\Core\Settings;
use App\Core\Updater;

final class System extends Base
{
    public function __construct()
    {
        parent::__construct();
        $this->adminOnly();
    }

    public function index(): void
    {
        $checks = [];
        foreach (['pdo', 'pdo_mysql', 'pdo_sqlite', 'mbstring', 'openssl', 'curl', 'zip', 'gd', 'fileinfo', 'json'] as $e) {
            $checks[$e] = extension_loaded($e);
        }
        $dirs = [];
        foreach (['storage', 'storage/files', 'storage/backups', 'uploads', 'uploads/products', 'app', 'assets'] as $d) {
            $dirs[$d] = is_writable(ROOT . '/' . $d);
        }
        $backups = [];
        foreach (glob(STORAGE . '/backups/*.{zip,sql}', GLOB_BRACE) ?: [] as $f) {
            $backups[] = ['name' => basename($f), 'size' => filesize($f), 'time' => date('Y-m-d H:i', filemtime($f))];
        }
        usort($backups, fn($a, $b) => strcmp($b['time'], $a['time']));
        $history = json_decode((string) setting('update_history', '[]'), true) ?: [];
        $dbv = Db::$driver === 'sqlite' ? 'SQLite ' . Db::val('SELECT sqlite_version()') : 'MySQL ' . Db::val('SELECT VERSION()');
        $info = [
            'version' => APP_VERSION, 'php' => PHP_VERSION, 'db' => $dbv, 'server' => $_SERVER['SERVER_SOFTWARE'] ?? '—',
            'upload_max' => ini_get('upload_max_filesize'), 'post_max' => ini_get('post_max_size'),
            'memory' => ini_get('memory_limit'), 'disk_free' => function_exists('disk_free_space') ? round(@disk_free_space(ROOT) / 1048576) . ' MB' : '—',
        ];
        title('System');
        $this->render('system', compact('checks', 'dirs', 'backups', 'history', 'info'));
    }

    public function update(): void
    {
        $f = $_FILES['package'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
            $msg = ($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'The file is larger than the server limit (upload_max_filesize = ' . ini_get('upload_max_filesize') . ').' : 'Choose an update.zip file first.';
            flash('error', $msg);
            redirect('admin/system');
        }
        if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'zip') {
            flash('error', 'Please upload a .zip file.');
            redirect('admin/system');
        }
        $tmp = STORAGE . '/tmp/update-' . bin2hex(random_bytes(6)) . '.zip';
        if (!move_uploaded_file($f['tmp_name'], $tmp)) {
            flash('error', 'Could not store the upload. Check storage/tmp permissions.');
            redirect('admin/system');
        }
        @set_time_limit(300);
        try {
            $r = Updater::apply($tmp);
        } finally {
            @unlink($tmp); // the package is always removed afterwards
        }
        if ($r['ok']) {
            flash('success', $r['msg'] . '. ' . implode(' · ', $r['log'] ?? []));
        } else {
            flash('error', $r['msg']);
        }
        redirect('admin/system');
    }

    public function backup(): void
    {
        $file = STORAGE . '/backups/db-' . date('Ymd-His') . '.sql';
        $pdo = Db::$pdo;
        $out = "-- KIOSK database backup " . now() . " (v" . APP_VERSION . ")\n-- driver: " . Db::$driver . "\n\n";
        $tables = Db::$driver === 'sqlite'
            ? Db::col("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
            : Db::col('SHOW TABLES');
        foreach ($tables as $t) {
            if (Db::$driver === 'sqlite') {
                $out .= Db::val("SELECT sql FROM sqlite_master WHERE name = ?", [$t]) . ";\n";
            } else {
                $r = Db::one("SHOW CREATE TABLE `$t`");
                $out .= 'DROP TABLE IF EXISTS `' . $t . "`;\n" . array_values($r)[1] . ";\n";
            }
            $st = $pdo->query("SELECT * FROM $t");
            while ($row = $st->fetch()) {
                $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row));
                $out .= "INSERT INTO $t (" . implode(',', array_keys($row)) . ') VALUES (' . implode(',', $vals) . ");\n";
            }
            $out .= "\n";
        }
        file_put_contents($file, $out);
        flash('success', 'Database backup created: ' . basename($file));
        redirect('admin/system');
    }

    public function download(array $a): void
    {
        $f = basename($a['file']);
        if (!preg_match('/^[A-Za-z0-9._-]+\.(zip|sql)$/', $f) || !is_file(STORAGE . '/backups/' . $f)) abort(404);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $f . '"');
        header('Content-Length: ' . filesize(STORAGE . '/backups/' . $f));
        readfile(STORAGE . '/backups/' . $f);
        exit;
    }

    public function cache(): void
    {
        Updater::clearCache();
        Settings::reset();
        flash('success', 'Cache cleared.');
        redirect('admin/system');
    }
}
