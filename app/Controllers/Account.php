<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\View;

final class Account
{
    public function login(): void
    {
        if (auth_user()) redirect('account');
        title('Sign in');
        View::page('account/login', []);
    }

    public function loginPost(): void
    {
        if (Auth::attempt(str_in('email'), (string) input('password', ''))) {
            $to = $_SESSION['after_login'] ?? 'account';
            unset($_SESSION['after_login']);
            if (Auth::isStaff() && $to === 'account') $to = 'account';
            redirect($to);
        }
        keep_old(['email' => str_in('email')]);
        flash('error', Auth::tooManyAttempts() ? 'Too many attempts. Try again in 15 minutes.' : 'Wrong email or password.');
        redirect('login');
    }

    public function register(): void
    {
        if (auth_user()) redirect('account');
        title('Create account');
        View::page('account/register', []);
    }

    public function registerPost(): void
    {
        $name = str_in('name');
        $email = mb_strtolower(str_in('email'));
        $pass = (string) input('password', '');
        $err = null;
        if (mb_strlen($name) < 2) $err = 'Enter your name.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $err = 'Enter a valid email.';
        elseif (strlen($pass) < 8) $err = 'Password must be at least 8 characters.';
        elseif (Db::val('SELECT COUNT(*) FROM users WHERE email = ?', [$email])) $err = 'An account with that email already exists.';
        if ($err) {
            keep_old(['name' => $name, 'email' => $email]);
            flash('error', $err);
            redirect('register');
        }
        $id = Db::insert('users', ['name' => $name, 'email' => $email, 'password' => Auth::hash($pass), 'role' => 'customer', 'status' => 'active', 'created_at' => now()]);
        // attach earlier guest orders placed with this email
        Db::exec('UPDATE orders SET user_id = ? WHERE email = ? AND user_id IS NULL', [$id, $email]);
        Auth::login(Db::one('SELECT * FROM users WHERE id = ?', [$id]));
        forget_old();
        flash('success', 'Welcome aboard, ' . explode(' ', $name)[0] . '!');
        $to = $_SESSION['after_login'] ?? 'account';
        unset($_SESSION['after_login']);
        redirect($to);
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('');
    }

    public function index(): void
    {
        $u = auth_user();
        if (!$u) {
            $_SESSION['after_login'] = 'account';
            redirect('login');
        }
        $orders = Db::all('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 100', [$u['id']]);
        title('My account');
        View::page('account/index', compact('u', 'orders'));
    }

    public function profile(): void
    {
        $u = auth_user();
        if (!$u) redirect('login');
        $name = str_in('name');
        $upd = ['name' => $name ?: $u['name'], 'phone' => str_in('phone')];
        $new = (string) input('new_password', '');
        if ($new !== '') {
            if (!password_verify((string) input('current_password', ''), $u['password'])) {
                flash('error', 'Current password is incorrect.');
                redirect('account');
            }
            if (strlen($new) < 8) {
                flash('error', 'New password must be at least 8 characters.');
                redirect('account');
            }
            $upd['password'] = Auth::hash($new);
        }
        Db::update('users', $upd, 'id = ?', [$u['id']]);
        flash('success', 'Profile updated.');
        redirect('account');
    }

    public function track(): void
    {
        title('Track an order');
        View::page('account/track', []);
    }

    public function trackPost(): void
    {
        if (Auth::tooManyAttempts('track')) {
            flash('error', 'Too many tries. Please wait a few minutes.');
            redirect('track');
        }
        $n = strtoupper(str_in('number'));
        $e = mb_strtolower(str_in('email'));
        $o = Db::one('SELECT * FROM orders WHERE number = ? AND email = ?', [$n, $e]);
        if (!$o) {
            Db::insert('attempts', ['ip' => client_ip(), 'scope' => 'track', 'created_at' => now()]);
            flash('error', 'No order matches those details.');
            redirect('track');
        }
        redirect('order/' . $o['number'] . '?t=' . $o['token']);
    }
}
