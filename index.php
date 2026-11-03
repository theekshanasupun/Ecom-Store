<?php
/**
 * KIOSK – front controller.
 */
require __DIR__ . '/app/bootstrap.php';

use App\Core\Router;
use App\Core\Auth;

if (!installed()) {
    if (is_dir(__DIR__ . '/install')) {
        header('Location: ' . BASE_PATH . '/install/');
        exit;
    }
    http_response_code(503);
    exit('KIOSK is not installed and the installer folder is missing. Re-upload the /install folder.');
}

set_exception_handler(function (Throwable $e) {
    @file_put_contents(STORAGE . '/logs/app-error.log', '[' . date('c') . '] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n", FILE_APPEND);
    if (!headers_sent()) http_response_code(500);
    while (ob_get_level()) ob_end_clean();
    if (is_ajax()) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => cfg('debug') ? $e->getMessage() : 'Something went wrong.']);
        return;
    }
    try {
        App\Core\View::page('error', ['code' => 500, 'heading' => 'Something broke', 'message' => cfg('debug') ? $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : 'We logged the problem. Please try again in a moment.']);
    } catch (Throwable) {
        echo 'Something went wrong.';
    }
});

$path = Router::path();
$isAdminArea = str_starts_with($path, '/admin');
if (is_file(STORAGE . '/maintenance.flag') && (time() - (int) @file_get_contents(STORAGE . '/maintenance.flag')) < 300 && !$isAdminArea) {
    http_response_code(503);
    header('Retry-After: 60');
    exit('<h1 style="font-family:sans-serif">Updating…</h1><p style="font-family:sans-serif">Back in a minute.</p>');
}
if (setting('maintenance') === '1' && !$isAdminArea && !Auth::isStaff()) {
    http_response_code(503);
    header('Retry-After: 600');
    echo App\Core\View::partial('maintenance');
    exit;
}

require APP . '/routes.php';
Router::dispatch();
