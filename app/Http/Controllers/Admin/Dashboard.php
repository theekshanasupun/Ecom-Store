<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class Dashboard extends Controller
{
    public function index()
    {
        $paid = Order::where('payment_status', 'paid');
        $sum = fn ($from) => (int) (clone $paid)->where('paid_at', '>=', $from)->sum('total');
        $stats = [
            'today' => $sum(today()), 'week' => $sum(today()->subDays(6)), 'month' => $sum(today()->subDays(29)),
            'all' => (int) (clone $paid)->sum('total'), 'orders' => Order::count(),
            'to_process' => Order::whereIn('status', ['pending', 'processing'])->count(),
            'customers' => User::where('role', 'customer')->count(), 'products' => Product::where('status', 'active')->count(),
        ];
        $by = (clone $paid)->where('paid_at', '>=', today()->subDays(13))->get(['total', 'paid_at'])->groupBy(fn ($o) => $o->paid_at->format('Y-m-d'));
        $series = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = today()->subDays($i);
            $rows = $by[$d->format('Y-m-d')] ?? collect();
            $series[] = ['d' => $d->format('Y-m-d'), 'label' => $d->format('M j'), 'total' => (int) $rows->sum('total'), 'n' => $rows->count()];
        }
        $recent = Order::orderByDesc('id')->limit(8)->get()->all();
        $top = Product::where('sales', '>', 0)->orderByDesc('sales')->limit(5)->get()->all();
        $thr = (int) setting('low_stock', 3);
        $low = Product::where('status', 'active')->where('unlimited', false)->whereIn('type', ['physical', 'key', 'account'])
            ->withCount(['keys as pool' => fn ($q) => $q->where('status', 'available')])->get()
            ->map(function ($p) {
                $p->left = in_array($p->type, ['key', 'account'], true) ? (int) $p->pool : (int) $p->stock;

                return $p;
            })->filter(fn ($p) => $p->left <= $thr)->take(6)->values()->all();
        title('Dashboard');

        return admin_page('dashboard', compact('stats', 'series', 'recent', 'top', 'low'));
    }
}
