<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Offer extends Model
{
    protected $guarded = [];

    private static function ids(?string $s): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $s))));
    }

    public function triggerProducts(): array
    {
        return self::ids($this->trigger_product_ids);
    }

    public function triggerCategories(): array
    {
        return self::ids($this->trigger_category_ids);
    }

    public function offerProducts(): array
    {
        return self::ids($this->offer_product_ids);
    }

    public function placements(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->placement))));
    }
}
