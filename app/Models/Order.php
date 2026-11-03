<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'has_physical' => 'boolean'];
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events()
    {
        return $this->hasMany(OrderEvent::class)->orderByDesc('id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function publicUrl(): string
    {
        return url('order/'.$this->number).'?t='.$this->token;
    }
}
