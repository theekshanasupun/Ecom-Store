<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class Customers extends Controller
{
    public function index(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $p = User::where('role', 'customer')
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('email', 'like', "%$q%")))
            ->withCount('orders as orders_n')
            ->withSum(['orders as spent' => fn ($o) => $o->where('payment_status', 'paid')], 'total')
            ->orderByDesc('id')->paginate(25)->withQueryString();
        $rows = $p->items();
        $page = $p->currentPage();
        $pages = $p->lastPage();
        $total = $p->total();
        title('Customers');

        return admin_page('customers', compact('rows', 'q', 'page', 'pages', 'total'));
    }
}
