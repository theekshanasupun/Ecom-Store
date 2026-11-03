<?php

namespace App\Services;

use App\Support\Settings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * One-click system updates.
 *
 * update.zip layout:
 *   update.json                 {"version":"2.1.0","min_version":"2.0.0","notes":"…","delete":["old/file.php"]}
 *   <files mirroring the site root>  app/…, resources/…, routes/…, public/…, config/…, database/migrations/…, vendor/…
 *   post_update.php             optional, executed once then deleted
 * Never overwritten: .env, storage/, public/uploads/, install files.
 */
final class Updater
{
    /** How many past updates (history entries, log lines, pre-update backups) are kept. */
    public const KEEP = 3;

    private const PROTECTED = ['storage/', 'public/uploads/', '.env', '.git/', 'bootstrap/cache/'];

    public static function apply(string $zipPath): array
    {
        $current = (string) config('kiosk.version');
        if (! class_exists(ZipArchive::class)) return ['ok' => false, 'msg' => 'PHP zip extension is not installed.'];
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) return ['ok' => false, 'msg' => 'Could not open the zip file. Is it a valid update package?'];
        $meta = json_decode((string) $zip->getFromName('update.json'), true);
        if (! is_array($meta) || empty($meta['version']) || ! preg_match('/^\d+\.\d+\.\d+$/', (string) $meta['version'])) {
            $zip->close();

            return ['ok' => false, 'msg' => 'Invalid package: update.json with a valid "version" is missing.'];
        }
        $new = (string) $meta['version'];
        $fail = function (string $m) use ($zip) { $zip->close(); return ['ok' => false, 'msg' => $m]; };
        if (version_compare($new, $current, '<=')) return $fail("This package (v$new) is not newer than your installed version (v$current).");
        if (! empty($meta['min_version']) && version_compare($current, (string) $meta['min_version'], '<')) return $fail('This update requires at least v'.$meta['min_version'].' (you have v'.$current.').');
        if (! empty($meta['min_php']) && version_compare(PHP_VERSION, (string) $meta['min_php'], '<')) return $fail('This update needs PHP '.$meta['min_php'].'+.');

        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = preg_replace('#/+#', '/', str_replace('\\', '/', $zip->getNameIndex($i)));
            while (str_starts_with($name, './')) $name = substr($name, 2);
            if ($name === '' || str_ends_with($name, '/') || $name === 'update.json' || str_starts_with($name, '__MACOSX/')) continue;
            if (str_contains($name, '..') || str_starts_with($name, '/') || preg_match('#^[a-z]:#i', $name) || str_contains($name, "\0")) return $fail('Package rejected: unsafe path "'.$name.'".');
            foreach (self::PROTECTED as $pp) {
                if ($name === $pp || str_starts_with($name, $pp)) continue 2;
            }
            $files[] = ['idx' => $i, 'name' => $name];
        }
        if (! $files) return $fail('The package contains no files to install.');

        $log = [];
        $backup = storage_path('app/backups/pre-update-v'.$current.'-'.date('Ymd-His').'.zip');
        File::ensureDirectoryExists(dirname($backup));
        $bz = new ZipArchive;
        $added = 0;
        if ($bz->open($backup, ZipArchive::CREATE) === true) {
            foreach ($files as $f) {
                if (is_file(base_path($f['name']))) { $bz->addFile(base_path($f['name']), $f['name']); $added++; }
            }
            $bz->close();
            if ($added) $log[] = 'Backup: '.basename($backup);
        }

        foreach ($files as $f) {
            $t = base_path($f['name']);
            $probe = is_file($t) ? $t : dirname($t);
            while (! file_exists($probe) && $probe !== base_path()) $probe = dirname($probe);
            if (! is_writable($probe)) return $fail('Not writable: '.$f['name'].'. Fix file permissions (PHP user must own the site files).');
        }

        file_put_contents(storage_path('framework/updating.flag'), (string) time());
        try {
            foreach ($files as $f) {
                $t = base_path($f['name']);
                File::ensureDirectoryExists(dirname($t));
                $data = $zip->getFromIndex($f['idx']);
                if ($data === false || file_put_contents($t, $data) === false) throw new \RuntimeException('Failed writing '.$f['name']);
            }
            $zip->close();
            $log[] = count($files).' files updated';
            foreach ((array) ($meta['delete'] ?? []) as $d) {
                if (! is_string($d)) continue;
                $d = preg_replace('#/+#', '/', str_replace('\\', '/', $d));
                while (str_starts_with($d, './')) $d = substr($d, 2);
                if (str_contains($d, '..') || str_starts_with($d, '/') || str_starts_with($d, '.')) continue;
                foreach (self::PROTECTED as $pp) if (str_starts_with($d, $pp)) continue 2;
                if (is_file(base_path($d))) @unlink(base_path($d));
            }
            if (function_exists('opcache_reset')) @opcache_reset();

            Artisan::call('migrate', ['--force' => true]);
            $log[] = 'Database migrated';
            // authoritative version file — written only after the migrations succeeded so a failed run can be retried
            file_put_contents(config_path('kiosk.php'), "<?php\n\nreturn ['version' => '$new'];\n");
            $post = base_path('post_update.php');
            if (is_file($post)) {
                (static function () use ($post) { include $post; })();
                @unlink($post);
                $log[] = 'Post-update script executed';
            }
            self::clearCaches();
            $hist = json_decode((string) setting('update_history', '[]'), true) ?: [];
            array_unshift($hist, ['version' => $new, 'from' => $current, 'at' => now()->toDateTimeString(), 'notes' => (string) ($meta['notes'] ?? '')]);
            Settings::set('update_history', json_encode(array_slice($hist, 0, self::KEEP)));
            @file_put_contents(storage_path('logs/updates.log'), '['.now().'] v'.$current.' -> v'.$new.' | '.implode(' | ', $log)."\n", FILE_APPEND);
            $pruned = self::prune();
            if ($pruned['removed']) $log[] = 'Cleaned '.$pruned['removed'].' old update file(s)';
        } catch (\Throwable $e) {
            @unlink(storage_path('framework/updating.flag'));
            @$zip->close();

            return ['ok' => false, 'msg' => 'Update failed: '.$e->getMessage().'. A backup of replaced files is in storage/app/backups.'];
        }
        @unlink(storage_path('framework/updating.flag'));

        return ['ok' => true, 'msg' => "Updated to v$new", 'log' => $log, 'version' => $new];
    }

    /**
     * Keep only the last KEEP updates: history list, updates.log lines and pre-update backup zips.
     * Manual database backups are never touched.
     * @return array{removed:int,freed:int}
     */
    public static function prune(): array
    {
        $removed = 0;
        $freed = 0;
        $hist = json_decode((string) setting('update_history', '[]'), true) ?: [];
        if (count($hist) > self::KEEP) {
            Settings::set('update_history', json_encode(array_slice($hist, 0, self::KEEP)));
            $removed += count($hist) - self::KEEP;
        }
        $log = storage_path('logs/updates.log');
        if (is_file($log)) {
            $lines = array_values(array_filter(file($log, FILE_IGNORE_NEW_LINES) ?: [], fn ($l) => trim($l) !== ''));
            if (count($lines) > self::KEEP) {
                $removed += count($lines) - self::KEEP;
                @file_put_contents($log, implode("\n", array_slice($lines, -self::KEEP))."\n");
            }
        }
        $zips = glob(storage_path('app/backups/pre-update-*.zip')) ?: [];
        usort($zips, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($zips, self::KEEP) as $z) {
            $freed += (int) @filesize($z);
            if (@unlink($z)) $removed++;
        }
        foreach (glob(storage_path('app/tmp/update-*.zip')) ?: [] as $t) { $freed += (int) @filesize($t); if (@unlink($t)) $removed++; }

        return ['removed' => $removed, 'freed' => $freed];
    }

    public static function clearCaches(): void
    {
        foreach (glob(storage_path('framework/views/*.php')) ?: [] as $f) @unlink($f);
        foreach (glob(base_path('bootstrap/cache/*.php')) ?: [] as $f) { if (basename($f) !== '.gitignore') @unlink($f); }
        try { Artisan::call('cache:clear'); } catch (\Throwable) {}
        if (function_exists('opcache_reset')) @opcache_reset();
    }
}
