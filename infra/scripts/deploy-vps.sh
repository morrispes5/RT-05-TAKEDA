#!/usr/bin/env bash
# Deploy RT05 TAKEDA ke VPS Hostinger (Coolify sudah terpasang). Dijalankan PEMILIK di Konsol web/SSH:
#   curl -fsSL https://raw.githubusercontent.com/morrispes5/RT-05-TAKEDA/main/infra/scripts/deploy-vps.sh -o /root/rt05-deploy.sh
#   bash /root/rt05-deploy.sh staging        # atau: production
# Pertama kali, script meminta isi env (tempel isi file infra/env/.env.<env> dari PC, lalu Ctrl-D).
# Aman diulang: menarik kode terbaru, build image, migrasi additive, lalu restart container.
# Tidak menyentuh service/container lain, port 80/443, firewall, atau DNS.
set -euo pipefail

ENV_NAME="${1:-}"
case "$ENV_NAME" in staging|production) ;; *) echo "Pakai: $0 staging|production"; exit 1 ;; esac

DIR=/opt/rt05-takeda
REPO=https://github.com/morrispes5/RT-05-TAKEDA.git
PROJECT="rt05-$ENV_NAME"
SECRETS=/root/.rt05
ENV_FILE="$SECRETS/.env.$ENV_NAME"

command -v docker >/dev/null || { echo "Docker tidak ditemukan."; exit 1; }
docker network inspect coolify >/dev/null 2>&1 || { echo "Jaringan docker 'coolify' tidak ada (proxy Coolify belum jalan)."; exit 1; }

if [ -d "$DIR/.git" ]; then
  git -C "$DIR" fetch --depth 1 origin main && git -C "$DIR" reset --hard origin/main
else
  git clone --depth 1 "$REPO" "$DIR"
fi

mkdir -p "$SECRETS" && chmod 700 "$SECRETS"
if [ ! -s "$ENV_FILE" ]; then
  echo ">>> Tempel isi .env.$ENV_NAME (dari PC: RT-05-TAKEDA/infra/env/.env.$ENV_NAME), lalu tekan Ctrl-D:"
  (umask 077; cat > "$ENV_FILE")
fi
chmod 600 "$ENV_FILE"
grep -q '^DATABASE_URL=' "$ENV_FILE" || { echo "Env tidak lengkap."; exit 1; }

cd "$DIR/infra"
DC=(docker compose -p "$PROJECT" --env-file "$ENV_FILE" -f compose.production.yml -f compose.vps.yml)

echo ">>> Build image (beberapa menit pada build pertama)"
"${DC[@]}" build
echo ">>> Redis lalu migrasi database (endpoint direct)"
"${DC[@]}" up -d redis
"${DC[@]}" run --rm migrate
echo ">>> Menjalankan layanan"
"${DC[@]}" up -d --remove-orphans gateway api web worker scheduler redis
sleep 8
"${DC[@]}" ps
echo ">>> Health dari dalam jaringan"
"${DC[@]}" exec -T api php -r 'echo @file_get_contents("http://127.0.0.1/health/ready") ?: "ready gagal", PHP_EOL;'
HOST=$(grep '^RT05_HOST=' "$ENV_FILE" | cut -d= -f2)
echo ">>> Selesai. Buka https://$HOST (sertifikat HTTPS bisa butuh 1–2 menit pertama)."
