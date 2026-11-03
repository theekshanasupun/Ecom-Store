<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Popup extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['capture_email' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
