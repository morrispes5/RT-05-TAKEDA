# M10 — Push Android nyata di staging (17 Oktober 2026)

Uji di **HP Android milik pemilik** (bukan emulator, atas permintaan hemat RAM). APK dipasang dari Google Drive
pemilik, karena pemilik bekerja dari luar rumah dan tidak bisa mencolok HP ke PC. Akun **warga sintetis** staging (data
uji, bukan warga). Pengumuman uji dikirim dari akun pengurus sintetis lewat API, lalu diarsipkan setelah uji
(riwayat inbox tetap). Tidak ada token, email, atau ID akun di dokumen ini.

## Konfigurasi

| Hal | Nilai |
| --- | --- |
| Firebase | Project `rt05-takeda` (Spark, gratis), Analytics mati, app Android `tech.morriz.takeda`, FCM API (V1) aktif |
| Server | Staging `a86a497` (PR #30 + PR #31), deploy immutable, migration `2026_10_17_000100_create_perangkat_push_table` DONE, 0 GAGAL; `push_configured=true` di `api` dan `worker-default` |
| Kredensial | Service account di env privat `/root/.rt05/.env.staging` (mode 600) sebagai base64; tidak ada di Git atau log |
| APK | CI `workflow_dispatch` run 39011796586, flavor staging, empat nilai `FIREBASE_*` dari GitHub repository variables; SHA-256 artefak cocok |

## Hasil

| Uji | Kirim (WIB) | Inbox dibuat | Status push | Hasil di HP |
| --- | --- | --- | --- | --- |
| Perangkat terdaftar | — | — | 1 baris `perangkat_push` (android) | Izin notifikasi diberikan saat login |
| Aplikasi terbuka | 14.33.26 | 14.34.02 | terkirim 1, dilewati 11 (akun tanpa HP) | Alert info di dalam aplikasi muncul |
| Latar belakang | 16.08.57 | 16.10.03 | terkirim 1, dilewati 11 | Notifikasi sistem muncul; ketuk → inbox → detail pengumuman |
| Ditutup (digeser dari aplikasi terbaru) | 16.59.56 | 17.00.02 | terkirim 1, dilewati 11 | Muncul ±40 detik kemudian; ketuk → layar pembuka → detail pengumuman |
| Izin ditolak | — | — | — | Pengaturan notifikasi menampilkan "Diblokir" + panduan; layanan dan inbox tetap berjalan |

Jeda inbox 6–66 detik mengikuti profil VPS (dispatcher outbox tiap menit). Jalur shared (setelah response) diuji lokal
oleh `SharedRuntimeTest`, belum di host shared nyata.

## Temuan dan tindak lanjut

- **Play Protect** memblokir APK debug dari "developer tak dikenal". Untuk uji dipakai "Tetap instal". Untuk warga,
  pemilik menetapkan rilis **tanpa Play Store**: kunci rilis tetap, verifikasi developer Android, dan aplikasi
  terdaftar. Ini syarat M15/M16 (verifikasi developer berlaku di Indonesia sejak 30 September 2026 untuk toko yang
  ikut serta).
- **Aplikasi ditutup**: sebagian merek Android menahan push setelah aplikasi digeser. Panduan "Agar notifikasi tidak
  terlewat" (baterai tidak dibatasi, mulai otomatis) ditambahkan di Pengaturan notifikasi (PR #32).
- Saat uji, pemilik meminta revisi: kas RT khusus pengurus dan transparansi iuran tersembunyi secara bawaan (ADR26, PR #32).

## Batas

Penerimaan staging sintetis, satu HP. Bukan production, UAT warga, atau beban massal. Tautan basi/tidak berhak diuji
di API dan widget test, bukan di HP. FCM tidak menjamin exactly-once; duplikat digantikan lewat tag Android.
