<?php
namespace App\Controllers\Admin;

use App\Core\Db;

final class Pages extends Base
{
    public function index(): void
    {
        $rows = Db::all('SELECT * FROM pages ORDER BY id DESC');
        title('Pages');
        $this->render('pages', compact('rows'));
    }

    public function form(array $a = []): void
    {
        $pg = !empty($a['id']) ? Db::one('SELECT * FROM pages WHERE id = ?', [(int) $a['id']]) : null;
        if (!empty($a['id']) && !$pg) abort(404);
        title($pg ? 'Edit page' : 'New page');
        $this->render('page_form', compact('pg'));
    }

    public function save(): void
    {
        $id = int_in('id');
        $title = str_in('title');
        if ($title === '') {
            flash('error', 'Title is required.');
            redirect($id ? "admin/pages/$id" : 'admin/pages/new');
        }
        $data = [
            'title' => $title,
            'body' => (string) input('body', ''),
            'status' => str_in('status') === 'draft' ? 'draft' : 'published',
            'in_footer' => int_in('in_footer') ? 1 : 0,
        ];
        if ($id) {
            $data['slug'] = unique_slug('pages', str_in('slug') ?: $title, $id);
            Db::update('pages', $data, 'id = ?', [$id]);
        } else {
            $data['slug'] = unique_slug('pages', str_in('slug') ?: $title);
            $data['created_at'] = now();
            $id = Db::insert('pages', $data);
        }
        flash('success', 'Page saved.');
        redirect('admin/pages');
    }

    public function delete(array $a): void
    {
        Db::delete('pages', 'id = ?', [(int) $a['id']]);
        flash('success', 'Page deleted.');
        redirect('admin/pages');
    }
}
