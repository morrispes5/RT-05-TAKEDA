#!/usr/bin/env bash
# Deploy PRODUCTION SEMENTARA RT05 TAKEDA di VPS Biznet (ADR57, soft launch 8 Nov 2026) dari SHA turunan main.
# Pola sama dengan deploy-staging-immutable.sh (worktree immutable per SHA, image bertag SHA, tanpa registry).
# Database = Neon production yang SUDAH dimigrasi (M16.2): skrip ini TIDAK menjalankan migrasi dan tidak butuh sandi
# owner; ditolak bila SHA membawa migrasi yang belum diterapkan. Tidak membuat/mengubah secret: env
# /root/.rt05/.env.production disiapkan terpisah (mode 600). Staging harus dihentikan dulu (RAM VPS 2 GB):
#   docker compose -p rt05-staging stop        (volume staging tetap)
# Jalankan: sudo bash -c "(setsid nohup bash /root/rt05-deploy-production.sh <sha40> > /root/rt05-prod-<sha8>.log 2>&1 &)"
set -euo pipefail
export GIT_TERMINAL_PROMPT=0

ORIGIN=https://github.com/morrispes5/RT-05-TAKEDA.git
RELEASES=/opt/rt05-releases
BARE="$RELEASES/origin.git"
PROJECT=rt05-production
ENV_FILE=/root/.rt05/.env.production
SERVICES=(gateway api web worker-default worker-media worker-exports scheduler redis redis-cache)
# Migrasi terakhir yang sudah diterapkan di Neon production (M16.2, 6 Nov 2026).
LAST_APPLIED_MIGRATION=2026_10_31_000100_m18_metode_pemutihan.php

finish_ok=0
trap 'if [ "$finish_ok" != 1 ]; then echo "GAGAL: deploy production berhenti sebelum selesai." >&2; fi' EXIT
fail() { echo "GAGAL: $*" >&2; exit 1; }

[ "$#" -eq 1 ] || fail "Pakai: $0 <sha40>"
SHA="$1"
printf '%s' "$SHA" | grep -qE '^[0-9a-f]{40}$' || fail "Argumen harus SHA 40 hex huruf kecil."

require_env() { grep -qE "^${1}=.+" "$ENV_FILE" || fail "Env $1 wajib dan tidak boleh kosong."; }
require_identity() { grep -qE "^${1}=${2}$" "$ENV_FILE" || fail "Env $1 harus nilai production yang ditetapkan."; }

echo ">>> Preflight"
command -v docker >/dev/null || fail "Docker tidak ditemukan."
docker network ls --format '{{.Name}}' | grep -qx coolify || fail "Jaringan docker coolify tidak ada."
[ -f "$ENV_FILE" ] && [ ! -L "$ENV_FILE" ] || fail "Env production tidak ada atau symlink."
[ "$(stat -c '%a' "$ENV_FILE")" = 600 ] || fail "Mode env production harus 600."
for v in DATABASE_URL DATABASE_URL_UNPOOLED API_APP_KEY WEB_APP_KEY REDIS_PASSWORD REDIS_CACHE_PASSWORD; do require_env "$v"; done
require_identity APP_ENV production
require_identity RT05_ENV production
require_identity RT05_HOST 'takeda\.morriz\.tech'
grep -qE '^DATABASE_URL=postgresql://rt05_app:[^@]+@ep-snowy-union-b33dixmf-pooler\.' "$ENV_FILE" || fail "DATABASE_URL bukan Neon production rt05_app."
if docker ps --filter label=com.docker.compose.project=rt05-staging --format '{{.Names}}' | grep -q .; then
  fail "Staging masih berjalan; hentikan dulu agar RAM cukup."
fi
mem_kb=$(awk '/MemAvailable/ {print $2}' /proc/meminfo)
[ "$mem_kb" -ge 524288 ] || fail "MemAvailable di bawah 512 MiB."
free -m

echo ">>> Worktree immutable $SHA"
mkdir -p "$RELEASES"
[ -d "$BARE" ] || git -c safe.directory="$RELEASES" clone --bare "$ORIGIN" "$BARE"
git -c safe.directory="$BARE" -C "$BARE" fetch --no-tags origin refs/heads/main
main_head=$(git -c safe.directory="$BARE" -C "$BARE" rev-parse FETCH_HEAD)
git -c safe.directory="$BARE" -C "$BARE" merge-base --is-ancestor "$SHA" "$main_head" || fail "SHA bukan turunan main."
DEST="$RELEASES/$SHA"
if [ -e "$DEST" ]; then
  [ "$(git -c safe.directory="$DEST" -C "$DEST" rev-parse HEAD)" = "$SHA" ] || fail "Worktree ada tetapi HEAD bukan SHA diminta."
else
  git -c safe.directory="$BARE" -C "$BARE" worktree add --detach "$DEST" "$SHA"
fi
[ -z "$(git -c safe.directory="$DEST" -C "$DEST" status --porcelain)" ] || fail "Worktree rilis kotor."
latest=$(ls "$DEST/rest-api/database/migrations" | sort | tail -n 1)
[ "$latest" = "$LAST_APPLIED_MIGRATION" ] || fail "SHA membawa migrasi baru ($latest); jalankan migrasi production terpisah dulu."

# shellcheck source=staging-metadata.sh
source "$DEST/infra/scripts/staging-metadata.sh"
before=$(list_unrelated)
export RT05_IMAGE_TAG="$SHA"
cd "$DEST/infra"
DC=(docker compose -p "$PROJECT" --env-file "$ENV_FILE" -f compose.production.yml -f compose.vps.yml)
"${DC[@]}" config --quiet

echo ">>> Build api dan web (1 vCPU: ±10 menit). Tidak ada push registry."
"${DC[@]}" build api web
"${DC[@]}" up -d --no-build redis redis-cache
"${DC[@]}" up -d --no-build --remove-orphans "${SERVICES[@]}"

echo ">>> Health ready (batas 180 detik, body tidak dicetak)"
ready_ok=0; deadline=$((SECONDS + 180))
while [ "$SECONDS" -lt "$deadline" ]; do
  ready=$(timeout 10s "${DC[@]}" exec -T api php -r 'echo @file_get_contents("http://127.0.0.1/health/ready", false, stream_context_create(["http" => ["timeout" => 5]])) ?: "";' || true)
  if printf '%s' "$ready" | grep -q '"status":"ready"'; then ready_ok=1; break; fi
  sleep 5
done
[ "$ready_ok" = 1 ] || fail "health ready gagal"

echo ">>> Heartbeat worker/scheduler (batas 240 detik)"
deadline=$((SECONDS + 240))
for component in worker-default worker-media worker-exports scheduler; do
  ok=0
  while [ "$SECONDS" -lt "$deadline" ]; do
    if timeout 15s "${DC[@]}" exec -T "$component" php artisan rt05:heartbeat-check --component "$component" --max-age=300; then ok=1; break; fi
    sleep 5
  done
  [ "$ok" = 1 ] || fail "heartbeat $component tidak sehat"
done

if docker ps --filter label=com.docker.compose.project="$PROJECT" --format '{{.Names}} {{.Ports}}' | grep -E '0\.0\.0\.0:|\[::\]:'; then
  fail "Service production mempublikasikan port host."
fi
assert_unrelated "$before"
printf '%s\n' "$SHA" > "$RELEASES/production-current-sha"
"${DC[@]}" ps
echo ">>> Selesai production SHA $SHA"
finish_ok=1
