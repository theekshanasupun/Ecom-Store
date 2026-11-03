<?php
namespace App\Core;

final class Router
{
    private static array $routes = [];

    public static function add(string $method, string $pattern, array $handler, array $opts = []): void
    {
        $regex = preg_replace_callback('#\{(\w+)\}#', function ($m) {
            return str_ends_with($m[1], 'id') ? '(?P<' . $m[1] . '>\d+)' : '(?P<' . $m[1] . '>[^/]+)';
        }, $pattern);
        self::$routes[] = [$method, '#^' . $regex . '/?$#u', $handler, $opts];
    }

    public static function get(string $p, array $h, array $o = []): void { self::add('GET', $p, $h, $o); }
    public static function post(string $p, array $h, array $o = []): void { self::add('POST', $p, $h, $o); }
    public static function any(string $p, array $h, array $o = []): void { self::add('GET', $p, $h, $o); self::add('POST', $p, $h, $o); }

    public static function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = rawurldecode($uri);
        if (BASE_PATH !== '' && str_starts_with($uri, BASE_PATH)) {
            $uri = substr($uri, strlen(BASE_PATH));
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, 10);
        }
        return '/' . trim($uri, '/');
    }

    public static function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $path = self::path();
        $allowedElsewhere = false;
        foreach (self::$routes as [$m, $regex, $handler, $opts]) {
            if (!preg_match($regex, $path, $mt)) {
                continue;
            }
            if ($m !== $method) {
                $allowedElsewhere = true;
                continue;
            }
            if ($method === 'POST' && empty($opts['nocsrf'])) {
                $tok = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
                if (!is_string($tok) || !hash_equals(csrf_token(), $tok)) {
                    abort(403, 'Your session expired. Please go back, refresh and try again.');
                }
            }
            $params = array_filter($mt, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $fn] = $handler;
            $obj = new $class();
            $obj->$fn($params);
            return;
        }
        if ($allowedElsewhere) {
            abort(405, 'Method not allowed');
        }
        abort(404);
    }
}
