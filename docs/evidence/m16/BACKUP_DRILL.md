# Latihan backup + restore — 22 Oktober 2026 (M16, gate G3)

- **Sumber:** Neon branch staging (data sintetis), role pemilik schema lewat `DATABASE_URL_UNPOOLED`, TLS verify-full.
- **Alat:** `infra/scripts/backup-drill.sh` di VPS staging (Docker `postgres:17-alpine`), dijalankan agent 10.29 WIB.
- **Restore:** ke container PostgreSQL 17 sementara (bukan Neon, tanpa memakai kuota CU), lalu dibandingkan dengan sumber.
- **Hasil:** dump 260 KB dalam 5 dtk, restore 4 dtk (total 9 dtk). 12 tabel utama **cocok semua**: akun 26, rumah 16,
  keluarga 15, warga 40, tagihan_iuran 26 / Rp1.950.000, pembayaran_iuran 14 / Rp1.575.000, transaksi_kas 24 / Rp2.030.000,
  pengaduan 17, aspirasi 6, konten 34, notifikasi 413, audit_logs 683.
- **Enkripsi + offsite:** `rt05-20261022T0329Z.dump.gpg` (152 KB, AES256, sha256 `697fcf2393394dab…`) disalin ke Google Drive
  pemilik `RT05 TAKEDA - Backup/staging/` lewat rclone; dekripsi di PC diuji (header `PGDMP`). Kunci enkripsi diberikan ke
  pemilik lewat berkas catatan lokal (tidak di Git, tidak di Drive yang sama). Container, jaringan, dan berkas sementara di VPS dihapus.
- **RPO/RTO:** RPO = umur backup terakhir (target ≤24 jam saat production); RTO data 9 dtk untuk ukuran ini, belum termasuk
  mengarahkan API ke database hasil restore.

Keterbatasan: baru staging; production belum ada. Jadwal otomatis belum dipasang (perlu keputusan pemilik: tempat menjalankan
skrip). Rclone memakai client_id bersama Google Drive yang akan dihentikan selama 2026; buat client_id sendiri sebelum backup
production terjadwal.
