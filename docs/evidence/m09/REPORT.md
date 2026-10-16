# M09 — Aspirasi: laporan

Branch `codex/m09-aspirations` dari `main` `79df581` (M08 merged). Pemilik meminta lanjut ke M09 dan
menyelaraskannya dengan PRD, dokumen `.md`, dan capstone (13 Oktober 2026).

**Status: passed untuk staging sintetis (gate teknis).** Bukan production, perangkat fisik, atau UAT
mitra. Bagian Google Doc native untuk M09 **belum dikerjakan** (menunggu konfirmasi pemilik; bukan gate
ROADMAP).

| Jenis bukti | Hasil | Rujukan |
| --- | --- | --- |
| Source/lokal | Backend **245/245, 2414 assertion** (15 tes M09 baru); Flutter **164** (7 tes M09 baru); Pint, analyzer, format, Redocly, check-repo lulus | bagian di bawah |
| CI | Head `617e179`: API, Infra, Mobile verify, APK staging, Repo, Vercel SUCCESS. APK dev gagal sekali karena `sdkmanager` di runner (infrastruktur), lulus pada rerun job yang sama | PR #24 |
| Staging | Rollout `617e179` (tanpa migration: "Nothing to migrate"), 0 baris GAGAL, 9 container sehat, `/health/ready` ready | bagian Staging |
| API live | **48 kasus, 138 assertion PASS** (`Sintetis M09 F21B1E`) | [m09-live-summary-617e179.json](m09-live-summary-617e179.json) |
| Android | APK CI checksum cocok; emulator Pixel 8a; alur warga, warga lain, pengurus, inbox, offline, skala teks 2,0 | [ANDROID_RESULTS.md](ANDROID_RESULTS.md) |

## Inventaris gap (sebelum implementasi)

Aspirasi sudah ada dari jalur lama (tabel `aspirasi`/`riwayat_aspirasi`, endpoint, layar Flutter bersama
dengan pengaduan). Audit terhadap gate ROADMAP M09 menemukan:

| Gap | Bukti | Penanganan |
| --- | --- | --- |
| Respons aspirasi tanpa `archived_at` | Tes M09 gagal; di mobile `diarsipkan` selalu null sehingga pengurus tidak melihat banner arsip dan tombol aksi tetap tampil | `Present::aspirasi` memuat `archived_at` (terisi hanya untuk pengurus); dibuktikan di Android |
| Filter `status` tidak valid → 200 kosong | Tes M09 gagal | 422 untuk aspirasi dan pengaduan |
| OpenAPI aspirasi generik (`Data`) | Tidak ada skema, body, filter, kode domain | Skema `Aspiration*`, `ReportHistory*`, filter, kode domain; tes memvalidasi respons terhadap skema |
| Hanya 1 tes aspirasi | Gate meminta konfirmasi sesudah commit, transisi, anonimitas, edit pemilik, dedup notifikasi, filter/pagination | `M09AspirationTest` (15), `m09_aspirations_test.dart` (7), skrip live 48 kasus |
| Teks layar aspirasi memakai "laporan/pelapor/foto" | Pemeriksaan layar | Teks khusus aspirasi |
| Judul panjang terpecah di tengah kata pada skala 2,0 (temuan M08) | Android M08 | Chip status pindah di bawah judul pada skala >1,3; dibuktikan di Android |

Yang sudah benar dan terbukti tanpa perubahan: transaksi tunggal (aspirasi, riwayat, audit, outbox),
lock baris + versi (409), transisi dan buka ulang beralasan, arsip, anonimitas (detail, feed, riwayat,
notifikasi; pengirim pengurus anonim tetap "Warga"), notifikasi per transisi tanpa duplikat.

## Source dan tes lokal

PC: PHP 8.4.13, Postgres 17.10 sementara di scratchpad (`127.0.0.1:54329`, `rt05_test`; bukan Neon), Flutter 3.47.7.

| Perintah | Hasil |
| --- | --- |
| `php artisan test` (rest-api) | **245/245, 2414 assertion** (sebelumnya 230/2233 + 15 tes M09) |
| `php artisan test --filter=M09AspirationTest` | 15/15, 179 assertion; 4 gagal sebelum perbaikan (archived_at 3 kali, filter status 1 kali) |
| `vendor/bin/pint --test` | passed |
| `npx @redocly/cli@2 lint docs/openapi.yaml` | valid |
| `flutter analyze` / `dart format --set-exit-if-changed lib test` | No issues / 0 changed |
| `flutter test` | **164 lulus** (157 + 7 M09) |
| `node infra/scripts/check-repo.mjs` | OK (800 berkas) |

## Staging

| Waktu UTC | Langkah | Hasil |
| --- | --- | --- |
| 14:51 | Script `infra/scripts/deploy-staging-immutable.sh` pada `617e179` (hanya allowlist branch M09 yang ditambahkan) disalin ke `/root/.rt05/deploy-staging-immutable-m09.sh` mode 700; SHA-256 `aa50ca1a629ba5a7b0c29b7c24ac357118a96f5ded6246a35f55e693b6c52c75` sama dengan lokal; `bash -n` OK; tanpa CRLF | |
| 14:51–15:01 | Deploy `617e179…` | `Selesai`, 0 baris GAGAL, "Nothing to migrate". Semua service `rt05-*:617e179…`, sehat; sebelumnya `6b6987c` |
| 15:03 | API live | 48/48 kasus, 138 assertion |

Hanya project Docker `rt05-staging`; 13 container layanan lain tidak disentuh; tanpa DNS/firewall/port/production.
Rollback: `/root/.rt05/deploy-staging-immutable-m09.sh rollback`.

## Kecocokan PRD, aturan bisnis, dan capstone

| Sumber | Syarat | Bukti |
| --- | --- | --- |
| PRD FR09 | Aspirasi terpisah dari pengaduan; kirim, tampil, tindak lanjut, histori | Live A07–A33; tabel/endpoint terpisah; `GET /complaints/{id aspirasi}` 404 (A33); Android form, feed, detail, riwayat |
| PRD FR08/FR20 (aspirasi) | Status berhistori; notifikasi inbox | Live A24–A27 (riwayat aman, inbox pengirim tiga entri tanpa duplikat, pengurus mendapat satu "Aspirasi baru") |
| BR08 | Aspirasi terpisah; dapat dibaca warga terdaftar | Akun menunggu/nonaktif 403/401 (A02, A03, A45) |
| BR09 + status | `diajukan → ditinjau → selesai`; buka ulang beralasan; edit pemilik hanya saat diajukan; anonim terhadap warga lain | Live A09–A23, A35; tes M09 |
| BR18 | Arsip, bukan hapus | Live A36–A43; Android banner arsip |
| ROADMAP M09 "confirmation nyata setelah commit" | Pesan sukses hanya sesudah server menerima | Tes Flutter; Android offline: tidak ada konfirmasi, isian tetap, tanpa duplikat; setelah jaringan pulih terkirim sekali |
| Capstone Tugas 03 | Tabel `aspirasi` (`judul`, `isi`, `status`), `riwayat_aspirasi`, notifikasi tindak lanjut pengurus | Sesuai; selisih (anonimitas aspirasi, tiga status, kolom `versi`) dicatat di [CAPSTONE_ALIGNMENT.md](../../CAPSTONE_ALIGNMENT.md) dan ADR22 |

## Keterbatasan diketahui

- Aspirasi tidak punya kategori dan lampiran (sesuai ERD capstone; bukan kekurangan).
- Preferensi notifikasi per jenis (`preferensi_notifikasi` di capstone) belum diterapkan; inbox memakai v1 tanpa preferensi (M10).
- Push FCM menunggu M10. Pemulihan draf bila proses aplikasi dimatikan Android ke M15.
- Data uji sintetis tertinggal di staging (dua aspirasi skrip live, dua aspirasi Android; sebagian diarsipkan).
- Bukan production, perangkat fisik, atau UAT mitra. Insiden credential historis tetap terbuka untuk M16.
- Google Doc native M09 belum dikerjakan.

## Commit

- `617e179` feat(m09): complete aspirations with contract, archive flag and tests.
- Commit bukti ini: hanya `docs/` (tanpa perubahan source runtime).
