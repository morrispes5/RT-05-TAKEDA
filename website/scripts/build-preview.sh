#!/usr/bin/env bash
# Build the Vite assets, then render every public/demo route from Laravel.
set -euo pipefail
cd "$(dirname "$0")/.."
npm run build
php artisan view:clear
php scripts/export-preview.php
