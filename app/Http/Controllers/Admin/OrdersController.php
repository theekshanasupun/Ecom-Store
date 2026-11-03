<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\ManualOrders;
use App\Services\Orders;
use App\Support\Mailer;
use App\Support\Triggers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrdersController extends Controller
{
    public const CARRIERS = ['', 'DHL', 'FedEx', 'UPS', 'USPS', 'Royal Mail', 'Australia Post', 'Canada Post', 'DPD', 'Aramex', 'Sri Lanka Post', 'Local courier', 'Pickup / hand delivery'];

    private function filtered(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $st = (string) $r->query('status');
        $pay = (string) $r->query('pay');

        return Order::query()
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('number', 'like', "%$q%")->orWhere('email', 'like', "%$q%")->orWhere('name', 'like', "%$q%")))
            ->when(isset(Orders::STATUSES[$st]), fn ($x) => $x->where('status', $st))
            ->when(in_array($pay, ['paid', 'unpaid'], true), fn ($x) => $x->where('payment_status', $pay))
            ->when($r->query('source') === 'admin', fn ($x) => $x->where('source', 'admin'))
            ->orderByDesc('id');
    }

    public function index(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $st = (string) $r->query('status');
        $pay = (string) $r->query('pay');
        $p = $this->filtered($r)->paginate(25)->withQueryString();
        $rows = $p->items();
        $page = $p->currentPage();
        $pages = $p->lastPage();
        $total = $p->total();
        $counts = Order::selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status')->all();
        $carriers = self::CARRIERS;
        title('Orders');

        return admin_page('orders', compact('rows', 'q', 'st', 'pay', 'page', 'pages', 'total', 'counts', 'carriers'));
    }

    public function show(int $id)
    {
        $o = Order::findOrFail($id);
        $items = $o->items()->get()->all();
        $events = $o->events()->get()->all();
        $customer = $o->user;
        $carriers = self::CARRIERS;
        title('Order '.$o->number);

        return admin_page('order_show', compact('o', 'items', 'events', 'customer', 'carriers'));
    }

    private function actor(): string
    {
        return 'admin:'.Auth::user()->name;
    }

    /* ------------------------------------------------------------------ status / shipping helpers */

    private function fire(Order $o, string $status): void
    {
        $map = ['shipped' => 'order.shipped', 'completed' => 'order.completed', 'cancelled' => 'order.cancelled', 'refunded' => 'order.refunded'];
        if (isset($map[$status])) Triggers::fire($map[$status], ['email' => $o->email, 'name' => $o->name, 'order' => $o, 'total' => $o->total, 'ref' => 'o'.$o->id.'-'.$status]);
    }

    private function shippingEmail(Order $o): bool
    {
        $link = $o->tracking_url ?: '';
        $rows = '<p style="color:#cfcfd6;line-height:1.6">Order <b>'.e($o->number).'</b> is on its way'.($o->carrier ? ' with <b>'.e($o->carrier).'</b>' : '').'.</p>';
        if ($o->tracking) $rows .= '<p style="color:#cfcfd6;line-height:1.6">Tracking number: <b style="color:#fff">'.e($o->tracking).'</b></p>';
        if ($o->ship_method) $rows .= '<p style="color:#8a8a93;font-size:13px">Delivery method: '.e($o->ship_method).'</p>';
        $btn = fn ($href, $label, $bg = true) => '<a style="display:inline-block;'.($bg ? 'background:'.e(setting('accent', '#c6ff3d')).';color:#0b0b0d' : 'border:1px solid #34343c;color:#f3f1ec').';padding:12px 22px;border-radius:99px;font-weight:700;text-decoration:none;margin:0 8px 8px 0" href="'.e($href).'">'.e($label).'</a>';
        $rows .= '<p style="margin-top:18px">'.($link && preg_match('#^https?://#i', $link) ? $btn($link, 'Track your parcel') : '').$btn($o->publicUrl(), 'View order', ! $link).'</p>';

        return Mailer::send($o->email, 'Your order is on its way · '.$o->number, Mailer::wrap('Your order is on its way', $rows));
    }

    /** Save carrier / tracking / method fields. */
    private function saveShipping(Order $o, Request $r): void
    {
        $s = fn ($k, $n) => mb_substr(trim((string) $r->input($k)), 0, $n);
        $u = [];
        foreach (['carrier' => 80, 'tracking' => 190, 'tracking_url' => 255, 'ship_method' => 80] as $k => $n) if ($r->has($k)) $u[$k] = $s($k, $n);
        if (isset($u['tracking_url']) && $u['tracking_url'] !== '' && ! preg_match('#^https?://#i', $u['tracking_url'])) $u['tracking_url'] = 'https://'.$u['tracking_url'];
        if ($u) $o->update($u);
    }

    /** Move to a status using the right path (our own shipped flow carries carrier + link). */
    private function applyStatus(Order $o, string $status, bool $notify = true): bool
    {
        if (! isset(Orders::STATUSES[$status]) || $o->status === $status) return false;
        if ($status === 'shipped') {
            $old = $o->status;
            $o->update(['status' => 'shipped', 'shipped_at' => now()]);
            Orders::event($o, 'Status: '.(Orders::STATUSES[$old] ?? $old).' → Shipped'.($o->tracking ? " (tracking {$o->tracking})" : ''), $this->actor());
            if ($notify) $this->shippingEmail($o);
        } else {
            Orders::setStatus($o, $status, $this->actor(), (string) $o->tracking);
        }
        $o->refresh();
        $this->fire($o, $status);

        return true;
    }

    private function rowJson(Order $o): array
    {
        $o->refresh();

        return ['status' => $o->status, 'status_label' => Orders::STATUSES[$o->status] ?? $o->status, 'payment_status' => $o->payment_status, 'tracking' => (string) $o->tracking,
            'carrier' => (string) $o->carrier, 'tracking_url' => (string) $o->tracking_url, 'ship' => trim($o->ship_address.', '.$o->ship_city, ', ')];
    }

    /* ------------------------------------------------------------------ order page actions */

    public function status(Request $r, int $id)
    {
        $o = Order::findOrFail($id);
        $this->saveShipping($o, $r);
        $new = (string) $r->input('status');
        if (! $this->applyStatus($o, $new, $r->boolean('notify', true)) && (string) $r->input('tracking') !== '' ) $o->update(['tracking' => trim((string) $r->input('tracking'))]);
        flash('success', 'Order updated.');

        return redirect('/admin/orders/'.$id);
    }

    public function shipping(Request $r, int $id)
    {
        $o = Order::findOrFail($id);
        $s = fn ($k, $n) => mb_substr(trim((string) $r->input($k)), 0, $n);
        $o->update([
            'ship_name' => $s('ship_name', 150), 'ship_address' => $s('ship_address', 255), 'ship_address2' => $s('ship_address2', 255), 'ship_city' => $s('ship_city', 100),
            'ship_state' => $s('ship_state', 100), 'ship_zip' => $s('ship_zip', 20), 'ship_country' => $s('ship_country', 100),
        ]);
        $this->saveShipping($o, $r);
        $note = [];
        if ($r->has('shipping_cost') && $r->input('shipping_cost') !== '') {
            $new = max(0, to_cents($r->input('shipping_cost')));
            if ($new !== (int) $o->shipping) {
                $o->update(['total' => max(0, (int) $o->total - (int) $o->shipping + $new), 'shipping' => $new]);
                $note[] = 'shipping cost '.money($new);
            }
        }
        Orders::event($o, 'Shipping details edited'.($note ? ' ('.implode(', ', $note).')' : ''), $this->actor());
        if ($r->boolean('mark_shipped')) $this->applyStatus($o, 'shipped', $r->boolean('notify'));
        elseif ($r->boolean('notify') && $o->status === 'shipped') $this->shippingEmail($o);
        flash('success', 'Shipping details saved'.($r->boolean('notify') ? ' and the customer was emailed.' : '.'));

        return redirect('/admin/orders/'.$id);
    }

    public function contact(Request $r, int $id)
    {
        $o = Order::findOrFail($id);
        $email = mb_strtolower(trim((string) $r->input('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Enter a valid email address.');

            return redirect('/admin/orders/'.$id);
        }
        $o->update(['email' => $email, 'name' => mb_substr(trim((string) $r->input('name')) ?: $o->name, 0, 150), 'phone' => mb_substr(trim((string) $r->input('phone')), 0, 40)]);
        Orders::event($o, 'Customer contact details edited', $this->actor());
        flash('success', 'Customer details saved.');

        return redirect('/admin/orders/'.$id);
    }

    public function resendShipping(int $id)
    {
        $o = Order::findOrFail($id);
        $ok = $this->shippingEmail($o);
        flash($ok ? 'success' : 'error', $ok ? 'Shipping email sent to '.$o->email.'.' : 'Sending failed — check Settings → Email.');

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

    public function refundCredit(Request $r, int $id)
    {
        $o = Order::findOrFail($id);
        $already = (int) \App\Models\CreditLog::where('order_id', $o->id)->where('reason', 'like', 'Refund of order%')->where('amount', '>', 0)->sum('amount');
        $max = max(0, $o->due() - $already); // what the customer actually paid by card/bank, minus earlier refunds (credit/gift portions are restored with the status change)
        $cents = to_cents($r->input('amount', 0));
        if ($cents <= 0) $cents = $max;
        if ($cents <= 0 || $cents > $max) {
            flash('error', 'You can refund at most '.money($max).' to store credit for this order.');

            return redirect('/admin/orders/'.$id);
        }
        if (! $o->user_id || ! ($u = User::find($o->user_id))) {
            flash('error', 'Store credit needs a customer account. Create one for this customer first (Customers → Add customer).');

            return redirect('/admin/orders/'.$id);
        }
        if (! class_exists(\App\Services\StoreCredit::class)) {
            flash('error', 'Store credit is not available in this build.');

            return redirect('/admin/orders/'.$id);
        }
        \App\Services\StoreCredit::add($u, $cents, 'Refund of order '.$o->number, $o->id, $this->actor());
        Orders::event($o, 'Refunded '.money($cents).' to store credit', $this->actor());
        if ($r->boolean('mark_refunded')) {
            $o->update(['payment_status' => 'refunded']);
            $this->applyStatus($o, 'refunded');
        }
        flash('success', money($cents).' added to '.$u->name."'s store credit.");

        return redirect('/admin/orders/'.$id);
    }

    public function print(int $id)
    {
        $o = Order::findOrFail($id);
        $items = $o->items()->get()->all();

        return response(view('admin.order_print', compact('o', 'items'))->render());
    }

    /* ------------------------------------------------------------------ quick actions (JSON) */

    public function quick(Request $r, int $id)
    {
        $o = Order::findOrFail($id);
        $do = (string) $r->input('do');
        $msg = 'Updated';
        if ($do === 'status') {
            $st = (string) $r->input('status');
            if (! isset(Orders::STATUSES[$st])) return response()->json(['ok' => false, 'error' => 'Unknown status.'], 422);
            $this->applyStatus($o, $st, $r->boolean('notify', true));
            $msg = 'Status: '.Orders::STATUSES[$st];
        } elseif ($do === 'paid') {
            if ($o->isPaid()) return response()->json(['ok' => false, 'error' => 'Already paid.'], 422);
            Orders::markPaid($o, trim((string) $r->input('ref')) ?: 'manual', $this->actor());
            $msg = 'Marked as paid';
        } elseif ($do === 'shipping') {
            $this->saveShipping($o, $r);
            Orders::event($o, 'Shipping details added'.($r->input('tracking') ? ' (tracking '.trim((string) $r->input('tracking')).')' : ''), $this->actor());
            if ($eta = trim((string) $r->input('eta'))) Orders::event($o, 'Estimated delivery: '.mb_substr($eta, 0, 120), $this->actor());
            $o->refresh();
            if ($r->boolean('mark_shipped', true) && ! in_array($o->status, ['shipped', 'completed', 'cancelled', 'refunded'], true)) $this->applyStatus($o, 'shipped', $r->boolean('notify'));
            elseif ($r->boolean('notify')) $this->shippingEmail($o);
            $msg = 'Shipping details saved'.($r->boolean('notify') ? ' · customer emailed' : '');
        } elseif ($do === 'address') {
            $s = fn ($k, $n) => mb_substr(trim((string) $r->input($k)), 0, $n);
            $o->update(['ship_name' => $s('ship_name', 150), 'ship_address' => $s('ship_address', 255), 'ship_address2' => $s('ship_address2', 255), 'ship_city' => $s('ship_city', 100), 'ship_state' => $s('ship_state', 100), 'ship_zip' => $s('ship_zip', 20), 'ship_country' => $s('ship_country', 100)]);
            Orders::event($o, 'Shipping address edited', $this->actor());
            $msg = 'Address saved';
        } else {
            return response()->json(['ok' => false, 'error' => 'Unknown action.'], 422);
        }

        return response()->json(['ok' => true, 'message' => $msg] + $this->rowJson($o));
    }

    public function bulk(Request $r)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) $r->input('ids')))));
        $act = (string) $r->input('action');
        if (! $ids || $act === '') {
            flash('error', 'Select at least one order and an action.');

            return redirect()->back();
        }
        $orders = Order::whereIn('id', $ids)->get();
        $n = 0;
        if ($act === 'export') return $this->csv($orders);
        foreach ($orders as $o) {
            if (str_starts_with($act, 'status_')) { if ($this->applyStatus($o, substr($act, 7))) $n++; }
            elseif ($act === 'paid') { if (! $o->isPaid() && ! in_array($o->status, ['cancelled', 'refunded'], true)) { Orders::markPaid($o, 'manual', $this->actor()); $n++; } }
            elseif ($act === 'delete') { if (in_array($o->status, ['cancelled'], true)) { $o->items()->delete(); $o->events()->delete(); $o->delete(); $n++; } }
        }
        flash('success', $n.' order(s) updated.');

        return redirect()->back();
    }

    public function export(Request $r)
    {
        return $this->csv($this->filtered($r)->limit(5000)->get());
    }

    private function csv($orders)
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['number', 'date', 'status', 'payment', 'method', 'name', 'email', 'phone', 'subtotal', 'discount', 'shipping', 'tax', 'total', 'currency', 'coupon', 'ship_address', 'ship_city', 'ship_zip', 'ship_country', 'carrier', 'tracking']);
        $safe = fn ($v) => preg_match('/^[=+\-@\t\r]/', (string) $v) ? "'".$v : $v;
        foreach ($orders as $o) {
            fputcsv($out, [$o->number, $o->created_at, $o->status, $o->payment_status, $o->payment_method, $safe($o->name), $safe($o->email), $safe($o->phone), from_cents($o->subtotal), from_cents($o->discount), from_cents($o->shipping), from_cents($o->tax), from_cents($o->total), $o->currency, $o->coupon_code,
                $safe($o->ship_address), $safe($o->ship_city), $safe($o->ship_zip), $safe($o->ship_country), $safe($o->carrier), $safe($o->tracking)]);
        }
        rewind($out);

        return response(stream_get_contents($out), 200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="orders-'.date('Ymd-His').'.csv"']);
    }

    /* ------------------------------------------------------------------ create order (backend) */

    public function create(Request $r)
    {
        $customer = null;
        $ship = [];
        if ($cid = (int) $r->query('customer')) {
            $u = User::find($cid);
            if ($u) {
                $customer = ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => (string) $u->phone] + ['ship' => $this->lastShip($u->id, $u->email)];
            }
        }
        $methods = ManualOrders::PAY_METHODS;
        $taxRate = (float) setting('tax_rate', 0);
        $sym = (string) setting('currency_symbol', '$');
        $shipDefault = (int) setting('shipping_flat', 0);
        title('Create order');

        return admin_page('order_new', compact('customer', 'methods', 'taxRate', 'sym', 'shipDefault'));
    }

    private function lastShip(?int $uid, string $email): array
    {
        $o = Order::where('ship_address', '<>', '')->where(fn ($q) => $q->where('email', $email)->when($uid, fn ($x) => $x->orWhere('user_id', $uid)))->orderByDesc('id')->first();

        return $o ? ['ship_name' => $o->ship_name, 'ship_address' => $o->ship_address, 'ship_address2' => $o->ship_address2, 'ship_city' => $o->ship_city, 'ship_state' => $o->ship_state, 'ship_zip' => $o->ship_zip, 'ship_country' => $o->ship_country] : [];
    }

    /** Typeahead for the order builder: customers or products. */
    public function lookup(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $like = '%'.addcslashes($q, '%_\\').'%';
        if ($r->query('type') === 'customers') {
            $out = [];
            $seen = [];
            foreach (User::where('role', 'customer')->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))->orderBy('name')->limit(6)->get() as $u) {
                $seen[$u->email] = 1;
                $out[] = ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => (string) $u->phone, 'type' => 'Account', 'ship' => $this->lastShip($u->id, $u->email)];
            }
            foreach (Order::whereNull('user_id')->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like))->orderByDesc('id')->limit(30)->get() as $o) {
                if (isset($seen[$o->email]) || count($out) >= 8) continue;
                $seen[$o->email] = 1;
                $out[] = ['id' => 0, 'name' => $o->name, 'email' => $o->email, 'phone' => (string) $o->phone, 'type' => 'Guest', 'ship' => $this->lastShip(null, $o->email)];
            }

            return response()->json(['results' => $out]);
        }
        $out = [];
        $prods = Product::with('cat')->withCount(['keys as pool' => fn ($k) => $k->where('status', 'available')])
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('sku', 'like', $like)->orWhere('tags', 'like', $like)))
            ->orderByDesc('sales')->orderBy('name')->limit(8)->get();
        foreach ($prods as $p) {
            $variants = [];
            foreach ($p->variantList() as $v) {
                if (! $v['active']) continue;
                $variants[] = ['opts' => $v['opts'], 'price' => $p->unitPrice($v['opts']), 'stock' => $v['stock'], 'sku' => $v['sku']];
            }
            $av = $p->available();
            $out[] = ['id' => $p->id, 'name' => $p->name, 'sku' => (string) $p->sku, 'type' => $p->type, 'status' => $p->status, 'price' => $p->unitPrice(), 'price_txt' => money($p->unitPrice()), 'stock' => $av === PHP_INT_MAX ? null : $av,
                'img' => $p->image ? upload_url($p->image) : '', 'cat' => $p->cat?->name, 'options' => $p->options_list, 'variants' => $variants];
        }

        return response()->json(['results' => $out]);
    }

    /** Server-side totals (coupons, tax) for the live summary. */
    public function totals(Request $r)
    {
        try {
            $t = ManualOrders::totals($r->all());
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()]);
        }

        return response()->json(['ok' => true, 'subtotal' => money($t['subtotal']), 'discount' => money($t['discount']), 'has_discount' => $t['discount'] > 0, 'shipping' => money($t['shipping']), 'tax' => money($t['tax']), 'has_tax' => $t['tax'] > 0, 'total' => money($t['total']), 'free_ship' => $t['coupon'] && $t['coupon']->free_shipping,
            'lines' => array_map(fn ($l) => ['total' => money($l['total'])], $t['lines'])]);
    }

    public function store(Request $r)
    {
        try {
            $o = ManualOrders::create($r->all(), $this->actor());
        } catch (\RuntimeException $e) {
            keep_old($r->except(['lines']));
            flash('error', $e->getMessage());

            return redirect('/admin/orders/new'.($r->input('user_id') ? '?customer='.(int) $r->input('user_id') : ''));
        }
        flash('success', 'Order '.$o->number.' created'.($o->isPaid() ? ' and marked as paid.' : '.'));

        return redirect('/admin/orders/'.$o->id);
    }
}
