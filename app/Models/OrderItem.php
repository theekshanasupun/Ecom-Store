<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function keys()
    {
        return $this->hasMany(ProductKey::class, 'order_item_id');
    }

    /** Decrypted delivered keys / accounts. */
    protected function delivered(): Attribute
    {
        return Attribute::get(fn () => in_array($this->type, ['key', 'account'], true)
            ? $this->keys()->orderBy('id')->get()->pluck('payload')->all() : []);
    }
}
