# Keputusan dan ADR

## Prioritas sumber

1. Instruksi langsung terbaru pemilik.
2. Kebutuhan bisnis yang terverifikasi dari diskusi/logbook.
3. Baseline frontend yang diterima dan handoff terbaru.
4. Keputusan rekayasa v1 dalam paket ini.
5. Dokumen/asisten lama yang belum terverifikasi.

Konflik tidak diselesaikan dengan menyalin keputusan lama yang tampak paling teknis. SQLite/Drizzle/web-only pernah muncul dalam rancangan lama, tetapi paket kini mengikuti permintaan eksplisit website+mobile+REST API+Neon+Redis+Hostinger.

## Kebutuhan produk dipertahankan

| ID | Keputusan | Dasar |
| --- | --- | --- |
| P01 | Nama RT05 TAKEDA | Nama final proyek |
| P02 | Website publik tanpa akun, layanan warga mobile | Logbook 2 dan diskusi |
| P03 | Iuran Rp75.000/rumah/bulan, offline, tanpa gateway | Koreksi eksplisit pembayaran |
| P04 | Pengurus admin setara | Logbook 2/diskusi |
| P05 | Akun tambahan approval dan link keluarga/rumah | Logbook 2 |
| P06 | Token 6 digit acak dan rotasi | Logbook 2/diskusi |
| P07 | Anonim ke warga, terlihat admin | Logbook 2/diskusi |
| P08 | Arsip/audit/transparansi/histori ≥12 bulan | Logbook 2/diskusi |
| P09 | Approved frontend dipertahankan; CMS Artikel/Dokumentasi | Handoff repo terbaru |
| P10 | Paket konteks dan milestone dikirim di chat, tidak edit GitHub pada sesi desain | Instruksi 10 Oktober 2026 |
| P11 | Domain morriz.tech | Koreksi typo pemilik 10 Oktober 2026 |
| P12 | Jumlah developer tidak membatasi rancangan | Koreksi pemilik |
| P13 | Vercel tetap preview; production di subdomain morriz.tech (rancangan takeda.morriz.tech) pada VPS Hostinger | Instruksi pemilik di sesi M01, 10 Oktober 2026 |
| P14 | Pemilik menawarkan akses panel Hostinger/DNS lewat Chrome dan emulator Android untuk tahap berikutnya | Instruksi pemilik di sesi M01; dipakai pada M04/M05 dengan konfirmasi per tindakan eksternal |

## ADR rekayasa v1

### ADR01 — Modular monolith dan empat komponen

Status: dipilih untuk eksekusi v1.
website/mobile/rest-api/docs pada satu Git repository; infra/ untuk dukungan.
Laravel API memiliki domain/database; web mempunyai shell renderer saja.
Alasan: mempertahankan frontend, mengurangi duplikasi bisnis, deploy terpisah tanpa microservices.

### ADR02 — Stack

Laravel 13/Eloquent/PostgreSQL Neon, Blade/Tailwind existing, Flutter Android.
Flutter adalah pilihan rekayasa paket ini; handoff sebelumnya belum menetapkan framework.
Redis queue/cache dan Docker Hostinger. Tidak memakai Express/Nest atau database bisnis kedua.
Versi pasti dipin setelah kompatibilitas diverifikasi; tidak mengubah lockfile accepted untuk mengejar versi terbaru.

### ADR03 — Same-origin API

Host takeda.morriz.tech, API /api/v1, csrf route ke API.
Alasan: menyederhanakan cookie/CSRF/CORS CMS dan konfigurasi mobile.
Staging host/data/secrets terpisah. Host ini rancangan; belum klaim DNS telah aktif.

### ADR04 — Auth

Sanctum bearer per device mobile; cookie session+CSRF CMS.
API role/status policy selalu menjadi otoritas. Tidak menggunakan Neon Auth tambahan pada v1; Neon hanya database untuk arsitektur ini.
Token registrasi bukan API token.

### ADR05 — Keuangan sinkron dan immutable

DB transaction/row locks/constraints/idempotency untuk offline payment.
V1 full-month settlement; partial/overpay ditolak karena kebijakannya belum disepakati.
Koreksi reversal/replacement, kas signed entries, audit.
Alasan: menjaga rekam asal dan mencegah retry/concurrency menggandakan uang.

### ADR06 — Redis+outbox

Antrean dan cache terpisah. Outbox durable di Postgres, jobs at-least-once, consumer idempotent.
Payment commit tidak bergantung push/email sukses.
Polling outbox tiap menit dapat menahan compute Neon tetap aktif; evaluasi biaya/interval setelah pengukuran. Jangan menjanjikan scale-to-zero sambil rutin mengakses DB.

### ADR07 — Storage

VPS persistent volume + offsite encrypted backup; filesystem adapter memudahkan pindah ke S3 bila kebutuhan muncul.
Private upload terlebih dahulu; publik hanya published media. Tidak menambah MinIO/S3 berbayar untuk mulai.

### ADR08 — Android first

Satu aplikasi warga/pengurus; APK signed melalui HTTPS. iOS/Play Store lanjutan.
Runtime Flutter berada di HP; VPS menyediakan layanan dan artifact download.

### ADR09 — Iterasi dan staging awal

M00 baseline +16 milestone lanjutan. Staging fondasi M04, production M16.
Gate berdasarkan perilaku nyata, bukan jumlah developer/jumlah LOC.

### ADR10 — Batas administratif v1

Akun utama dapat mengedit keluarga sendiri, tambahan memakai layanan setelah approval.
Grant admin hanya operator command; seluruh admin aplikasi hak setara.
House billing start/enabled manual; tidak menghapus tunggakan ketika penghuni pindah.
Detail ini dipilih sebagai default terbatas, bukan diklaim persetujuan mitra baru.

### ADR11 — Migrasi monorepo dengan git mv di repository asal

Tanggal/status: 10 Oktober 2026, diterapkan pada M01.
Keputusan: frontend dipindah ke `website/` dengan `git mv` di repository `morrispes5/RT-05-TAKEDA`, bukan ZIP/subtree ke repo baru. Dokumentasi frontend ke `docs/website/`, workflow ke `.github/workflows/website.yml` (working-directory `website`), pemeriksaan repo di `.github/workflows/repo.yml`.
Alasan: Git history dan release tag tetap satu; `git log --follow` tetap bekerja; tidak ada `.git` bersarang.
Terdampak: semua perintah web dijalankan dari `website/`; `scripts/package-frontend.ps1` kini mengarsipkan subtree `website/` dengan prefix ZIP `web/` yang sama.

### ADR12 — Jembatan vercel.json root selama transisi

Tanggal/status: 10 Oktober 2026, sementara.
Keputusan: `website/vercel.json` kanonik (`outputDirectory: preview`). `vercel.json` root (`outputDirectory: website/preview`) dipertahankan sampai pemilik mengubah Root Directory proyek Vercel menjadi `website`; `infra/scripts/check-repo.mjs` menjaga kesetaraan keduanya.
Alasan: M01 tidak boleh mengubah integrasi Vercel eksternal, tetapi merge ke `main` tidak boleh memutus preview live.
Penghapusan: setelah Root Directory diubah dan `check-live` lulus, hapus file root dalam PR terpisah. Instruksi: docs/website/VERCEL_ROOT.md.

## Input operasional yang belum tersedia

| Input | Ditangani pada | Dampak |
| --- | --- | --- |
| Neon project/branches/credentials actual | M02/M04 | Local bisa jalan; Neon gate blocked sampai tersedia |
| VPS access/proxy/resources actual | M04 | Images/runbook bisa dibuat; deploy live perlu akses |
| DNS morriz.tech dan otorisasi record | M04/M16 | Tidak menebak IP atau mengubah apex |
| SMTP credentials | M06/M10 | Adapter local tersedia; reset real belum passed |
| FCM project/service account | M10 | Inbox jalan; push real belum passed |
| Offsite backup destination/key | M16 | Production recovery gate belum passed |
| Android app ID/signing key | M05/M15 | Pin app ID tanpa konflik; release perlu key aman |
| Kontak nyata/privacy retention mitra | M15 | Tidak memasukkan data nyata sebelum notice final |
| ERD/prototype lama lengkap | M02 | Reconcile bila tersedia; tidak mengarang isi file |
| Flutter SDK di PC pengembang | M05 | Tidak ditemukan pada inspeksi M01; Android SDK, AVD Pixel_7a/Pixel_8a, dan JBR Android Studio tersedia |
| Akses proyek Vercel preview dari connector agent | M01/M14 | Proyek tidak terlihat di team `morriz`; perubahan Root Directory dilakukan pemilik di dashboard |

## Template perubahan

ADR-NN — Judul
Tanggal/status:
Trigger:
Keputusan sebelumnya:
Keputusan baru:
Alasan/tradeoff:
Dokumen/endpoint/migration/mobile/web terdampak:
Rencana kompatibilitas dan pengujian:
Sumber instruksi pemilik:

Catat sumber apa adanya; keputusan agent tidak dilabeli sebagai persetujuan RT yang belum diberikan.
