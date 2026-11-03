<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationQueue extends Model
{
    protected $table = 'automation_queue';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['run_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function automation()
    {
        return $this->belongsTo(Automation::class);
    }

    public function payloadArray(): array
    {
        $d = json_decode((string) $this->payload, true);

        return is_array($d) ? $d : [];
    }
}
