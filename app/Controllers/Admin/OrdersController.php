<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Db;
use App\Services\Orders;

final class OrdersController extends Base
{
    public function index(): void
    {
        $q = str_in('q');
        $st = str_in('status');
        $pay = str_in('pay');
        $where = ['1=1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(number LIKE ? OR email LIKE ? OR name LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        if (isset(Orders::STATUSES[$st])) { $where[] = 'status = ?'; $params[] = $st; }
        if (in_array($pay, ['paid', 'unpaid'], true)) { $where[] = 'payment_status = ?'; $params[] = $pay; }
        $w = implode(' AND ', $where);
        $total = (int) Db::val("SELECT COUNT(*) FROM orders WHERE $w", $params);
        [$off, $page, $pages] = $this->paginate($total, 25);
        $rows = Db::all("SELECT * FROM orders WHERE $w ORDER BY id DESC LIMIT 25 OFFSET $off", $params);
        $counts = [];
        foreach (Db::all('SELECT status, COUNT(*) n FROM orders GROUP BY status') as $r) $counts[$r['status']] = (int) $r['n'];
        title('Orders');
        $this->render('orders', compact('rows', 'q', 'st', 'pay', 'page', 'pages', 'total', 'counts'));
    }

    private function load(array $a): array
    {
        $o = Orders::find((int) $a['id']);
        if (!$o) abort(404);
        return $o;
    }

    public function show(array $a): void
    {
        $o = $this->load($a);
        $items = Orders::items((int) $o['id']);
        $events = Orders::events((int) $o['id']);
        $customer = $o['user_id'] ? Db::one('SELECT id, name, email FROM users WHERE id = ?', [$o['user_id']]) : null;
        title('Order ' . $o['number']);
        $this->render('order_show', compact('o', 'items', 'events', 'customer'));
    }

    public function status(array $a): void
    {
        $o = $this->load($a);
        Orders::setStatus((int) $o['id'], str_in('status'), $this->actor(), str_in('tracking'));
        if (str_in('tracking') !== '' && $o['status'] === str_in('status')) {
            Db::update('orders', ['tracking' => str_in('tracking')], 'id = ?', [$o['id']]);
        }
        flash('success', 'Order updated.');
        redirect('admin/orders/' . $o['id']);
    }

    public function markPaid(array $a): void
    {
        $o = $this->load($a);
        Orders::markPaid((int) $o['id'], str_in('ref') ?: 'manual', $this->actor());
        flash('success', 'Marked as paid. Digital items were delivered and the customer was emailed.');
        redirect('admin/orders/' . $o['id']);
    }

    public function deliver(array $a): void
    {
        $o = $this->load($a);
        if ($o['payment_status'] !== 'paid') {
            flash('error', 'Order is not paid yet.');
            redirect('admin/orders/' . $o['id']);
        }
        $missing = Orders::deliver((int) $o['id']);
        Orders::refreshStatus((int) $o['id']);
        if ($missing === 0) Orders::sendReceipt(Orders::find((int) $o['id']), true);
        flash($missing ? 'error' : 'success', $missing ? "$missing units still lack stock. Add keys to the product and try again." : 'Delivered and emailed to the customer.');
        redirect('admin/orders/' . $o['id']);
    }

    public function note(array $a): void
    {
        $o = $this->load($a);
        if (str_in('note') !== '') Orders::event((int) $o['id'], 'Note: ' . str_in('note'), $this->actor());
        redirect('admin/orders/' . $o['id']);
    }

    public function resend(array $a): void
    {
        $o = $this->load($a);
        $o['payment_status'] === 'paid' ? Orders::sendReceipt($o, true) : Orders::sendPending($o);
        flash('success', 'Email sent to ' . $o['email'] . '.');
        redirect('admin/orders/' . $o['id']);
    }

    private function actor(): string
    {
        return 'admin:' . (Auth::user()['name'] ?? '?');
    }
}
