<?php
namespace App\Core;

use ZipArchive;

/**
 * One-click system updates.
 *
 * update.zip layout:
 *   update.json            {"version":"1.1.0","min_version":"1.0.0","notes":"...","delete":["old/file.php"]}
 *   <files mirroring the site root>   e.g. app/Controllers/Shop.php, assets/css/store.css
 *   database/migrations/002_*.php     (run automatically, in order)
 *   post_update.php        (optional, executed once then removed)
 *
 * Never overwritten: storage/, uploads/, install/, .htaccess-protected config.
 */
final class Updater
{
    private const PROTECTED = ['storage/', 'uploads/', 'install/', '.git/'];

    public static function apply(string $zipPath): array
    {
        $log = [];
        if (!class_exists(ZipArchive::class)) {
            return ['ok' => false, 'msg' => 'PHP zip extension is not installed.'];
        }
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['ok' => false, 'msg' => 'Could not open the zip file. Is it a valid update package?'];
        }
        $metaRaw = $zip->getFromName('update.json');
        $meta = $metaRaw ? json_decode($metaRaw, true) : null;
        if (!is_array($meta) || empty($meta['version'])) {
            $zip->close();
            return ['ok' => false, 'msg' => 'Invalid package: update.json with a "version" is missing.'];
        }
        $new = (string) $meta['version'];
        if (!preg_match('/^\d+\.\d+\.\d+$/', $new)) {
            $zip->close();
            return ['ok' => false, 'msg' => 'Invalid version format in update.json.'];
        }
        if (version_compare($new, APP_VERSION, '<=')) {
            $zip->close();
            return ['ok' => false, 'msg' => "This package (v$new) is not newer than your installed version (v" . APP_VERSION . ').'];
        }
        if (!empty($meta['min_version']) && version_compare(APP_VERSION, (string) $meta['min_version'], '<')) {
            $zip->close();
            return ['ok' => false, 'msg' => 'This update requires at least v' . $meta['min_version'] . ' (you have v' . APP_VERSION . '). Install the earlier update first.'];
        }
        if (!empty($meta['min_php']) && version_compare(PHP_VERSION, (string) $meta['min_php'], '<')) {
            $zip->close();
            return ['ok' => false, 'msg' => 'This update needs PHP ' . $meta['min_php'] . '+.'];
        }

        // collect + validate entries
        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', $zip->getNameIndex($i));
            if ($name === '' || str_ends_with($name, '/') || $name === 'update.json' || str_starts_with($name, '__MACOSX/')) {
                continue;
            }
            if (str_contains($name, '..') || str_starts_with($name, '/') || preg_match('#^[a-z]:#i', $name) || str_contains($name, "\0")) {
                $zip->close();
                return ['ok' => false, 'msg' => 'Package rejected: unsafe path "' . $name . '".'];
            }
            foreach (self::PROTECTED as $pp) {
                if (str_starts_with($name, $pp)) {
                    continue 2;
                }
            }
            $files[] = ['idx' => $i, 'name' => $name];
        }
        if (!$files) {
            $zip->close();
            return ['ok' => false, 'msg' => 'The package contains no files to install.'];
        }

        // backup of files about to be replaced
        $backup = STORAGE . '/backups/pre-update-v' . APP_VERSION . '-' . date('Ymd-His') . '.zip';
        $bz = new ZipArchive();
        if ($bz->open($backup, ZipArchive::CREATE) === true) {
            foreach ($files as $f) {
                if (is_file(ROOT . '/' . $f['name'])) {
                    $bz->addFile(ROOT . '/' . $f['name'], $f['name']);
                }
            }
            foreach ((array) ($meta['delete'] ?? []) as $d) {
                if (is_string($d) && is_file(ROOT . '/' . $d)) $bz->addFile(ROOT . '/' . $d, $d);
            }
            $bz->close();
            $log[] = 'Backup created: ' . basename($backup);
        }

        // writable check
        foreach ($files as $f) {
            $target = ROOT . '/' . $f['name'];
            $dir = is_file($target) ? $target : dirname($target);
            while (!file_exists($dir) && $dir !== ROOT) $dir = dirname($dir);
            if (!is_writable($dir)) {
                $zip->close();
                return ['ok' => false, 'msg' => 'Not writable: ' . $f['name'] . '. Fix file permissions (the PHP user must own the site files) and retry.'];
            }
        }

        @file_put_contents(STORAGE . '/maintenance.flag', (string) time());
        try {
            foreach ($files as $f) {
                $target = ROOT . '/' . $f['name'];
                if (!is_dir(dirname($target))) {
                    mkdir(dirname($target), 0755, true);
                }
                $data = $zip->getFromIndex($f['idx']);
                if ($data === false || file_put_contents($target, $data) === false) {
                    throw new \RuntimeException('Failed writing ' . $f['name']);
                }
            }
            $log[] = count($files) . ' files updated';
            foreach ((array) ($meta['delete'] ?? []) as $d) {
                if (!is_string($d) || str_contains($d, '..') || str_starts_with($d, '/')) continue;
                foreach (self::PROTECTED as $pp) if (str_starts_with($d, $pp)) continue 2;
                if (is_file(ROOT . '/' . $d)) @unlink(ROOT . '/' . $d);
            }
            $zip->close();

            // version file (authoritative)
            file_put_contents(APP . '/version.php', "<?php\nreturn '" . $new . "';\n");
            if (function_exists('opcache_reset')) @opcache_reset();

            Db::boot();
            $ran = Migrator::run();
            if ($ran) $log[] = 'Migrations: ' . implode(', ', $ran);

            $post = ROOT . '/post_update.php';
            if (is_file($post)) {
                (static function () use ($post) { include $post; })();
                @unlink($post);
                $log[] = 'Post-update script executed';
            }
            self::clearCache();
            Settings::set('version', $new);
            $hist = json_decode((string) setting('update_history', '[]'), true) ?: [];
            array_unshift($hist, ['version' => $new, 'from' => APP_VERSION, 'at' => now(), 'notes' => (string) ($meta['notes'] ?? '')]);
            Settings::set('update_history', json_encode(array_slice($hist, 0, 20)));
            @file_put_contents(STORAGE . '/logs/updates.log', '[' . now() . '] v' . APP_VERSION . ' -> v' . $new . ' | ' . implode(' | ', $log) . "\n", FILE_APPEND);
        } catch (\Throwable $e) {
            @unlink(STORAGE . '/maintenance.flag');
            @$zip->close();
            return ['ok' => false, 'msg' => 'Update failed: ' . $e->getMessage() . '. A backup of replaced files is in storage/backups.'];
        }
        @unlink(STORAGE . '/maintenance.flag');
        return ['ok' => true, 'msg' => "Updated to v$new", 'log' => $log, 'version' => $new];
    }

    public static function clearCache(): void
    {
        foreach (glob(STORAGE . '/cache/*') ?: [] as $f) {
            if (is_file($f) && basename($f) !== '.gitkeep') @unlink($f);
        }
        if (function_exists('opcache_reset')) @opcache_reset();
    }
}
