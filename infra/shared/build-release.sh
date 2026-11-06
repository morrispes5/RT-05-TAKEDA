#!/usr/bin/env bash
# RT05 TAKEDA — paket rilis shared hosting (M16.3, ADR54/ADR55).
#
#   bash infra/shared/build-release.sh [folder-keluaran]      (default: dist/)
#
# Isi zip (diekstrak di home akun cPanel, mis. /home/<akun>/):
#   rt05/api/      rest-api tanpa public/ dan tests/, vendor --no-dev (termasuk bin/neon-ws-bridge.php)
#   rt05/web/      website tanpa public/, tests/, preview/, node_modules/, vendor --no-dev
#   rt05/infra/sql grant-runtime-role.sql (dipakai rt05:db-grant-runtime-role)
#   rt05/RELEASE.json
#   public_html/   website/public + build Vite + auth-assets API + index.php/index-api.php/.htaccess shared
# Tidak ada .env, kredensial, storage berisi, atau data warga. Hanya berkas yang dilacak Git di HEAD (git archive).
# Build production dijalankan di CI (workflow "Shared release package"); di PC hanya untuk uji.
set -euo pipefail

ROOT=$(git rev-parse --show-toplevel)
cd "$ROOT"
PHP=${PHP:-php}
if [ -n "$(git status --porcelain -- rest-api website infra/sql infra/shared)" ] && [ "${RT05_ALLOW_DIRTY:-}" != 1 ]; then
  echo "Working tree rest-api/website/infra berubah. Commit dulu (atau RT05_ALLOW_DIRTY=1 khusus uji lokal)." >&2
  exit 1
fi
COMMIT=$(git rev-parse HEAD)
SHORT=${COMMIT:0:7}
mkdir -p "${1:-dist}"
OUT=$(cd "${1:-dist}" && pwd)
WORK=$(mktemp -d)
trap 'rm -rf "$WORK"' EXIT
mkdir -p "$WORK/src" "$WORK/pkg/rt05" "$WORK/pkg/public_html"

echo "== sumber $SHORT (git archive)"
git archive --format=tar HEAD rest-api website infra/sql infra/shared/release | tar -x -C "$WORK/src"

echo "== rest-api: composer --no-dev"
(cd "$WORK/src/rest-api" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress)

echo "== website: composer --no-dev + vite build"
(cd "$WORK/src/website" && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress \
  && npm ci --no-audit --no-fund && npm run build)

echo "== susun paket"
cp -a "$WORK/src/rest-api" "$WORK/pkg/rt05/api"
cp -a "$WORK/src/website" "$WORK/pkg/rt05/web"
mkdir -p "$WORK/pkg/rt05/infra"
cp -a "$WORK/src/infra/sql" "$WORK/pkg/rt05/infra/sql"

cp -a "$WORK/src/website/public/." "$WORK/pkg/public_html/"
cp -a "$WORK/src/rest-api/public/auth-assets" "$WORK/pkg/public_html/auth-assets"
cp "$WORK/src/infra/shared/release/index.php" "$WORK/src/infra/shared/release/index-api.php" \
  "$WORK/src/infra/shared/release/.htaccess" "$WORK/pkg/public_html/"

for app in api web; do
  rm -rf "$WORK/pkg/rt05/$app/public" "$WORK/pkg/rt05/$app/tests" "$WORK/pkg/rt05/$app/node_modules" \
    "$WORK/pkg/rt05/$app/phpunit.xml" "$WORK/pkg/rt05/$app/.phpunit.result.cache"
done
rm -rf "$WORK/pkg/rt05/web/preview" "$WORK/pkg/rt05/web/vercel.json"

cat > "$WORK/pkg/rt05/RELEASE.json" <<JSON
{"commit":"$COMMIT","built_at":"$(date -u +%FT%TZ)","php":"$($PHP -r 'echo PHP_VERSION;')","node":"$(node -v)"}
JSON

ZIP="$OUT/rt05-shared-$SHORT.zip"
"$PHP" infra/shared/release-zip.php pack "$WORK/pkg" "$ZIP"
"$PHP" infra/shared/release-zip.php check "$ZIP"
"$PHP" -r 'echo hash_file("sha256", $argv[1])."  ".basename($argv[1])."\n";' "$ZIP" | tee "$ZIP.sha256"
