# KIOSK — next-gen Laravel store

Toys, clothing, digital keys, accounts, downloads — **one cart, one checkout**. Built on **Laravel 13 / PHP 8.3** (Eloquent, Blade, migrations), MySQL/MariaDB or SQLite, with a web installer, a full admin panel and a one-click zip updater.

## Requirements
PHP **8.3+** with `pdo_mysql` or `pdo_sqlite`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `json` (+ `zip` for updates, `curl`/`gd` recommended). Install on a domain or sub-domain root.

## Install (CodeCanyon style)
1. Upload and unzip `site.zip` into your web root (e.g. `public_html`).
2. Make these writable by PHP: `storage`, `bootstrap/cache`, `database`, `public/uploads`, `.env`
   ```bash
   chmod -R 775 storage bootstrap/cache database public/uploads && chmod 664 .env
   ```
3. Open `https://yourdomain.com/` — the installer wizard starts automatically (requirements → database → store & admin → install).
4. When it finishes the installer files **delete themselves**. Sign in at `/login` with the admin account — the **Admin panel** link is in your account menu (there is no separate admin login page).

**Apache:** `mod_rewrite` + `AllowOverride All`. The root `.htaccess` routes everything through `/public`, so `.env`, `app/` and `vendor/` are never exposed.
**Nginx / VPS (recommended):** point the web root at `public/`:
```
root /var/www/kiosk/public;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
location ~ /\.(?!well-known) { deny all; }
location ^~ /uploads/ { location ~ \.php$ { deny all; } }
```
> Back up `.env` — its `APP_KEY` encrypts your stored digital keys.

## Selling digital goods
Product types: **Physical**, **Key/code** and **Account** (delivered automatically from an encrypted pool — paste one per line), **Downloadable file** (private, token-protected), **Service** (manual). Keys appear on the order page and in the email the moment payment is confirmed. If the pool runs dry the order waits and you can deliver after adding keys.

## Payments
Admin → Settings → Payments: Stripe Checkout, PayPal, bank transfer, cash on delivery. Stripe webhook: `/pay/stripe/webhook` (event `checkout.session.completed`). Test with sandbox credentials first.

## 2.7.5
- **Rewards UI redesign**: product pages show an animated earn card (points, money value, your tier multiplier, progress toward your next reward, sign-in prompt for guests, a hint when a price is too low to earn). The checkout Rewards bar is a collapsible panel with “+N pts” / “−amount” pills, earn card, next-reward progress, a redeem slider with 25% / 50% / Max chips and a cashback toggle.
- **Fix**: money formatting error in the browser (`str.indexOf is not a function`) that froze live prices and the **gift-card preview for amounts of 1,000 or more**; the preview now also shrinks long amounts and warns when the amount is outside the allowed range.
- **Fix**: checkout summary no longer overflows laptop screens (sidebar width capped, order total font reduced).

## 2.7.1
- Earning is now configured as **spend X → earn Y points** (default: spend 100, earn 1; Loyalty → Programme settings → Earning). Product/category rules use the same spend block.
- Product pages show **“You'll earn N points”** (based on the price actually paid — sale/discounted price, not the list price; updates with variant and quantity; guests see a sign-in prompt).
- Checkout has a **collapsible Rewards bar**: points you'll earn on the order plus redeem points/cashback for signed-in customers; guests see “Log in to redeem”.

## What's new in 2.7 — Loyalty & Rewards
A full loyalty ecosystem built on the existing accounts, catalogue, cart, checkout, orders, payments and admin. **Three separate wallets** with their own ledgers, rules and expiry: **Loyalty points**, **Cashback** and **Store credit** (the 2.6 wallet is untouched).
- **Customer area** (`/account/loyalty`): dashboard (points, lifetime/redeemed/pending/expired/expiring, cashback, store credit, tier + progress bar, promotions, badges, activity), digital membership card with QR + PNG download, wallet histories with filters, tiers, rewards marketplace (search/filter/sort, points + cash, VIP/limited-stock/limited-time rewards, voucher history & cancel), challenges, daily check-in streaks, spin-the-wheel, scratch & win, leaderboards, referrals (link, code, WhatsApp/social share, history, milestones), notification centre + preferences, terms/policies and help request.
- **Checkout**: redeem points (partial, with min/max/percent limits) and cashback next to gift cards and store credit; tier discount and free-delivery benefits apply automatically; VIP-only products and early access are enforced in the cart.
- **Admin → Loyalty & Rewards**: overview analytics, members (search/filter/profile/adjust/tier/suspend/bulk/export), reward catalogue, redemptions + approval queue, tiers, campaigns & earning rules (double/triple, stacking, priority, budgets), gamification (challenges, badges, competitions), referrals (approve/reject, fraud flags, ambassadors), workflows, segments & recommendations, 15 reports with CSV/Excel export, security & fraud log, audit trail and programme settings.
- **Ledger rules**: balances are never overwritten — every credit, debit, reversal, expiry and adjustment is a ledger row with a unique reference (idempotent). Points from purchases stay *pending* until the configured order status; cancellations/refunds reverse them (partial refunds proportionally); expiry is first-expiring-first-out.
- **Cron** (optional, a pseudo-cron also runs on traffic): `php artisan kiosk:loyalty` hourly — releases held points, expires points, reminders, birthdays, anniversaries, win-back, competitions, fraud scan.
- **API/webhooks**: signed outbound webhook and a token-protected read-only `GET /api/loyalty/member` (Programme settings → Security & API).
- **Tests**: `tests/Feature/LoyaltyTest.php` (16 tests: ledger, idempotency, expiry, refunds, redemption, tiers, campaigns, referrals). Run with `composer require --dev phpunit/phpunit mockery/mockery` then `vendor/bin/phpunit`.

## What's new in 2.6
- **Email builder & custom automations** (Admin → Email automations): drag-and-drop email builder with a live desktop/mobile preview (palette → drop at an exact position, product cards, coupon box, merge tags, undo/redo, test send, starter templates). Create **your own automations from any action** — order placed / paid / shipped / completed / cancelled / refunded, customer registered, newsletter signup, gift card issued, store credit added — with conditions (order total, product, department, first order, payment method), multi-step sequences with delays, optional coupon or store-credit rewards, and per-automation stats. The built-in flows can be switched to a designed email too.
- **Newsletters** (Admin → Newsletters): design with the same builder, pick a segment, send now or schedule; batched sending that works with or without cron. Footer signup form on the storefront.
- **Page builder shows your current layout**: the home page opens as editable blocks (hero, departments, trending, mix-it-all-up) instead of an empty canvas; the preview is the real store (announcement bar, header, footer) and refreshes live without flicker. Drag blocks from the palette to an exact spot, save layout templates, reset to the original.
- **Advanced product variants**: options as buttons, colour swatches or dropdowns; every combination can have its own price, compare-at price, stock, SKU and image; sold-out combinations are dimmed on the product page and prices update live.
- **Discounts** (Admin → Promotions → Discounts): automatic sitewide, department, product and quantity-tier price rules with schedules, audience targeting (members/guests) and badges. **Upsell, cross-sell & bundles**: recommendations on product pages, in the cart and as checkout order-bumps; bundles discount automatically when every item is in the cart.
- **Store credit** (wallet with ledger, refund-to-credit, spend at checkout) and **Gift cards** (sell on /gift-cards with live card preview, scheduled email delivery, designs, balance checker, issue/bulk-generate/adjust/void, activity log, CSV export). Both can pay (part of) an order; amounts are restored automatically if an order is cancelled.
- **Next-gen checkout**: 3-step flow (contact → delivery → payment) with inline validation, saved last address, delivery methods (Settings → Shipping & tax), live coupon / gift card / store-credit application, "you're saving" summary, order bumps, passwordless account creation. Clearer, compact price display on product, cart and checkout pages.
- **Admin experience**: auto-collapsing sidebar groups (folds to an icon rail when idle), slide-over drawers instead of forms at the bottom of the page (coupons, discounts, offers, gift cards, customers, team…), live previews, global search (Ctrl+K), **create orders from the backend** (customer + products + variants + discount + payment status), **quick order updates** (status, mark paid, shipping/tracking details, bulk actions, packing slip, refund to store credit).
- **Search suggestions** while typing (products, departments, recent & popular searches, "did you mean"). **Sign in with a password or a one-time email code**; register either way.
- **Update history keeps only the last 3 updates** (log + automatic backups pruned too).

## What's new in 2.5
- **Quick edit & bulk actions** (Admin → Products): edit price, compare-at price, stock, status, name and SKU inline; tick rows (shift-click ranges) to publish/draft, feature, move department, change prices by %, run a % sale (and end it), set/add stock or delete. Bulk actions also on Customers, Coupons and Subscribers.
- **Add customers from the backend** (Admin → Customers → Add customer) — optional emailed login details, auto-generated password, mailing-list opt-in.
- **Advanced coupons**: cart, item (specific products) and department coupons; % or fixed; free-shipping codes; discount cap; min spend/quantity; total and per-customer limits; first-order-only; exclude sale items; automatic (no code) discounts.
- **SEO / GEO** (Settings → SEO, GEO / local): title templates, meta, canonical, noindex, Open Graph/Twitter, verification tags, GA4/GTM/Meta Pixel, Organization/LocalBusiness/Product/Breadcrumb/FAQ schema, robots.txt rules, AI-crawler switch, **/llms.txt**, geo meta tags, opening hours, service areas. Per product/department/page SEO fields.
- **Favicon upload** (Settings → General).
- **Popups** (Admin → Popups): modal, slide-in, bar and full-screen layouts; delay / scroll / exit-intent / click triggers; frequency caps; device, audience and page targeting; email capture + coupon reveal; schedule; live preview; views/conversion stats.
- **Visual page builder** (Admin → Page builder, and any page → Open visual builder): drag-and-drop blocks (hero, text, image+text, product grid, department tiles, banner, features, testimonials, FAQ, countdown, video, email signup, spacer, HTML), live preview with desktop/tablet/mobile, per-block spacing/colour/width/visibility, undo. Works for the home page and custom pages.
- **No separate /admin login.** Everyone signs in at `/login`; staff get an **Admin panel** link in their account menu. `/admin` shows a 404 to visitors and customers.
- **Email automations** (Admin → Email automations): abandoned-cart series (3 emails, optional coupon), welcome, post-purchase and win-back. Recovery links refill the cart; unsubscribe link in every mail; recovered-revenue stats; Abandoned carts and Subscribers lists (CSV export). Runs automatically on visitor traffic, or add a cron: `* * * * * php /path/to/artisan schedule:run`.
- **Social login**: Google, Facebook, GitHub, Discord (Settings → Social login; no extra packages).
- **Banners**: upper announcement bar (static / rotating / scrolling, schedule, dismissible) and a live **free-delivery progress bar** (Settings → Banners & delivery bar).

## System updates
Admin → **System & updates** → upload `update.zip`. The package is applied, `php artisan migrate` runs, replaced files are backed up to `storage/app/backups`, and the zip is deleted automatically.

Package layout (`tools/make-update.sh <base-ref> <min_version> "notes"` builds it from the git diff; an optional `tools/post_update.php` is shipped too):
```
update.json                {"version":"2.1.0","min_version":"2.0.0","notes":"...","delete":["old/file.php"]}
app/… resources/… routes/… public/… config/kiosk.php (bump version) database/migrations/…  (mirror the site root)
vendor/…                   only if dependencies changed
post_update.php            optional, executed once then removed
```
Never overwritten: `.env`, `storage/`, `public/uploads/`.

## Build the install package
```bash
tools/release.sh        # -> release/site.zip (production vendor/ included)
```

## Layout
`app/Models` Eloquent · `app/Services` cart, orders, payments, updater · `app/Http/Controllers` (+`Admin`) · `resources/views` Blade · `database/migrations` · `public/css|js` · `routes` · `config/kiosk.php` (version)

## Security
CSRF on all forms, Eloquent prepared queries, hashed passwords, login throttling, AES-256 encrypted keys (Laravel `encrypted` cast), upload validation, private file downloads, no PHP execution in `/uploads`.
