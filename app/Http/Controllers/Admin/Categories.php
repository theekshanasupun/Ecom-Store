<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\Upload;
use Illuminate\Http\Request;

class Categories extends Controller
{
    public function index()
    {
        $rows = Category::withCount('products as n')->orderBy('sort_order')->orderBy('id')->get()->all();
        title('Departments');

        return admin_page('categories', compact('rows'));
    }

    public function form(?int $id = null)
    {
        $c = $id ? Category::findOrFail($id) : null;
        title($c ? 'Edit '.$c->name : 'New department');

        return admin_page('category_form', compact('c'));
    }

    public function save(Request $r)
    {
        $id = (int) $r->input('id');
        $old = $id ? Category::find($id) : null;
        $name = trim((string) $r->input('name'));
        if ($name === '') {
            flash('error', 'Name is required.');

            return redirect($id ? "/admin/categories/$id" : '/admin/categories/new');
        }
        $data = [
            'name' => $name, 'headline' => mb_substr(trim((string) $r->input('headline')) ?: $name, 0, 40),
            'eyebrow' => mb_substr(trim((string) $r->input('eyebrow')), 0, 150), 'tagline' => mb_substr(trim((string) $r->input('tagline')), 0, 250),
            'description' => (string) $r->input('description'),
            'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) $r->input('color')) ? $r->input('color') : '#c6ff3d',
            'seo_title' => mb_substr(trim((string) $r->input('seo_title')), 0, 190), 'seo_description' => mb_substr(trim((string) $r->input('seo_description')), 0, 320), 'seo_noindex' => $r->boolean('seo_noindex'),
            'sort_order' => (int) $r->input('sort_order'), 'status' => $r->input('status') === 'hidden' ? 'hidden' : 'active',
        ];
        $slugBase = trim((string) $r->input('slug')) ?: $name;
        if ($old) {
            $data['slug'] = unique_slug(Category::class, $slugBase, $id);
            $old->update($data);
        } else {
            $data['slug'] = unique_slug(Category::class, $slugBase);
            Category::create($data);
        }
        flash('success', 'Department saved.');

        return redirect('/admin/categories');
    }

    public function delete(int $id)
    {
        $c = Category::findOrFail($id);
        if ($c->products()->exists()) flash('error', 'Move or delete the products in this department first.');
        else { $c->delete(); flash('success', 'Department deleted.'); }

        return redirect('/admin/categories');
    }
}
