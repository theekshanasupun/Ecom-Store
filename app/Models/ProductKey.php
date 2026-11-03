<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One unit of digital stock (licence key, account login…). Payload is encrypted at rest. */
class ProductKey extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'encrypted', 'sold_at' => 'datetime'];
    }
}
