#!/bin/sh
# Cache konfigurasi dari env runtime lalu jalankan perintah container (server/worker/scheduler/migrate).
set -e
cd /app
php artisan package:discover --ansi >/dev/null
php artisan config:cache --ansi >/dev/null
php artisan route:cache --ansi >/dev/null
php artisan event:cache --ansi >/dev/null || true
exec "$@"
