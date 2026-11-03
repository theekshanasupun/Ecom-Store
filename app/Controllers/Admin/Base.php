<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;

abstract class Base
{
    public function __construct()
    {
        if (!Auth::isStaff()) {
            if (is_ajax()) json_out(['ok' => false, 'error' => 'Not signed in'], 401);
            redirect('admin/login');
        }
        header('X-Robots-Tag: noindex');
        header('Cache-Control: no-store');
    }

    protected function adminOnly(): void
    {
        if (!Auth::isAdmin()) abort(403, 'Only administrators can open this page.');
    }

    protected function render(string $tpl, array $data = []): void
    {
        View::page('admin/' . $tpl, $data, 'admin/layout');
    }

    /** Paginate helper: returns [offset, page, pages]. */
    protected function paginate(int $total, int $per = 25): array
    {
        $pages = max(1, (int) ceil($total / $per));
        $page = max(1, min($pages, int_in('page', 1)));
        return [($page - 1) * $per, $page, $pages];
    }
}
