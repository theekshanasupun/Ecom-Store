<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\EmailBlocks;
use App\Support\Mailer;
use Illuminate\Http\Request;

/** Shared endpoints of the email builder: live preview, test send, product search. */
class EmailTools extends Controller
{
    /** Renders the full-screen builder for a config array (used by automations + newsletters). */
    public static function builder(array $cfg)
    {
        $reg = [];
        foreach (EmailBlocks::registry() as $k => $d) $reg[$k] = ['label' => $d[0], 'icon' => $d[1], 'fields' => $d[2], 'defaults' => $d[3], 'caps' => $d[4]];
        $cfg += [
            'reg' => $reg, 'tags' => EmailBlocks::TAGS, 'templates' => EmailBlocks::templates(), 'csrf' => csrf_token(),
            'previewUrl' => url('admin/email/preview'), 'testUrl' => url('admin/email/test'), 'productsUrl' => url('admin/email/products.json'),
            'cats' => Category::orderBy('sort_order')->get(['id', 'name'])->map(fn ($c) => [(string) $c->id, $c->name])->all(),
            'sender' => (string) (setting('mail_from_name') ?: setting('store_name')), 'defaultTo' => (string) auth()->user()->email,
            'accent' => (string) setting('accent', '#c6ff3d'),
        ];

        return response(view('admin.email_builder', ['cfg' => $cfg])->render());
    }

    private function blocks(Request $r): array
    {
        return EmailBlocks::decode(EmailBlocks::clean((string) $r->input('layout', '[]')));
    }

    public function preview(Request $r)
    {
        $html = EmailBlocks::render($this->blocks($r), EmailBlocks::vars(EmailBlocks::sampleCtx()), [
            'editor' => true, 'subject' => (string) $r->input('subject'), 'preheader' => (string) $r->input('preheader'), 'footer' => false,
        ]);

        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function test(Request $r)
    {
        $to = trim((string) $r->input('to'));
        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) return response()->json(['ok' => false, 'error' => 'Enter a valid email address.'], 422);
        $vars = EmailBlocks::vars(EmailBlocks::sampleCtx($to));
        $subject = trim((string) $r->input('subject')) ?: 'Test email';
        $subject = strtr($subject, ['{store}' => (string) setting('store_name'), '{first_name}' => 'Alex', '{name}' => 'Alex Example', '{order_number}' => $vars['order_number']]);
        $html = EmailBlocks::render($this->blocks($r), $vars, ['subject' => $subject, 'preheader' => (string) $r->input('preheader'), 'footer' => true]);
        $ok = Mailer::send($to, '[Test] '.$subject, $html);

        return response()->json(['ok' => $ok, 'error' => $ok ? null : 'Sending failed — check Settings → Email.']);
    }

    public function products(Request $r)
    {
        $q = trim((string) $r->query('q'));
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $r->query('ids')))));
        $rows = Product::where('status', 'active')
            ->when($ids, fn ($x) => $x->whereIn('id', $ids))
            ->when(! $ids && $q !== '', fn ($x) => $x->where(fn ($w) => $w->where('name', 'like', "%$q%")->orWhere('sku', 'like', "%$q%")))
            ->orderBy('name')->limit(20)->get();

        return response()->json($rows->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => money($p->price, true), 'image' => $p->image ? upload_url($p->image) : ''])->all());
    }
}
