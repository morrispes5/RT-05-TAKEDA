# M12 — Deploy staging, rekonsiliasi, dan cek live (17 Oktober 2026)

Hanya project Docker `rt05-staging` lewat SSH (`BatchMode`, `StrictHostKeyChecking=yes`). Tanpa production, DNS, firewall,
port, atau service lain. Tidak ada pembayaran atau kas baru yang dicatat; satu-satunya tulisan adalah baris audit
`ekspor.csv` milik pengurus sintetis.

## Deploy

| Langkah | Hasil |
| --- | --- |
| Script deploy dari `337a8bb` (merge PR #35) → `/root/.rt05/deploy-staging-immutable-m12.sh` mode 700 | SHA-256 diawali `aa50ca1a629ba5a7`, sama dengan lokal; `bash -n` OK |
| Deploy `337a8bb00eb6a9f7a112e4a0be5a23fb7ea72c6f` | `Selesai`, 0 baris GAGAL, tanpa migration baru |
| Pemeriksaan | Image `rt05-api:337a8bb`; `/health/ready` ready; total container host tetap 22 |

## Rekonsiliasi buku (data uji M06–M12 di staging)

`docker exec rt05-staging-api-1 php artisan rt05:rekonsiliasi-keuangan` → **12 pemeriksaan, total_masalah 0**,
saldo Rp605.000, exit 0 ("Rekonsiliasi cocok"). Keluaran hanya angka.

## Cek live

Skrip: [`infra/scripts/m12-stage-check.mjs`](../../../infra/scripts/m12-stage-check.mjs). Ringkasan:
[m12-live-summary-337a8bb.json](m12-live-summary-337a8bb.json).

| Kasus | Hasil |
| --- | --- |
| M01 daftar kas memuat `dapat_dibalik` | PASS (8 baris, saldo 605.000) |
| M02 kas iuran tidak dapat dibalik langsung | PASS (6 baris iuran) |
| M03 total ekspor kas = saldo ledger, `no-store` | PASS (605.000 = 605.000) |
| M04 warga ekspor iuran/kas/rumah | PASS (403 ×3) |
| M05 warga daftar kas | PASS (403) |

**7/7 PASS.** Pembayaran/pembatalan bersamaan dan retry kunci sama dibuktikan di tes antarproses lokal/CI
(`M12ConcurrencyTest`), bukan dengan menulis uang uji di staging.
