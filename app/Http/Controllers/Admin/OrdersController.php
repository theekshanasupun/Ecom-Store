<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrdersController extends Controller
{
    public function index(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $st = (string) $r->query('status');
        $pay = (string) $r->query('pay');
        $p = Order::query()
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('number', 'like', "%$q%")->orWhere('email', 'like', "%$q%")->orWhere('name', 'like', "%$q%")))
            ->when(isset(Orders::STATUSES[$st]), fn ($x) => $x->where('status', $st))
            ->when(in_array($pay, ['paid', 'unpaid'], true), fn ($x) => $x->where('payment_status', $pay))
            ->orderByDesc('id')->paginate(25)->withQueryString();
        $rows = $p->items();
        $page = $p->currentPage();
        $pages = $p->lastPage();
        $total = $p->total();
        $counts = Order::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all();
        title('Orders');

        return admin_page('orders', compact('rows', 'q', 'st', 'pay', 'page', 'pages', 'total', 'counts'));
    }

    public function show(int $id)
    {
        $o = Order::findOrFail($id);
        $items = $o->items()->get()->all();
        $events = $o->events()->get()->all();
        $customer = $o->user;
        title('Order '.$o->number);

        return admin_page('order_show', compact('o', 'items', 'events', 'customer'));
    }

    private function actor(): string
    {
        return 'admin:'.Auth::user()->name;
    }

    public function status(Request $r, int $id)
    {
        $o = Order::findOrFail($id);
        $tracking = trim((string) $r->input('tracking'));
        $newStatus = (string) $r->input('status');
        Orders::setStatus($o, $newStatus, $this->actor(), $tracking);
        if ($tracking !== '' && $o->status === $newStatus) $o->update(['tracking' => $tracking]);
        flash('success', 'Order updated.');

        return redirect('/admin/orders/'.$id);
    }

    public function markPaid(Request $r, int $id)
    {
        Orders::markPaid(Order::findOrFail($id), trim((string) $r->input('ref')) ?: 'manual', $this->actor());
        flash('success', 'Marked as paid. Digital items were delivered and the customer was emailed.');

        return redirect('/admin/orders/'.$id);
    }

    public function deliver(int $id)
    {
        $o = Order::findOrFail($id);
        if (! $o->isPaid()) {
            flash('error', 'Order is not paid yet.');

            return redirect('/admin/orders/'.$id);
        }
        $missing = Orders::deliver($o);
        Orders::refreshStatus($o);
        if ($missing === 0) Orders::sendReceipt($o->refresh(), true);
        flash($missing ? 'error' : 'success', $missing ? "$missing units still lack stock. Add keys to the product and try again." : 'Delivered and emailed to the customer.');

        return redirect('/admin/orders/'.$id);
    }

    public function note(Request $r, int $id)
    {
        $o = Order::findOrFail($id);
        if (trim((string) $r->input('note')) !== '') Orders::event($o, 'Note: '.trim((string) $r->input('note')), $this->actor());

        return redirect('/admin/orders/'.$id);
    }

    public function resend(int $id)
    {
        $o = Order::findOrFail($id);
        $o->isPaid() ? Orders::sendReceipt($o, true) : Orders::sendPending($o);
        flash('success', 'Email sent to '.$o->email.'.');

        return redirect('/admin/orders/'.$id);
    }
}
