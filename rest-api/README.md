# rest-api/ — Laravel REST API

**Status: placeholder (M01).** API belum dibuat. Fondasi dikerjakan pada **M02** sesuai [docs/ARCHITECTURE.md](../docs/ARCHITECTURE.md), [docs/DATABASE.md](../docs/DATABASE.md), dan [docs/API.md](../docs/API.md).

`rest-api/` adalah pemilik tunggal database (PostgreSQL Neon), migration, dan aturan bisnis: identitas, rumah/keluarga, pengaduan, aspirasi, agenda/pengumuman, iuran offline Rp75.000/rumah/bulan, kas, konten, audit, outbox. Website dan mobile hanya memanggil `/api/v1`.

Runtime lokal yang terinspeksi: PHP 8.4.13, Composer 2.8.12, Docker 29.6.1. Credential Neon/Redis/SMTP/FCM tidak pernah di-commit; gunakan `.env` lokal dan `.env.example` tanpa nilai rahasia.
