<?php
namespace App\Controllers\Admin;

use App\Core\Db;

final class Customers extends Base
{
    public function index(): void
    {
        $q = str_in('q');
        $where = "u.role = 'customer'";
        $params = [];
        if ($q !== '') { $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)'; array_push($params, "%$q%", "%$q%"); }
        $total = (int) Db::val("SELECT COUNT(*) FROM users u WHERE $where", $params);
        [$off, $page, $pages] = $this->paginate($total, 25);
        $rows = Db::all("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) orders_n,
            (SELECT COALESCE(SUM(o.total),0) FROM orders o WHERE o.user_id = u.id AND o.payment_status = 'paid') spent
            FROM users u WHERE $where ORDER BY u.id DESC LIMIT 25 OFFSET $off", $params);
        title('Customers');
        $this->render('customers', compact('rows', 'q', 'page', 'pages', 'total'));
    }
}
