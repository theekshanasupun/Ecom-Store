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
}
