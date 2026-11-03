<?php
use App\Core\Session;
use App\Core\Settings;

function e(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cfg(string $key, mixed $default = null): mixed
{
    static $c = null;
    if ($c === null || $c === []) {
        // re-check while empty so the installer picks up config.php right after writing it
        $f = STORAGE . '/config.php';
        $c = is_file($f) ? (array) require $f : [];
    }
    return $c[$key] ?? $default;
}

function installed(): bool
{
    return is_file(STORAGE . '/installed.lock') && is_file(STORAGE . '/config.php');
}

function url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/')) . '?v=' . APP_VERSION;
}

function upload_url(?string $path): string
{
    return $path ? url('uploads/' . ltrim($path, '/')) : '';
}

function site_url(): string
{
    $o = cfg('url');
    if ($o) {
        return rtrim((string) $o, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_PATH;
}

function abs_url(string $path = ''): string
{
    return site_url() . '/' . ltrim($path, '/');
}

function redirect(string $to, int $code = 302): never
{
    if (!preg_match('#^https?://#i', $to)) {
        $to = url($to);
    }
    header('Location: ' . $to, true, $code);
    exit;
}

function back(string $fallback = ''): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === explode(':', $host)[0]) {
        redirect($ref);
    }
    redirect($fallback);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function input(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function str_in(string $key, string $default = ''): string
{
    $v = input($key, $default);
    return is_string($v) ? trim($v) : $default;
}

function int_in(string $key, int $default = 0): int
{
    return (int) input($key, $default);
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function flash(string $type, ?string $msg = null): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $f;
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function keep_old(array $data): void
{
    unset($data['password'], $data['password_confirmation'], $data['_csrf']);
    $_SESSION['_old'] = $data;
}

function forget_old(): void
{
    unset($_SESSION['_old']);
}

function now(): string
{
    return date('Y-m-d H:i:s');
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
    $out = $sym . number_format($cents / 100, $dec, '.', ',');
    return $neg ? '-' . $out : $out;
}

function to_cents(mixed $v): int
{
    $v = str_replace([',', ' '], ['', ''], (string) $v);
    return (int) round(((float) $v) * 100);
}

function from_cents(int $c): string
{
    return number_format($c / 100, 2, '.', '');
}

function slugify(string $text): string
{
    $text = mb_strtolower(trim($text));
    $text = preg_replace('~[^\p{L}\p{N}]+~u', '-', $text);
    $text = trim((string) $text, '-');
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($t !== false && $t !== '') {
            $text = preg_replace('~[^a-z0-9-]+~', '', strtolower($t));
        }
    }
    return $text !== '' ? $text : 'item-' . substr(bin2hex(random_bytes(3)), 0, 6);
}

function unique_slug(string $table, string $base, ?int $ignoreId = null): string
{
    $slug = slugify($base);
    $i = 1;
    $try = $slug;
    while (true) {
        $row = App\Core\Db::one("SELECT id FROM {$table} WHERE slug = ?" . ($ignoreId ? ' AND id <> ' . (int) $ignoreId : ''), [$try]);
        if (!$row) {
            return $try;
        }
        $try = $slug . '-' . (++$i);
    }
}

function str_limit(string $s, int $n = 120): string
{
    $s = trim(strip_tags($s));
    return mb_strlen($s) > $n ? rtrim(mb_substr($s, 0, $n - 1)) . '…' : $s;
}

function time_ago(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $d = time() - strtotime($date);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . 'm ago';
    if ($d < 86400) return floor($d / 3600) . 'h ago';
    if ($d < 86400 * 14) return floor($d / 86400) . 'd ago';
    return date('M j, Y', strtotime($date));
}

function fmt_date(?string $date, bool $time = true): string
{
    if (!$date) return '—';
    return date($time ? 'M j, Y · H:i' : 'M j, Y', strtotime($date));
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function abort(int $code = 404, string $msg = ''): never
{
    http_response_code($code);
    if (is_ajax()) {
        json_out(['ok' => false, 'error' => $msg ?: 'Error ' . $code], $code);
    }
    $titles = [403 => 'Not allowed', 404 => 'Page not found', 500 => 'Something broke'];
    App\Core\View::page('error', ['code' => $code, 'heading' => $titles[$code] ?? 'Error', 'message' => $msg]);
    exit;
}

function auth_user(): ?array
{
    return App\Core\Auth::user();
}

function title(string $t): void
{
    App\Core\View::$meta['title'] = $t;
}

function meta_desc(string $d): void
{
    App\Core\View::$meta['description'] = $d;
}

function partial(string $tpl, array $data = []): string
{
    return App\Core\View::partial($tpl, $data);
}

function field_text(string $name, string $label, mixed $value = '', array $o = []): string
{
    $type = $o['type'] ?? 'text';
    $attr = '';
    foreach (['placeholder', 'required', 'min', 'max', 'step', 'maxlength', 'autocomplete', 'pattern'] as $a) {
        if (isset($o[$a])) $attr .= ' ' . $a . ($o[$a] === true ? '' : '="' . e($o[$a]) . '"');
    }
    $hint = isset($o['hint']) ? '<small class="hint">' . $o['hint'] . '</small>' : '';
    $cls = $o['class'] ?? '';
    return '<label class="fld ' . $cls . '"><span>' . e($label) . '</span><input type="' . $type . '" name="' . e($name) . '" value="' . e($value) . '"' . $attr . '>' . $hint . '</label>';
}

function field_area(string $name, string $label, mixed $value = '', array $o = []): string
{
    $rows = $o['rows'] ?? 4;
    $hint = isset($o['hint']) ? '<small class="hint">' . $o['hint'] . '</small>' : '';
    $ph = isset($o['placeholder']) ? ' placeholder="' . e($o['placeholder']) . '"' : '';
    $cls = $o['class'] ?? '';
    return '<label class="fld ' . $cls . '"><span>' . e($label) . '</span><textarea name="' . e($name) . '" rows="' . $rows . '"' . $ph . '>' . e($value) . '</textarea>' . $hint . '</label>';
}

function field_select(string $name, string $label, array $options, mixed $value = '', array $o = []): string
{
    $h = '<label class="fld ' . ($o['class'] ?? '') . '"><span>' . e($label) . '</span><select name="' . e($name) . '">';
    foreach ($options as $k => $v) {
        $h .= '<option value="' . e($k) . '"' . ((string) $k === (string) $value ? ' selected' : '') . '>' . e($v) . '</option>';
    }
    $hint = isset($o['hint']) ? '<small class="hint">' . $o['hint'] . '</small>' : '';
    return $h . '</select>' . $hint . '</label>';
}

function field_check(string $name, string $label, bool $checked = false, string $hint = ''): string
{
    return '<label class="chk"><input type="hidden" name="' . e($name) . '" value="0"><input type="checkbox" name="' . e($name) . '" value="1"' . ($checked ? ' checked' : '') . '><i></i><span>' . e($label) . ($hint ? ' <small class="hint">' . $hint . '</small>' : '') . '</span></label>';
}

function rrmdir(string $dir): bool
{
    if (!is_dir($dir)) {
        return true;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    return @rmdir($dir);
}
