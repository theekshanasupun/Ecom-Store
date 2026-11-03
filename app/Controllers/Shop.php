<?php
namespace App\Controllers;

use App\Core\Db;
use App\Core\View;
use App\Services\Catalog;

final class Shop
{
    public function home(): void
    {
        $cats = Catalog::categories();
        $trending = Catalog::list([], 'p.featured DESC, p.sales DESC, p.id DESC', 8);
        $panels = [];
        foreach ($cats as $c) {
            $feat = Catalog::list(['p.category_id = ?' => (int) $c['id']], 'p.featured DESC, p.sales DESC, p.id DESC', 1);
            $panels[] = ['cat' => $c, 'product' => $feat[0] ?? null];
        }
        // "Mix it all up" – one product per department
        $mix = [];
        foreach ($panels as $p) {
            if ($p['product'] && count($mix) < 3) $mix[] = $p['product'];
        }
        title((string) setting('store_name') . ' — ' . setting('store_tagline'));
        meta_desc((string) setting('meta_description'));
        View::page('home', compact('cats', 'trending', 'panels', 'mix'));
    }

    public function category(array $a): void
    {
        $cat = Db::one("SELECT * FROM categories WHERE slug = ? AND status = 'active'", [$a['slug']]);
        if (!$cat) abort(404);
        $sort = str_in('sort', 'new');
        $order = match ($sort) {
            'price-asc' => 'p.price ASC', 'price-desc' => 'p.price DESC', 'popular' => 'p.sales DESC, p.id DESC',
            default => 'p.featured DESC, p.id DESC',
        };
        $products = Catalog::list(['p.category_id = ?' => (int) $cat['id']], $order, 120);
        $feature = $products[0] ?? null;
        foreach ($products as $p) {
            if ($p['featured']) { $feature = $p; break; }
        }
        $tags = [];
        foreach ($products as $p) foreach ($p['tag_list'] as $t) $tags[$t] = ($tags[$t] ?? 0) + 1;
        $others = array_values(array_filter(Catalog::categories(), fn($c) => (int) $c['id'] !== (int) $cat['id']));
        title($cat['name']);
        meta_desc((string) ($cat['tagline'] ?: $cat['description']));
        View::page('category', compact('cat', 'products', 'feature', 'tags', 'others', 'sort'));
    }

    public function product(array $a): void
    {
        $p = Catalog::find($a['slug']);
        if (!$p) abort(404);
        $related = Catalog::related($p);
        title($p['name']);
        meta_desc(str_limit((string) ($p['tagline'] ?: $p['description']), 155));
        View::$meta['og_image'] = $p['image'] ? abs_url('uploads/' . $p['image']) : '';
        View::page('product', compact('p', 'related'));
    }

    public function search(): void
    {
        $q = str_in('q');
        $results = mb_strlen($q) >= 2 ? Catalog::search($q, 60) : [];
        title($q !== '' ? 'Search: ' . $q : 'Search');
        View::page('search', compact('q', 'results'));
    }

    public function searchJson(): void
    {
        $q = str_in('q');
        $out = [];
        if (mb_strlen($q) >= 2) {
            foreach (Catalog::search($q, 6) as $p) {
                $out[] = [
                    'name' => $p['name'], 'url' => url('product/' . $p['slug']), 'price' => money($p['price'], true),
                    'cat' => $p['cat']['name'] ?? '', 'color' => $p['color'], 'media' => Catalog::media($p, false),
                ];
            }
        }
        json_out(['ok' => true, 'results' => $out]);
    }

    public function page(array $a): void
    {
        $pg = Db::one("SELECT * FROM pages WHERE slug = ? AND status = 'published'", [$a['slug']]);
        if (!$pg) abort(404);
        title($pg['title']);
        View::page('page', compact('pg'));
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        $u = [abs_url('')];
        foreach (Catalog::categories() as $c) $u[] = abs_url('shop/' . $c['slug']);
        foreach (Db::all("SELECT slug FROM products WHERE status = 'active'") as $p) $u[] = abs_url('product/' . $p['slug']);
        foreach (Db::all("SELECT slug FROM pages WHERE status = 'published'") as $p) $u[] = abs_url('p/' . $p['slug']);
        echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($u as $x) echo '<url><loc>' . e($x) . '</loc></url>';
        echo '</urlset>';
    }

    public function robots(): void
    {
        header('Content-Type: text/plain');
        echo "User-agent: *\nDisallow: /admin\nDisallow: /cart\nDisallow: /checkout\nDisallow: /account\nSitemap: " . abs_url('sitemap.xml') . "\n";
    }
}
