# Progress — RT05 TAKEDA

Updated: 10 Oktober 2026.

## Status awal

Dokumen ini menjadi template operasional setelah paket diimpor ke checkout. Tidak ada kode aplikasi, server, DNS, Neon, Redis, atau repository yang diubah melalui pembuatan paket ini.

| Milestone | Status | Bukti/ketergantungan |
| --- | --- | --- |
| M00 | accepted_baseline | Handoff repo menyatakan desain accepted; belum rerun melalui paket ini |
| M01 | planned | Migrasi repo belum dilakukan |
| M02 | planned | API/Neon integration belum dilakukan |
| M03 | planned | Redis/worker/outbox belum dilakukan |
| M04 | planned | Staging Hostinger belum diinspeksi/deploy |
| M05 | planned | Flutter belum dibuat |
| M06 | planned | Auth/registrasi belum operasional |
| M07 | planned | Pendataan/akun tambahan belum operasional |
| M08 | planned | Pengaduan/media belum operasional |
| M09 | planned | Aspirasi belum operasional |
| M10 | planned | Agenda/pengumuman/push belum operasional |
| M11 | planned | Tagihan/tarif belum operasional |
| M12 | planned | Payment offline/kas/export belum operasional |
| M13 | planned | CMS API belum operasional |
| M14 | planned | Website real publication belum terintegrasi |
| M15 | planned | UAT/full QA belum dilakukan |
| M16 | planned | Production baru belum dirilis |

M00 memakai label accepted_baseline khusus, bukan status passed hasil tes baru. M01–M16 menggunakan planned/in_progress/passed/blocked.

## Milestone aktif

Belum ada. Target pertama M01.

## Template laporan milestone

### MNN — Nama

- Status:
- Branch/commit/PR:
- Tanggal:
- Scope dan FR/BR:
- Dependency verified:
- File berubah:
- Endpoint/migration/config baru:
- Environment/data:
- Commands:
- Actual results:
- Evidence path:
- Acceptance gates passed:
- Acceptance gates blocked/failed:
- Security/finance/privacy implications:
- External mutation performed dan dasar otorisasi:
- Known limitations:
- ADR/doc updates:
- Next milestone:

## Checkpoint sesi

Catat Git status, perubahan pengguna yang belum commit, migration yang sudah applied,
image/tag deploy, jobs pending, dan credential input missing tanpa menuliskan nilainya.

Jangan mengulangi provisioning/migration/deploy yang sudah berhasil hanya karena sesi baru.
Selesaikan perubahan pengguna dengan hati-hati; tidak reset/force-push.

## Bug/risiko

| ID | Severity | Domain | Repro/bukti | Milestone | Status |
| --- | --- | --- | --- | --- | --- |
| — | — | — | Belum ada temuan implementasi baru; isi dari pemeriksaan nyata | — | — |

## Bukti historis frontend

Lihat docs/website/FRONTEND_HANDOFF.md setelah M01. Angka tes di handoff adalah bukti commit terdahulu, bukan otomatis hasil untuk HEAD yang baru.
