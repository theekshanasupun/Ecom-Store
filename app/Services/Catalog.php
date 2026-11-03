<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Support\Art;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class Catalog
{
    private static ?Collection $cats = null;

    public static function categories(): Collection
    {
        return self::$cats ??= Category::active()->get();
    }

    public static function forget(): void
    {
        self::$cats = null;
    }

    /** Base query for storefront products (with department + key pool size). */
    public static function query(): Builder
    {
        return Product::query()->live()->with('cat')
            ->withCount(['keys as pool' => fn ($q) => $q->where('status', 'available')]);
    }

    public static function find(string $slug): ?Product
    {
        return self::query()->where('slug', $slug)->first();
    }

    public static function byId(int $id): ?Product
    {
        return self::query()->where('products.id', $id)->first();
    }

    public static function search(string $q, int $limit = 24): Collection
    {
        $like = '%'.addcslashes($q, '%_\\').'%';

        return self::query()->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('tagline', 'like', $like)->orWhere('tags', 'like', $like)->orWhere('sku', 'like', $like))
            ->orderByDesc('sales')->orderByDesc('products.id')->limit($limit)->get();
    }

    public static function related(Product $p, int $n = 4): Collection
    {
        return self::query()->where('category_id', $p->category_id)->where('products.id', '<>', $p->id)
            ->orderByDesc('sales')->limit($n)->get();
    }

    /** Photo or vector art markup. */
    public static function media(array|Product $p, bool $lazy = true): string
    {
        $g = fn ($k) => $p instanceof Product ? $p->$k : ($p[$k] ?? null);
        if ($g('image')) {
            return '<img src="'.e(upload_url($g('image'))).'" alt="'.e($g('name')).'"'.($lazy ? ' loading="lazy"' : '').'>';
        }
        $art = $g('art') ?: match ($g('type')) {
            'key' => 'key', 'account' => 'account', default => 'box',
        };

        return Art::svg($art, $g('art_color') ?: ($g('color') ?: '#c6ff3d'), (string) $g('name'));
    }

    public static function priceHtml(Product $p, bool $short = true): string
    {
        $h = '<b>'.money($p->price, $short).'</b>';
        if ($p->compare_price > $p->price) {
            $h .= ' <s>'.money($p->compare_price, $short).'</s>';
        }

        return $h;
    }

    /* ------------------------------------------------------------ typeahead (v2.6) */

    private static function rank(string $q, string $text, int $weight): int
    {
        $t = mb_strtolower($text);
        if ($t === '') return 0;
        if ($t === $q) return 100 + $weight;
        if (str_starts_with($t, $q)) return 60 + $weight;
        if (preg_match('/(^|[\s\-_\/·,])'.preg_quote($q, '/').'/u', $t)) return 35 + $weight;
        if (str_contains($t, $q)) return 15 + $weight;

        return 0;
    }

    /** Words/phrases the store actually uses — the base for "did you mean". Cached 10 minutes. */
    public static function vocabulary(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('kiosk.vocab', 600, function () {
            $v = [];
            foreach (self::query()->get(['products.id', 'name', 'tags', 'category_id', 'status']) as $p) {
                foreach (preg_split('/[\s,\/·\-]+/u', mb_strtolower($p->name.' '.$p->tags)) as $w) if (mb_strlen($w) >= 3) $v[$w] = ($v[$w] ?? 0) + 1;
            }
            foreach (self::categories() as $c) foreach (preg_split('/[\s,\/·\-]+/u', mb_strtolower($c->name)) as $w) if (mb_strlen($w) >= 3) $v[$w] = ($v[$w] ?? 0) + 3;
            arsort($v);

            return array_slice($v, 0, 1500, true);
        });
    }

    public static function didYouMean(string $q): ?string
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($q))) ?: [];
        $vocab = self::vocabulary();
        $out = [];
        $changed = false;
        foreach ($words as $w) {
            if (mb_strlen($w) < 3 || isset($vocab[$w])) { $out[] = $w; continue; }
            $best = null;
            $bd = mb_strlen($w) > 6 ? 3 : 2;
            foreach ($vocab as $cand => $_) {
                if (abs(strlen($cand) - strlen($w)) > $bd) continue;
                $d = levenshtein($w, $cand);
                if ($d < $bd || ($d === $bd && $best === null)) { $bd = $d; $best = $cand; }
            }
            if ($best !== null && $best !== $w) { $out[] = $best; $changed = true; } else $out[] = $w;
        }

        return $changed ? implode(' ', $out) : null;
    }

    /** Ranked search: prefix > word start > contains; name > tags > tagline > sku. */
    public static function searchRanked(string $q, int $limit = 24): Collection
    {
        $n = mb_strtolower(trim($q));
        $like = '%'.addcslashes($n, '%_\\').'%';
        $rows = self::query()->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('tagline', 'like', $like)->orWhere('tags', 'like', $like)->orWhere('sku', 'like', $like))
            ->orderByDesc('sales')->limit(80)->get();
        $multi = array_filter(preg_split('/\s+/u', $n));
        if (count($multi) > 1) {
            // every word must appear somewhere
            $rows = $rows->filter(function ($p) use ($multi) {
                $hay = mb_strtolower($p->name.' '.$p->tags.' '.$p->tagline.' '.$p->sku);
                foreach ($multi as $w) if (! str_contains($hay, $w)) return false;

                return true;
            });
        }

        return $rows->sortByDesc(fn ($p) => max(self::rank($n, $p->name, 40), self::rank($n, (string) $p->tags, 20), self::rank($n, (string) $p->tagline, 8), self::rank($n, (string) $p->sku, 25)) * 1000 + min(999, (int) $p->sales))->values()->take($limit);
    }

    /** Everything the search overlay needs for one query. */
    public static function suggest(string $q): array
    {
        $n = mb_strtolower(trim($q));
        $out = ['q' => $q, 'terms' => [], 'products' => [], 'cats' => [], 'total' => 0, 'did_you_mean' => null, 'popular' => []];
        if (mb_strlen($n) < 2) {
            $out['popular'] = self::popular();

            return $out;
        }
        $all = self::searchRanked($n, 60);
        $out['total'] = $all->count();
        foreach ($all->take(6) as $p) {
            $out['products'][] = ['id' => $p->id, 'name' => $p->name, 'url' => url('product/'.$p->slug), 'price' => money($p->price, true), 'compare' => $p->compare_price > $p->price ? money($p->compare_price, true) : '', 'cat' => $p->cat?->name, 'color' => $p->color, 'media' => self::media($p, false), 'instant' => (bool) $p->instant];
        }
        $terms = [];
        foreach ($all as $p) {
            $terms[$p->name] = max($terms[$p->name] ?? 0, self::rank($n, $p->name, 10));
            foreach ($p->tag_list as $t) if (($r = self::rank($n, $t, 5)) > 0) $terms[$t] = max($terms[$t] ?? 0, $r);
        }
        foreach (self::categories() as $c) {
            $r = max(self::rank($n, $c->name, 12), self::rank($n, (string) $c->headline, 6));
            if ($r > 0) {
                $out['cats'][] = ['name' => $c->name, 'url' => url('shop/'.$c->slug), 'color' => $c->color, 'tagline' => (string) $c->tagline];
                $terms[$c->name] = max($terms[$c->name] ?? 0, $r + 5);
            }
        }
        arsort($terms);
        $out['terms'] = array_slice(array_keys($terms), 0, 6);
        if ($out['total'] === 0) {
            $dm = self::didYouMean($n);
            if ($dm) {
                $out['did_you_mean'] = $dm;
                $out['products'] = array_map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'url' => url('product/'.$p->slug), 'price' => money($p->price, true), 'compare' => '', 'cat' => $p->cat?->name, 'color' => $p->color, 'media' => self::media($p, false), 'instant' => (bool) $p->instant], self::searchRanked($dm, 4)->all());
            }
            $out['popular'] = self::popular();
        }

        return $out;
    }

    /** Popular terms for an empty search box. */
    public static function popular(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('kiosk.popular', 600, function () {
            $terms = [];
            foreach (self::query()->orderByDesc('sales')->orderByDesc('featured')->limit(12)->get() as $p) {
                foreach ($p->tag_list as $t) $terms[$t] = ($terms[$t] ?? 0) + 2 + (int) $p->sales;
                if (count($terms) < 4) $terms[$p->name] = ($terms[$p->name] ?? 0) + 1;
            }
            foreach (self::categories() as $c) $terms[$c->name] = ($terms[$c->name] ?? 0) + 1;
            arsort($terms);

            return array_slice(array_keys($terms), 0, 8);
        });
    }
}
