<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;

class Coupons extends Controller
{
    public function index(Request $r)
    {
        $rows = Coupon::orderByDesc('id')->get()->all();
        $edit = $r->query('edit') ? Coupon::find((int) $r->query('edit')) : null;
        title('Coupons');

        return admin_page('coupons', compact('rows', 'edit'));
    }

    public function save(Request $r)
    {
        $id = (int) $r->input('id');
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $r->input('code')));
        $type = $r->input('type') === 'fixed' ? 'fixed' : 'percent';
        $value = $type === 'fixed' ? to_cents($r->input('value', 0)) : max(1, min(100, (int) $r->input('value')));
        if ($code === '' || $value <= 0) {
            flash('error', 'Code and a positive value are required.');

            return redirect('/admin/coupons');
        }
        if (Coupon::where('code', $code)->when($id, fn ($q) => $q->where('id', '<>', $id))->exists()) {
            flash('error', 'That code already exists.');

            return redirect('/admin/coupons');
        }
        $data = [
            'code' => $code, 'type' => $type, 'value' => $value, 'min_total' => to_cents($r->input('min_total', 0)), 'max_uses' => max(0, (int) $r->input('max_uses')),
            'starts_at' => $r->input('starts_at') ? date('Y-m-d 00:00:00', strtotime($r->input('starts_at'))) : null,
            'ends_at' => $r->input('ends_at') ? date('Y-m-d 23:59:59', strtotime($r->input('ends_at'))) : null,
            'status' => $r->input('status') === 'disabled' ? 'disabled' : 'active',
        ];
        $id ? Coupon::whereKey($id)->update($data) : Coupon::create($data);
        flash('success', 'Coupon saved.');

        return redirect('/admin/coupons');
    }

    public function delete(int $id)
    {
        Coupon::whereKey($id)->delete();
        flash('success', 'Coupon deleted.');

        return redirect('/admin/coupons');
    }
}
