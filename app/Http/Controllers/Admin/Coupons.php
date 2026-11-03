<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Product;
use Illuminate\Http\Request;

class Coupons extends Controller
{
    public function index(Request $r)
    {
        $rows = Coupon::orderByDesc('id')->get()->all();
        $edit = $r->query('edit') ? Coupon::find((int) $r->query('edit')) : null;
        $cats = Category::orderBy('sort_order')->get(['id', 'name'])->all();
        $products = Product::orderBy('name')->limit(500)->get(['id', 'name', 'sku'])->all();
        $stats = ['total' => count($rows), 'active' => count(array_filter($rows, fn ($c) => $c['status'] === 'active')), 'uses' => (int) array_sum(array_column($rows, 'used')), 'auto' => count(array_filter($rows, fn ($c) => $c['auto_apply']))];
        title('Coupons');

        return admin_page('coupons', compact('rows', 'edit', 'cats', 'products', 'stats'));
    }

    public function save(Request $r)
    {
        $id = (int) $r->input('id');
        $code = strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', (string) $r->input('code')));
        $type = $r->input('type') === 'fixed' ? 'fixed' : 'percent';
        $shippingOnly = $r->input('kind') === 'shipping';
        $freeShip = $shippingOnly || $r->boolean('free_shipping');
        $value = $shippingOnly ? 0 : ($type === 'fixed' ? to_cents($r->input('value', 0)) : max(0, min(100, (int) $r->input('value'))));
        $scope = in_array($r->input('scope'), ['cart', 'products', 'categories'], true) && ! $shippingOnly ? $r->input('scope') : 'cart';
        $pids = $scope === 'products' ? array_values(array_filter(array_map('intval', (array) $r->input('product_ids')))) : [];
        $cids = $scope === 'categories' ? array_values(array_filter(array_map('intval', (array) $r->input('category_ids')))) : [];
        $back = $id ? "/admin/coupons?edit=$id" : '/admin/coupons?new=1';
        keep_old($r->except(['product_ids', 'category_ids']));
        if ($code === '' || ($value <= 0 && ! $freeShip)) {
            flash('error', 'A code and either a positive value or "free shipping" are required.');

            return redirect($back);
        }
        if ($scope === 'products' && ! $pids) { flash('error', 'Pick at least one product for an item coupon.'); return redirect($back); }
        if ($scope === 'categories' && ! $cids) { flash('error', 'Pick at least one department.'); return redirect($back); }
        if (Coupon::where('code', $code)->when($id, fn ($q) => $q->where('id', '<>', $id))->exists()) {
            flash('error', 'That code already exists.');

            return redirect($back);
        }
        $data = [
            'code' => $code, 'type' => $type, 'value' => $value, 'scope' => $scope, 'product_ids' => implode(',', $pids), 'category_ids' => implode(',', $cids),
            'min_total' => to_cents($r->input('min_total', 0)), 'min_qty' => max(0, (int) $r->input('min_qty')), 'max_discount' => to_cents($r->input('max_discount', 0)),
            'max_uses' => max(0, (int) $r->input('max_uses')), 'per_user_limit' => max(0, (int) $r->input('per_user_limit')),
            'exclude_sale' => $r->boolean('exclude_sale'), 'first_order_only' => $r->boolean('first_order_only'), 'free_shipping' => $freeShip, 'auto_apply' => $r->boolean('auto_apply'),
            'note' => mb_substr(trim((string) $r->input('note')), 0, 190),
            'starts_at' => $r->input('starts_at') ? date('Y-m-d 00:00:00', strtotime($r->input('starts_at'))) : null,
            'ends_at' => $r->input('ends_at') ? date('Y-m-d 23:59:59', strtotime($r->input('ends_at'))) : null,
            'status' => $r->input('status') === 'disabled' ? 'disabled' : 'active',
        ];
        $id ? Coupon::whereKey($id)->update($data) : Coupon::create($data);
        session()->forget('_old_input');
        flash('success', 'Coupon saved.');

        return redirect('/admin/coupons');
    }

    public function bulk(Request $r)
    {
        $ids = array_values(array_filter(array_map('intval', (array) $r->input('ids'))));
        $act = (string) $r->input('action');
        if ($ids && $act === 'delete') Coupon::whereIn('id', $ids)->delete();
        elseif ($ids && in_array($act, ['enable', 'disable'], true)) Coupon::whereIn('id', $ids)->update(['status' => $act === 'enable' ? 'active' : 'disabled']);
        else { flash('error', 'Select coupons and an action.'); return redirect('/admin/coupons'); }
        flash('success', count($ids).' coupon(s) updated.');

        return redirect('/admin/coupons');
    }

    public function delete(int $id)
    {
        Coupon::whereKey($id)->delete();
        flash('success', 'Coupon deleted.');

        return redirect('/admin/coupons');
    }

    /** AJAX on/off switch from the table. */
    public function toggle(Request $r, int $id)
    {
        $c = Coupon::findOrFail($id);
        $c->update(['status' => $r->input('value') === '1' ? 'active' : 'disabled']);

        return response()->json(['ok' => true, 'message' => $c->code.($c->status === 'active' ? ' enabled' : ' disabled')]);
    }

    public function duplicate(int $id)
    {
        $c = Coupon::findOrFail($id);
        $n = $c->replicate();
        $base = preg_replace('/-COPY\d*$/', '', $c->code);
        $code = $base.'-COPY';
        $i = 1;
        while (Coupon::where('code', $code)->exists()) $code = $base.'-COPY'.(++$i);
        $n->code = $code;
        $n->used = 0;
        $n->status = 'disabled';
        $n->save();
        flash('success', "Duplicated as $code (disabled — review and enable it).");

        return redirect('/admin/coupons?edit='.$n->id);
    }
}
