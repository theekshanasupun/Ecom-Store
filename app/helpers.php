<?php

use App\Support\Meta;
use App\Support\Settings;
use Illuminate\Support\Str;

if (! function_exists('installed')) {
    function installed(): bool
    {
        return is_file(storage_path('app/installed.lock')) && is_file(base_path('.env'));
    }
}

function setting(string $key, mixed $default = null): mixed
{
    return Settings::get($key, $default);
}

function currency_code(): string
{
    return (string) setting('currency', 'USD');
}

function money(int|float|string|null $cents, bool $short = false): string
{
    $cents = (int) $cents;
    $sym = (string) setting('currency_symbol', '$');
    $neg = $cents < 0;
    $cents = abs($cents);
    $dec = ($short && $cents % 100 === 0) ? 0 : 2;
    $out = $sym.number_format($cents / 100, $dec, '.', ',');

    return $neg ? '-'.$out : $out;
}

function to_cents(mixed $v): int
{
    return (int) round(((float) str_replace([',', ' '], '', (string) $v)) * 100);
}

function from_cents(int $c): string
{
    return number_format($c / 100, 2, '.', '');
}

function asset_v(string $path): string
{
    return asset($path).'?v='.config('kiosk.version');
}

function upload_url(?string $path): string
{
    return $path ? asset('uploads/'.ltrim($path, '/')) : '';
}

function partial(string $tpl, array $data = []): string
{
    return view(str_replace('/', '.', $tpl), $data)->render();
}

function title(string $t): void
{
    Meta::$data['title'] = $t;
}

function meta_desc(string $d): void
{
    Meta::$data['description'] = $d;
}

function flash(string $type, ?string $msg = null): void
{
    $f = session('_flashes', []);
    $f[] = ['type' => $type, 'msg' => $msg];
    session()->flash('_flashes', $f);
}

function flashes(): array
{
    return session('_flashes', []);
}

function keep_old(array $data): void
{
    unset($data['password'], $data['password_confirmation'], $data['_token']);
    session()->flashInput($data);
}

function str_limit(string $s, int $n = 120): string
{
    return Str::limit(trim(strip_tags($s)), $n);
}

function time_ago(mixed $date): string
{
    if (! $date) {
        return '—';
    }
    $t = strtotime((string) $date);
    $d = time() - $t;
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60).'m ago';
    if ($d < 86400) return floor($d / 3600).'h ago';
    if ($d < 86400 * 14) return floor($d / 86400).'d ago';

    return date('M j, Y', $t);
}

function fmt_date(mixed $date, bool $time = true): string
{
    if (! $date) return '—';

    return date($time ? 'M j, Y · H:i' : 'M j, Y', strtotime((string) $date));
}

function unique_slug(string $modelClass, string $base, ?int $ignoreId = null): string
{
    $slug = Str::slug($base) ?: 'item-'.Str::lower(Str::random(5));
    $try = $slug;
    $i = 1;
    while ($modelClass::where('slug', $try)->when($ignoreId, fn ($q) => $q->where('id', '<>', $ignoreId))->exists()) {
        $try = $slug.'-'.(++$i);
    }

    return $try;
}

function field_text(string $name, string $label, mixed $value = '', array $o = []): string
{
    $type = $o['type'] ?? 'text';
    $attr = '';
    foreach (['placeholder', 'required', 'min', 'max', 'step', 'maxlength', 'autocomplete', 'pattern'] as $a) {
        if (isset($o[$a])) $attr .= ' '.$a.($o[$a] === true ? '' : '="'.e($o[$a]).'"');
    }
    $hint = isset($o['hint']) ? '<small class="hint">'.$o['hint'].'</small>' : '';

    return '<label class="fld '.($o['class'] ?? '').'"><span>'.e($label).'</span><input type="'.$type.'" name="'.e($name).'" value="'.e($value).'"'.$attr.'>'.$hint.'</label>';
}

function field_area(string $name, string $label, mixed $value = '', array $o = []): string
{
    $hint = isset($o['hint']) ? '<small class="hint">'.$o['hint'].'</small>' : '';
    $ph = isset($o['placeholder']) ? ' placeholder="'.e($o['placeholder']).'"' : '';

    return '<label class="fld '.($o['class'] ?? '').'"><span>'.e($label).'</span><textarea name="'.e($name).'" rows="'.($o['rows'] ?? 4).'"'.$ph.'>'.e($value).'</textarea>'.$hint.'</label>';
}

function field_select(string $name, string $label, array $options, mixed $value = '', array $o = []): string
{
    $h = '<label class="fld '.($o['class'] ?? '').'"><span>'.e($label).'</span><select name="'.e($name).'">';
    foreach ($options as $k => $v) {
        $h .= '<option value="'.e($k).'"'.((string) $k === (string) $value ? ' selected' : '').'>'.e($v).'</option>';
    }
    $hint = isset($o['hint']) ? '<small class="hint">'.$o['hint'].'</small>' : '';

    return $h.'</select>'.$hint.'</label>';
}

function field_check(string $name, string $label, bool $checked = false, string $hint = ''): string
{
    return '<label class="chk"><input type="hidden" name="'.e($name).'" value="0"><input type="checkbox" name="'.e($name).'" value="1"'.($checked ? ' checked' : '').'><i></i><span>'.e($label).($hint ? ' <small class="hint">'.$hint.'</small>' : '').'</span></label>';
}

/** Render a view wrapped in the storefront (or admin) layout. */
function page(string $tpl, array $data = [], string $layout = 'layout')
{
    $content = new Illuminate\Support\HtmlString(view(str_replace('/', '.', $tpl), $data)->render());

    return response(view(str_replace('/', '.', $layout), ['content' => $content] + $data)->render());
}

function admin_page(string $tpl, array $data = [])
{
    return page('admin/'.$tpl, $data, 'admin/layout');
}
