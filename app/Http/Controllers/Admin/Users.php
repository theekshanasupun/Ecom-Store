<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Users extends Controller
{
    public function index(Request $r)
    {
        $rows = User::whereIn('role', ['admin', 'staff'])->orderBy('id')->get()->all();
        $edit = $r->query('edit') ? User::whereIn('role', ['admin', 'staff'])->find((int) $r->query('edit')) : null;
        title('Team');

        return admin_page('users', compact('rows', 'edit'));
    }

    public function save(Request $r)
    {
        $id = (int) $r->input('id');
        $email = mb_strtolower(trim((string) $r->input('email')));
        $name = trim((string) $r->input('name'));
        $pass = (string) $r->input('password');
        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || (! $id && strlen($pass) < 8) || ($pass !== '' && strlen($pass) < 8)) {
            flash('error', 'Name, valid email and an 8+ character password are required.');

            return redirect('/admin/users');
        }
        if (User::where('email', $email)->when($id, fn ($q) => $q->where('id', '<>', $id))->exists()) {
            flash('error', 'That email is already in use.');

            return redirect('/admin/users');
        }
        $data = ['name' => $name, 'email' => $email, 'role' => $r->input('role') === 'admin' ? 'admin' : 'staff', 'status' => $r->input('status') === 'disabled' ? 'disabled' : 'active'];
        if ($pass !== '') $data['password'] = $pass;
        if ($id) {
            if ($id === Auth::id()) { $data['role'] = 'admin'; $data['status'] = 'active'; }
            User::whereKey($id)->first()?->update($data);
        } else {
            User::create($data);
        }
        flash('success', 'Team member saved.');

        return redirect('/admin/users');
    }

    public function delete(int $id)
    {
        if ($id === Auth::id()) flash('error', "You can't delete your own account.");
        else { User::whereKey($id)->whereIn('role', ['admin', 'staff'])->delete(); flash('success', 'Removed.'); }

        return redirect('/admin/users');
    }

    public function profile()
    {
        title('My profile');

        return admin_page('profile', ['u' => Auth::user()]);
    }

    public function profileSave(Request $r)
    {
        $u = Auth::user();
        $upd = ['name' => trim((string) $r->input('name')) ?: $u->name];
        $email = mb_strtolower(trim((string) $r->input('email')));
        if ($email !== $u->email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || User::where('email', $email)->where('id', '<>', $u->id)->exists()) {
                flash('error', 'That email is invalid or already used.');

                return redirect('/admin/profile');
            }
            $upd['email'] = $email;
        }
        $new = (string) $r->input('new_password');
        if ($new !== '') {
            if (! password_verify((string) $r->input('current_password'), $u->password) || strlen($new) < 8) {
                flash('error', 'Current password is wrong, or the new one is shorter than 8 characters.');

                return redirect('/admin/profile');
            }
            $upd['password'] = $new;
        }
        $u->update($upd);
        flash('success', 'Profile updated.');

        return redirect('/admin/profile');
    }
}
