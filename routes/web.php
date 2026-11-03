<?php

use App\Http\Controllers\Account;
use App\Http\Controllers\Admin as A;
use App\Http\Controllers\CartController;
use App\Http\Controllers\Checkout;
use App\Http\Controllers\Shop;
use Illuminate\Support\Facades\Route;

// Web installer (file is deleted automatically after a successful install)
if (is_file(__DIR__.'/install.php')) {
    require __DIR__.'/install.php';
}

// ---- Storefront ----
Route::get('/', [Shop::class, 'home']);
Route::get('/shop/{slug}', [Shop::class, 'category']);
Route::get('/product/{slug}', [Shop::class, 'product']);
Route::get('/search', [Shop::class, 'search']);
Route::get('/search.json', [Shop::class, 'searchJson']);
Route::get('/p/{slug}', [Shop::class, 'page']);
Route::get('/sitemap.xml', [Shop::class, 'sitemap']);
Route::get('/robots.txt', [Shop::class, 'robots']);

Route::get('/cart', [CartController::class, 'show']);
Route::get('/cart/mini', [CartController::class, 'mini']);
Route::post('/cart/add', [CartController::class, 'add']);
Route::post('/cart/update', [CartController::class, 'update']);
Route::post('/cart/remove', [CartController::class, 'remove']);
Route::post('/cart/coupon', [CartController::class, 'coupon']);

Route::get('/checkout', [Checkout::class, 'show']);
Route::post('/checkout', [Checkout::class, 'place']);
Route::get('/order/{number}', [Checkout::class, 'order']);
Route::post('/order/{number}/pay', [Checkout::class, 'repay']);
Route::get('/pay/stripe/return', [Checkout::class, 'stripeReturn']);
Route::post('/pay/stripe/webhook', [Checkout::class, 'stripeWebhook']);
Route::get('/pay/paypal/return', [Checkout::class, 'paypalReturn']);
Route::get('/pay/cancel', [Checkout::class, 'cancel']);
Route::get('/download/{id}', [Checkout::class, 'download'])->whereNumber('id');

Route::get('/track', [Account::class, 'track']);
Route::post('/track', [Account::class, 'trackPost']);
Route::get('/login', [Account::class, 'login'])->name('login');
Route::post('/login', [Account::class, 'loginPost']);
Route::get('/register', [Account::class, 'register']);
Route::post('/register', [Account::class, 'registerPost']);
Route::post('/logout', [Account::class, 'logout']);
Route::middleware('auth')->group(function () {
    Route::get('/account', [Account::class, 'index']);
    Route::post('/account/profile', [Account::class, 'profile']);
});

// ---- Admin ----
Route::prefix('admin')->group(function () {
    Route::get('login', [A\AuthController::class, 'login']);
    Route::post('login', [A\AuthController::class, 'loginPost']);

    Route::middleware('staff')->group(function () {
        Route::post('logout', [A\AuthController::class, 'logout']);
        Route::get('/', [A\Dashboard::class, 'index']);

        Route::get('products', [A\Products::class, 'index']);
        Route::get('products/new', [A\Products::class, 'form']);
        Route::get('products/{id}', [A\Products::class, 'form'])->whereNumber('id');
        Route::post('products/save', [A\Products::class, 'save']);
        Route::post('products/{id}/delete', [A\Products::class, 'delete'])->whereNumber('id');
        Route::post('products/{id}/keys', [A\Products::class, 'addKeys'])->whereNumber('id');
        Route::post('products/{id}/keys/delete', [A\Products::class, 'deleteKeys'])->whereNumber('id');

        Route::get('categories', [A\Categories::class, 'index']);
        Route::get('categories/new', [A\Categories::class, 'form']);
        Route::get('categories/{id}', [A\Categories::class, 'form'])->whereNumber('id');
        Route::post('categories/save', [A\Categories::class, 'save']);
        Route::post('categories/{id}/delete', [A\Categories::class, 'delete'])->whereNumber('id');

        Route::get('orders', [A\OrdersController::class, 'index']);
        Route::get('orders/{id}', [A\OrdersController::class, 'show'])->whereNumber('id');
        Route::post('orders/{id}/status', [A\OrdersController::class, 'status'])->whereNumber('id');
        Route::post('orders/{id}/paid', [A\OrdersController::class, 'markPaid'])->whereNumber('id');
        Route::post('orders/{id}/deliver', [A\OrdersController::class, 'deliver'])->whereNumber('id');
        Route::post('orders/{id}/note', [A\OrdersController::class, 'note'])->whereNumber('id');
        Route::post('orders/{id}/resend', [A\OrdersController::class, 'resend'])->whereNumber('id');

        Route::get('customers', [A\Customers::class, 'index']);
        Route::get('coupons', [A\Coupons::class, 'index']);
        Route::post('coupons/save', [A\Coupons::class, 'save']);
        Route::post('coupons/{id}/delete', [A\Coupons::class, 'delete'])->whereNumber('id');

        Route::get('pages', [A\Pages::class, 'index']);
        Route::get('pages/new', [A\Pages::class, 'form']);
        Route::get('pages/{id}', [A\Pages::class, 'form'])->whereNumber('id');
        Route::post('pages/save', [A\Pages::class, 'save']);
        Route::post('pages/{id}/delete', [A\Pages::class, 'delete'])->whereNumber('id');

        Route::get('profile', [A\Users::class, 'profile']);
        Route::post('profile', [A\Users::class, 'profileSave']);

        Route::middleware('admin')->group(function () {
            Route::get('settings', [A\SettingsController::class, 'index']);
            Route::post('settings', [A\SettingsController::class, 'save']);
            Route::post('settings/test-mail', [A\SettingsController::class, 'testMail']);

            Route::get('users', [A\Users::class, 'index']);
            Route::post('users/save', [A\Users::class, 'save']);
            Route::post('users/{id}/delete', [A\Users::class, 'delete'])->whereNumber('id');

            Route::get('system', [A\System::class, 'index']);
            Route::post('system/update', [A\System::class, 'update']);
            Route::post('system/backup', [A\System::class, 'backup']);
            Route::post('system/cache', [A\System::class, 'cache']);
            Route::get('system/backups/{file}', [A\System::class, 'download']);
        });
    });
});
