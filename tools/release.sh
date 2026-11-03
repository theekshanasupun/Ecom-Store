#!/bin/bash
# Builds release/site.zip — the CodeCanyon-style install package (production vendor/ included).
#   tools/release.sh           -> release/site.zip
#   tools/release.sh update    -> release/update-<version>.zip from tools/update/ (see README)
set -e
cd "$(dirname "$0")/.."
export COMPOSER_ALLOW_SUPERUSER=1
VER=$(php -r "echo (require 'config/kiosk.php')['version'];")
if [ "$1" = "update" ]; then
  [ -f tools/update/update.json ] || { echo "Put update.json + changed files (mirroring the site root) in tools/update/"; exit 1; }
  rm -f "release/update-$VER.zip"; (cd tools/update && zip -qr "../../release/update-$VER.zip" .)
  echo "Built release/update-$VER.zip"; exit 0
fi
TMP=$(mktemp -d)
tar --exclude=.git --exclude=release --exclude=tools --exclude=vendor --exclude=.env --exclude=database/database.sqlite --exclude='storage/logs/*.log' --exclude='storage/framework/views/*.php' --exclude='bootstrap/cache/*.php' --exclude='storage/framework/sessions/*' --exclude='public/uploads/products/*' --exclude='public/uploads/site/*' -cf - . | tar -xf - -C "$TMP"
cd "$TMP"
composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --prefer-dist -q
# slim vendor/: drop VCS metadata, tests and docs that are never loaded in production
find vendor -type d \( -name .git -o -name .github \) -prune -exec rm -rf {} + 2>/dev/null || true
rm -rf vendor/*/*/tests vendor/*/*/Tests vendor/*/*/docs vendor/*/*/.phpunit.cache 2>/dev/null || true
find vendor -type f \( -name '*.md' -o -name 'phpunit*.xml*' -o -name '.gitattributes' -o -name '.gitignore' \) -delete 2>/dev/null || true
rm -f bootstrap/cache/*.php
php -r 'require "vendor/autoload.php"; echo "autoload ok\n";'
# temporary installer key (the wizard generates a fresh unique key per site)
cp .env.example .env
KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|" .env
sed -i 's|^DB_CONNECTION=.*|DB_CONNECTION=sqlite|' .env
php artisan package:discover --ansi >/dev/null 2>&1 || { echo "package:discover failed"; exit 1; }
mkdir -p storage/app/{public,private-files} storage/framework/{cache/data,sessions,views,testing} storage/logs bootstrap/cache public/uploads/{products,site}
for d in storage/app/public storage/app/private-files storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache; do touch $d/.gitkeep; done
cd - >/dev/null
mkdir -p release; rm -f release/site.zip
(cd "$TMP" && zip -qr "$OLDPWD/release/site.zip" . -x '*.DS_Store')
rm -rf "$TMP"
echo "Built release/site.zip (v$VER, $(du -h release/site.zip | cut -f1))"
