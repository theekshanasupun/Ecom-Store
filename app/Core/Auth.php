<?php
namespace App\Core;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = $_SESSION['uid'] ?? null;
            if ($id) {
                $u = Db::one('SELECT * FROM users WHERE id = ? AND status = ?', [(int) $id, 'active']);
                self::$user = $u ?: null;
                if (!$u) {
                    unset($_SESSION['uid']);
                }
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::user() ? (int) self::user()['id'] : null;
    }

    public static function isStaff(): bool
    {
        $u = self::user();
        return $u && in_array($u['role'], ['admin', 'staff'], true);
    }

    public static function isAdmin(): bool
    {
        $u = self::user();
        return $u && $u['role'] === 'admin';
    }

    public static function tooManyAttempts(string $scope = 'login'): bool
    {
        $since = date('Y-m-d H:i:s', time() - 900);
        Db::delete('attempts', 'created_at < ?', [date('Y-m-d H:i:s', time() - 86400)]);
        $n = (int) Db::val('SELECT COUNT(*) FROM attempts WHERE ip = ? AND scope = ? AND created_at > ?', [client_ip(), $scope, $since]);
        return $n >= 8;
    }

    public static function attempt(string $email, string $password, ?array $roles = null): bool
    {
        if (self::tooManyAttempts('login')) {
            return false;
        }
        $u = Db::one('SELECT * FROM users WHERE email = ? AND status = ?', [mb_strtolower(trim($email)), 'active']);
        if (!$u || !password_verify($password, $u['password'])) {
            Db::insert('attempts', ['ip' => client_ip(), 'scope' => 'login', 'created_at' => now()]);
            return false;
        }
        if ($roles && !in_array($u['role'], $roles, true)) {
            Db::insert('attempts', ['ip' => client_ip(), 'scope' => 'login', 'created_at' => now()]);
            return false;
        }
        if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) {
            Db::update('users', ['password' => self::hash($password)], 'id = ?', [(int) $u['id']]);
        }
        self::login($u);
        return true;
    }

    public static function login(array $u): void
    {
        $cart = $_SESSION['cart'] ?? null;
        Session::regenerate();
        $_SESSION['uid'] = (int) $u['id'];
        if ($cart) {
            $_SESSION['cart'] = $cart;
        }
        Db::update('users', ['last_login' => now()], 'id = ?', [(int) $u['id']]);
        self::$loaded = false;
        self::$user = null;
    }

    public static function logout(): void
    {
        unset($_SESSION['uid']);
        Session::regenerate();
        self::$loaded = false;
        self::$user = null;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
