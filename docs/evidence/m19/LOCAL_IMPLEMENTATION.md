# M19 — implementasi dan QA lokal

Tanggal: **27 Oktober 2026**. Branch `codex/m19-tombol-panik`, worktree `m17-verifikasi-1018`.
Status milestone **in_progress**. Otorisasi eksekusi langsung setelah rencana; ADR43/45 dan [M19_PLAN.md](../../M19_PLAN.md).
Pada checkpoint lokal ini tidak ada akses remote origin/fetch/pull/push, PR/merge/deploy, distribusi APK baru,
atau broadcast warga asli. **Checkpoint rilis sesudahnya:** [RELEASE_1023.md](RELEASE_1023.md).
Otorisasi rilis uji terbaru tercatat pada ADR45; angka/gate di bawah adalah bukti tahap lokal.

## Perubahan

- DB: `kejadian_darurat`, CHECK/status/versi/FK, satu aktif per akun, trigger mempertahankan asal dan penanganan terminal;
  migration down menolak menghapus riwayat. Konteks notifikasi baru; kontrak bukti iuran M15b tetap dipertahankan.
- Domain/API: kirim/penanganan/pengaturan memakai transaksi, satu advisory lock dan Idempotency-Key. Akun/rumah
  dari server, role segar, privasi wajib; tiga penilaian eksplisit memblokir pengiriman akun. Default teknis 3 akun/jam,
  10 RT/menit; replay tidak menambah kejadian/budget. Warga menerima data darurat sesuai scope, tanpa catatan pengurus.
- Outbox/inbox/push: generic tanpa nama/alamat; event+recipient unik. Event basi/terminal/fitur mati menahan pengiriman
  yang belum diserahkan provider. TTL sisa 10 menit. Penilaian penyalahgunaan memberi peringatan sistem, bukan alarm baru.
  Detail membedakan antrean/proses/gagal/dilewati/provider menerima; penerimaan provider bukan bukti HP berbunyi.
- Privasi: teks M19 27 Oktober, APK membawa versi 25/26/27; server memvalidasi teks yang ditampilkan. Website memakai
  JSON M19 hanya saat flag aktif. Teks M18 dan preview default tetap 26 Oktober. Cache idempotensi hanya ID, sehingga
  tidak menyimpan salinan nama/alamat setelah anonimisasi. Riwayat merujuk data master, bukan snapshot nama/alamat.
- Mobile: tiga langkah, Batal, retry body/key tetap, perlindungan sesi lama, riwayat/detail, pengaturan pengurus dan
  penilaian palsu terpisah dari penyalahgunaan. Shortcut menunggu login/privasi lalu membuka langkah pertama saja.
  Kanal Android khusus dengan bunyi sistem alarm/getar dan isi generik; tanpa GPS/full-screen/override DND.
- Kontrak: OpenAPI, API.md, ERD, aturan bisnis, rencana, strategi tes, progress dan handoff diperbarui. Compose
  meneruskan flag API/workers/web; default off dan audience restricted kosong. Tidak ada dependency aplikasi baru.

## Verifikasi

| Pemeriksaan | Hasil |
| --- | --- |
| Backend regresi sebelum status pengiriman detail | 430/430, 4.509 assertions; PostgreSQL lokal 54329, role runtime |
| Backend final `php artisan test --compact` | **431/431**, 4.518 assertions, 171.541 ms; PostgreSQL lokal 54329 |
| Tes mobile M19 | 9/9, termasuk shortcut menunggu privasi, false bukan otomatis abuse, retry dan sesi berganti |
| Flutter suite sebelum penambahan teks status pengiriman | 335/335 |
| Flutter final `flutter test --no-pub --concurrency=2` | **335/335**, 73 detik |
| `flutter analyze --no-pub` / `dart format --output=none --set-exit-if-changed lib test` | Bersih; 101 berkas, 0 berubah |
| Website `php artisan test --compact` | 41/41, 549 assertions |
| Website `npm.cmd run build:preview` | PASS, 24 rute; output preview default tidak berubah |
| Pint | PASS |
| OpenAPI `npx.cmd --yes @redocly/cli@2 lint docs/openapi.yaml` | PASS tanpa warning |
| `node infra/scripts/check-m03-compose.mjs` | PASS |
| `node infra/scripts/check-repo.mjs` / `git diff --check` | PASS, 1.094 berkas; pemeriksaan akhir diulang sebelum commit bukti |
| Build APK Android debug lokal | **PASS**, awal 439,6 detik; rebuild manifest/ikon 14,9 detik. Bukan APK rilis/distribusi |
| Manifest APK hasil rebuild | `tech.morriz.takeda`, VIBRATE dan POST_NOTIFICATIONS ada; tanpa izin lokasi/full-screen |

Tes backend menjalankan kasus konfirmasi/terms/jenis, rumah tertaut, akun pending/nonaktif, privasi, fitur off,
audience terbatas, replay, event basi/ditutup, pembatasan laju DB, 3 penyalahgunaan, status pengiriman dan SQL guards.
Proses PHP independen menguji key sama/berbeda dan dua pengurus menilai penyalahgunaan ketiga. Regresi M18 tetap
mencakup uang, konkurensi, rekonsiliasi dan skala 300 rumah × 12 bulan; angka final mengikuti output aktual tes.

Bug yang ditangkap dan diperbaiki selama QA: default status belum dimuat ke respons; fungsi trigger tertinggal saat
database tes dibuat ulang; CHECK konteks semula menghilangkan pengecualian iuran M15b. Tes shortcut menggunakan
jalur update persetujuan yang sebenarnya; restore/login ulang tetap membuang antrean milik sesi lama untuk keamanan.
Pemeriksaan APK juga menangkap izin VIBRATE yang belum tercantum; manifest dan ikon monokrom darurat diperbaiki,
build ulang lulus. Payload FCM darurat memakai ikon yang sama, jalur notifikasi biasa tetap semula.

APK debug lokal akhir: 176.557.308 byte, SHA-256
`7a9dbb4492e2e88a85f29ff76c98796e47e340467f83769ffecc3d513468cae4`,
di `mobile/build/app/outputs/flutter-apk/app-debug.apk` (diabaikan Git). Tidak dipasang menimpa APK rilis, tidak
diunggah/disajikan ke Drive atau /unduh, dan tidak diberi nomor rilis baru. Bukti ini memverifikasi kompilasi Android,
bukan penerimaan bunyi/shortcut di perangkat nyata.

## Lingkungan build dan batas bukti

Cache transform Gradle global bermasalah; upaya rename ditolak OS, cache itu tidak dihapus/diubah. Build memakai
GRADLE_USER_HOME terisolasi di temp. NDK 28.2 semula hanya folder installer, lalu unduhan Java terputus (Connection reset).
Folder parsial dipertahankan. NDK resmi diunduh terpisah, diverifikasi terhadap metadata repository Android:
748.118.221 byte, SHA-1 `086bba43ff2f5eb0e387b15c8278bb4e0d89ba1d`, lalu dipasang sesuai revisi. Build akhir dicatat di tabel.
Ini perbaikan alat lokal; tidak ada perubahan versi Flutter/Laravel/PostgreSQL atau konfigurasi Git/auth.
Android SDK Platform 35 dan CMake 3.22.1 dipasang otomatis oleh build. Peringatan kompatibilitas KGP firebase_core
untuk versi Flutter mendatang tetap tercatat; tidak menghalangi build saat ini dan dependency tidak dinaikkan di sesi ini.
PostgreSQL lokal/daemon Gradle khusus tugas dihentikan setelah verifikasi; pgdata yang sebelumnya dipertahankan
tidak dihapus. Emulator tidak dijalankan atau di-wipe.

## Gate yang belum selesai

1. Review kandidat, CI dan APK bertanda tangan belum dilakukan karena sesi ini tetap lokal tanpa origin. Jangan
   memakai jalur remote lain untuk melewati instruksi itu. Kandidat lokal bukan kode yang sudah merged/deployed.
2. Staging M19 belum dideploy atau diaktifkan. Rilis terakhir tetap API `b6e1c4b` dan APK **1021** berdasarkan bukti
   rilis sebelumnya; status live tidak diperiksa ulang sesi ini. Saat uji selanjutnya, allowlist akun sintetis saja.
3. Belum emulator integrasi M19, HP nyata, bunyi layar terkunci/aplikasi tertutup/force-stop, izin ditolak, atau UAT
   pengurus. Widget/build tidak membuktikan alarm bekerja. Gate itu wajib sebelum status M19 passed.
4. Tidak ada reset pemblokiran, koreksi penilaian terminal atau fitur tambahan yang belum diputuskan; jangan menambah
   otomatis. Jadwal beta/production M16 tetap memerlukan penilaian kesiapan setelah hasil M19.
