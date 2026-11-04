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
Route::get('/search.json', [Shop::class, 'searchJson'])->middleware('throttle:90,1');
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
    Route::get('/account/loyalty', [\App\Http\Controllers\LoyaltyController::class, 'index']);
    Route::post('/account/loyalty/checkin', [\App\Http\Controllers\LoyaltyController::class, 'checkin'])->middleware('throttle:20,1');
    Route::post('/account/loyalty/redeem', [\App\Http\Controllers\LoyaltyController::class, 'redeem'])->middleware('throttle:20,1');
    Route::post('/account/loyalty/redemption/{id}/cancel', [\App\Http\Controllers\LoyaltyController::class, 'cancelRedemption'])->whereNumber('id');
    Route::post('/account/loyalty/play', [\App\Http\Controllers\LoyaltyController::class, 'play'])->middleware('throttle:20,1');
    Route::post('/account/loyalty/prefs', [\App\Http\Controllers\LoyaltyController::class, 'prefs']);
    Route::post('/account/loyalty/read', [\App\Http\Controllers\LoyaltyController::class, 'read']);
    Route::post('/account/loyalty/assist', [\App\Http\Controllers\LoyaltyController::class, 'assist'])->middleware('throttle:5,1');
});

Route::get('/api/loyalty/member', [\App\Http\Controllers\LoyaltyController::class, 'api'])->middleware('throttle:60,1');

// >>> v26 storefront routes (search, auth/OTP, newsletter, gift cards, offers, checkout) — each workstream adds below its own marker
// >>> v26:front-core
Route::post('/cart/add-many', [CartController::class, 'addMany']);
Route::post('/checkout/apply', [Checkout::class, 'apply'])->middleware('throttle:60,1');
Route::get('/gift-cards', [App\Http\Controllers\GiftCardController::class, 'index']);
Route::post('/gift-cards/add', [App\Http\Controllers\GiftCardController::class, 'add']);
Route::get('/gift-cards/balance', [App\Http\Controllers\GiftCardController::class, 'balance']);
Route::post('/gift-cards/balance', [App\Http\Controllers\GiftCardController::class, 'balancePost'])->middleware('throttle:12,1');
// >>> v26:front-auth
Route::post('/login/code', [Account::class, 'loginCode'])->middleware('throttle:12,1');
Route::get('/login/reset-code', function () { session()->forget(['otp_email', 'otp_name']); return redirect('/login?tab=code'); });
Route::post('/login/verify', [Account::class, 'loginVerify'])->middleware('throttle:20,1');
Route::post('/account/password', [Account::class, 'password'])->middleware('auth');
// >>> v26:front-search
// >>> v26:front-newsletter
Route::post('/newsletter/subscribe', [\App\Http\Controllers\NewsletterController::class, 'subscribe'])->middleware('throttle:10,1');
Route::post('/account/newsletter', [\App\Http\Controllers\NewsletterController::class, 'preference'])->middleware('auth');

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

        Route::get('carts', [A\Growth::class, 'carts']);
        Route::post('carts/{id}', [A\Growth::class, 'cartAction'])->whereNumber('id');
        Route::get('subscribers', [A\Growth::class, 'subscribers']);
        Route::get('subscribers/export', [A\Growth::class, 'export']);
        Route::post('subscribers/add', [A\Growth::class, 'subscriberAdd']);
        Route::post('subscribers/bulk', [A\Growth::class, 'subscriberBulk']);

        Route::get('profile', [A\Users::class, 'profile']);
        Route::post('profile', [A\Users::class, 'profileSave']);

        // >>> v26:admin-core
        Route::get('discounts', [A\Discounts::class, 'index']);
        Route::post('discounts/save', [A\Discounts::class, 'save']);
        Route::post('discounts/{id}/toggle', [A\Discounts::class, 'toggle'])->whereNumber('id');
        Route::post('discounts/{id}/duplicate', [A\Discounts::class, 'duplicate'])->whereNumber('id');
        Route::post('discounts/{id}/delete', [A\Discounts::class, 'delete'])->whereNumber('id');
        Route::get('offers', [A\Offers::class, 'index']);
        Route::post('offers/save', [A\Offers::class, 'save']);
        Route::post('offers/{id}/toggle', [A\Offers::class, 'toggle'])->whereNumber('id');
        Route::post('offers/{id}/duplicate', [A\Offers::class, 'duplicate'])->whereNumber('id');
        Route::post('offers/{id}/delete', [A\Offers::class, 'delete'])->whereNumber('id');
        Route::get('products/search.json', [A\Offers::class, 'products']);
        Route::get('gift-cards', [A\GiftCardsAdmin::class, 'index']);
        Route::post('gift-cards/issue', [A\GiftCardsAdmin::class, 'issue']);
        Route::post('gift-cards/settings', [A\GiftCardsAdmin::class, 'settings']);
        Route::get('gift-cards/export', [A\GiftCardsAdmin::class, 'export']);
        Route::get('gift-cards/{id}', [A\GiftCardsAdmin::class, 'show'])->whereNumber('id');
        Route::post('gift-cards/{id}/adjust', [A\GiftCardsAdmin::class, 'adjust'])->whereNumber('id');
        Route::post('gift-cards/{id}/status', [A\GiftCardsAdmin::class, 'status'])->whereNumber('id');
        Route::post('gift-cards/{id}/resend', [A\GiftCardsAdmin::class, 'resend'])->whereNumber('id');
        Route::get('credit', [A\Credit::class, 'index']);
        Route::post('credit/settings', [A\Credit::class, 'settings']);
        Route::post('credit/adjust', [A\Credit::class, 'adjust']);
        Route::get('credit/{id}', [A\Credit::class, 'show'])->whereNumber('id');
        // >>> v26:admin-email
        Route::get('automations', [A\Automations::class, 'index']);
        Route::get('automations/new', [A\Automations::class, 'form']);
        Route::post('automations/create', [A\Automations::class, 'saveCustom']);
        Route::post('automations/settings', [A\Automations::class, 'settings']);
        Route::post('automations/run', [A\Automations::class, 'runNow']);
        Route::get('automations/{id}/edit', [A\Automations::class, 'form'])->whereNumber('id');
        Route::get('automations/{id}/design', [A\Automations::class, 'design'])->whereNumber('id');
        Route::post('automations/{id}/design', [A\Automations::class, 'designSave'])->whereNumber('id');
        Route::post('automations/{id}/reset-design', [A\Automations::class, 'resetDesign'])->whereNumber('id');
        Route::post('automations/{id}/toggle', [A\Automations::class, 'toggle'])->whereNumber('id');
        Route::post('automations/{id}/test', [A\Automations::class, 'test'])->whereNumber('id');
        Route::post('automations/{id}/duplicate', [A\Automations::class, 'duplicate'])->whereNumber('id');
        Route::post('automations/{id}/delete', [A\Automations::class, 'delete'])->whereNumber('id');
        Route::post('automations/{id}', [A\Automations::class, 'save'])->whereNumber('id');
        Route::get('newsletters', [A\Newsletters::class, 'index']);
        Route::get('newsletters/new', [A\Newsletters::class, 'form']);
        Route::post('newsletters/save', [A\Newsletters::class, 'save']);
        Route::get('newsletters/{id}', [A\Newsletters::class, 'form'])->whereNumber('id');
        Route::get('newsletters/{id}/design', [A\Newsletters::class, 'design'])->whereNumber('id');
        Route::post('newsletters/{id}/design', [A\Newsletters::class, 'designSave'])->whereNumber('id');
        Route::post('newsletters/{id}/send', [A\Newsletters::class, 'send'])->whereNumber('id');
        Route::post('newsletters/{id}/batch', [A\Newsletters::class, 'batch'])->whereNumber('id');
        Route::post('newsletters/{id}/pause', [A\Newsletters::class, 'pause'])->whereNumber('id');
        Route::post('newsletters/{id}/duplicate', [A\Newsletters::class, 'duplicate'])->whereNumber('id');
        Route::post('newsletters/{id}/delete', [A\Newsletters::class, 'delete'])->whereNumber('id');
        Route::post('email/preview', [A\EmailTools::class, 'preview']);
        Route::post('email/test', [A\EmailTools::class, 'test']);
        Route::get('email/products.json', [A\EmailTools::class, 'products']);
        // >>> v26:admin-ux
        Route::get('orders/new', [A\OrdersController::class, 'create']);
        Route::post('orders/create', [A\OrdersController::class, 'store']);
        Route::get('orders/lookup', [A\OrdersController::class, 'lookup']);
        Route::post('orders/totals', [A\OrdersController::class, 'totals']);
        Route::get('orders/export', [A\OrdersController::class, 'export']);
        Route::post('orders/bulk', [A\OrdersController::class, 'bulk']);
        Route::post('orders/{id}/quick', [A\OrdersController::class, 'quick'])->whereNumber('id');
        Route::post('orders/{id}/shipping', [A\OrdersController::class, 'shipping'])->whereNumber('id');
        Route::post('orders/{id}/contact', [A\OrdersController::class, 'contact'])->whereNumber('id');
        Route::post('orders/{id}/resend-shipping', [A\OrdersController::class, 'resendShipping'])->whereNumber('id');
        Route::post('orders/{id}/refund-credit', [A\OrdersController::class, 'refundCredit'])->whereNumber('id');
        Route::get('orders/{id}/print', [A\OrdersController::class, 'print'])->whereNumber('id');
        Route::get('search.json', [A\Search::class, 'json']);
        Route::post('coupons/{id}/toggle', [A\Coupons::class, 'toggle'])->whereNumber('id');
        Route::post('coupons/{id}/duplicate', [A\Coupons::class, 'duplicate'])->whereNumber('id');
        // >>> v26:admin-builder
        Route::post('builder/template', [A\Builder::class, 'template']);
        Route::post('builder/template/delete', [A\Builder::class, 'templateDelete']);

        // >>> v27:loyalty
        Route::prefix('loyalty')->group(function () {
            $L = \App\Http\Controllers\Admin\LoyaltyAdmin::class;
            Route::get('/', [$L, 'overview']);
            Route::get('members', [$L, 'members']);
            Route::get('members/{id}', [$L, 'member'])->whereNumber('id');
            Route::post('members/{id}/{act}', [$L, 'memberAction'])->whereNumber('id')->where('act', 'adjust|credit|tier|status|reward|voucher|challenge|activity|profile');
            Route::get('referrals', [$L, 'referrals']);
            Route::post('referrals/{id}/{act}', [$L, 'referralAction'])->whereNumber('id')->where('act', 'approve|reject');
            Route::get('redemptions', [$L, 'redemptions']);
            Route::post('redemptions/{id}/{act}', [$L, 'redemptionAction'])->whereNumber('id')->where('act', 'approve|reject|cancel');
            Route::get('segments', [$L, 'segments']);
            Route::get('reports', [$L, 'reports']);
            Route::get('security', [$L, 'security']);
            Route::post('flags/{id}', [$L, 'flagAction'])->whereNumber('id');
        });
        Route::middleware('admin')->prefix('loyalty')->group(function () {
            $L = \App\Http\Controllers\Admin\LoyaltyAdmin::class;
            Route::get('settings', [$L, 'settings']);
            Route::post('settings', [$L, 'settingsSave']);
            Route::post('enrol', [$L, 'enrol']);
            Route::post('run', [$L, 'runNow']);
            Route::post('bulk', [$L, 'bulk']);
            Route::post('segments/save', [$L, 'segmentSave']);
            Route::post('segments/{id}/delete', [$L, 'segmentDelete'])->whereNumber('id');
            $ent = 'tiers|rewards|rules|campaigns|challenges|badges|competitions|workflows';
            Route::get('{e}', [$L, 'crud'])->where('e', $ent);
            Route::post('{e}/save', [$L, 'crudSave'])->where('e', $ent);
            Route::post('{e}/{id}/toggle', [$L, 'crudToggle'])->where('e', $ent)->whereNumber('id');
            Route::post('{e}/{id}/delete', [$L, 'crudDelete'])->where('e', $ent)->whereNumber('id');
        });
        Route::middleware('admin')->group(function () {
            Route::get('themes', [A\Themes::class, 'index']);
            Route::post('themes', [A\Themes::class, 'save']);
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
            Route::post('system/prune', [A\System::class, 'prune']);
            Route::get('system/backups/{file}', [A\System::class, 'download']);
        });
    });
});
