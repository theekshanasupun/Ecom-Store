<?php
use App\Core\Router as R;
use App\Controllers as C;
use App\Controllers\Admin as A;

// ---- Storefront ----------------------------------------------------------------
R::get('/', [C\Shop::class, 'home']);
R::get('/shop/{slug}', [C\Shop::class, 'category']);
R::get('/product/{slug}', [C\Shop::class, 'product']);
R::get('/search', [C\Shop::class, 'search']);
R::get('/search.json', [C\Shop::class, 'searchJson']);
R::get('/p/{slug}', [C\Shop::class, 'page']);
R::get('/sitemap.xml', [C\Shop::class, 'sitemap']);
R::get('/robots.txt', [C\Shop::class, 'robots']);

R::get('/cart', [C\CartController::class, 'show']);
R::get('/cart/mini', [C\CartController::class, 'mini']);
R::post('/cart/add', [C\CartController::class, 'add']);
R::post('/cart/update', [C\CartController::class, 'update']);
R::post('/cart/remove', [C\CartController::class, 'remove']);
R::post('/cart/coupon', [C\CartController::class, 'coupon']);

R::get('/checkout', [C\Checkout::class, 'show']);
R::post('/checkout', [C\Checkout::class, 'place']);
R::get('/order/{number}', [C\Checkout::class, 'order']);
R::post('/order/{number}/pay', [C\Checkout::class, 'repay']);
R::get('/pay/stripe/return', [C\Checkout::class, 'stripeReturn']);
R::post('/pay/stripe/webhook', [C\Checkout::class, 'stripeWebhook'], ['nocsrf' => true]);
R::get('/pay/paypal/return', [C\Checkout::class, 'paypalReturn']);
R::get('/pay/cancel', [C\Checkout::class, 'cancel']);
R::get('/download/{id}', [C\Checkout::class, 'download']);

R::get('/track', [C\Account::class, 'track']);
R::post('/track', [C\Account::class, 'trackPost']);
R::get('/login', [C\Account::class, 'login']);
R::post('/login', [C\Account::class, 'loginPost']);
R::get('/register', [C\Account::class, 'register']);
R::post('/register', [C\Account::class, 'registerPost']);
R::post('/logout', [C\Account::class, 'logout']);
R::get('/account', [C\Account::class, 'index']);
R::post('/account/profile', [C\Account::class, 'profile']);

// ---- Admin ---------------------------------------------------------------------
R::get('/admin', [A\Dashboard::class, 'index']);
R::get('/admin/login', [A\AuthController::class, 'login']);
R::post('/admin/login', [A\AuthController::class, 'loginPost']);
R::post('/admin/logout', [A\AuthController::class, 'logout']);

R::get('/admin/products', [A\Products::class, 'index']);
R::get('/admin/products/new', [A\Products::class, 'form']);
R::get('/admin/products/{id}', [A\Products::class, 'form']);
R::post('/admin/products/save', [A\Products::class, 'save']);
R::post('/admin/products/{id}/delete', [A\Products::class, 'delete']);
R::post('/admin/products/{id}/keys', [A\Products::class, 'addKeys']);
R::post('/admin/products/{id}/keys/delete', [A\Products::class, 'deleteKeys']);

R::get('/admin/categories', [A\Categories::class, 'index']);
R::get('/admin/categories/new', [A\Categories::class, 'form']);
R::get('/admin/categories/{id}', [A\Categories::class, 'form']);
R::post('/admin/categories/save', [A\Categories::class, 'save']);
R::post('/admin/categories/{id}/delete', [A\Categories::class, 'delete']);

R::get('/admin/orders', [A\OrdersController::class, 'index']);
R::get('/admin/orders/{id}', [A\OrdersController::class, 'show']);
R::post('/admin/orders/{id}/status', [A\OrdersController::class, 'status']);
R::post('/admin/orders/{id}/paid', [A\OrdersController::class, 'markPaid']);
R::post('/admin/orders/{id}/deliver', [A\OrdersController::class, 'deliver']);
R::post('/admin/orders/{id}/note', [A\OrdersController::class, 'note']);
R::post('/admin/orders/{id}/resend', [A\OrdersController::class, 'resend']);

R::get('/admin/customers', [A\Customers::class, 'index']);
R::get('/admin/coupons', [A\Coupons::class, 'index']);
R::post('/admin/coupons/save', [A\Coupons::class, 'save']);
R::post('/admin/coupons/{id}/delete', [A\Coupons::class, 'delete']);

R::get('/admin/pages', [A\Pages::class, 'index']);
R::get('/admin/pages/new', [A\Pages::class, 'form']);
R::get('/admin/pages/{id}', [A\Pages::class, 'form']);
R::post('/admin/pages/save', [A\Pages::class, 'save']);
R::post('/admin/pages/{id}/delete', [A\Pages::class, 'delete']);

R::get('/admin/settings', [A\SettingsController::class, 'index']);
R::post('/admin/settings', [A\SettingsController::class, 'save']);
R::post('/admin/settings/test-mail', [A\SettingsController::class, 'testMail']);

R::get('/admin/users', [A\Users::class, 'index']);
R::post('/admin/users/save', [A\Users::class, 'save']);
R::post('/admin/users/{id}/delete', [A\Users::class, 'delete']);
R::get('/admin/profile', [A\Users::class, 'profile']);
R::post('/admin/profile', [A\Users::class, 'profileSave']);

R::get('/admin/system', [A\System::class, 'index']);
R::post('/admin/system/update', [A\System::class, 'update']);
R::post('/admin/system/backup', [A\System::class, 'backup']);
R::post('/admin/system/cache', [A\System::class, 'cache']);
R::get('/admin/system/backups/{file}', [A\System::class, 'download']);
