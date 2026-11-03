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

Package layout (`tools/release.sh update` zips `tools/update/`):
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
