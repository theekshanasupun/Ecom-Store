<?php
namespace App\Controllers\Admin;

use App\Core\Db;

final class Coupons extends Base
{
    public function index(): void
    {
        $rows = Db::all('SELECT * FROM coupons ORDER BY id DESC');
        $edit = int_in('edit') ? Db::one('SELECT * FROM coupons WHERE id = ?', [int_in('edit')]) : null;
        title('Coupons');
        $this->render('coupons', compact('rows', 'edit'));
    }

    public function save(): void
    {
        $id = int_in('id');
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', str_in('code')));
        $type = str_in('type') === 'fixed' ? 'fixed' : 'percent';
        $value = $type === 'fixed' ? to_cents(input('value', 0)) : max(1, min(100, int_in('value')));
        if ($code === '' || $value <= 0) {
            flash('error', 'Code and a positive value are required.');
            redirect('admin/coupons');
        }
        $dup = Db::val('SELECT id FROM coupons WHERE code = ?' . ($id ? ' AND id <> ' . $id : ''), [$code]);
        if ($dup) {
            flash('error', 'That code already exists.');
            redirect('admin/coupons');
        }
        $data = [
            'code' => $code, 'type' => $type, 'value' => $value,
            'min_total' => to_cents(input('min_total', 0)), 'max_uses' => max(0, int_in('max_uses')),
            'starts_at' => str_in('starts_at') ? date('Y-m-d 00:00:00', strtotime(str_in('starts_at'))) : null,
            'ends_at' => str_in('ends_at') ? date('Y-m-d 23:59:59', strtotime(str_in('ends_at'))) : null,
            'status' => str_in('status') === 'disabled' ? 'disabled' : 'active',
        ];
        if ($id) {
            Db::update('coupons', $data, 'id = ?', [$id]);
        } else {
            $data['created_at'] = now();
            Db::insert('coupons', $data);
        }
        flash('success', 'Coupon saved.');
        redirect('admin/coupons');
    }

    public function delete(array $a): void
    {
        Db::delete('coupons', 'id = ?', [(int) $a['id']]);
        flash('success', 'Coupon deleted.');
        redirect('admin/coupons');
    }
}
