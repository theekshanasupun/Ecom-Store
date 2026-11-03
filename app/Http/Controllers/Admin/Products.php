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
        $p = $query->paginate(20)->withQueryString();
        $rows = $p->items();
        $page = $p->currentPage();
        $pages = $p->lastPage();
        $total = $p->total();
        $cats = Category::orderBy('sort_order')->get();
        title('Products');

        return admin_page('products', compact('rows', 'cats', 'q', 'cat', 'type', 'page', 'pages', 'total'));
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
        $opts = [];
        foreach (preg_split('/\R/', (string) $r->input('options_text', '')) as $line) {
            if (! str_contains($line, ':')) continue;
            [$n, $v] = explode(':', $line, 2);
            $vals = array_values(array_unique(array_filter(array_map('trim', explode(',', $v)))));
            if (trim($n) !== '' && $vals) $opts[] = ['name' => trim($n), 'values' => $vals];
        }
        $s = fn ($k, $l = 250) => mb_substr(trim((string) $r->input($k)), 0, $l);
        $data = [
            'category_id' => (int) $r->input('category_id'), 'name' => $name, 'tagline' => $s('tagline'),
            'description' => (string) $r->input('description'), 'type' => $type,
            'price' => to_cents($r->input('price', 0)), 'compare_price' => to_cents($r->input('compare_price', 0)),
            'stock' => max(0, (int) $r->input('stock')), 'unlimited' => $r->boolean('unlimited'), 'sku' => $s('sku', 80),
            'art' => $s('art', 20), 'art_color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $r->input('art_color')) ? $r->input('art_color') : '',
            'badge' => $s('badge', 30), 'tags' => $s('tags'), 'options' => $opts ? json_encode($opts, JSON_UNESCAPED_UNICODE) : '',
            'details' => (string) $r->input('details'), 'delivery_note' => $s('delivery_note'),
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
}
