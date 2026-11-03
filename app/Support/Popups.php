<?php

namespace App\Support;

use App\Models\Popup;
use Illuminate\Http\Request;

/** Selects and renders the popups that match the current page, audience and schedule. */
final class Popups
{
    public static function matches(Popup $p, string $path, bool $loggedIn): bool
    {
        if ($p->status !== 'active') return false;
        if ($p->starts_at && $p->starts_at->isFuture()) return false;
        if ($p->ends_at && $p->ends_at->isPast()) return false;
        if ($p->audience === 'guests' && $loggedIn) return false;
        if ($p->audience === 'customers' && ! $loggedIn) return false;
        $path = '/'.ltrim($path, '/');

        return match ($p->pages) {
            'home' => $path === '/',
            'products' => str_starts_with($path, '/product/') || str_starts_with($path, '/shop/'),
            'cart' => str_starts_with($path, '/cart') || str_starts_with($path, '/checkout'),
            'custom' => self::custom((string) $p->page_rules, $path),
            default => true,
        };
    }

    private static function custom(string $rules, string $path): bool
    {
        foreach (preg_split('/\R/', $rules) as $r) {
            $r = trim($r);
            if ($r === '') continue;
            $re = '#^'.str_replace('\*', '.*', preg_quote('/'.ltrim($r, '/'), '#')).'$#i';
            if (preg_match($re, $path)) return true;
        }

        return false;
    }

    public static function render(Request $r): string
    {
        if (setting('popups_on') !== '1' || str_starts_with($r->path(), 'admin') || str_starts_with($r->path(), 'checkout')) return '';
        try {
            $rows = Popup::where('status', 'active')->get();
        } catch (\Throwable) {
            return '';
        }
        $h = '';
        foreach ($rows as $p) {
            if (! self::matches($p, $r->path(), auth()->check())) continue;
            $h .= self::html($p);
        }

        return $h;
    }

    public static function html(Popup $p, bool $preview = false): string
    {
        $cfg = ['id' => $p->id, 't' => $p->trigger, 'v' => (int) $p->trigger_value, 'f' => $p->frequency, 'd' => (int) $p->frequency_days, 'dev' => $p->devices, 'preview' => $preview];
        $style = '--pbg:'.$p->bg.';--pfg:'.$p->fg.';--pbtn:'.$p->btn_bg.';--pbtnfg:'.$p->btn_fg.';--pov:'.($p->overlay / 100).';--pw:'.(int) $p->width.'px;--pr:'.(int) $p->radius.'px';
        $body = '';
        if ($p->image) $body .= '<div class="pop-img"><img src="'.e(upload_url($p->image)).'" alt="" loading="lazy"></div>';
        $body .= '<div class="pop-body">';
        if ($p->title) $body .= '<h3 class="pop-h">'.e($p->title).'</h3>';
        if ($p->body) $body .= '<div class="pop-text">'.$p->body.'</div>';
        if ($p->coupon_code && ! $p->capture_email) $body .= '<button type="button" class="pop-code" data-copy="'.e($p->coupon_code).'" title="Copy code">'.e($p->coupon_code).'<small>tap to copy</small></button>';
        if ($p->capture_email) {
            $body .= '<form class="pop-form" data-popup-subscribe="'.$p->id.'"><input type="text" name="website" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true"><input type="email" name="email" placeholder="Your email" required aria-label="Email"><button class="pop-btn">'.e($p->button_text ?: 'Subscribe').'</button></form>'
                .'<div class="pop-ok" hidden>'.e($p->success_text ?: 'Thanks — you are in!').($p->coupon_code ? ' <button type="button" class="pop-code" data-copy="'.e($p->coupon_code).'">'.e($p->coupon_code).'</button>' : '').'</div>';
        } elseif ($p->button_text) {
            $body .= '<a class="pop-btn" data-popup-click href="'.e($p->button_url ?: '#').'">'.e($p->button_text).'</a>';
        }
        $body .= '</div>';

        return '<div class="pop pop-'.e($p->layout).' pos-'.e($p->position).'" data-popup="'.e(json_encode($cfg)).'" style="'.e($style).'" hidden>'
            .'<div class="pop-scrim"></div><div class="pop-card" role="dialog" aria-modal="'.($p->layout === 'bar' ? 'false' : 'true').'" aria-label="'.e($p->title ?: $p->name).'">'
            .'<button type="button" class="pop-x" aria-label="Close">✕</button>'.$body.'</div></div>'."\n";
    }
}
