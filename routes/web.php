<?php

use App\Http\Controllers\Account;
use App\Http\Controllers\Admin as A;
use App\Http\Controllers\CartController;
use App\Http\Controllers\Checkout;
use App\Http\Controllers\PopupController;
use App\Http\Controllers\Shop;
use App\Http\Controllers\SocialController;
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
Route::get('/llms.txt', [Shop::class, 'llms']);

Route::get('/cart', [CartController::class, 'show']);
Route::get('/cart/mini', [CartController::class, 'mini']);
Route::post('/cart/add', [CartController::class, 'add']);
Route::post('/cart/update', [CartController::class, 'update']);
Route::post('/cart/remove', [CartController::class, 'remove']);
Route::post('/cart/coupon', [CartController::class, 'coupon']);
Route::get('/cart/recover/{token}', [CartController::class, 'recover'])->where('token', '[A-Za-z0-9]{20,64}');
Route::post('/checkout/capture', [Checkout::class, 'capture'])->middleware('throttle:30,1');

Route::post('/popup/track', [PopupController::class, 'track'])->middleware('throttle:60,1');
Route::post('/popup/subscribe', [PopupController::class, 'subscribe'])->middleware('throttle:10,1');
Route::get('/unsubscribe/{token}', [PopupController::class, 'unsubscribe'])->where('token', '[A-Za-z0-9]{1,64}');

Route::get('/auth/{provider}', [SocialController::class, 'redirect'])->where('provider', 'google|facebook|github|discord');
Route::get('/auth/{provider}/callback', [SocialController::class, 'callback'])->where('provider', 'google|facebook|github|discord');

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
    // No separate admin login: staff sign in at /login and open the panel from their account menu.
    Route::get('login', fn () => redirect('/login'));

    Route::middleware('staff')->group(function () {
        Route::get('/', [A\Dashboard::class, 'index']);

        Route::get('products', [A\Products::class, 'index']);
        Route::get('products/new', [A\Products::class, 'form']);
        Route::get('products/{id}', [A\Products::class, 'form'])->whereNumber('id');
        Route::post('products/save', [A\Products::class, 'save']);
        Route::post('products/bulk', [A\Products::class, 'bulk']);
        Route::post('products/{id}/quick', [A\Products::class, 'quick'])->whereNumber('id');
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
        Route::post('customers/save', [A\Customers::class, 'store']);
        Route::post('customers/bulk', [A\Customers::class, 'bulk']);
        Route::get('coupons', [A\Coupons::class, 'index']);
        Route::post('coupons/save', [A\Coupons::class, 'save']);
        Route::post('coupons/bulk', [A\Coupons::class, 'bulk']);
        Route::post('coupons/{id}/delete', [A\Coupons::class, 'delete'])->whereNumber('id');

        Route::get('pages', [A\Pages::class, 'index']);
        Route::get('pages/new', [A\Pages::class, 'form']);
        Route::get('pages/{id}', [A\Pages::class, 'form'])->whereNumber('id');
        Route::post('pages/save', [A\Pages::class, 'save']);
        Route::post('pages/{id}/delete', [A\Pages::class, 'delete'])->whereNumber('id');

        Route::get('popups', [A\Popups::class, 'index']);
        Route::get('popups/new', [A\Popups::class, 'form']);
        Route::get('popups/{id}', [A\Popups::class, 'form'])->whereNumber('id');
        Route::post('popups/save', [A\Popups::class, 'save']);
        Route::post('popups/preview', [A\Popups::class, 'preview']);
        Route::post('popups/{id}/toggle', [A\Popups::class, 'toggle'])->whereNumber('id');
        Route::post('popups/{id}/duplicate', [A\Popups::class, 'duplicate'])->whereNumber('id');
        Route::post('popups/{id}/delete', [A\Popups::class, 'delete'])->whereNumber('id');

        Route::get('builder/home', [A\Builder::class, 'home']);
        Route::get('builder/page/{id}', [A\Builder::class, 'page'])->whereNumber('id');
        Route::post('builder/save', [A\Builder::class, 'save']);
        Route::post('builder/preview', [A\Builder::class, 'preview']);

        Route::get('automations', [A\Growth::class, 'automations']);
        Route::post('automations/settings', [A\Growth::class, 'settings']);
        Route::post('automations/run', [A\Growth::class, 'runNow']);
        Route::post('automations/{id}', [A\Growth::class, 'automationSave'])->whereNumber('id');
        Route::post('automations/{id}/toggle', [A\Growth::class, 'automationToggle'])->whereNumber('id');
        Route::post('automations/{id}/test', [A\Growth::class, 'automationTest'])->whereNumber('id');
        Route::get('carts', [A\Growth::class, 'carts']);
        Route::post('carts/{id}', [A\Growth::class, 'cartAction'])->whereNumber('id');
        Route::get('subscribers', [A\Growth::class, 'subscribers']);
        Route::get('subscribers/export', [A\Growth::class, 'export']);
        Route::post('subscribers/add', [A\Growth::class, 'subscriberAdd']);
        Route::post('subscribers/bulk', [A\Growth::class, 'subscriberBulk']);

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
