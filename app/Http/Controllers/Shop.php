<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Services\Catalog;
use Illuminate\Http\Request;

class Shop extends Controller
{
    public function home()
    {
        $cats = Catalog::categories();
        $trending = Catalog::query()->orderByDesc('featured')->orderByDesc('sales')->orderByDesc('products.id')->limit(8)->get()->all();
        $panels = $cats->map(fn ($c) => ['cat' => $c, 'product' => Catalog::query()->where('category_id', $c->id)->orderByDesc('featured')->orderByDesc('sales')->first()])->all();
        $mix = collect($panels)->pluck('product')->filter()->take(3)->values()->all();
        title(setting('store_name').' — '.setting('store_tagline'));
        meta_desc((string) setting('meta_description'));

        return page('home', compact('cats', 'trending', 'panels', 'mix'));
    }

    public function category(Request $r, string $slug)
    {
        $cat = Category::where('slug', $slug)->where('status', 'active')->firstOrFail();
        $sort = $r->query('sort', 'new');
        $q = Catalog::query()->where('category_id', $cat->id);
        match ($sort) {
            'price-asc' => $q->orderBy('price'), 'price-desc' => $q->orderByDesc('price'),
            'popular' => $q->orderByDesc('sales')->orderByDesc('products.id'),
            default => $q->orderByDesc('featured')->orderByDesc('products.id'),
        };
        $all = $q->limit(120)->get();
        $feature = $all->firstWhere('featured', true) ?? $all->first();
        $products = $all->all();
        $tags = [];
        foreach ($all as $p) foreach ($p->tag_list as $t) $tags[$t] = ($tags[$t] ?? 0) + 1;
        $others = Catalog::categories()->where('id', '<>', $cat->id)->values()->all();
        title($cat->name);
        meta_desc((string) ($cat->tagline ?: $cat->description));

        return page('category', compact('cat', 'products', 'feature', 'tags', 'others', 'sort'));
    }

    public function product(string $slug)
    {
        $p = Catalog::find($slug) ?? abort(404);
        $related = Catalog::related($p)->all();
        title($p->name);
        meta_desc(str_limit((string) ($p->tagline ?: $p->description), 155));
        \App\Support\Meta::$data['og_image'] = $p->image ? upload_url($p->image) : '';

        return page('product', compact('p', 'related'));
    }

    public function search(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $results = mb_strlen($q) >= 2 ? Catalog::search($q, 60)->all() : [];
        title($q !== '' ? 'Search: '.$q : 'Search');

        return page('search', compact('q', 'results'));
    }

    public function searchJson(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $out = [];
        if (mb_strlen($q) >= 2) {
            foreach (Catalog::search($q, 6) as $p) {
                $out[] = ['name' => $p->name, 'url' => url('product/'.$p->slug), 'price' => money($p->price, true), 'cat' => $p->cat?->name, 'color' => $p->color, 'media' => Catalog::media($p, false)];
            }
        }

        return response()->json(['ok' => true, 'results' => $out]);
    }

    public function page(string $slug)
    {
        $pg = Page::where('slug', $slug)->where('status', 'published')->firstOrFail();
        title($pg->title);

        return page('page', compact('pg'));
    }

    public function sitemap()
    {
        $u = [url('/')];
        foreach (Catalog::categories() as $c) $u[] = url('shop/'.$c->slug);
        foreach (Product::where('status', 'active')->pluck('slug') as $s) $u[] = url('product/'.$s);
        foreach (Page::where('status', 'published')->pluck('slug') as $s) $u[] = url('p/'.$s);
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($u as $x) $xml .= '<url><loc>'.e($x).'</loc></url>';

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    public function robots()
    {
        return response("User-agent: *\nDisallow: /admin\nDisallow: /cart\nDisallow: /checkout\nDisallow: /account\nSitemap: ".url('sitemap.xml')."\n", 200, ['Content-Type' => 'text/plain']);
    }
}
