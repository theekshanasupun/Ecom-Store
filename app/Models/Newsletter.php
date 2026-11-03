<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Newsletter extends Model
{
    public const SEGMENTS = [
        'all' => 'Everyone subscribed',
        'customers' => 'Subscribers who have bought (paid order)',
        'never_ordered' => 'Subscribers who have never ordered',
        'subscribers_only' => 'Subscribers without a customer account',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function blocks(): array
    {
        $d = json_decode((string) $this->design, true);

        return is_array($d) ? ($d['blocks'] ?? (array_is_list($d) ? $d : [])) : [];
    }

    /** Subscribed addresses that match the chosen segment. */
    public function audience()
    {
        $q = Subscriber::where('status', 'subscribed');
        $paid = fn () => \App\Models\Order::query()->where('payment_status', 'paid')->select('email');
        $any = fn () => \App\Models\Order::query()->select('email');

        return match ($this->segment) {
            'customers' => $q->whereIn('email', $paid()),
            'never_ordered' => $q->whereNotIn('email', $any()),
            'subscribers_only' => $q->whereNotIn('email', User::query()->select('email')),
            default => $q,
        };
    }
}
