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
4. When it finishes the installer files **delete themselves**. Admin lives at `/admin`.

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
