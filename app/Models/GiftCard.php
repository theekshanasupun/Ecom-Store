<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCard extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'deliver_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function log()
    {
        return $this->hasMany(GiftCardLog::class)->orderByDesc('id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function usable(): bool
    {
        return $this->status === 'active' && $this->balance > 0 && ! $this->isExpired();
    }

    /** 1234-5678-… masked for logs and customer-facing lists. */
    public function masked(): string
    {
        return substr($this->code, 0, 4).'-••••-••••-'.substr($this->code, -4);
    }
}
