<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'exclude_sale' => 'boolean', 'first_order_only' => 'boolean', 'free_shipping' => 'boolean', 'auto_apply' => 'boolean'];
    }

    public function productIds(): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $this->product_ids))));
    }

    public function categoryIds(): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $this->category_ids))));
    }
}
