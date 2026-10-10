# mobile/ — aplikasi Flutter RT05 TAKEDA (warga & pengurus)

Satu aplikasi Android; navigasi mengikuti peran dari API. Semua data dan aturan bisnis dari `rest-api/` lewat `/api/v1`. Aplikasi tidak terhubung langsung ke Neon/Redis dan tidak menghitung saldo/tunggakan sendiri.

Flutter **3.47.7** (Dart 3.13.5). Paket: flutter_riverpod 3, dio 5, flutter_secure_storage 11 (token di keystore OS), image_picker, url_launcher, intl, google_fonts.

## Layar

| Peran | Tab | Isi |
| --- | --- | --- |
| Semua | Akses | Splash/cek sesi, login, daftar akun utama (rumah + keluarga), daftar akun tambahan, menunggu persetujuan, lupa/reset kata sandi |
| Warga | Beranda | Iuran bulan ini + tunggakan, aksi cepat, agenda terdekat, pengumuman |
| Warga | Layanan | Pengaduan & aspirasi (tab terpisah), filter status, laporan saya, form + foto kamera/galeri + sembunyikan identitas, detail + riwayat |
| Warga | Agenda | Daftar agenda + tambah ke Google Calendar, pengumuman |
| Warga | Iuran | Tagihan rumah ≥12 bulan, transparansi per kode rumah (tanpa nama), saldo & mutasi kas |
| Warga | Akun | Profil, data keluarga (edit akun utama), ganti kata sandi, keluar |
| Pengurus | Ringkasan | Saldo kas, status iuran bulan ini, permohonan/laporan baru, catat iuran, token |
| Pengurus | Warga | Rumah & keluarga (verifikasi, iuran per rumah), permohonan akun tambahan (setujui/tolak) |
| Pengurus | Layanan | Tindak lanjut status pengaduan/aspirasi (versi, buka ulang beralasan) |
| Pengurus | Keuangan | Catat pembayaran offline multi-bulan (pratinjau total, Idempotency-Key), pembalikan, kas manual, transparansi |
| Pengurus | Lainnya | Agenda, pengumuman, token registrasi, akun |
| Semua | Notifikasi | Inbox dari app bar, tandai dibaca, buka laporan terkait (dimuat ulang dari API) |

Push FCM belum aktif (butuh project Firebase); inbox notifikasi berjalan tanpa push.

## Menjalankan di emulator (hemat SSD)

APK di-build di GitHub Actions (`.github/workflows/mobile.yml`) sehingga cache Gradle tidak memenuhi disk PC.

1. Jalankan API lokal: `cd rest-api && php artisan serve --port=8077` (memakai Neon dev dari `.env`).
2. Unduh artifact `rt05-takeda-debug-apk` dari run workflow terbaru (`gh run download -n rt05-takeda-debug-apk`).
3. Nyalakan emulator: `%LOCALAPPDATA%\Android\Sdk\emulator\emulator -avd Pixel_8a`.
4. Pasang: `%LOCALAPPDATA%\Android\Sdk\platform-tools\adb install -r app-debug.apk`.

APK debug default memanggil `http://10.0.2.2:8077` (alamat PC dari emulator). Untuk server lain: jalankan workflow manual dengan input `api_base_url` (HTTPS). Build release hanya mengizinkan HTTPS.

## Pengembangan

```powershell
$env:Path = "C:\Users\USER\dev\flutter\bin;$env:Path"
flutter pub get
flutter analyze
flutter test
```

Signing key rilis, `key.properties`, dan `google-services.json` tidak pernah di-commit.
