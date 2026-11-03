<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SavedCart extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['last_activity_at' => 'datetime', 'last_reminder_at' => 'datetime', 'recovered_at' => 'datetime'];
    }

    public function lines(): array
    {
        $d = json_decode((string) $this->data, true);

        return is_array($d) ? $d : ['lines' => [], 'coupon' => null];
    }
}
