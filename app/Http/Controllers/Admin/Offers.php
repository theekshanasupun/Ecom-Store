<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Offer;
use App\Models\Product;
use Illuminate\Http\Request;

/** Upsell, cross-sell and bundle builder. */
class Offers extends Controller
{
    public function index(Request $r)
    {
        $rows = Offer::orderByDesc('status')->orderBy('sort')->orderByDesc('id')->get()->all();
        $edit = $r->query('edit') ? Offer::find((int) $r->query('edit')) : null;
        $cats = Category::orderBy('sort_order')->get(['id', 'name'])->all();
        $init = fn (array $ids) => array_values(array_map(fn ($p) => ['id' => $p->id, 'name' => $p->name], Product::whereIn('id', $ids)->get(['id', 'name'])->all()));
        $initTrig = $edit ? $init($edit->triggerProducts()) : [];
        $initOffer = $edit ? $init($edit->offerProducts()) : [];
        $stats = ['total' => count($rows), 'active' => count(array_filter($rows, fn ($x) => $x->status === 'active')), 'views' => array_sum(array_column($rows, 'views')), 'adds' => array_sum(array_column($rows, 'adds'))];
        title('Upsell & bundles');

        return admin_page('offers', compact('rows', 'edit', 'cats', 'initTrig', 'initOffer', 'stats'));
    }

    public function products(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $out = [];
        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            foreach (Product::withoutEvents(fn () => Product::where('name', 'like', $like)->orWhere('sku', 'like', $like)->orderBy('name')->limit(12)->get(['id', 'name', 'sku', 'price'])) as $p) {
                $out[] = ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'price' => money($p->price)];
            }
        }

        return response()->json(['ok' => true, 'results' => $out]);
    }

    public function save(Request $r)
    {
        $id = (int) $r->input('id');
        $ids = fn ($k) => array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $r->input($k))))));
        $kind = in_array($r->input('kind'), ['upsell', 'crosssell', 'bundle'], true) ? $r->input('kind') : 'crosssell';
        $dtype = in_array($r->input('discount_type'), ['percent', 'fixed'], true) ? $r->input('discount_type') : 'none';
        $dval = $dtype === 'fixed' ? max(0, to_cents($r->input('discount_value', 0))) : ($dtype === 'percent' ? max(0, min(100, (int) round((float) $r->input('discount_value')))) : 0);
        $offer = $ids('offer_product_ids');
        $back = $id ? "/admin/offers?edit=$id" : '/admin/offers?new=1';
        $name = trim((string) $r->input('name'));
        if ($name === '') { flash('error', 'Give this offer a name.'); keep_old($r->all()); return redirect($back); }
        if ($kind === 'bundle' && count($offer) < 2) { flash('error', 'A bundle needs at least two products.'); keep_old($r->all()); return redirect($back); }
        if ($kind !== 'bundle' && ! $offer) { flash('error', 'Pick the product(s) to recommend.'); keep_old($r->all()); return redirect($back); }
        if ($dtype !== 'none' && $dval <= 0) $dtype = 'none';
        $pl = array_values(array_intersect((array) $r->input('placement', []), ['product', 'cart', 'checkout']));
        $data = [
            'name' => mb_substr($name, 0, 150), 'kind' => $kind, 'status' => $r->input('status') === 'draft' ? 'draft' : 'active',
            'trigger_product_ids' => $kind === 'bundle' ? '' : implode(',', $ids('trigger_product_ids')), 'trigger_category_ids' => $kind === 'bundle' ? '' : implode(',', array_filter(array_map('intval', (array) $r->input('trigger_category_ids')))),
            'offer_product_ids' => implode(',', $offer), 'discount_type' => $dtype, 'discount_value' => $dval,
            'placement' => implode(',', $pl ?: ['product']), 'headline' => mb_substr(trim((string) $r->input('headline')), 0, 120) ?: null, 'subtext' => mb_substr(trim((string) $r->input('subtext')), 0, 190) ?: null,
            'sort' => max(0, (int) $r->input('sort')),
        ];
        $id ? Offer::whereKey($id)->update($data) : Offer::create($data);
        \App\Services\Offers::forget();
        flash('success', 'Offer saved.');

        return redirect('/admin/offers');
    }

    public function toggle(Request $r, int $id)
    {
        $x = Offer::findOrFail($id);
        $x->update(['status' => $r->input('value') === '1' ? 'active' : 'draft']);

        return response()->json(['ok' => true, 'message' => $x->status === 'active' ? 'Offer is live' : 'Offer paused']);
    }

    public function duplicate(int $id)
    {
        $x = Offer::findOrFail($id)->replicate();
        $x->name .= ' (copy)';
        $x->status = 'draft';
        $x->views = $x->adds = 0;
        $x->save();
        flash('success', 'Duplicated as a draft.');

        return redirect('/admin/offers?edit='.$x->id);
    }

    public function delete(int $id)
    {
        Offer::whereKey($id)->delete();
        flash('success', 'Offer deleted.');

        return redirect('/admin/offers');
    }
}
