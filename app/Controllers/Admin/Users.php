<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Db;

final class Users extends Base
{
    public function index(): void
    {
        $this->adminOnly();
        $rows = Db::all("SELECT * FROM users WHERE role IN ('admin','staff') ORDER BY id");
        $edit = int_in('edit') ? Db::one("SELECT * FROM users WHERE id = ? AND role IN ('admin','staff')", [int_in('edit')]) : null;
        title('Team');
        $this->render('users', compact('rows', 'edit'));
    }

    public function save(): void
    {
        $this->adminOnly();
        $id = int_in('id');
        $email = mb_strtolower(str_in('email'));
        $name = str_in('name');
        $role = str_in('role') === 'admin' ? 'admin' : 'staff';
        $pass = (string) input('password', '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || (!$id && strlen($pass) < 8) || ($pass !== '' && strlen($pass) < 8)) {
            flash('error', 'Name, valid email and an 8+ character password are required.');
            redirect('admin/users');
        }
        $dup = Db::val('SELECT id FROM users WHERE email = ?' . ($id ? ' AND id <> ' . $id : ''), [$email]);
        if ($dup) {
            flash('error', 'That email is already in use.');
            redirect('admin/users');
        }
        $data = ['name' => $name, 'email' => $email, 'role' => $role, 'status' => str_in('status') === 'disabled' ? 'disabled' : 'active'];
        if ($pass !== '') $data['password'] = Auth::hash($pass);
        if ($id) {
            if ($id === Auth::id()) { $data['role'] = 'admin'; $data['status'] = 'active'; }
            Db::update('users', $data, 'id = ?', [$id]);
        } else {
            $data['created_at'] = now();
            Db::insert('users', $data);
        }
        flash('success', 'Team member saved.');
        redirect('admin/users');
    }

    public function delete(array $a): void
    {
        $this->adminOnly();
        $id = (int) $a['id'];
        if ($id === Auth::id()) {
            flash('error', "You can't delete your own account.");
        } else {
            Db::delete('users', "id = ? AND role IN ('admin','staff')", [$id]);
            flash('success', 'Removed.');
        }
        redirect('admin/users');
    }

    public function profile(): void
    {
        title('My profile');
        $this->render('profile', ['u' => Auth::user()]);
    }

    public function profileSave(): void
    {
        $u = Auth::user();
        $upd = ['name' => str_in('name') ?: $u['name']];
        $email = mb_strtolower(str_in('email'));
        if ($email !== $u['email']) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || Db::val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $u['id']])) {
                flash('error', 'That email is invalid or already used.');
                redirect('admin/profile');
            }
            $upd['email'] = $email;
        }
        $new = (string) input('new_password', '');
        if ($new !== '') {
            if (!password_verify((string) input('current_password', ''), $u['password']) || strlen($new) < 8) {
                flash('error', 'Current password is wrong, or the new one is shorter than 8 characters.');
                redirect('admin/profile');
            }
            $upd['password'] = Auth::hash($new);
        }
        Db::update('users', $upd, 'id = ?', [$u['id']]);
        flash('success', 'Profile updated.');
        redirect('admin/profile');
    }
}
