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

    protected $guarded = [];

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

            return is_array($o) ? $o : [];
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
        return Attribute::get(fn () => in_array($this->type, ['key', 'account', 'file'], true));
    }

    protected function inStock(): Attribute
    {
        return Attribute::get(fn () => $this->available() > 0);
    }

    /** Units that can be sold right now. PHP_INT_MAX = unlimited. */
    public function available(): int
    {
        if ($this->unlimited || in_array($this->type, ['file', 'service'], true)) {
            return PHP_INT_MAX;
        }
        if (in_array($this->type, ['key', 'account'], true)) {
            if (array_key_exists('pool', $this->attributes)) {
                return (int) $this->attributes['pool'];
            }

            return $this->keys()->where('status', 'available')->count();
        }

        return max(0, (int) $this->stock);
    }

    public function discountPct(): int
    {
        return $this->compare_price > $this->price ? (int) round(100 - $this->price / $this->compare_price * 100) : 0;
    }
}
