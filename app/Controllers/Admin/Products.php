<?php
namespace App\Controllers\Admin;

use App\Core\Art;
use App\Core\Crypto;
use App\Core\Db;
use App\Core\Upload;
use App\Services\Catalog;

final class Products extends Base
{
    public const TYPES = [
        'physical' => 'Physical (ships)', 'key' => 'Digital key / code (from pool)', 'account' => 'Account / credentials (from pool)',
        'file' => 'Downloadable file', 'service' => 'Service / manual delivery',
    ];

    public function index(): void
    {
        $q = str_in('q');
        $cat = int_in('cat');
        $type = str_in('type');
        $where = ['1=1'];
        $params = [];
        if ($q !== '') { $where[] = '(p.name LIKE ? OR p.sku LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
        if ($cat) { $where[] = 'p.category_id = ?'; $params[] = $cat; }
        if (isset(self::TYPES[$type])) { $where[] = 'p.type = ?'; $params[] = $type; }
        $w = implode(' AND ', $where);
        $total = (int) Db::val("SELECT COUNT(*) FROM products p WHERE $w", $params);
        [$off, $page, $pages] = $this->paginate($total, 20);
        $rows = Db::all("SELECT p.*, (SELECT COUNT(*) FROM product_keys k WHERE k.product_id = p.id AND k.status = 'available') pool FROM products p WHERE $w ORDER BY p.id DESC LIMIT 20 OFFSET $off", $params);
        $rows = array_map([Catalog::class, 'hydrate'], $rows);
        $cats = Catalog::categories(false);
        title('Products');
        $this->render('products', compact('rows', 'cats', 'q', 'cat', 'type', 'page', 'pages', 'total'));
    }

    public function form(array $a = []): void
    {
        $p = null;
        $keys = [];
        $keyStats = ['available' => 0, 'sold' => 0];
        if (!empty($a['id'])) {
            $p = Db::one('SELECT * FROM products WHERE id = ?', [(int) $a['id']]);
            if (!$p) abort(404);
            $p = Catalog::hydrate($p);
            foreach (Db::all('SELECT status, COUNT(*) n FROM product_keys WHERE product_id = ? GROUP BY status', [$p['id']]) as $r) $keyStats[$r['status']] = (int) $r['n'];
            foreach (Db::all("SELECT id, payload FROM product_keys WHERE product_id = ? AND status = 'available' ORDER BY id DESC LIMIT 200", [$p['id']]) as $k) {
                $keys[] = ['id' => $k['id'], 'text' => Crypto::decrypt($k['payload'])];
            }
        }
        $cats = Catalog::categories(false);
        $arts = Art::TYPES;
        title($p ? 'Edit ' . $p['name'] : 'New product');
        $this->render('product_form', compact('p', 'cats', 'arts', 'keys', 'keyStats'));
    }

    public function save(): void
    {
        $id = int_in('id');
        $old = $id ? Db::one('SELECT * FROM products WHERE id = ?', [$id]) : null;
        $name = str_in('name');
        if ($name === '') {
            flash('error', 'Product name is required.');
            keep_old($_POST);
            redirect($id ? "admin/products/$id" : 'admin/products/new');
        }
        $type = str_in('type', 'physical');
        if (!isset(self::TYPES[$type])) $type = 'physical';

        // options textarea: "Size: XS, S, M" per line
        $opts = [];
        foreach (preg_split('/\R/', (string) input('options_text', '')) as $line) {
            if (!str_contains($line, ':')) continue;
            [$n, $v] = explode(':', $line, 2);
            $vals = array_values(array_unique(array_filter(array_map('trim', explode(',', $v)))));
            if (trim($n) !== '' && $vals) $opts[] = ['name' => trim($n), 'values' => $vals];
        }

        $data = [
            'category_id' => int_in('category_id'),
            'name' => $name,
            'tagline' => mb_substr(str_in('tagline'), 0, 250),
            'description' => (string) input('description', ''),
            'type' => $type,
            'price' => to_cents(input('price', 0)),
            'compare_price' => to_cents(input('compare_price', 0)),
            'stock' => max(0, int_in('stock')),
            'unlimited' => int_in('unlimited') ? 1 : 0,
            'sku' => str_in('sku'),
            'art' => str_in('art'),
            'art_color' => preg_match('/^#[0-9a-fA-F]{6}$/', str_in('art_color')) ? str_in('art_color') : '',
            'badge' => mb_substr(str_in('badge'), 0, 30),
            'tags' => mb_substr(str_in('tags'), 0, 250),
            'options' => $opts ? json_encode($opts, JSON_UNESCAPED_UNICODE) : '',
            'details' => (string) input('details', ''),
            'delivery_note' => mb_substr(str_in('delivery_note'), 0, 250),
            'featured' => int_in('featured') ? 1 : 0,
            'status' => str_in('status') === 'draft' ? 'draft' : 'active',
            'updated_at' => now(),
        ];
        try {
            if ($img = Upload::image($_FILES['image'] ?? null)) {
                if ($old) Upload::remove($old['image']);
                $data['image'] = $img;
            } elseif ($old && int_in('remove_image')) {
                Upload::remove($old['image']);
                $data['image'] = '';
            }
            $gallery = $old && $old['gallery'] ? (json_decode($old['gallery'], true) ?: []) : [];
            foreach (array_values((array) input('remove_gallery', [])) as $rg) {
                $gallery = array_values(array_filter($gallery, function ($g) use ($rg) {
                    if ($g === $rg) { Upload::remove($g); return false; }
                    return true;
                }));
            }
            $gallery = array_merge($gallery, Upload::images($_FILES['gallery'] ?? null));
            $data['gallery'] = $gallery ? json_encode($gallery) : '';
            if ($type === 'file' && ($f = Upload::privateFile($_FILES['file'] ?? null))) {
                if ($old && $old['file_path'] && is_file(STORAGE . '/files/' . basename($old['file_path']))) @unlink(STORAGE . '/files/' . basename($old['file_path']));
                $data['file_path'] = $f;
            }
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            keep_old($_POST);
            redirect($id ? "admin/products/$id" : 'admin/products/new');
        }

        if ($id && $old) {
            $data['slug'] = unique_slug('products', str_in('slug') ?: $name, $id);
            Db::update('products', $data, 'id = ?', [$id]);
        } else {
            $data['slug'] = unique_slug('products', str_in('slug') ?: $name);
            $data['created_at'] = now();
            $id = Db::insert('products', $data);
        }
        // optional keys pasted on first save
        $n = $this->storeKeys($id, (string) input('new_keys', ''));
        forget_old();
        flash('success', 'Product saved' . ($n ? " · $n keys added" : '') . '.');
        redirect('admin/products/' . $id);
    }

    private function storeKeys(int $pid, string $text): int
    {
        $n = 0;
        $seen = [];
        foreach (preg_split('/\R/', $text) as $line) {
            $line = trim($line);
            if ($line === '' || isset($seen[$line])) continue;
            $seen[$line] = 1;
            Db::insert('product_keys', ['product_id' => $pid, 'payload' => Crypto::encrypt($line), 'status' => 'available', 'created_at' => now()]);
            $n++;
        }
        return $n;
    }

    public function addKeys(array $a): void
    {
        $id = (int) $a['id'];
        $n = $this->storeKeys($id, (string) input('keys', ''));
        flash($n ? 'success' : 'error', $n ? "$n keys added to the pool." : 'Paste at least one key (one per line).');
        redirect("admin/products/$id");
    }

    public function deleteKeys(array $a): void
    {
        $id = (int) $a['id'];
        $ids = array_map('intval', (array) input('key_ids', []));
        if (input('all')) {
            $n = Db::delete('product_keys', "product_id = ? AND status = 'available'", [$id]);
        } elseif ($ids) {
            $n = Db::delete('product_keys', "product_id = ? AND status = 'available' AND id IN (" . Db::placeholders($ids) . ')', array_merge([$id], $ids));
        } else {
            $n = 0;
        }
        flash('success', "$n keys removed.");
        redirect("admin/products/$id");
    }

    public function delete(array $a): void
    {
        $id = (int) $a['id'];
        $p = Db::one('SELECT * FROM products WHERE id = ?', [$id]);
        if (!$p) abort(404);
        $used = (int) Db::val('SELECT COUNT(*) FROM order_items WHERE product_id = ?', [$id]);
        if ($used) {
            Db::update('products', ['status' => 'draft'], 'id = ?', [$id]);
            flash('info', 'This product has orders, so it was hidden (draft) instead of deleted.');
        } else {
            Upload::remove($p['image']);
            Db::delete('product_keys', 'product_id = ?', [$id]);
            Db::delete('products', 'id = ?', [$id]);
            flash('success', 'Product deleted.');
        }
        redirect('admin/products');
    }
}
