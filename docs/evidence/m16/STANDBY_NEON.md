# Database cadangan Neon project kedua — 22 Oktober 2026 (ADR39, gate G4 sebagian)

- Project `rt05-takeda-cadangan` (`long-star-75282377`, aws-ap-southeast-1, PG17, 0,25 CU) dibuat agent atas izin pemilik.
- Role `rt05_owner` + `rt05_app` (SQL, bukan `neon_superuser`), grant sama dengan `infra/sql/grant-runtime-role.sql`.
- `infra/scripts/standby-sync.sh /root/.rt05/.env.staging /root/.rt05/standby/standby.env` di VPS: 19 dtk, cocok semua —
  akun 26, rumah 16, keluarga 15, warga 40, tagihan 26 / Rp1.950.000, pembayaran 14 / Rp1.575.000, kas 24 / Rp2.030.000,
  pengaduan 17, aspirasi 6, konten 34, notifikasi 413, audit_logs 686, migrations 25.
- Container sementara `rt05-api:b1d080d` dengan `DATABASE_URL` = role `rt05_app` di cadangan (verify-full):
  `rt05:db-smoke` → tulis+baca ok, rollback bersih, DDL denied; `rt05:rekonsiliasi-keuangan` → 0 masalah, saldo 2.030.000.

Belum: pindah + kembali pada staging aktif (gate ADR29 penuh), sinkron terjadwal, pemantau kuota otomatis.
