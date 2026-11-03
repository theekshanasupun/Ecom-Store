<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public const TYPES = [
        'physical' => 'Physical (ships)', 'key' => 'Digital key / code (from pool)', 'account' => 'Account / credentials (from pool)',
        'file' => 'Downloadable file', 'service' => 'Service / manual delivery',
    ];

    /** Option display styles for the variant editor / storefront. */
    public const OPTION_TYPES = ['button' => 'Buttons', 'color' => 'Colour swatches', 'select' => 'Dropdown'];

    protected $guarded = [];

    /** Storefront price-rule info set by Pricing::apply (never persisted). */
    public ?array $disc = null;

    protected static function booted(): void
    {
        static::retrieved(function (Product $p) {
            try {
                \App\Services\Pricing::apply($p);
            } catch (\Throwable) {
            }
        });
    }

    protected function casts(): array
    {
        return ['unlimited' => 'boolean', 'featured' => 'boolean'];
    }

    public function cat()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function keys()
    {
        return $this->hasMany(ProductKey::class);
    }

    public function scopeLive($q)
    {
        return $q->where('products.status', 'active')
            ->whereHas('cat', fn ($c) => $c->where('status', 'active'));
    }

    protected function color(): Attribute
    {
        return Attribute::get(fn () => $this->cat?->color ?: '#c6ff3d');
    }

    protected function optionsList(): Attribute
    {
        return Attribute::get(function () {
            $o = $this->options ? json_decode($this->options, true) : [];
            if (! is_array($o)) return [];
            foreach ($o as &$x) {
                $x['values'] = array_values((array) ($x['values'] ?? []));
                $x['type'] = $x['type'] ?? (stripos((string) ($x['name'] ?? ''), 'col') === 0 ? 'color' : 'button');
                $x['colors'] = (array) ($x['colors'] ?? []);
            }

            return $o;
        });
    }

    protected function galleryList(): Attribute
    {
        return Attribute::get(fn () => $this->gallery ? (json_decode($this->gallery, true) ?: []) : []);
    }

    protected function tagList(): Attribute
    {
        return Attribute::get(fn () => array_values(array_filter(array_map('trim', explode(',', (string) $this->tags)))));
    }

    protected function instant(): Attribute
    {
        return Attribute::get(fn () => in_array($this->type, ['key', 'account', 'file', 'giftcard'], true));
    }

    protected function inStock(): Attribute
    {
        return Attribute::get(fn () => $this->available() > 0);
    }

    /** Variant rows: [{key, opts{Name:value}, price?, compare?, stock?, sku?, image?, active}] */
    public function variantList(): array
    {
        $v = $this->variants ? json_decode((string) $this->variants, true) : [];
        if (! is_array($v)) return [];
        $out = [];
        foreach ($v as $row) {
            if (! is_array($row) || empty($row['opts'])) continue;
            $out[] = [
                'key' => (string) ($row['key'] ?? implode('|', (array) $row['opts'])), 'opts' => (array) $row['opts'],
                'price' => isset($row['price']) && $row['price'] !== '' && $row['price'] !== null ? (int) $row['price'] : null,
                'compare' => isset($row['compare']) && $row['compare'] !== '' && $row['compare'] !== null ? (int) $row['compare'] : null,
                'stock' => isset($row['stock']) && $row['stock'] !== '' && $row['stock'] !== null ? max(0, (int) $row['stock']) : null,
                'sku' => (string) ($row['sku'] ?? ''), 'image' => (string) ($row['image'] ?? ''), 'active' => ! isset($row['active']) || (bool) $row['active'],
            ];
        }

        return $out;
    }

    public function hasVariants(): bool
    {
        return $this->variantList() !== [];
    }

    /** The variant matching the chosen options (null when the product has none / nothing matches). */
    public function variantFor(array $opts): ?array
    {
        $list = $this->variantList();
        if (! $list) return null;
        foreach ($list as $v) {
            $ok = true;
            foreach ($v['opts'] as $n => $val) {
                if (($opts[$n] ?? null) !== $val) { $ok = false; break; }
            }
            if ($ok) return $v;
        }

        return null;
    }

    /**
     * Everything the storefront needs to price a line: price (after discount rules and quantity tiers),
     * compare (the "was" price, 0 when none), sku, image, label.
     */
    public function priceInfo(array $opts = [], int $qty = 1): array
    {
        $v = $this->variantFor($opts);
        $rawPrice = $this->disc['orig'] ?? (int) $this->getRawOriginal('price');
        $rawCompare = $this->disc['orig_compare'] ?? (int) $this->getRawOriginal('compare_price');
        $price = $v && $v['price'] !== null ? $v['price'] : $rawPrice;
        $compare = $v && $v['compare'] !== null ? $v['compare'] : ($v && $v['price'] !== null ? 0 : $rawCompare);
        [$final, $label] = \App\Services\Pricing::discounted($this, $price, $compare, $qty);
        if ($final < $price) $compare = max($compare, $price);
        if ($compare <= $final) $compare = 0;

        return ['price' => $final, 'compare' => $compare, 'save' => $compare ? $compare - $final : 0, 'pct' => $compare ? (int) round(100 - $final / $compare * 100) : 0,
            'label' => $label, 'sku' => ($v['sku'] ?? '') ?: (string) $this->sku, 'image' => ($v['image'] ?? '') ?: (string) $this->image, 'variant' => $v];
    }

    public function unitPrice(array $opts = [], int $qty = 1): int
    {
        return $this->priceInfo($opts, $qty)['price'];
    }

    /** Units that can be sold right now. PHP_INT_MAX = unlimited. Pass the chosen options for per-variant stock. */
    public function available(array $opts = []): int
    {
        if ($this->unlimited || in_array($this->type, ['file', 'service', 'giftcard'], true)) {
            return PHP_INT_MAX;
        }
        if (in_array($this->type, ['key', 'account'], true)) {
            if (array_key_exists('pool', $this->attributes)) {
                return (int) $this->attributes['pool'];
            }

            return $this->keys()->where('status', 'available')->count();
        }
        $list = $this->variantList();
        if ($list) {
            if ($opts) {
                $v = $this->variantFor($opts);
                if ($v && ! $v['active']) return 0;
                if ($v && $v['stock'] !== null) return $v['stock'];
            } else {
                $sum = 0;
                $any = false;
                foreach ($list as $v) {
                    if (! $v['active']) continue;
                    $any = true;
                    if ($v['stock'] === null) return max(0, (int) $this->stock);
                    $sum += $v['stock'];
                }
                if ($any) return $sum;
            }
        }

        return max(0, (int) $this->stock);
    }

    /** Remove units of a variant's own stock (physical products). Returns true when a variant stock was touched. */
    public function adjustVariantStock(array $opts, int $delta): bool
    {
        $list = $this->variantList();
        $v = $this->variantFor($opts);
        if (! $list || ! $v || $v['stock'] === null) return false;
        foreach ($list as &$row) {
            if ($row['key'] === $v['key']) $row['stock'] = max(0, (int) $row['stock'] + $delta);
        }
        unset($row);
        \Illuminate\Support\Facades\DB::table('products')->where('id', $this->id)->update(['variants' => json_encode($list, JSON_UNESCAPED_UNICODE)]);
        $this->variants = json_encode($list, JSON_UNESCAPED_UNICODE);

        return true;
    }

    public function discountPct(): int
    {
        return $this->compare_price > $this->price ? (int) round(100 - $this->price / $this->compare_price * 100) : 0;
    }
}
