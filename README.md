# KIOSK — next-gen PHP store

Toys, clothing, digital keys, accounts, downloads — one cart, one checkout. Pure PHP 8.1+ (no Composer, no build step), MySQL/MariaDB or SQLite.

## Install (CodeCanyon style)
1. Upload and unzip `site.zip` into your web root (e.g. `public_html`).
2. Open `https://yourdomain.com/` — the installer wizard starts automatically.
3. Follow the 4 steps (requirements → database → store & admin → install).
4. The `/install` folder **deletes itself** when finished. Admin: `/admin`.

Apache: `mod_rewrite` + `AllowOverride All` (the included `.htaccess` does the rest).
Nginx:
```
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ ^/(app|storage|database)/ { deny all; }
location ^~ /uploads/ { location ~ \.php$ { deny all; } }
location ~ \.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php-fpm.sock; }
```
Writable by PHP: `storage/`, `uploads/` (and `app/`, `assets/` for one-click updates).

## Selling digital goods
Product types: **Physical**, **Key/code** and **Account** (delivered automatically from an encrypted pool you paste in — one per line), **Downloadable file** (private, token-protected), **Service** (manual). Keys appear on the order page and in the email the moment payment is confirmed. If the pool runs dry the order waits and you can deliver after adding keys.

## Payments
Admin → Settings → Payments: Stripe Checkout, PayPal, bank transfer, cash on delivery. Stripe webhook URL: `/pay/stripe/webhook` (event `checkout.session.completed`). Test with Stripe/PayPal sandbox credentials before going live.

## System updates
Admin → System & updates → upload `update.zip`. The package is applied, migrations run, a backup of replaced files is saved to `storage/backups`, and the zip is deleted automatically.

Package layout (`tools/build.sh update` zips `tools/update/`):
```
update.json   {"version":"1.1.0","min_version":"1.0.0","notes":"...","delete":["old/file.php"]}
app/...       files mirroring the site root (storage/, uploads/, install/ are never overwritten)
database/migrations/002_name.php   run automatically (see 001_init.php)
post_update.php  optional, executed once then removed
```
Bump `app/version.php` inside the package too.

## Project layout
`app/Core` framework · `app/Services` cart, orders, payments · `app/Controllers` (+`Admin`) · `app/Views` · `database/migrations` · `assets` · `install` · `storage` (private) · `uploads` (public)

## Security notes
CSRF on all POSTs, prepared statements, password_hash, login throttling, AES-256-GCM encrypted keys, upload validation, private file downloads, `app/ storage/ database/` blocked from the web. Set HTTPS on your domain.
