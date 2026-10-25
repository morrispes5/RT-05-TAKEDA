# Verifikasi ulang M17 — APK 1018

Tanggal: 25 Oktober 2026, sesi Codex; uji Android sekitar 18.41–18.51 WIB.
Baseline kode `cd4a21f` (merge PR #80), identik secara tree dengan `c91192f`.
APK 1.0.0 build 1018 dari halaman unduh staging, versionCode 3018 (arm64).
SHA-256 `d5867a1b0b1f2e294d13b2a4f3364bf0049bdee5e7ff169107d5b0544c3d33b4`;
ukuran 22.743.473 byte, sesuai `/unduh/versi.json`.

## Keputusan penerimaan

Pemilik memilih **“Ya, emulator cukup”** untuk menutup M17 jika seluruh tes dan uji emulator lulus.
Ini adalah penerimaan staging + emulator, **bukan bukti HP nyata atau UAT mitra**.
Tidak ada perubahan kode aplikasi, deployment, APK baru, atau mutasi peran akun dalam sesi ini.

## Tes otomatis yang dijalankan ulang

| Perintah / lingkungan | Hasil |
| --- | --- |
| `rest-api`: `php artisan test` | **358/358 PASS**, 3.801 assertion, 128,582 detik |
| `rest-api`: `php vendor/bin/pint --test` | PASS |
| `website`: `php artisan test` | **40/40 PASS**, 538 assertion, 19,746 detik |
| `mobile`: `flutter analyze` | Tidak ada masalah |
| `mobile`: `dart format --output=none --set-exit-if-changed lib test` | 86 file, 0 perubahan |
| `mobile`: `flutter test --no-pub --reporter compact` | **293 PASS** |
| `node infra/scripts/check-repo.mjs` | PASS sebelum perubahan; diulang untuk commit dokumentasi |

Backend memakai PostgreSQL 17.10 sementara di `127.0.0.1:54329/rt05_test`, role migrasi/runtime terpisah.
URL tes ditetapkan eksplisit ke localhost; tidak memakai `--env=testing` maupun Neon untuk tes otomatis.
Binari PostgreSQL lama tidak lengkap (DLL tidak tersedia), sehingga paket embedded-postgres yang sama diunduh
ke direktori sementara sesi. Cluster baru dibuat khusus sesi dan dihentikan sesudah tes. Penghapusan pgdata ditolak kebijakan alat;
folder sementara tetap lokal (bukan Git), tidak ada listener database yang tertinggal.
Tes website awal gagal membuka autoload dalam sandbox, lalu berhasil dijalankan ulang di lingkungan lokal tanpa sandbox.

## Pemeriksaan staging tanpa login

Dilakukan 18.48 WIB lewat HTTPS, tanpa menulis nomor kontak ke bukti:

| Pemeriksaan | Hasil |
| --- | --- |
| `/health/ready` | 200, status ready, database ok |
| `/api/v1/public/contacts` | 200, tepat satu field `ketua_whatsapp`; cocok dengan referensi privat pemilik |
| `/kontak` | 200, Agus Ferdiansyah dan tautan WhatsApp cocok dengan referensi privat |
| `/profil` | 200, nama Wirawan tampil |

## Uji emulator Android

AVD existing `Pixel_8a`, headless, RAM 2 GiB, 2 core, tanpa wipe/uninstall. `adb install -r` sukses;
AVD sudah memakai 3018 sebelum pemasangan ulang, jadi ini **reinstall in-place 1018→1018**, bukan bukti upgrade dari 1015.
Sesi warga dan data keluarga tetap tersedia setelah pemasangan.

| Kasus | Bukti hasil |
| --- | --- |
| Akun warga utama → Akun | Kartu profil dan papan rumah menampilkan avatar Keluarga |
| Ketuk avatar rumah | Lembar berisi 15 avatar dan pilihan hapus tampil |
| Pilih Ibu berhijab → tutup paksa aplikasi → buka lagi | Avatar Ibu berhijab tampil di kartu profil atas dan papan rumah; GET household mengonfirmasi nilai tersimpan |
| Daftar anggota keluarga | Anggota sintetis menampilkan “Anak · 7 tahun” |
| Masuk super admin dengan catatan privat pemilik | Berhasil masuk sebagai pengurus |
| Lainnya → Nomor WhatsApp Ketua RT | Nomor tersimpan cocok dengan referensi privat; tidak diubah |
| Warga → Akun warga → Riwayat akun sintetis | “Jadikan pengurus” terlihat; tidak ditekan. Penolakan pengurus biasa/warga juga tercakup dalam suite backend M17 |
| Masuk akun tambahan sintetis yang belum menyetujui versi baru | Halaman Kebijakan Privasi diwajibkan; teks Ketua RT, retensi 12 bulan, versi 25 Oktober 2026 tampil |
| Centang persetujuan → Setuju dan lanjutkan | Beranda terbuka; API auth/me mengonfirmasi tidak perlu setuju ulang |

AVD menampilkan ANR Pixel Launcher/System UI pada awal boot, pulih setelah dialog ditutup; tidak ada wipe atau
perubahan aplikasi untuk mengatasinya. Tidak ada blocker aplikasi M17 yang ditemukan dalam checklist ini.
Avatar sintetis dikembalikan ke Keluarga melalui API resmi. Persetujuan akun uji dipertahankan sebagai hasil uji.
Sesi super admin dikeluarkan; sesi API verifikasi dicabut. Emulator dihentikan setelah dipakai.
Screenshot/XML mentah dan kredensial hanya berada di penyimpanan privat sementara, tidak ditambahkan ke Git.

## Batas hasil

M17 **passed** atas cakupan yang disetujui pemilik. HP nyata, perilaku OEM, kamera/push/TalkBack, UAT mitra,
serta upgrade dari APK lebih lama tidak dibuktikan sesi ini; gate M16 yang relevan tetap terpisah.
M18 hanya boleh dilanjutkan ke rencana dan usulan ADR44; migrasi menunggu persetujuan rencana konkret.
