<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use App\Services\Orders;
use Illuminate\Http\Request;

/** Global admin search (top bar, Ctrl+K). */
class Search extends Controller
{
    public function json(Request $r)
    {
        $q = trim((string) $r->query('q'));
        if (mb_strlen($q) < 2) return response()->json(['groups' => []]);
        $like = '%'.addcslashes($q, '%_\\').'%';
        $groups = [];

        $items = [];
        foreach (Product::where(fn ($w) => $w->where('name', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('tags', 'like', $like))->orderByDesc('sales')->limit(5)->get() as $p) {
            $items[] = ['label' => $p->name, 'sub' => trim(($p->sku ? $p->sku.' · ' : '').money($p->getRawOriginal('price')).($p->status !== 'active' ? ' · draft' : '')), 'url' => url('admin/products/'.$p->id), 'img' => $p->image ? upload_url($p->image) : '', 'icon' => '▣', 'go' => 'Edit'];
        }
        $groups[] = ['title' => 'Products', 'items' => $items];

        $items = [];
        foreach (Order::where(fn ($w) => $w->where('number', 'like', $like)->orWhere('email', 'like', $like)->orWhere('name', 'like', $like)->orWhere('tracking', 'like', $like))->orderByDesc('id')->limit(5)->get() as $o) {
            $items[] = ['label' => $o->number, 'sub' => $o->name.' · '.money($o->total).' · '.(Orders::STATUSES[$o->status] ?? $o->status), 'url' => url('admin/orders/'.$o->id), 'icon' => '🧾', 'go' => $o->payment_status];
        }
        $groups[] = ['title' => 'Orders', 'items' => $items];

        $items = [];
        foreach (User::where('role', 'customer')->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))->orderBy('name')->limit(4)->get() as $u) {
            $items[] = ['label' => $u->name, 'sub' => $u->email, 'url' => url('admin/orders?q='.urlencode($u->email)), 'icon' => '👤', 'go' => 'Orders'];
            $items[] = ['label' => 'Create order for '.$u->name, 'sub' => $u->email, 'url' => url('admin/orders/new?customer='.$u->id), 'icon' => '＋', 'go' => 'New'];
        }
        $groups[] = ['title' => 'Customers', 'items' => $items];

        $items = [];
        foreach (Coupon::where('code', 'like', $like)->orWhere('note', 'like', $like)->limit(4)->get() as $c) {
            $items[] = ['label' => $c->code, 'sub' => ($c->type === 'percent' ? (int) $c->value.'%' : money($c->value)).' · '.$c->status.($c->note ? ' · '.$c->note : ''), 'url' => url('admin/coupons?edit='.$c->id), 'icon' => '🎟', 'go' => 'Edit'];
        }
        $groups[] = ['title' => 'Coupons', 'items' => $items];

        $items = [];
        foreach (Page::where('title', 'like', $like)->limit(3)->get() as $p) {
            $items[] = ['label' => $p->title, 'sub' => '/p/'.$p->slug, 'url' => url('admin/pages/'.$p->id), 'icon' => '📄', 'go' => 'Edit'];
        }
        $groups[] = ['title' => 'Pages', 'items' => $items];

        return response()->json(['groups' => array_values(array_filter($groups, fn ($g) => $g['items']))]);
    }
}
