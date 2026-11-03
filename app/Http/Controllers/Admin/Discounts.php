<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\DiscountRule;
use App\Models\Product;
use Illuminate\Http\Request;

/** Automatic discounts: sitewide, product, department and quantity-tier price rules. */
class Discounts extends Controller
{
    public function index(Request $r)
    {
        $rows = DiscountRule::orderByDesc('status')->orderByDesc('priority')->orderByDesc('id')->get()->all();
        $edit = $r->query('edit') ? DiscountRule::find((int) $r->query('edit')) : null;
        $cats = Category::orderBy('sort_order')->get(['id', 'name'])->all();
        $names = Product::whereIn('id', array_merge(...array_map(fn ($x) => $x->productIds(), $rows) ?: [[]]))->pluck('name', 'id')->all();
        $init = [];
        if ($edit) foreach ($edit->productIds() as $pid) if ($pr = Product::find($pid)) $init[] = ['id' => $pr->id, 'name' => $pr->name];
        $stats = [
            'total' => count($rows), 'active' => count(array_filter($rows, fn ($x) => $x->status === 'active' && (! $x->ends_at || $x->ends_at->isFuture()) && (! $x->starts_at || $x->starts_at->isPast()))),
            'scheduled' => count(array_filter($rows, fn ($x) => $x->status === 'active' && $x->starts_at && $x->starts_at->isFuture())),
            'tiers' => count(array_filter($rows, fn ($x) => $x->min_qty > 1)),
        ];
        $sample = Product::where('status', 'active')->where('price', '>', 0)->orderByDesc('sales')->first(['id', 'name', 'price']);
        title('Discounts');

        return admin_page('discounts', compact('rows', 'edit', 'cats', 'names', 'init', 'stats', 'sample'));
    }

    public function save(Request $r)
    {
        $id = (int) $r->input('id');
        $scope = in_array($r->input('scope'), ['all', 'products', 'categories'], true) ? $r->input('scope') : 'all';
        $type = $r->input('type') === 'fixed' ? 'fixed' : 'percent';
        $value = $type === 'fixed' ? max(0, to_cents($r->input('value', 0))) : max(0, min(100, (int) round((float) $r->input('value'))));
        $pids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $r->input('product_ids'))))));
        $cids = array_values(array_unique(array_filter(array_map('intval', (array) $r->input('category_ids')))));
        $back = $id ? "/admin/discounts?edit=$id" : '/admin/discounts?new=1';
        $name = trim((string) $r->input('name'));
        if ($name === '' || $value <= 0) { flash('error', 'Give the discount a name and a value above zero.'); keep_old($r->all()); return redirect($back); }
        if ($scope === 'products' && ! $pids) { flash('error', 'Pick at least one product.'); keep_old($r->all()); return redirect($back); }
        if ($scope === 'categories' && ! $cids) { flash('error', 'Pick at least one department.'); keep_old($r->all()); return redirect($back); }
        $data = [
            'name' => mb_substr($name, 0, 150), 'status' => $r->input('status') === 'draft' ? 'draft' : 'active', 'scope' => $scope,
            'product_ids' => $scope === 'products' ? implode(',', $pids) : '', 'category_ids' => $scope === 'categories' ? implode(',', $cids) : '',
            'type' => $type, 'value' => $value, 'min_qty' => max(1, (int) $r->input('min_qty', 1)), 'audience' => in_array($r->input('audience'), ['guests', 'customers'], true) ? $r->input('audience') : 'all',
            'skip_sale' => $r->boolean('skip_sale'), 'label' => mb_substr(trim((string) $r->input('label')), 0, 40) ?: null, 'priority' => max(0, (int) $r->input('priority')),
            'starts_at' => $r->input('starts_at') ? date('Y-m-d 00:00:00', strtotime($r->input('starts_at'))) : null,
            'ends_at' => $r->input('ends_at') ? date('Y-m-d 23:59:59', strtotime($r->input('ends_at'))) : null,
        ];
        $id ? DiscountRule::whereKey($id)->update($data) : DiscountRule::create($data);
        \App\Services\Pricing::forget();
        flash('success', 'Discount saved — prices update on the storefront immediately.');

        return redirect('/admin/discounts');
    }

    public function toggle(Request $r, int $id)
    {
        $x = DiscountRule::findOrFail($id);
        $x->update(['status' => $r->input('value') === '1' ? 'active' : 'draft']);

        return response()->json(['ok' => true, 'message' => $x->status === 'active' ? 'Discount is live' : 'Discount paused']);
    }

    public function duplicate(int $id)
    {
        $x = DiscountRule::findOrFail($id)->replicate();
        $x->name .= ' (copy)';
        $x->status = 'draft';
        $x->save();
        flash('success', 'Duplicated as a draft.');

        return redirect('/admin/discounts?edit='.$x->id);
    }

    public function delete(int $id)
    {
        DiscountRule::whereKey($id)->delete();
        flash('success', 'Discount deleted.');

        return redirect('/admin/discounts');
    }
}
