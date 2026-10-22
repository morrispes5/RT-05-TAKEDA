#!/usr/bin/env bash
# Backup + latihan restore database RT05 (M16, RUNBOOK_PRODUCTION.md bagian 5). Dijalankan operator di mesin yang punya Docker.
#   bash backup-drill.sh <berkas-env> <keluaran-dir> <berkas-passphrase>
# - <berkas-env> berisi DATABASE_URL_UNPOOLED (role pemilik schema, koneksi langsung). Nilai tidak pernah dicetak.
# - TLS verify-full memakai CA sistem container (PGSSLROOTCERT=system).
# - Dump format custom dari postgres:17-alpine, dipulihkan ke container PostgreSQL sementara (bukan Neon), lalu jumlah baris
#   dan total rupiah tabel utama dibandingkan sumber vs hasil restore. Beda = exit 1.
# - Hasil akhir hanya <keluaran-dir>/rt05-<stamp>.dump.gpg (AES256, passphrase dari berkas). Dump polos dihapus.
# Tidak pernah menulis ke database sumber. Jangan diarahkan ke production sebelum ada izin pemilik.
set -euo pipefail
umask 077
env_file=${1:?berkas env}; out=${2:?direktori keluaran}; pass=${3:?berkas passphrase}
[ -s "$pass" ] || { echo "Passphrase kosong/tidak ada." >&2; exit 1; }
url=$(grep -E '^DATABASE_URL_UNPOOLED=' "$env_file" | head -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//")
[ -n "$url" ] || { echo "DATABASE_URL_UNPOOLED tidak ada di berkas env." >&2; exit 1; }
image=postgres:17-alpine
name=rt05-restore-drill-$$
net=rt05-restore-drill-$$
stamp=$(date -u +%Y%m%dT%H%MZ)
mkdir -p "$out"
dump="$out/rt05-$stamp.dump"
cleanup() {
  docker rm -f "$name" >/dev/null 2>&1 || true
  docker network rm "$net" >/dev/null 2>&1 || true
  rm -f "$dump"
}
trap cleanup EXIT

# Jumlah baris + total rupiah tabel utama (urutan stabil).
check_sql="select 'akun', count(*)::text from akun union all select 'rumah', count(*)::text from rumah
 union all select 'keluarga', count(*)::text from keluarga union all select 'warga', count(*)::text from warga
 union all select 'tagihan_iuran', count(*)||'/'||coalesce(sum(nominal_tagihan),0) from tagihan_iuran
 union all select 'pembayaran_iuran', count(*)||'/'||coalesce(sum(total_bayar),0) from pembayaran_iuran
 union all select 'transaksi_kas', count(*)||'/'||coalesce(sum(nominal),0) from transaksi_kas
 union all select 'pengaduan', count(*)::text from pengaduan union all select 'aspirasi', count(*)::text from aspirasi
 union all select 'konten', count(*)::text from konten union all select 'notifikasi', count(*)::text from notifikasi
 union all select 'audit_logs', count(*)::text from audit_logs"

t0=$(date +%s)
docker run --rm -e PGURL="$url" -e PGSSLROOTCERT=system "$image" sh -c 'pg_dump --format=custom --no-owner --no-privileges "$PGURL"' > "$dump"
source_counts=$(docker run --rm -e PGURL="$url" -e PGSSLROOTCERT=system "$image" sh -c "psql \"\$PGURL\" -At -F' ' -c \"$check_sql\"")
t1=$(date +%s)
echo ">>> Dump selesai: $(du -h "$dump" | cut -f1), $((t1 - t0)) dtk"

docker network create "$net" >/dev/null
docker run -d --name "$name" --network "$net" -e POSTGRES_PASSWORD=drill -e POSTGRES_DB=pulih "$image" >/dev/null
# Server inisialisasi image hanya lewat socket lalu restart; tunggu server final yang mendengar TCP.
for _ in $(seq 1 90); do docker exec "$name" pg_isready -h 127.0.0.1 -U postgres -d pulih >/dev/null 2>&1 && break; sleep 1; done
docker exec -i "$name" pg_restore -h 127.0.0.1 --no-owner --no-privileges -U postgres -d pulih < "$dump"
restored_counts=$(docker exec "$name" psql -h 127.0.0.1 -U postgres -d pulih -At -F' ' -c "$check_sql")
t2=$(date +%s)
echo ">>> Restore ke container sementara: $((t2 - t1)) dtk (total $((t2 - t0)) dtk)"
echo ">>> Sumber vs hasil restore (baris atau baris/total rupiah):"
paste -d' ' <(echo "$source_counts") <(echo "$restored_counts" | cut -d' ' -f2) | awk '{ printf "%-18s %-16s %-16s %s\n", $1, $2, $3, ($2 == $3 ? "cocok" : "BEDA") }'
[ "$source_counts" = "$restored_counts" ] || { echo "GAGAL: hasil restore berbeda dari sumber." >&2; exit 1; }

gpg --batch --yes --pinentry-mode loopback --passphrase-file "$pass" --symmetric --cipher-algo AES256 -o "$dump.gpg" "$dump"
echo ">>> Terenkripsi: $(basename "$dump.gpg") ($(du -h "$dump.gpg" | cut -f1)), sha256 $(sha256sum "$dump.gpg" | cut -c1-16)…"
echo ">>> LULUS: dump dapat dipulihkan dan identik dengan sumber."
