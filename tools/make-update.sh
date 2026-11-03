#!/bin/bash
# Builds release/update-<version>.zip containing every site file that changed since a base git ref.
#   tools/make-update.sh <base-ref> [min_version] "release notes"
# e.g. tools/make-update.sh d1f11f6 2.0.0 "Growth & merchandising tools"
# Upload the zip in Admin -> System & updates. Version comes from config/kiosk.php.
set -e
cd "$(dirname "$0")/.."
BASE="${1:?base git ref required}"; MIN="${2:-2.0.0}"; NOTES="${3:-}"
VER=$(php -r "echo (require 'config/kiosk.php')['version'];")
OUT=$(mktemp -d)
KEEP='^(app|bootstrap/app\.php|config|database/migrations|public/(css|js|favicon\.ico)|resources|routes/(web|console)\.php)'
CHANGED=$( (git diff --name-only --diff-filter=AMT --no-renames "$BASE"; git ls-files -o --exclude-standard) | sort -u | grep -E "$KEEP" | grep -vE '^(resources/views/install/|app/Http/Controllers/InstallController|routes/install)' || true)
DELETED=$(git diff --name-only --diff-filter=D --no-renames "$BASE" | grep -E "$KEEP" || true)
[ -n "$CHANGED" ] || { echo "No changed files since $BASE"; exit 1; }
while IFS= read -r f; do [ -f "$f" ] && { mkdir -p "$OUT/$(dirname "$f")"; cp "$f" "$OUT/$f"; }; done <<< "$CHANGED"
DEL_JSON=$(printf '%s\n' "$DELETED" | php -r 'echo json_encode(array_values(array_filter(array_map("trim", file("php://stdin")))));')
NOTES_ENV="$NOTES" php -r 'echo json_encode(["version"=>$argv[1],"min_version"=>$argv[2],"notes"=>getenv("NOTES_ENV"),"delete"=>json_decode($argv[3],true)], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);' "$VER" "$MIN" "$DEL_JSON" > "$OUT/update.json"
# optional one-shot hook executed by the updater after the migrations (then deleted)
[ -f tools/post_update.php ] && cp tools/post_update.php "$OUT/post_update.php"
mkdir -p release; rm -f "release/update-$VER.zip"
(cd "$OUT" && zip -qr "$OLDPWD/release/update-$VER.zip" . -x '*.DS_Store')
echo "Built release/update-$VER.zip ($(echo "$CHANGED" | wc -l) files, $(du -h release/update-$VER.zip | cut -f1))"
rm -rf "$OUT"
