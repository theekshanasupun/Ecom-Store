#!/bin/bash
# Usage: tools/build.sh            -> release/site.zip (fresh install package)
#        tools/build.sh update     -> release/update-<version>.zip (needs update.json in tools/update/ ; see README)
set -e
cd "$(dirname "$0")/.."
VER=$(php -r "echo require 'app/version.php';")
if [ "$1" = "update" ]; then
  [ -f tools/update/update.json ] || { echo "Create tools/update/update.json and put changed files beside it (mirroring site root)"; exit 1; }
  (cd tools/update && zip -qr "../../release/update-$VER.zip" .)
  echo "Built release/update-$VER.zip"; exit 0
fi
rm -f release/site.zip
zip -qr release/site.zip . -x '.git/*' 'release/*' 'tools/*' '.gitignore' 'storage/config.php' 'storage/installed.lock' 'storage/database.sqlite' 'storage/logs/*' 'storage/cache/*' 'storage/backups/*' 'storage/tmp/*' 'storage/sessions/*' 'storage/files/*' 'uploads/products/*' 'uploads/site/*' -x '*.DS_Store'
zip -qr release/site.zip storage/*/.gitkeep uploads/*/.gitkeep 2>/dev/null || true
echo "Built release/site.zip (v$VER)"
