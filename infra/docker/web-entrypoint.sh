#!/bin/sh
set -e
cd /app
php artisan package:discover --ansi >/dev/null
php artisan config:cache --ansi >/dev/null
php artisan route:cache --ansi >/dev/null
php artisan view:cache --ansi >/dev/null
exec "$@"
