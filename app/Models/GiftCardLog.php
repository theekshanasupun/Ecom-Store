<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCardLog extends Model
{
    protected $table = 'gift_card_log';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
