<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;

class Pages extends Controller
{
    public function index()
    {
        $rows = Page::orderByDesc('id')->get()->all();
        title('Pages');

        return admin_page('pages', compact('rows'));
    }

    public function form(?int $id = null)
    {
        $pg = $id ? Page::findOrFail($id) : null;
        title($pg ? 'Edit page' : 'New page');

        return admin_page('page_form', compact('pg'));
    }

    public function save(Request $r)
    {
        $id = (int) $r->input('id');
        $title = trim((string) $r->input('title'));
        if ($title === '') {
            flash('error', 'Title is required.');

            return redirect($id ? "/admin/pages/$id" : '/admin/pages/new');
        }
        $data = ['title' => $title, 'body' => (string) $r->input('body'), 'status' => $r->input('status') === 'draft' ? 'draft' : 'published', 'in_footer' => $r->boolean('in_footer')];
        $slugBase = trim((string) $r->input('slug')) ?: $title;
        $data['slug'] = unique_slug(Page::class, $slugBase, $id ?: null);
        $id ? Page::whereKey($id)->update($data) : Page::create($data);
        flash('success', 'Page saved.');

        return redirect('/admin/pages');
    }

    public function delete(int $id)
    {
        Page::whereKey($id)->delete();
        flash('success', 'Page deleted.');

        return redirect('/admin/pages');
    }
}
