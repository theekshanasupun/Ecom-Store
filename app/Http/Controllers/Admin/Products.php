<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductKey;
use App\Support\Art;
use App\Support\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class Products extends Controller
{
    public function index(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $cat = (int) $r->query('cat');
        $type = (string) $r->query('type');
        $query = Product::with('cat')->withCount(['keys as pool' => fn ($k) => $k->where('status', 'available')])
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('sku', 'like', "%$q%")))
            ->when($cat, fn ($x) => $x->where('category_id', $cat))
            ->when(isset(Product::TYPES[$type]), fn ($x) => $x->where('type', $type))
            ->orderByDesc('id');
        $per = in_array((int) $r->query('per'), [20, 50, 100], true) ? (int) $r->query('per') : 20;
        $p = $query->paginate($per)->withQueryString();
        $rows = $p->items();
        $page = $p->currentPage();
        $pages = $p->lastPage();
        $total = $p->total();
        $cats = Category::orderBy('sort_order')->get();
        title('Products');

        return admin_page('products', compact('rows', 'cats', 'q', 'cat', 'type', 'page', 'pages', 'total', 'per'));
    }

    public function form(?int $id = null)
    {
        $p = null;
        $keys = [];
        $keyStats = ['available' => 0, 'sold' => 0];
        if ($id) {
            $p = Product::with('cat')->findOrFail($id);
            foreach (ProductKey::where('product_id', $id)->selectRaw('status, count(*) n')->groupBy('status')->get() as $r) $keyStats[$r->status] = (int) $r->n;
            foreach (ProductKey::where('product_id', $id)->where('status', 'available')->orderByDesc('id')->limit(200)->get() as $k) {
                $keys[] = ['id' => $k->id, 'text' => $k->payload];
            }
        }
        $cats = Category::orderBy('sort_order')->get();
        $arts = Art::TYPES;
        title($p ? 'Edit '.$p->name : 'New product');

        return admin_page('product_form', compact('p', 'cats', 'arts', 'keys', 'keyStats'));
    }

    public function save(Request $r)
    {
        $id = (int) $r->input('id');
        $old = $id ? Product::find($id) : null;
        $name = trim((string) $r->input('name'));
        $back = $id ? "/admin/products/$id" : '/admin/products/new';
        if ($name === '') {
            flash('error', 'Product name is required.');
            keep_old($r->all());

            return redirect($back);
        }
        $type = isset(Product::TYPES[$r->input('type')]) ? $r->input('type') : 'physical';
        [$opts, $variants] = $this->parseVariants($r);
        $s = fn ($k, $l = 250) => mb_substr(trim((string) $r->input($k)), 0, $l);
        $data = [
            'category_id' => (int) $r->input('category_id'), 'name' => $name, 'tagline' => $s('tagline'),
            'description' => (string) $r->input('description'), 'type' => $type,
            'price' => to_cents($r->input('price', 0)), 'compare_price' => to_cents($r->input('compare_price', 0)),
            'stock' => max(0, (int) $r->input('stock')), 'unlimited' => $r->boolean('unlimited'), 'sku' => $s('sku', 80),
            'art' => $s('art', 20), 'art_color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $r->input('art_color')) ? $r->input('art_color') : '',
            'badge' => $s('badge', 30), 'tags' => $s('tags'), 'options' => $opts ? json_encode($opts, JSON_UNESCAPED_UNICODE) : '', 'variants' => $variants ? json_encode($variants, JSON_UNESCAPED_UNICODE) : '',
            'vip_tier_id' => ((int) $r->input('vip_tier_id')) ?: null, 'early_access_tier_id' => ((int) $r->input('early_access_tier_id')) ?: null,
            'early_access_until' => $r->filled('early_access_until') ? \Carbon\Carbon::parse((string) $r->input('early_access_until')) : null,
            'details' => (string) $r->input('details'), 'delivery_note' => $s('delivery_note'),
            'seo_title' => mb_substr(trim((string) $r->input('seo_title')), 0, 190), 'seo_description' => mb_substr(trim((string) $r->input('seo_description')), 0, 320), 'seo_noindex' => $r->boolean('seo_noindex'),
            'featured' => $r->boolean('featured'), 'status' => $r->input('status') === 'draft' ? 'draft' : 'active',
        ];
        try {
            if ($img = Upload::image($r->file('image'))) {
                if ($old) Upload::remove($old->image);
                $data['image'] = $img;
            } elseif ($old && $r->boolean('remove_image')) {
                Upload::remove($old->image);
                $data['image'] = '';
            }
            $gallery = $old ? $old->gallery_list : [];
            foreach ((array) $r->input('remove_gallery', []) as $rg) {
                $gallery = array_values(array_filter($gallery, function ($g) use ($rg) {
                    if ($g === $rg) { Upload::remove($g); return false; }

                    return true;
                }));
            }
            $gallery = array_merge($gallery, Upload::images($r->file('gallery')));
            $data['gallery'] = $gallery ? json_encode($gallery) : '';
            if ($type === 'file' && ($f = Upload::privateFile($r->file('file')))) {
                if ($old && $old->file_path) @unlink(storage_path('app/private-files/'.basename($old->file_path)));
                $data['file_path'] = $f;
            }
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            keep_old($r->all());

            return redirect($back);
        }
        $slugBase = trim((string) $r->input('slug')) ?: $name;
        if ($old) {
            $data['slug'] = unique_slug(Product::class, $slugBase, $id);
            $old->update($data);
        } else {
            $data['slug'] = unique_slug(Product::class, $slugBase);
            $id = Product::create($data)->id;
        }
        $n = $this->storeKeys($id, (string) $r->input('new_keys'));
        flash('success', 'Product saved'.($n ? " · $n keys added" : '').'.');

        return redirect('/admin/products/'.$id);
    }

    /**
     * Options + variants from the v2.6 editor (options_json / variants_json). Falls back to the legacy
     * "Name: a, b" textarea so old forms and API-style posts keep working.
     * @return array{0:array,1:array}
     */
    private function parseVariants(Request $r): array
    {
        $opts = [];
        $raw = json_decode((string) $r->input('options_json', ''), true);
        if (is_array($raw)) {
            foreach (array_slice($raw, 0, 3) as $o) {
                $name = mb_substr(trim((string) ($o['name'] ?? '')), 0, 40);
                $vals = array_values(array_unique(array_filter(array_map(fn ($x) => mb_substr(trim((string) $x), 0, 60), (array) ($o['values'] ?? [])), fn ($x) => $x !== '')));
                if ($name === '' || ! $vals) continue;
                $type = isset(Product::OPTION_TYPES[$o['type'] ?? '']) ? $o['type'] : 'button';
                $colors = [];
                if ($type === 'color') foreach ((array) ($o['colors'] ?? []) as $k => $hex) if (in_array($k, $vals, true) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $hex)) $colors[$k] = strtolower($hex);
                $opts[] = ['name' => $name, 'type' => $type, 'values' => $vals] + ($colors ? ['colors' => $colors] : []);
            }
        } else {
            foreach (preg_split('/\R/', (string) $r->input('options_text', '')) as $line) {
                if (! str_contains($line, ':')) continue;
                [$n, $v] = explode(':', $line, 2);
                $vals = array_values(array_unique(array_filter(array_map('trim', explode(',', $v)))));
                if (trim($n) !== '' && $vals) $opts[] = ['name' => trim($n), 'values' => $vals];
            }
        }
        $variants = [];
        $vraw = json_decode((string) $r->input('variants_json', ''), true);
        if ($opts && is_array($vraw)) {
            $names = array_column($opts, 'name');
            $valid = array_column($opts, 'values', 'name');
            foreach (array_slice($vraw, 0, 300) as $v) {
                $o = [];
                foreach ($names as $n) {
                    $val = $v['opts'][$n] ?? null;
                    if (! is_string($val) || ! in_array($val, $valid[$n], true)) continue 2;
                    $o[$n] = $val;
                }
                $num = fn ($x) => ($x === null || $x === '') ? null : max(0, to_cents($x));
                $variants[] = ['key' => implode('|', $o), 'opts' => $o, 'price' => $num($v['price'] ?? null), 'compare' => $num($v['compare'] ?? null),
                    'stock' => ($v['stock'] ?? '') === '' || ($v['stock'] ?? null) === null ? null : max(0, (int) $v['stock']), 'sku' => mb_substr(trim((string) ($v['sku'] ?? '')), 0, 80),
                    'image' => preg_match('#^[A-Za-z0-9/_\-.]+$#', (string) ($v['image'] ?? '')) ? (string) $v['image'] : '', 'active' => ! isset($v['active']) || (bool) $v['active']];
            }
        }

        return [$opts, $variants];
    }

    private function storeKeys(int $pid, string $text): int
    {
        $n = 0;
        foreach (array_unique(array_filter(array_map('trim', preg_split('/\R/', $text)))) as $line) {
            ProductKey::create(['product_id' => $pid, 'payload' => $line, 'status' => 'available']);
            $n++;
        }

        return $n;
    }

    public function addKeys(Request $r, int $id)
    {
        $n = $this->storeKeys($id, (string) $r->input('keys'));
        flash($n ? 'success' : 'error', $n ? "$n keys added to the pool." : 'Paste at least one key (one per line).');

        return redirect("/admin/products/$id");
    }

    public function deleteKeys(Request $r, int $id)
    {
        $q = ProductKey::where('product_id', $id)->where('status', 'available');
        if ($r->input('all')) $n = $q->delete();
        elseif ($ids = array_map('intval', (array) $r->input('key_ids', []))) $n = $q->whereIn('id', $ids)->delete();
        else $n = 0;
        flash('success', "$n keys removed.");

        return redirect("/admin/products/$id");
    }

    public function delete(int $id)
    {
        $p = Product::findOrFail($id);
        if (\App\Models\OrderItem::where('product_id', $id)->exists()) {
            $p->update(['status' => 'draft']);
            flash('info', 'This product has orders, so it was hidden (draft) instead of deleted.');
        } else {
            Upload::remove($p->image);
            ProductKey::where('product_id', $id)->delete();
            $p->delete();
            flash('success', 'Product deleted.');
        }

        return redirect('/admin/products');
    }

    /** Inline "quick edit" from the product list (Shopify-style). */
    public function quick(Request $r, int $id)
    {
        $p = Product::findOrFail($id);
        $f = (string) $r->input('field');
        $v = $r->input('value');
        $upd = match ($f) {
            'price' => ['price' => max(0, to_cents($v))],
            'compare_price' => ['compare_price' => max(0, to_cents($v))],
            'stock' => ['stock' => max(0, (int) $v)],
            'status' => ['status' => $v === 'draft' ? 'draft' : 'active'],
            'featured' => ['featured' => filter_var($v, FILTER_VALIDATE_BOOLEAN)],
            'name' => trim((string) $v) === '' ? [] : ['name' => mb_substr(trim((string) $v), 0, 190)],
            'sku' => ['sku' => mb_substr(trim((string) $v), 0, 80)],
            default => [],
        };
        if (! $upd) return response()->json(['ok' => false, 'error' => 'Nothing to update.'], 422);
        $p->update($upd);
        $p->refresh();

        return response()->json(['ok' => true, 'price' => from_cents((int) $p->price), 'compare' => $p->compare_price ? from_cents((int) $p->compare_price) : '', 'stock' => (int) $p->stock, 'status' => $p->status, 'featured' => (bool) $p->featured, 'name' => $p->name, 'sku' => (string) $p->sku]);
    }

    /** Bulk actions on selected products. */
    public function bulk(Request $r)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $r->input('ids')))));
        $act = (string) $r->input('action');
        $val = trim((string) $r->input('value'));
        if (! $ids || $act === '') {
            flash('error', 'Select at least one product and an action.');

            return redirect()->back();
        }
        $q = Product::whereIn('id', $ids);
        $n = count($ids);
        switch ($act) {
            case 'activate': $q->update(['status' => 'active']); $msg = "$n product(s) published."; break;
            case 'draft': $q->update(['status' => 'draft']); $msg = "$n product(s) moved to draft."; break;
            case 'feature': $q->update(['featured' => true]); $msg = "$n product(s) featured."; break;
            case 'unfeature': $q->update(['featured' => false]); $msg = "$n product(s) un-featured."; break;
            case 'category':
                if (! Category::whereKey((int) $val)->exists()) { flash('error', 'Pick a department.'); return redirect()->back(); }
                $q->update(['category_id' => (int) $val]); $msg = "$n product(s) moved."; break;
            case 'price_pct':
                $pct = (float) $val;
                if ($pct === 0.0 || $pct < -90 || $pct > 500) { flash('error', 'Enter a percentage between -90 and 500 (e.g. -10 for 10% off).'); return redirect()->back(); }
                foreach ($q->get() as $p) $p->update(['price' => max(0, (int) round($p->price * (1 + $pct / 100)))]);
                $msg = "Prices changed by $pct% on $n product(s)."; break;
            case 'sale_pct':
                $pct = (float) $val;
                if ($pct <= 0 || $pct >= 95) { flash('error', 'Enter a sale percentage between 1 and 94.'); return redirect()->back(); }
                foreach ($q->get() as $p) {
                    $base = $p->compare_price > $p->price ? $p->compare_price : $p->price;
                    $p->update(['compare_price' => $base, 'price' => max(0, (int) round($base * (1 - $pct / 100)))]);
                }
                $msg = "$pct% sale applied to $n product(s)."; break;
            case 'sale_end':
                foreach ($q->where('compare_price', '>', 0)->get() as $p) if ($p->compare_price > $p->price) $p->update(['price' => $p->compare_price, 'compare_price' => 0]);
                $msg = 'Sale prices reset.'; break;
            case 'stock_set': $q->where('type', 'physical')->update(['stock' => max(0, (int) $val)]); $msg = 'Stock set on physical products.'; break;
            case 'stock_add':
                foreach ($q->where('type', 'physical')->get() as $p) $p->update(['stock' => max(0, (int) $p->stock + (int) $val)]);
                $msg = 'Stock adjusted on physical products.'; break;
            case 'delete':
                $hidden = $gone = 0;
                foreach ($q->get() as $p) {
                    if (\App\Models\OrderItem::where('product_id', $p->id)->exists()) { $p->update(['status' => 'draft']); $hidden++; continue; }
                    Upload::remove($p->image);
                    ProductKey::where('product_id', $p->id)->delete();
                    $p->delete();
                    $gone++;
                }
                $msg = "$gone deleted".($hidden ? ", $hidden hidden (they have orders)." : '.'); break;
            default: flash('error', 'Unknown action.'); return redirect()->back();
        }
        flash('success', $msg);

        return redirect()->back();
    }
}
