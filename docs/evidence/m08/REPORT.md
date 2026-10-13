# M08 — Pengaduan dan media: laporan

Branch `codex/m08-complaints-media` dari `origin/main` `80d8180` (M07 merged). Pemilik
mengotorisasi M08 lewat handoff 13 Oktober 2026; Claude memegang eksekusi M08 saja.

**Status: gate teknis terpenuhi (lokal, CI, staging live, Android); publikasi Drive BLOCKED.**
Penerimaan akhir M08 menunggu coordinator/pemilik dan bagian Drive. Bukti dipisah per jenis.

| Jenis bukti | Hasil | Rujukan |
| --- | --- | --- |
| Source/lokal | Backend **230/230, 2233 assertion**; Flutter **157**; pint/analyze/format/redocly/check-repo lulus | bagian di bawah |
| CI | `d790ec2` dan `6b6987c`: API, Infra, Mobile (verify + APK dev/staging), Repo SUCCESS | [LIVE_RESULTS.md](LIVE_RESULTS.md) |
| Staging live | Deploy additive `d790ec2` lalu `6b6987c`; API live **42/42, 130 assertion** (run kedua); drill worker-media | [LIVE_RESULTS.md](LIVE_RESULTS.md) |
| Android | APK CI checksum cocok; izin kamera ditolak/diizinkan, galeri, foto diproses→siap, 409 draf tetap, anonim, pengurus, arsip, inbox | [ANDROID_RESULTS.md](ANDROID_RESULTS.md) |
| Drive native | **BLOCKED**: connector Google Docs tidak tersedia di sesi ini; draf siap sisip, Doc existing tidak diubah | [DRIVE_DRAFT.md](DRIVE_DRAFT.md) |

## Inventaris gap (sebelum implementasi)

Sudah ada dari pekerjaan lama: endpoint pengaduan/riwayat/status/arsip, anonim pada feed/detail,
alur status + buka ulang beralasan, versi 409, notifikasi status ke pelapor lewat outbox, foto
privat, form/detail Flutter.

| Gap | Penanganan |
| --- | --- |
| Foto ditranskode sinkron di request; tanpa status/worker/retry | `Photo::stage` (validasi + berkas mentah privat) → `ProcessComplaintPhoto` antrean media → `siap`/`gagal`; `rt05:media-recover` (ADR21) |
| Spoof: magic bytes tidak dicocokkan dengan tipe header | Tipe `getimagesize` wajib sama dengan magic bytes |
| Respons create `versi`/`created_at` null | `refresh()` setelah commit (ditemukan validasi skema OpenAPI) |
| Laporan arsip masih dapat diubah status/diarsip ulang | 409 `ALREADY_ARCHIVED` di bawah row lock |
| Riwayat menyebut "Pengurus RT" untuk pelapor anonim yang pengurus (petunjuk tidak langsung) | Entri oleh pelapor anonim selalu "Warga" bagi warga lain |
| Mobile: tidak ada edit pemilik / draf 409 | Form edit; 409 mempertahankan teks + memuat versi terbaru; 403 terkunci menonaktifkan Simpan |
| Mobile: CAMERA tidak dideklarasikan → tidak ada alur izin | Manifest `CAMERA` (feature opsional); pesan `camera_access_denied` + galeri tetap |
| Mobile: status foto, arsip pengurus | Placeholder diproses/gagal, tarik untuk muat ulang; tombol arsip beralasan; filter "Termasuk arsip" |
| OpenAPI pengaduan generik | Skema `Complaint*`, request body, kode domain |

## Source dan tes lokal

PC: PHP 8.4.13, Postgres 17.10 sementara di scratchpad sesi (`127.0.0.1:54329`, `rt05_test`,
salinan cluster tes M07; bukan Neon), Flutter SDK lokal.

| Perintah | Hasil |
| --- | --- |
| `php artisan test` (rest-api) | **230/230, 2233 assertion** (setelah fix notifikasi `6b6987c`; sebelumnya 229/2229 di `d790ec2`) |
| `php artisan test --filter=M08ComplaintMediaTest` | 10/10 — notifikasi terlambat menyebut tiap transisi; status diproses→siap, EXIF/GPS sintetis hilang, replay tidak mengubah, spoof/SVG/HTML/GIF 415, >50 MP 413, >12 MB 422, tanpa berkas tersisa; gagal permanen/habis; enqueue gagal → laporan tetap + recover; foreign parent 404; pelapor pengurus anonim; pending/nonaktif 403; arsip; 409 edit |
| `vendor/bin/pint --test` | passed |
| `npx @redocly/cli@2 lint docs/openapi.yaml` | valid |
| `flutter analyze` / `dart format --set-exit-if-changed` | No issues / 0 changed |
| `flutter test` | **157 lulus** (152 lama + 5 M08) |
| `node infra/scripts/check-repo.mjs` | OK |

Test M07 rollback disesuaikan agar me-rollback M07 beserta migration sesudahnya (`--step` dihitung).

## Temuan selama penerimaan

- Notifikasi status yang diproses terlambat menyebut status terkini, bukan status transisi
  (terlihat di inbox Android). Diperbaiki `6b6987c`, tes regresi, dibuktikan ulang di live.
- Android dapat menutup aplikasi debug saat kamera terbuka pada perangkat RAM kecil; draf hilang
  (keterbatasan, bukan kebocoran data).

## Commit

- `d790ec2` feat(m08): worker-media, gap mobile, kontrak/dokumen.
- `6b6987c` fix(m08): status per transisi pada notifikasi; script live M08.
- Commit bukti ini: hanya `docs/` (tanpa perubahan source runtime).

## Keterbatasan diketahui

- Foto tidak dapat ditambah/diganti setelah laporan dibuat (v1 tidak meminta; ADR21).
- Push FCM tetap blocked (M10); M08 memakai inbox.
- Pemulihan draf/foto setelah Android menutup aplikasi saat kamera terbuka belum dibuat (M15).
- Publikasi Google Doc native M08 tertunda connector Google Docs.
- Production, UAT mitra, dan insiden credential historis (M16) di luar M08. Bukan production ready.
