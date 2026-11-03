<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;

final class AuthController
{
    public function login(): void
    {
        if (Auth::isStaff()) redirect('admin');
        View::page('admin/login', [], 'admin/bare');
    }

    public function loginPost(): void
    {
        if (Auth::attempt(str_in('email'), (string) input('password', ''), ['admin', 'staff'])) {
            redirect('admin');
        }
        keep_old(['email' => str_in('email')]);
        flash('error', Auth::tooManyAttempts() ? 'Too many attempts. Try again in 15 minutes.' : 'Wrong email or password.');
        redirect('admin/login');
    }

    public function logout(): void
    {
        Auth::logout();
        redirect('admin/login');
    }
}
