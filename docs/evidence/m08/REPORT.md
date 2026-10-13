# M08 — Pengaduan dan media: laporan

Branch `codex/m08-complaints-media` dari `origin/main` `80d8180` (M07 merged). Pemilik
mengotorisasi M08 lewat handoff 13 Oktober 2026; Claude memegang eksekusi M08 saja.

**Status: passed (13 Oktober 2026) untuk staging sintetis.** Gate teknis terpenuhi (lokal, CI,
staging live, Android) dan diverifikasi ulang pada hari penutupan; bagian Drive native selesai.
Pemilik menerima M08 lewat instruksi langsung (verifikasi ulang, uji emulator, merge PR #23).
Bukti dipisah per jenis. Bukan production, perangkat fisik, atau UAT mitra.

| Jenis bukti | Hasil | Rujukan |
| --- | --- | --- |
| Source/lokal | Backend **230/230, 2233 assertion**; Flutter **157**; pint/analyze/format/redocly/check-repo lulus (dijalankan ulang 13 Oktober) | bagian di bawah |
| CI | `d790ec2`, `6b6987c`, dan head PR `a3d80e5`: API, Infra, Mobile (verify + APK dev/staging), Repo SUCCESS | [LIVE_RESULTS.md](LIVE_RESULTS.md) |
| Staging live | Deploy additive `d790ec2` lalu `6b6987c`; API live **42/42, 130 assertion** pada dua run lama dan satu run ulang (`615CCB`); drill worker-media dua kali | [LIVE_RESULTS.md](LIVE_RESULTS.md) |
| Android | APK CI checksum cocok; dua putaran: izin kamera ditolak/diizinkan, galeri, foto diproses→siap, 409 draf tetap, anonim, pengurus, arsip, inbox, skala teks 2,0 | [ANDROID_RESULTS.md](ANDROID_RESULTS.md) |
| Drive native | Bagian M08 disisipkan ke panduan dan laporan existing lewat editor Google Docs, dibaca ulang lewat connector Drive | [DRIVE_DRAFT.md](DRIVE_DRAFT.md) |

## Kecocokan dengan PRD dan aturan bisnis

| Sumber | Syarat | Bukti |
| --- | --- | --- |
| PRD FR07 | Pengaduan menerima kategori, judul, deskripsi, foto, pilihan identitas privat | Live C05 (kategori, anonim, dua foto, status foto `diproses`); Android form (kategori, judul, isi, anonim, 2 foto) |
| PRD FR08 | Status diajukan → diproses → selesai berhistori dan bernotifikasi | Live C22–C30 (transisi, 409 basi, buka ulang beralasan, riwayat, inbox); Android riwayat status dan inbox menyebut tiap transisi |
| PRD FR21 | Kamera/galeri, batas file, penghapusan metadata, media privat di server | Live C06/C08 (foto siap, EXIF/GPS hilang, unduhan `private`), C10–C14 (spoof/SVG/GIF 415, >50 MP 413, 13 MB 422), C15 (tanpa laporan parsial); Android izin kamera ditolak/diizinkan, galeri |
| BR08 | Pengaduan terpisah dari aspirasi; dapat dibaca warga terdaftar | Endpoint dan tabel terpisah; akun menunggu/nonaktif 403/401 (C02/C03, C38–C41) |
| Anonimitas | `anonymous_to_residents` tanpa identitas ke warga lain pada detail, feed, notifikasi, media; pemilik melihat laporannya; pengurus melihat pelapor | Live C05, C07, C09, C21, C29–C31; Android feed/detail warga lain, riwayat "Warga", pengurus melihat pelapor |
| BR18 | Arsip, bukan hapus | Live C32–C37; Android arsip dengan alasan, pemilik 404, pengurus tetap melihat |
| Kontrak | Kegagalan email/push tidak menghapus transaksi | Enqueue gagal → laporan tetap + `rt05:media-recover` (tes M08ComplaintMediaTest) |

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
- Putaran 2 (13 Oktober, siang UTC): tidak ada bug baru. Inbox menyebut Diproses → Selesai → Diproses
  dengan urutan benar (perbaikan `6b6987c` terbukti ulang di perangkat). Satu temuan UI kecil: judul
  panjang terpecah di tengah kata pada skala 2,0 (teks tidak terpotong). Kamera tidak dimatikan
  Android pada putaran ini.

## Penerimaan ulang dan penutupan — 13 Oktober 2026

Dijalankan atas instruksi pemilik; tanpa perubahan source runtime dan tanpa deploy baru.

| Langkah | Hasil |
| --- | --- |
| Diff runtime `6b6987c`..`a3d80e5` | hanya `infra/scripts/m08-stage-acceptance.mjs` (tes inbox per transisi) |
| Tes lokal | 230/2233, Pint, Redocly, Flutter 157/analyze/format, check-repo 776 berkas |
| Staging (SSH baca-saja) | semua service `6b6987c`, sehat, `/health/ready` ready |
| API live | `Sintetis M08 615CCB`: 42/42, 130 assertion ([ringkasan](m08-live-summary-rerun-615CCB.json)) |
| Android | [ANDROID_RESULTS.md](ANDROID_RESULTS.md) putaran 2; screenshot `m08r2-*` |
| Drive | panduan (`18yoO1-…KlM`, heading M08 + M8.1–M8.5 + 8 gambar dengan caption) dan laporan (`1Dxax50K…`, bagian "Penerimaan M08") |
| Dokumentasi | PROGRESS, COORDINATION, REPORT, LIVE_RESULTS, ANDROID_RESULTS, DRIVE_DRAFT |

## Commit

- `d790ec2` feat(m08): worker-media, gap mobile, kontrak/dokumen.
- `6b6987c` fix(m08): status per transisi pada notifikasi; script live M08.
- Commit bukti ini: hanya `docs/` (tanpa perubahan source runtime).

## Keterbatasan diketahui

- Foto tidak dapat ditambah/diganti setelah laporan dibuat (v1 tidak meminta; ADR21).
- Push FCM tetap blocked (M10); M08 memakai inbox.
- Pemulihan draf/foto setelah Android menutup aplikasi saat kamera terbuka belum dibuat (M15).
- Judul panjang pada skala teks 2,0 terpecah di tengah kata karena lebarnya dibagi dengan chip status;
  teks tidak terpotong (putaran 2). Perbaikan layout tidak termasuk gate M08.
- Penerimaan putaran 2 memakai emulator headless 2 GiB; pada PC RAM kecil kamera tidak dimatikan
  Android, tetapi risiko pada ponsel RAM kecil tetap dicatat untuk M15.
- Production, UAT mitra, dan insiden credential historis (M16) di luar M08. Bukan production ready.
