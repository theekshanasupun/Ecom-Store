<?php
namespace App\Core;

use PDO;
use PDOStatement;

final class Db
{
    public static ?PDO $pdo = null;
    public static string $driver = 'mysql';

    public static function connect(array $c): PDO
    {
        self::$driver = $c['driver'] ?? 'mysql';
        $opts = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
        ];
        if (self::$driver === 'sqlite') {
            $file = $c['file'] ?? (STORAGE . '/database.sqlite');
            self::$pdo = new PDO('sqlite:' . $file, null, null, $opts);
            self::$pdo->exec('PRAGMA busy_timeout = 5000');
            self::$pdo->exec('PRAGMA foreign_keys = OFF');
        } else {
            $dsn = 'mysql:host=' . $c['host'] . ';port=' . ($c['port'] ?? 3306) . ';dbname=' . $c['name'] . ';charset=utf8mb4';
            self::$pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
            self::$pdo->exec("SET NAMES utf8mb4");
            try {
                self::$pdo->exec("SET time_zone = '+00:00'");
            } catch (\Throwable) {
            }
        }
        return self::$pdo;
    }

    public static function boot(): void
    {
        if (!self::$pdo && installed()) {
            self::connect((array) cfg('db', []));
        }
    }

    public static function q(string $sql, array $params = []): PDOStatement
    {
        self::boot();
        $st = self::$pdo->prepare($sql);
        $i = 1;
        foreach ($params as $p) {
            $type = is_int($p) ? PDO::PARAM_INT : (is_null($p) ? PDO::PARAM_NULL : (is_bool($p) ? PDO::PARAM_BOOL : PDO::PARAM_STR));
            $st->bindValue($i++, $p, $type);
        }
        $st->execute();
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $r = self::q($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function val(string $sql, array $params = []): mixed
    {
        $r = self::q($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    public static function col(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function exec(string $sql, array $params = []): int
    {
        return self::q($sql, $params)->rowCount();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = "INSERT INTO {$table} (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::q($sql, array_values($data));
        return (int) self::$pdo->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        $set = implode(',', array_map(fn($c) => "$c = ?", array_keys($data)));
        return self::exec("UPDATE {$table} SET {$set} WHERE {$where}", array_merge(array_values($data), $params));
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::exec("DELETE FROM {$table} WHERE {$where}", $params);
    }

    public static function tx(callable $fn): mixed
    {
        self::boot();
        self::$pdo->beginTransaction();
        try {
            $r = $fn();
            self::$pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            if (self::$pdo->inTransaction()) {
                self::$pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function placeholders(array $items): string
    {
        return implode(',', array_fill(0, count($items), '?'));
    }
}
