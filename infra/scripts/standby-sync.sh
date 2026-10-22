#!/usr/bin/env bash
# Sinkron satu arah Neon utama → Neon cadangan (ADR29, ADR39: project kedua `rt05-takeda-cadangan`). Operator dengan Docker:
#   bash standby-sync.sh <env-sumber> <env-cadangan>
# Kedua berkas berisi DATABASE_URL_UNPOOLED (role pemilik schema, koneksi langsung). Nilai tidak pernah dicetak.
# Isi cadangan DIGANTI seluruhnya (--clean) dengan salinan sumber, lalu jumlah baris + total rupiah dibandingkan; beda = exit 1.
# Sumber hanya dibaca. Ditolak bila host sumber dan cadangan sama (mencegah menimpa database utama).
set -euo pipefail
umask 077
src_env=${1:?env sumber}; dst_env=${2:?env cadangan}
url_of() { grep -E '^DATABASE_URL_UNPOOLED=' "$1" | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"; }
host_of() { sed -E 's#^[a-z]+://[^@]*@([^/:?]+).*#\1#' <<<"$1"; }
src=$(url_of "$src_env"); dst=$(url_of "$dst_env")
[ -n "$src" ] && [ -n "$dst" ] || { echo "DATABASE_URL_UNPOOLED wajib di kedua berkas." >&2; exit 1; }
[ "$(host_of "$src")" != "$(host_of "$dst")" ] || { echo "GAGAL: sumber dan cadangan sama. Tidak ada yang ditulis." >&2; exit 1; }
image=postgres:17-alpine
run() { docker run --rm -i -e PGURL="$1" -e PGSSLROOTCERT=system "$image" sh -c "$2"; }

check_sql="select 'akun', count(*)::text from akun union all select 'rumah', count(*)::text from rumah
 union all select 'keluarga', count(*)::text from keluarga union all select 'warga', count(*)::text from warga
 union all select 'tagihan_iuran', count(*)||'/'||coalesce(sum(nominal_tagihan),0) from tagihan_iuran
 union all select 'pembayaran_iuran', count(*)||'/'||coalesce(sum(total_bayar),0) from pembayaran_iuran
 union all select 'transaksi_kas', count(*)||'/'||coalesce(sum(nominal),0) from transaksi_kas
 union all select 'pengaduan', count(*)::text from pengaduan union all select 'aspirasi', count(*)::text from aspirasi
 union all select 'konten', count(*)::text from konten union all select 'notifikasi', count(*)::text from notifikasi
 union all select 'audit_logs', count(*)::text from audit_logs union all select 'migrations', count(*)::text from migrations"

t0=$(date +%s)
# Satu snapshot sumber (pg_dump), disalurkan langsung ke pg_restore cadangan tanpa berkas polos di disk.
run "$src" 'pg_dump --format=custom --no-owner --no-privileges "$PGURL"' \
  | run "$dst" 'pg_restore --clean --if-exists --no-owner --no-privileges --exit-on-error -d "$PGURL"'
src_counts=$(run "$src" "psql \"\$PGURL\" -At -F' ' -c \"$check_sql\"")
dst_counts=$(run "$dst" "psql \"\$PGURL\" -At -F' ' -c \"$check_sql\"")
t1=$(date +%s)
echo ">>> Sinkron selesai dalam $((t1 - t0)) dtk. Sumber vs cadangan:"
paste -d' ' <(echo "$src_counts") <(echo "$dst_counts" | cut -d' ' -f2) | awk '{ printf "%-18s %-16s %-16s %s\n", $1, $2, $3, ($2 == $3 ? "cocok" : "BEDA") }'
[ "$src_counts" = "$dst_counts" ] || { echo "GAGAL: cadangan berbeda dari sumber (mungkin ada tulisan baru selama sinkron; ulangi)." >&2; exit 1; }
echo ">>> LULUS: cadangan identik dengan sumber."
