<?php
namespace App\Controllers\Admin;

use App\Core\Db;
use App\Core\Upload;

final class Categories extends Base
{
    public function index(): void
    {
        $rows = Db::all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) n FROM categories c ORDER BY sort_order, id');
        title('Departments');
        $this->render('categories', compact('rows'));
    }

    public function form(array $a = []): void
    {
        $c = !empty($a['id']) ? Db::one('SELECT * FROM categories WHERE id = ?', [(int) $a['id']]) : null;
        if (!empty($a['id']) && !$c) abort(404);
        title($c ? 'Edit ' . $c['name'] : 'New department');
        $this->render('category_form', compact('c'));
    }

    public function save(): void
    {
        $id = int_in('id');
        $old = $id ? Db::one('SELECT * FROM categories WHERE id = ?', [$id]) : null;
        $name = str_in('name');
        if ($name === '') {
            flash('error', 'Name is required.');
            redirect($id ? "admin/categories/$id" : 'admin/categories/new');
        }
        $data = [
            'name' => $name,
            'headline' => mb_substr(str_in('headline') ?: $name, 0, 40),
            'eyebrow' => mb_substr(str_in('eyebrow'), 0, 150),
            'tagline' => mb_substr(str_in('tagline'), 0, 250),
            'description' => (string) input('description', ''),
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', str_in('color')) ? str_in('color') : '#c6ff3d',
            'sort_order' => int_in('sort_order'),
            'status' => str_in('status') === 'hidden' ? 'hidden' : 'active',
        ];
        try {
            if ($img = Upload::image($_FILES['image'] ?? null, 'site')) {
                if ($old) Upload::remove($old['image']);
                $data['image'] = $img;
            }
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirect($id ? "admin/categories/$id" : 'admin/categories/new');
        }
        if ($old) {
            $data['slug'] = unique_slug('categories', str_in('slug') ?: $name, $id);
            Db::update('categories', $data, 'id = ?', [$id]);
        } else {
            $data['slug'] = unique_slug('categories', str_in('slug') ?: $name);
            $data['created_at'] = now();
            $id = Db::insert('categories', $data);
        }
        flash('success', 'Department saved.');
        redirect('admin/categories');
    }

    public function delete(array $a): void
    {
        $id = (int) $a['id'];
        if (Db::val('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id])) {
            flash('error', 'Move or delete the products in this department first.');
        } else {
            Db::delete('categories', 'id = ?', [$id]);
            flash('success', 'Department deleted.');
        }
        redirect('admin/categories');
    }
}
