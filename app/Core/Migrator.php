<?php
namespace App\Core;

/**
 * Runs database/migrations/*.php in order. Each file returns a callable
 * that receives the Migrator (use $m->ddl() for portable CREATE TABLE).
 */
final class Migrator
{
    public static function ddl(string $sql): string
    {
        if (Db::$driver === 'sqlite') {
            $sql = str_replace('{PK}', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
            return str_replace('{OPTS}', '', $sql);
        }
        $sql = str_replace('{PK}', 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', $sql);
        return str_replace('{OPTS}', 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', $sql);
    }

    public static function run(): array
    {
        Db::boot();
        Db::exec(self::ddl('CREATE TABLE IF NOT EXISTS migrations (id {PK}, name VARCHAR(191) NOT NULL UNIQUE, ran_at VARCHAR(19) NOT NULL) {OPTS}'));
        $done = Db::col('SELECT name FROM migrations');
        $files = glob(ROOT . '/database/migrations/*.php') ?: [];
        sort($files);
        $ran = [];
        foreach ($files as $f) {
            $name = basename($f, '.php');
            if (in_array($name, $done, true)) {
                continue;
            }
            $fn = require $f;
            if (is_callable($fn)) {
                $fn(new self());
            }
            Db::insert('migrations', ['name' => $name, 'ran_at' => now()]);
            $ran[] = $name;
        }
        return $ran;
    }

    public function sql(string $sql): void
    {
        Db::exec(self::ddl($sql));
    }

    public function hasColumn(string $table, string $col): bool
    {
        try {
            Db::q("SELECT {$col} FROM {$table} LIMIT 1");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
