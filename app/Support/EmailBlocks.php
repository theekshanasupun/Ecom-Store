<?php

namespace App\Support;

use App\Models\Product;
use App\Services\Catalog;

/**
 * Block registry + renderer for the drag & drop email builder (automations, newsletters).
 *
 * A design is a JSON array of blocks: {id, type, props{…}, style{bg,color,pad,align,size,btn_bg,btn_color,radius}}.
 * Output is table based, inline-styled and 600px wide so it survives Gmail / Outlook / Apple Mail.
 * Every prop value is HTML-escaped on output. The ONLY exception is the "html" block, which is trusted
 * admin markup (admin-only feature, same trust level as the page builder's HTML block).
 * Merge tags such as {first_name} are substituted AFTER escaping, with escaped values; {cart_button} and
 * {cart_items} are the only tags that inject HTML (generated here, never from user input).
 */
final class EmailBlocks
{
    /** Merge tags offered in the builder. */
    public const TAGS = ['{first_name}', '{name}', '{email}', '{store}', '{order_number}', '{order_total}', '{order_url}', '{tracking}', '{tracking_url}', '{coupon}', '{cart_button}', '{cart_items}', '{shop_url}', '{unsubscribe_url}', '{gift_code}', '{gift_amount}', '{credit_amount}'];

    private const HTML_TAGS = ['cart_button', 'cart_items'];

    private const PAD = ['none' => 0, 'sm' => 12, 'md' => 24, 'lg' => 40];

    /** type => [label, icon, fields[], defaults, caps[]] ; field = [key,label,type,(options)] */
    public static function registry(): array
    {
        $align = ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'];

        return [
            'header' => ['Logo / header', '◔', [['text', 'Text (blank = store name / logo)', 'text'], ['image', 'Logo image URL (blank = store logo)', 'image'], ['url', 'Link', 'text']], ['text' => '', 'image' => '', 'url' => '/'], []],
            'hero' => ['Hero', '▣', [['image', 'Image URL', 'image'], ['eyebrow', 'Small label', 'text'], ['title', 'Headline', 'text'], ['text', 'Paragraph', 'area'], ['btn', 'Button text', 'text'], ['url', 'Button link', 'text']],
                ['image' => '', 'eyebrow' => 'New', 'title' => 'A bold headline', 'text' => 'Tell your customers what this email is about.', 'btn' => 'Shop now', 'url' => '{shop_url}'], ['btn']],
            'heading' => ['Heading', 'H', [['text', 'Heading', 'text'], ['level', 'Size', 'select', ['1' => 'Large', '2' => 'Medium', '3' => 'Small']]], ['text' => 'Heading', 'level' => '2'], []],
            'text' => ['Text', '¶', [['body', 'Text (blank line = new paragraph)', 'area']], ['body' => 'Hi {first_name},' . "\n\n" . 'Write your message here.'], []],
            'image' => ['Image', '▨', [['url', 'Image URL', 'image'], ['alt', 'Alt text', 'text'], ['link', 'Link', 'text'], ['full', 'Edge to edge', 'select', ['0' => 'No', '1' => 'Yes']]], ['url' => '', 'alt' => '', 'link' => '', 'full' => '1'], []],
            'button' => ['Button', '▭', [['text', 'Label', 'text'], ['url', 'Link', 'text']], ['text' => 'Click here', 'url' => '{shop_url}'], ['btn']],
            'columns' => ['Image + text', '◨', [['image', 'Image URL', 'image'], ['title', 'Heading', 'text'], ['body', 'Text', 'area'], ['btn', 'Button text', 'text'], ['url', 'Button link', 'text'], ['side', 'Image side', 'select', ['left' => 'Left', 'right' => 'Right']]],
                ['image' => '', 'title' => 'A feature worth a look', 'body' => 'Pair a picture with a short description.', 'btn' => '', 'url' => '', 'side' => 'left'], ['btn']],
            'product' => ['Product', '▦', [['product_id', 'Product', 'product'], ['btn', 'Button text', 'text'], ['price', 'Show price', 'select', ['1' => 'Yes', '0' => 'No']]], ['product_id' => '', 'btn' => 'View product', 'price' => '1'], ['btn']],
            'product_grid' => ['Product grid', '▤', [['title', 'Heading', 'text'], ['source', 'Show', 'select', ['featured' => 'Featured / trending', 'new' => 'Newest', 'sale' => 'On sale', 'category' => 'From a department']], ['category', 'Department (when chosen)', 'category'], ['count', 'How many', 'number'], ['cols', 'Columns', 'select', ['2' => '2', '3' => '3']], ['btn', 'Button text', 'text']],
                ['title' => 'Picked for you', 'source' => 'featured', 'category' => '', 'count' => '4', 'cols' => '2', 'btn' => 'Shop'], ['btn']],
            'cart' => ['Cart items', '🛒', [['title', 'Heading', 'text'], ['btn', 'Button text', 'text']], ['title' => 'Your cart', 'btn' => 'Return to your cart'], ['btn']],
            'order' => ['Order summary', '🧾', [['title', 'Heading', 'text'], ['btn', 'Button text', 'text']], ['title' => 'Order {order_number}', 'btn' => 'View your order'], ['btn']],
            'coupon' => ['Coupon code', '％', [['label', 'Label', 'text'], ['code', 'Code (or {coupon})', 'text'], ['note', 'Small print', 'text']], ['label' => 'Your discount code', 'code' => '{coupon}', 'note' => 'Enter at checkout.'], []],
            'quote' => ['Testimonial', '❝', [['quote', 'Quote', 'area'], ['name', 'Name', 'text']], ['quote' => 'Fast, easy and exactly as described.', 'name' => 'Sam R.'], []],
            'social' => ['Social links', '✦', [['items', 'One per line: Name | URL', 'area']], ['items' => "Instagram | https://instagram.com/\nX | https://x.com/\nTikTok | https://tiktok.com/"], []],
            'divider' => ['Divider', '—', [['thickness', 'Thickness (px)', 'number']], ['thickness' => '1'], []],
            'spacer' => ['Spacer', '↕', [['h', 'Height (px)', 'number']], ['h' => '24'], []],
            'html' => ['Custom HTML', '</>', [['code', 'HTML (trusted admin markup)', 'area']], ['code' => '<p>Custom HTML</p>'], []],
            'footer' => ['Footer', '⎯', [['text', 'Text', 'area'], ['unsub', 'Unsubscribe link', 'select', ['1' => 'Show', '0' => 'Hide']]], ['text' => 'You are receiving this email because you shopped with or subscribed to {store}.', 'unsub' => '1'], []],
        ];
    }

    public static function decode(?string $json): array
    {
        $a = is_array($json) ? $json : json_decode((string) $json, true);
        if (! is_array($a)) return [];
        if (isset($a['blocks']) && is_array($a['blocks'])) $a = $a['blocks'];
        $reg = self::registry();

        return array_values(array_filter($a, fn ($b) => is_array($b) && isset($reg[$b['type'] ?? ''])));
    }

    /** Validate / normalise posted JSON (or array) before saving; returns a JSON array string. */
    public static function clean(string|array $json): string
    {
        $hex = fn ($v) => preg_match('/^#[0-9a-f]{6}$/i', (string) $v) ? (string) $v : '';
        $out = [];
        foreach (self::decode(is_array($json) ? json_encode($json) : $json) as $b) {
            $def = self::registry()[$b['type']];
            $props = [];
            foreach ($def[2] as $f) $props[$f[0]] = mb_substr((string) ($b['props'][$f[0]] ?? ($def[3][$f[0]] ?? '')), 0, 20000);
            $st = (array) ($b['style'] ?? []);
            $out[] = ['id' => preg_replace('/[^a-z0-9]/i', '', (string) ($b['id'] ?? '')) ?: bin2hex(random_bytes(4)), 'type' => $b['type'], 'props' => $props, 'style' => [
                'bg' => $hex($st['bg'] ?? ''), 'color' => $hex($st['color'] ?? ''), 'btn_bg' => $hex($st['btn_bg'] ?? ''), 'btn_color' => $hex($st['btn_color'] ?? ''),
                'pad' => in_array($st['pad'] ?? 'md', array_keys(self::PAD), true) ? ($st['pad'] ?? 'md') : 'md',
                'align' => in_array($st['align'] ?? 'left', ['left', 'center', 'right'], true) ? ($st['align'] ?? 'left') : 'left',
                'size' => (string) max(0, min(48, (int) ($st['size'] ?? 0))),
                'radius' => (string) max(0, min(40, (int) ($st['radius'] ?? 10))),
            ]];
        }

        return json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /* ------------------------------------------------------------ merge tags */

    /** Build the variable map. $ctx: email, name, order_number, order_total (cents), order_url, tracking, tracking_url, lines, cart_url, coupon, gift_code, gift_amount (cents), credit_amount (cents). */
    public static function vars(array $ctx): array
    {
        $name = trim((string) ($ctx['name'] ?? ''));
        $first = trim(explode(' ', $name)[0]) ?: 'there';
        $email = (string) ($ctx['email'] ?? '');
        $cart = (string) ($ctx['cart_url'] ?? url('cart'));
        $acc = e((string) setting('accent', '#c6ff3d'));
        $btn = '<a href="'.e($cart).'" style="display:inline-block;background:'.$acc.';color:'.e(Art::ink((string) setting('accent', '#c6ff3d'))).';text-decoration:none;font-weight:700;padding:13px 26px;border-radius:99px">Return to your cart</a>';

        return [
            'first_name' => $first, 'name' => $name ?: 'there', 'email' => $email, 'store' => (string) setting('store_name'),
            'order_number' => (string) ($ctx['order_number'] ?? ''), 'order_total' => isset($ctx['order_total']) ? money((int) $ctx['order_total']) : '',
            'order_url' => (string) ($ctx['order_url'] ?? url('track')), 'tracking' => (string) ($ctx['tracking'] ?? ''), 'tracking_url' => (string) ($ctx['tracking_url'] ?? ''),
            'coupon' => (string) ($ctx['coupon'] ?? ''), 'shop_url' => url('/'), 'cart_url' => $cart,
            'unsubscribe_url' => $email !== '' ? Marketing::unsubscribeUrl($email) : url('/'),
            'gift_code' => (string) ($ctx['gift_code'] ?? ''), 'gift_amount' => isset($ctx['gift_amount']) ? money((int) $ctx['gift_amount']) : '',
            'credit_amount' => isset($ctx['credit_amount']) ? money((int) $ctx['credit_amount']) : '',
            'cart_items' => self::cartItems((array) ($ctx['lines'] ?? [])), 'cart_button' => $btn,
        ];
    }

    /** Sample data for the live preview / test sends. */
    public static function sampleCtx(string $email = 'alex@example.com'): array
    {
        $p = Product::where('status', 'active')->orderByDesc('id')->first();

        return ['email' => $email, 'name' => 'Alex Example', 'order_number' => 'K000000-DEMO', 'order_total' => $p ? $p->price + 599 : 4999, 'order_url' => url('track'), 'tracking' => '1Z999AA10123456784', 'tracking_url' => url('track'),
            'coupon' => 'WELCOME10', 'gift_code' => 'GIFT-ABCD-1234', 'gift_amount' => 5000, 'credit_amount' => 1000, 'lines' => $p ? [['pid' => $p->id, 'qty' => 1, 'opts' => []]] : []];
    }

    private static function cartItems(array $lines): string
    {
        if (! $lines) return '';
        $h = '<table width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0">';
        foreach ($lines as $l) {
            $p = Catalog::byId((int) ($l['pid'] ?? 0));
            if (! $p) continue;
            $img = $p->image ? '<img src="'.e(upload_url($p->image)).'" width="56" height="56" style="border-radius:10px;object-fit:cover;display:block" alt="">' : '';
            $h .= '<tr><td width="68" style="padding:8px 0">'.$img.'</td><td style="padding:8px;font-size:14px"><b>'.e($p->name).'</b><br><span style="opacity:.6;font-size:12px">Qty '.(int) $l['qty'].'</span></td><td align="right" style="padding:8px 0;font-size:14px">'.money($p->price * (int) $l['qty']).'</td></tr>';
        }

        return $h.'</table>';
    }

    /** Escape then substitute tags. */
    private static function t(string $s, array $v): string
    {
        $map = [];
        foreach ($v as $k => $val) $map['{'.$k.'}'] = in_array($k, self::HTML_TAGS, true) ? (string) $val : e((string) $val);

        return strtr(e($s), $map);
    }

    private static function tl(string $s, array $v): string
    {
        return nl2br(self::t($s, $v), false);
    }

    /** Substitute tags (raw values), then make a safe href. */
    private static function u(string $s, array $v): string
    {
        $map = [];
        foreach ($v as $k => $val) if (! in_array($k, self::HTML_TAGS, true)) $map['{'.$k.'}'] = (string) $val;
        $u = trim(strtr($s, $map));
        if ($u === '') return '#';
        if (preg_match('/^\s*(javascript|data|vbscript):/i', $u)) return '#';
        if (preg_match('#^(https?:)?//#i', $u) || str_starts_with($u, 'mailto:') || str_starts_with($u, 'tel:')) return e($u);

        return e(url(ltrim($u, '/')));
    }

    private static function img(string $u): string
    {
        $u = trim($u);
        if ($u === '' || preg_match('/^\s*(javascript|data|vbscript):/i', $u)) return '';
        if (preg_match('#^(https?:)?//#i', $u)) return e($u);

        return e(str_starts_with($u, '/') ? url(ltrim($u, '/')) : upload_url($u));
    }

    /* ------------------------------------------------------------ rendering */

    private static function accent(): string
    {
        $a = (string) setting('accent', '#c6ff3d');

        return preg_match('/^#[0-9a-f]{6}$/i', $a) ? $a : '#c6ff3d';
    }

    private static function btn(string $label, string $href, array $st, string $align = 'left'): string
    {
        if (trim($label) === '') return '';
        $bg = $st['btn_bg'] ?: self::accent();
        $fg = $st['btn_color'] ?: Art::ink($bg);
        $r = (int) ($st['radius'] !== '' ? $st['radius'] : 10);

        return '<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr><td align="'.$align.'"><table cellpadding="0" cellspacing="0" role="presentation"><tr><td style="background:'.e($bg).';border-radius:'.$r.'px"><a href="'.$href.'" style="display:inline-block;padding:13px 26px;color:'.e($fg).';font:700 15px Arial,Helvetica,sans-serif;text-decoration:none">'.$label.'</a></td></tr></table></td></tr></table>';
    }

    private static function productCard(Product $p, array $v, array $st, bool $price, string $btn): string
    {
        $url = e(url('product/'.$p->slug));
        $media = $p->image
            ? '<a href="'.$url.'"><img src="'.e(upload_url($p->image)).'" width="100%" alt="'.e($p->name).'" style="display:block;width:100%;height:auto;border-radius:12px"></a>'
            : '<a href="'.$url.'" style="display:block;background:'.e($p->color).';border-radius:12px;height:140px;line-height:140px;text-align:center;font-family:Arial,sans-serif;font-size:18px;font-weight:800;color:'.e(Art::ink($p->color)).';text-decoration:none">'.e(mb_substr($p->name, 0, 18)).'</a>';
        $pr = '';
        if ($price) {
            $pr = '<div style="font:700 16px Arial,sans-serif;margin:4px 0 10px">'.money($p->price, true).($p->compare_price > $p->price ? ' <s style="font-weight:400;opacity:.55;font-size:13px">'.money($p->compare_price, true).'</s>' : '').'</div>';
        }

        return $media.'<div style="font:700 15px Arial,sans-serif;margin:10px 0 2px">'.e($p->name).'</div>'.($p->tagline ? '<div style="font:13px Arial,sans-serif;opacity:.65;margin-bottom:4px">'.e(mb_substr($p->tagline, 0, 60)).'</div>' : '').$pr
            .self::btn($btn, $url, $st, 'left');
    }

    private static function inner(string $type, array $p, array $st, array $v): string
    {
        $al = $st['align'];
        $size = (int) $st['size'];
        $fs = fn (int $d) => ($size > 0 ? $size : $d).'px';
        $font = 'font-family:Arial,Helvetica,sans-serif;';
        switch ($type) {
            case 'header':
                $img = trim((string) $p['image']) !== '' ? self::img($p['image']) : (setting('logo') ? e(upload_url(setting('logo'))) : '');
                $href = self::u($p['url'] ?: '/', $v);
                if (trim((string) $p['text']) === '' && $img) return '<a href="'.$href.'"><img src="'.$img.'" alt="'.e(setting('store_name')).'" height="40" style="height:40px;max-width:240px;border:0"></a>';
                $txt = trim((string) $p['text']) !== '' ? self::t($p['text'], $v) : e(strtoupper((string) setting('store_name')));

                return '<a href="'.$href.'" style="'.$font.'font-weight:900;letter-spacing:3px;font-size:'.$fs(22).';color:inherit;text-decoration:none">'.$txt.'<span style="color:'.e(self::accent()).'">.</span></a>';
            case 'hero':
                $h = '';
                if ($i = self::img($p['image'])) $h .= '<img src="'.$i.'" width="100%" alt="" style="display:block;width:100%;height:auto;border-radius:12px;margin-bottom:20px">';
                if (trim((string) $p['eyebrow']) !== '') $h .= '<div style="'.$font.'font-size:12px;letter-spacing:2px;text-transform:uppercase;font-weight:700;opacity:.65;margin-bottom:8px">'.self::t($p['eyebrow'], $v).'</div>';
                $h .= '<div style="'.$font.'font-weight:800;font-size:'.$fs(30).';line-height:1.15;margin:0 0 12px">'.self::t($p['title'], $v).'</div>';
                if (trim((string) $p['text']) !== '') $h .= '<div style="'.$font.'font-size:16px;line-height:1.6;opacity:.85;margin:0 0 20px">'.self::tl($p['text'], $v).'</div>';

                return $h.self::btn(self::t((string) $p['btn'], $v), self::u((string) $p['url'], $v), $st, $al);
            case 'heading':
                $sz = ['1' => 30, '2' => 24, '3' => 18][$p['level']] ?? 24;

                return '<div style="'.$font.'font-weight:800;font-size:'.$fs($sz).';line-height:1.25">'.self::t((string) $p['text'], $v).'</div>';
            case 'text':
                $h = '';
                foreach (preg_split("/\n{2,}/", trim(str_replace("\r", '', (string) $p['body']))) as $para) {
                    if (trim($para) !== '') $h .= '<p style="'.$font.'font-size:'.$fs(16).';line-height:1.6;margin:0 0 14px">'.self::tl($para, $v).'</p>';
                }

                return $h;
            case 'image':
                $i = self::img((string) $p['url']);
                if (! $i) return '<div style="'.$font.'font-size:13px;opacity:.5;text-align:center;padding:30px;border:1px dashed currentColor;border-radius:10px">Add an image URL</div>';
                $tag = '<img src="'.$i.'" width="100%" alt="'.e((string) $p['alt']).'" style="display:block;width:100%;height:auto;border:0">';

                return trim((string) $p['link']) !== '' ? '<a href="'.self::u($p['link'], $v).'">'.$tag.'</a>' : $tag;
            case 'button':
                return self::btn(self::t((string) $p['text'], $v), self::u((string) $p['url'], $v), $st, $al);
            case 'columns':
                $img = ($i = self::img((string) $p['image'])) ? '<img src="'.$i.'" width="100%" alt="" style="display:block;width:100%;height:auto;border-radius:12px">' : '<div style="background:'.e(self::accent()).';border-radius:12px;height:140px"></div>';
                $txt = '<div style="'.$font.'font-weight:800;font-size:'.$fs(20).';margin:0 0 8px">'.self::t((string) $p['title'], $v).'</div><div style="'.$font.'font-size:15px;line-height:1.55;opacity:.85;margin:0 0 14px">'.self::tl((string) $p['body'], $v).'</div>'.self::btn(self::t((string) $p['btn'], $v), self::u((string) $p['url'], $v), $st, 'left');
                $cells = [['w' => '45%', 'h' => $img], ['w' => '55%', 'h' => $txt]];
                if ($p['side'] === 'right') $cells = array_reverse($cells);

                return '<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>'
                    .'<td class="stack" width="'.$cells[0]['w'].'" valign="top" style="padding-right:10px">'.$cells[0]['h'].'</td>'
                    .'<td class="stack" width="'.$cells[1]['w'].'" valign="top" style="padding-left:10px">'.$cells[1]['h'].'</td></tr></table>';
            case 'product':
                $pr = $p['product_id'] ? Product::find((int) $p['product_id']) : null;
                if (! $pr) return '<div style="'.$font.'font-size:13px;opacity:.5;text-align:center;padding:24px;border:1px dashed currentColor;border-radius:10px">Pick a product</div>';

                return '<div style="'.$font.'max-width:320px;margin:'.($al === 'center' ? '0 auto' : '0').'">'.self::productCard($pr, $v, $st, $p['price'] !== '0', self::t((string) $p['btn'], $v)).'</div>';
            case 'product_grid':
                $q = Catalog::query();
                match ($p['source']) {
                    'new' => $q->orderByDesc('products.id'),
                    'sale' => $q->whereColumn('compare_price', '>', 'price')->orderByDesc('products.id'),
                    'category' => $q->where('category_id', (int) $p['category'])->orderByDesc('featured')->orderByDesc('sales'),
                    default => $q->orderByDesc('featured')->orderByDesc('sales')->orderByDesc('products.id'),
                };
                $items = $q->limit(max(1, min(12, (int) $p['count'])))->get();
                $cols = (int) $p['cols'] === 3 ? 3 : 2;
                $h = trim((string) $p['title']) !== '' ? '<div style="'.$font.'font-weight:800;font-size:'.$fs(22).';margin:0 0 16px">'.self::t((string) $p['title'], $v).'</div>' : '';
                if ($items->isEmpty()) return $h.'<div style="'.$font.'font-size:13px;opacity:.5">No matching products yet.</div>';
                $w = (int) floor(100 / $cols);
                $h .= '<table width="100%" cellpadding="0" cellspacing="0" role="presentation">';
                foreach ($items->chunk($cols) as $row) {
                    $h .= '<tr>';
                    foreach ($row as $pr) $h .= '<td class="stack" width="'.$w.'%" valign="top" style="padding:0 8px 18px 0;'.$font.'">'.self::productCard($pr, $v, $st, true, self::t((string) $p['btn'], $v)).'</td>';
                    for ($i = $row->count(); $i < $cols; $i++) $h .= '<td class="stack" width="'.$w.'%"></td>';
                    $h .= '</tr>';
                }

                return $h.'</table>';
            case 'cart':
                $h = trim((string) $p['title']) !== '' ? '<div style="'.$font.'font-weight:800;font-size:'.$fs(20).';margin:0 0 8px">'.self::t((string) $p['title'], $v).'</div>' : '';
                $btn = '<a href="'.e($v['cart_url']).'" style="display:inline-block;padding:13px 26px;background:'.e($st['btn_bg'] ?: self::accent()).';color:'.e($st['btn_color'] ?: Art::ink($st['btn_bg'] ?: self::accent())).';font:700 15px Arial,sans-serif;text-decoration:none;border-radius:'.(int) ($st['radius'] !== '' ? $st['radius'] : 10).'px">'.self::t((string) $p['btn'], $v).'</a>';

                return $h.'<div style="'.$font.'">'.($v['cart_items'] ?: '<div style="font-size:13px;opacity:.55;padding:10px 0">Your cart items appear here.</div>').'</div><div style="margin-top:14px">'.$btn.'</div>';
            case 'order':
                $h = '<div style="'.$font.'font-weight:800;font-size:'.$fs(20).';margin:0 0 10px">'.self::t((string) $p['title'], $v).'</div>'
                    .'<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="'.$font.'font-size:15px"><tr><td style="padding:8px 0;border-bottom:1px solid rgba(128,128,128,.25);opacity:.7">Order</td><td align="right" style="padding:8px 0;border-bottom:1px solid rgba(128,128,128,.25)"><b>'.self::t('{order_number}', $v).'</b></td></tr>'
                    .'<tr><td style="padding:8px 0;opacity:.7">Total</td><td align="right" style="padding:8px 0"><b>'.self::t('{order_total}', $v).'</b></td></tr></table><div style="margin-top:14px">'
                    .self::btn(self::t((string) $p['btn'], $v), self::u('{order_url}', $v), $st, 'left').'</div>';

                return $h;
            case 'coupon':
                $code = self::t((string) $p['code'], $v);
                $bd = e($st['btn_bg'] ?: self::accent());

                return '<div style="'.$font.'text-align:center;border:2px dashed '.$bd.';border-radius:14px;padding:20px"><div style="font-size:12px;letter-spacing:2px;text-transform:uppercase;opacity:.65">'.self::t((string) $p['label'], $v).'</div>'
                    .'<div style="font:800 '.$fs(28).' \'Courier New\',monospace;letter-spacing:4px;margin:8px 0">'.($code !== '' ? $code : '—').'</div>'
                    .(trim((string) $p['note']) !== '' ? '<div style="font-size:13px;opacity:.65">'.self::t((string) $p['note'], $v).'</div>' : '').'</div>';
            case 'quote':
                return '<div style="'.$font.'border-left:4px solid '.e(self::accent()).';padding:4px 0 4px 18px"><div style="font-size:'.$fs(18).';line-height:1.5;font-style:italic">“'.self::tl((string) $p['quote'], $v).'”</div><div style="font-size:13px;opacity:.65;margin-top:8px">— '.self::t((string) $p['name'], $v).'</div></div>';
            case 'social':
                $links = [];
                foreach (preg_split('/\R/', trim((string) $p['items'])) as $line) {
                    if (! str_contains($line, '|')) continue;
                    [$n, $u] = array_map('trim', explode('|', $line, 2));
                    if ($n !== '' && $u !== '') $links[] = '<a href="'.self::u($u, $v).'" style="color:inherit;text-decoration:underline;margin:0 8px;'.$font.'font-size:14px">'.e($n).'</a>';
                }

                return implode('', $links);
            case 'divider':
                return '<div style="border-top:'.max(1, min(10, (int) $p['thickness'])).'px solid '.e($st['color'] ?: 'rgba(128,128,128,.35)').';font-size:0;line-height:0">&nbsp;</div>';
            case 'spacer':
                $hh = max(4, min(200, (int) $p['h']));

                return '<div style="height:'.$hh.'px;line-height:'.$hh.'px;font-size:0">&nbsp;</div>';
            case 'html':
                return (string) $p['code']; // trusted admin markup (documented)
            case 'footer':
                $h = '<div style="'.$font.'font-size:12px;line-height:1.6;opacity:.7">'.self::tl((string) $p['text'], $v);
                if ($p['unsub'] !== '0') $h .= '<br><a href="'.e($v['unsubscribe_url']).'" style="color:inherit;text-decoration:underline">Unsubscribe</a>';
                $addr = trim((string) setting('store_address'));

                return $h.($addr !== '' ? '<br>'.e($addr) : '').'</div>';
        }

        return '';
    }

    /**
     * Render blocks to a complete email document.
     * $opts: editor (bool – adds data-bid hooks for the builder preview), subject, preheader, footer (bool – append unsubscribe footer when no footer block).
     */
    public static function render(array $blocks, array $vars = [], array $opts = []): string
    {
        $reg = self::registry();
        $rows = '';
        $hasFooter = false;
        foreach ($blocks as $b) {
            $type = $b['type'] ?? '';
            if (! isset($reg[$type])) continue;
            if ($type === 'footer') $hasFooter = true;
            $p = (array) ($b['props'] ?? []) + $reg[$type][3];
            $p = array_map(fn ($x) => (string) $x, $p);
            $st = (array) ($b['style'] ?? []) + ['bg' => '', 'color' => '', 'pad' => 'md', 'align' => 'left', 'size' => '', 'btn_bg' => '', 'btn_color' => '', 'radius' => ''];
            $in = self::inner($type, $p, $st, $vars);
            if ($in === '') continue;
            $pad = self::PAD[$st['pad']] ?? 24;
            if ($type === 'image' && $p['full'] === '1' && $st['pad'] !== 'none' && ! isset($b['style']['pad'])) $pad = 0;
            if ($type === 'image' && $p['full'] === '1') $pad = 0;
            $bg = preg_match('/^#[0-9a-f]{6}$/i', (string) $st['bg']) ? $st['bg'] : '';
            $col = preg_match('/^#[0-9a-f]{6}$/i', (string) $st['color']) ? $st['color'] : '';
            $css = 'background:'.($bg ?: '#ffffff').';color:'.($col ?: ($bg ? Art::ink($bg) : '#1a1a1f')).';padding:'.$pad.'px '.($pad === 0 ? 0 : max(16, $pad)).'px;text-align:'.$st['align'].';';
            $rows .= '<tr><td'.(! empty($opts['editor']) ? ' data-bid="'.e((string) ($b['id'] ?? '')).'"' : '').' style="'.$css.'">'.$in.'</td></tr>';
        }
        if (! empty($opts['footer']) && ! $hasFooter) {
            $rows .= '<tr><td style="background:#ffffff;color:#6b6b74;padding:18px 24px;font:12px Arial,sans-serif;text-align:center">You are receiving this email from '.e((string) setting('store_name')).'. <a href="'.e($vars['unsubscribe_url'] ?? url('/')).'" style="color:#6b6b74">Unsubscribe</a></td></tr>';
        }
        if ($rows === '') $rows = '<tr><td style="background:#ffffff;color:#8a8a93;padding:80px 20px;text-align:center;font:14px Arial,sans-serif">Drag a block from the left to start designing.</td></tr>';
        $pre = ! empty($opts['preheader']) ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">'.self::t((string) $opts['preheader'], $vars).str_repeat('&nbsp;&zwnj;', 30).'</div>' : '';

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="x-apple-disable-message-reformatting">'
            .'<title>'.e((string) ($opts['subject'] ?? '')).'</title><style>body{margin:0;padding:0;-webkit-text-size-adjust:100%}img{border:0;max-width:100%}a{color:inherit}@media(max-width:620px){.stack{display:block!important;width:100%!important;padding:0 0 16px!important}.wrap{width:100%!important}}</style></head>'
            .'<body style="margin:0;background:#eceff3">'.$pre.'<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#eceff3"><tr><td align="center" style="padding:20px 8px">'
            .'<table class="wrap" width="600" cellpadding="0" cellspacing="0" role="presentation" style="width:600px;max-width:100%;border-radius:14px;overflow:hidden;background:#fff">'.$rows.'</table></td></tr></table></body></html>';
    }

    /** Plain-text alternative. */
    public static function toText(array $blocks, array $vars = []): string
    {
        $map = [];
        foreach ($vars as $k => $val) if (! in_array($k, self::HTML_TAGS, true)) $map['{'.$k.'}'] = (string) $val;
        $out = [];
        foreach ($blocks as $b) {
            $p = (array) ($b['props'] ?? []);
            foreach (['title', 'text', 'body', 'quote', 'code', 'label', 'btn'] as $k) {
                if (! empty($p[$k]) && is_string($p[$k])) $out[] = trim(strip_tags(strtr($p[$k], $map)));
            }
            if (! empty($p['url']) && is_string($p['url']) && ! empty($p['btn'] ?? $p['text'] ?? '')) $out[] = strtr($p['url'], $map);
        }

        return trim(implode("\n\n", array_filter($out)));
    }

    /* ------------------------------------------------------------ starter templates */

    private static function b(string $type, array $props = [], array $style = []): array
    {
        return ['type' => $type, 'props' => $props, 'style' => $style];
    }

    public static function templates(): array
    {
        $dark = ['bg' => '#0b0b0d', 'color' => '#f3f1ec'];
        $foot = self::b('footer', [], ['bg' => '#f4f5f7', 'color' => '#6b6b74', 'align' => 'center']);

        return [
            'blank' => ['label' => 'Blank', 'desc' => 'Start from an empty canvas.', 'subject' => '', 'blocks' => [self::b('header', [], ['align' => 'center']), self::b('text'), $foot]],
            'welcome' => ['label' => 'Welcome', 'desc' => 'Greet new customers with a coupon.', 'subject' => 'Welcome to {store}, {first_name}!', 'blocks' => [
                self::b('header', [], $dark + ['align' => 'center']),
                self::b('hero', ['eyebrow' => 'Welcome', 'title' => 'Great to have you, {first_name}', 'text' => 'Thanks for joining {store}. Here is a little something to get you started.', 'btn' => 'Start shopping', 'url' => '{shop_url}'], $dark + ['pad' => 'lg']),
                self::b('coupon', ['label' => 'Your welcome code', 'code' => '{coupon}', 'note' => 'Use it on your first order.']),
                self::b('product_grid', ['title' => 'Popular right now', 'source' => 'featured', 'count' => '4', 'cols' => '2', 'btn' => 'Shop'], []),
                $foot]],
            'order' => ['label' => 'Order confirmation', 'desc' => 'Thank customers and link to their order.', 'subject' => 'Thanks for your order {order_number}', 'blocks' => [
                self::b('header', [], $dark + ['align' => 'center']),
                self::b('heading', ['text' => 'Thank you, {first_name}!', 'level' => '1'], []),
                self::b('text', ['body' => 'We have received your order and are getting it ready. You can follow every step from your order page.']),
                self::b('order', ['title' => 'Order {order_number}', 'btn' => 'View your order'], ['bg' => '#f4f5f7']),
                self::b('product_grid', ['title' => 'You may also like', 'source' => 'featured', 'count' => '2', 'cols' => '2', 'btn' => 'View'], []),
                $foot]],
            'cart' => ['label' => 'Abandoned cart', 'desc' => 'Bring shoppers back to their cart.', 'subject' => 'You left something in your cart', 'blocks' => [
                self::b('header', [], $dark + ['align' => 'center']),
                self::b('heading', ['text' => 'Still thinking it over, {first_name}?', 'level' => '1'], []),
                self::b('text', ['body' => 'Your items are saved, but popular products sell out fast.']),
                self::b('cart', ['title' => 'Your cart', 'btn' => 'Return to your cart'], ['bg' => '#f4f5f7']),
                self::b('coupon', ['label' => 'A little nudge', 'code' => '{coupon}', 'note' => 'Applies at checkout.']),
                $foot]],
            'sale' => ['label' => 'Sale announcement', 'desc' => 'Big headline + sale products.', 'subject' => 'The sale is on at {store}', 'blocks' => [
                self::b('header', [], $dark + ['align' => 'center']),
                self::b('hero', ['eyebrow' => 'Limited time', 'title' => 'Up to 50% off', 'text' => 'Our biggest sale of the season. Grab your favourites before they are gone.', 'btn' => 'Shop the sale', 'url' => '{shop_url}'], ['bg' => '#c6ff3d', 'color' => '#0b0b0d', 'btn_bg' => '#0b0b0d', 'btn_color' => '#ffffff', 'align' => 'center', 'pad' => 'lg']),
                self::b('product_grid', ['title' => 'On sale now', 'source' => 'sale', 'count' => '6', 'cols' => '3', 'btn' => 'Buy'], []),
                self::b('button', ['text' => 'See everything on sale', 'url' => '{shop_url}'], ['align' => 'center']),
                $foot]],
            'digest' => ['label' => 'Newsletter digest', 'desc' => 'Story, picks and a testimonial.', 'subject' => 'This week at {store}', 'blocks' => [
                self::b('header', [], ['align' => 'center']),
                self::b('hero', ['eyebrow' => 'This week', 'title' => 'What is new at {store}', 'text' => 'Fresh arrivals, staff picks and a few things we love.', 'btn' => 'Browse the store', 'url' => '{shop_url}'], ['align' => 'center', 'pad' => 'lg']),
                self::b('heading', ['text' => 'New arrivals', 'level' => '2'], []),
                self::b('product_grid', ['title' => '', 'source' => 'new', 'count' => '4', 'cols' => '2', 'btn' => 'Shop'], []),
                self::b('columns', ['title' => 'Our story', 'body' => 'Tell customers about a new collection, a behind-the-scenes moment or a helpful guide.', 'btn' => 'Read more', 'url' => '{shop_url}', 'side' => 'left'], ['bg' => '#f4f5f7']),
                self::b('quote', ['quote' => 'Fast, easy and exactly as described.', 'name' => 'Sam R.'], []),
                self::b('social', [], ['align' => 'center']),
                $foot]],
        ];
    }
}
