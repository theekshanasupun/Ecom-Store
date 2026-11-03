<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Services\Catalog;
use App\Support\Blocks;
use App\Support\Meta;
use App\Support\Seo;
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
        Meta::$data['home'] = true;
        if (setting('home_use_builder') === '1' && ($blocks = Blocks::decode((string) setting('home_builder')))) {
            return page('builder_page', ['html' => Blocks::renderAll($blocks)]);
        }

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
        title($cat->seo_title ?: $cat->name);
        meta_desc((string) ($cat->seo_description ?: ($cat->tagline ?: str_limit((string) $cat->description, 155))));
        Meta::$data['noindex'] = (bool) $cat->seo_noindex;
        Meta::$data['crumbs'] = [['Home', url('/')], [$cat->name, url('shop/'.$cat->slug)]];

        return page('category', compact('cat', 'products', 'feature', 'tags', 'others', 'sort'));
    }

    public function product(string $slug)
    {
        $p = Catalog::find($slug) ?? abort(404);
        $related = Catalog::related($p)->all();
        title($p->seo_title ?: $p->name);
        meta_desc((string) ($p->seo_description ?: str_limit((string) ($p->tagline ?: $p->description), 155)));
        Meta::$data['og_image'] = $p->image ? upload_url($p->image) : '';
        Meta::$data['noindex'] = (bool) $p->seo_noindex;
        Meta::$data['product'] = $p;
        Meta::$data['crumbs'] = array_values(array_filter([['Home', url('/')], $p->cat ? [$p->cat->name, url('shop/'.$p->cat->slug)] : null, [$p->name, url('product/'.$p->slug)]]));

        return page('product', compact('p', 'related'));
    }

    public function search(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $results = mb_strlen($q) >= 2 ? Catalog::search($q, 60)->all() : [];
        title($q !== '' ? 'Search: '.$q : 'Search');
        Meta::$data['noindex'] = true;

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
        title($pg->seo_title ?: $pg->title);
        if ($pg->seo_description) meta_desc($pg->seo_description);
        Meta::$data['noindex'] = (bool) $pg->seo_noindex;
        Meta::$data['crumbs'] = [['Home', url('/')], [$pg->title, url('p/'.$pg->slug)]];
        if ($pg->use_builder && ($blocks = Blocks::decode((string) $pg->builder))) {
            return page('builder_page', ['html' => Blocks::renderAll($blocks)]);
        }

        return page('page', compact('pg'));
    }

    public function sitemap()
    {
        $rows = [[url('/'), null]];
        foreach (Catalog::categories()->where('seo_noindex', false) as $c) $rows[] = [url('shop/'.$c->slug), $c->updated_at];
        foreach (Product::where('status', 'active')->where('seo_noindex', false)->get(['slug', 'updated_at']) as $p) $rows[] = [url('product/'.$p->slug), $p->updated_at];
        foreach (Page::where('status', 'published')->where('seo_noindex', false)->get(['slug', 'updated_at']) as $p) $rows[] = [url('p/'.$p->slug), $p->updated_at];
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($rows as [$u, $m]) $xml .= '<url><loc>'.e($u).'</loc>'.($m ? '<lastmod>'.$m->toAtomString().'</lastmod>' : '').'</url>';

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    public function robots()
    {
        return response(Seo::robots(), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public function llms()
    {
        abort_unless(setting('seo_llms_on') === '1' && setting('seo_noindex_site') !== '1', 404);

        return response(Seo::llms(), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
