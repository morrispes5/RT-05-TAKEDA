#!/usr/bin/env bash
# Render halaman Beranda Laravel menjadi bundel statis di preview/ (dilayani Vercel lewat vercel.json).
# Pakai: scripts/build-preview.sh   (opsional: SITE_URL=https://domain-lain.vercel.app PORT=8123)
set -euo pipefail
cd "$(dirname "$0")/.."

PORT=${PORT:-8123}
SITE_URL=${SITE_URL:-https://rt05takeda.vercel.app}

npm run build
php artisan view:clear >/dev/null

php -S "127.0.0.1:$PORT" -t public >/dev/null 2>&1 &
SERVER=$!
trap 'kill "$SERVER" 2>/dev/null' EXIT
for _ in $(seq 1 30); do
    curl -sf -o /dev/null "http://127.0.0.1:$PORT/" && break
    sleep 0.5
done

rm -rf preview
mkdir -p preview
curl -sf "http://127.0.0.1:$PORT/" -o preview/index.html

# og:image wajib URL absolut; aset lain cukup path dari root.
sed -i \
    -e "s#content=\"http://127.0.0.1:$PORT/#content=\"$SITE_URL/#g" \
    -e "s#http://127.0.0.1:$PORT##g" \
    preview/index.html

cp -r public/build preview/build
cp -r public/images preview/images
rm -f preview/build/manifest.json

echo "preview/ siap ($(du -sh preview | cut -f1))"
