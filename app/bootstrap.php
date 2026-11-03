<?php
/**
 * KIOSK bootstrap – shared by the storefront, admin and installer.
 */
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('KIOSK requires PHP 8.1 or newer. Your server runs PHP ' . PHP_VERSION);
}

define('ROOT', dirname(__DIR__));
define('APP', ROOT . '/app');
define('STORAGE', ROOT . '/storage');
define('UPLOADS', ROOT . '/uploads');
define('APP_VERSION', (string) require APP . '/version.php');

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = APP . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

// Base path (supports installs in a sub-folder)
$__sn = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$__base = rtrim(dirname($__sn), '/');
if (defined('IN_INSTALLER')) {
    $__base = rtrim(dirname($__base), '/');
}
define('BASE_PATH', $__base === '.' ? '' : $__base);
unset($__sn, $__base);

require APP . '/Core/helpers.php';

date_default_timezone_set((string) cfg('timezone', 'UTC'));
mb_internal_encoding('UTF-8');

if (cfg('debug', false)) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}
ini_set('log_errors', '1');
ini_set('error_log', STORAGE . '/logs/php-error.log');

App\Core\Session::start();
