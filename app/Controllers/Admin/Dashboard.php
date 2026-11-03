<?php
namespace App\Controllers\Admin;

use App\Core\Db;

final class Dashboard extends Base
{
    public function index(): void
    {
        $day = date('Y-m-d');
        $paid = "payment_status = 'paid'";
        $sum = fn(string $from) => (int) Db::val("SELECT COALESCE(SUM(total),0) FROM orders WHERE $paid AND paid_at >= ?", [$from]);
        $stats = [
            'today' => $sum($day . ' 00:00:00'),
            'week' => $sum(date('Y-m-d', strtotime('-6 days')) . ' 00:00:00'),
            'month' => $sum(date('Y-m-d', strtotime('-29 days')) . ' 00:00:00'),
            'all' => (int) Db::val("SELECT COALESCE(SUM(total),0) FROM orders WHERE $paid"),
            'orders' => (int) Db::val('SELECT COUNT(*) FROM orders'),
            'to_process' => (int) Db::val("SELECT COUNT(*) FROM orders WHERE status IN ('pending','processing')"),
            'customers' => (int) Db::val("SELECT COUNT(*) FROM users WHERE role = 'customer'"),
            'products' => (int) Db::val("SELECT COUNT(*) FROM products WHERE status = 'active'"),
        ];
        // 14-day revenue series
        $rows = Db::all("SELECT SUBSTR(paid_at,1,10) d, SUM(total) t, COUNT(*) n FROM orders WHERE $paid AND paid_at >= ? GROUP BY SUBSTR(paid_at,1,10)", [date('Y-m-d', strtotime('-13 days')) . ' 00:00:00']);
        $by = [];
        foreach ($rows as $r) $by[$r['d']] = $r;
        $series = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $series[] = ['d' => $d, 'label' => date('M j', strtotime($d)), 'total' => (int) ($by[$d]['t'] ?? 0), 'n' => (int) ($by[$d]['n'] ?? 0)];
        }
        $recent = Db::all('SELECT * FROM orders ORDER BY id DESC LIMIT 8');
        $top = Db::all("SELECT p.id, p.name, p.sales, p.price FROM products p WHERE p.sales > 0 ORDER BY p.sales DESC LIMIT 5");
        $low = Db::all("SELECT p.id, p.name, p.type, p.stock,
                (SELECT COUNT(*) FROM product_keys k WHERE k.product_id = p.id AND k.status = 'available') pool
                FROM products p WHERE p.status = 'active' AND p.unlimited = 0 AND p.type IN ('physical','key','account')");
        $thr = (int) setting('low_stock', 3);
        $low = array_values(array_filter($low, function ($r) use ($thr) {
            $left = in_array($r['type'], ['key', 'account'], true) ? (int) $r['pool'] : (int) $r['stock'];
            return $left <= $thr;
        }));
        $low = array_map(function ($r) {
            $r['left'] = in_array($r['type'], ['key', 'account'], true) ? (int) $r['pool'] : (int) $r['stock'];
            return $r;
        }, array_slice($low, 0, 6));
        title('Dashboard');
        $this->render('dashboard', compact('stats', 'series', 'recent', 'top', 'low'));
    }
}
