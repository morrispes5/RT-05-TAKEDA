# Flutter mobile — rancangan eksekusi

## Target

Satu Flutter/Dart application, Android first, layanan warga dan pengurus dalam aplikasi yang sama. UI berubah berdasarkan role/status dari API. Tidak membuat aplikasi admin terpisah atau memasukkan Laravel/Redis/Neon driver ke APK.

Flutter adalah keputusan teknis paket v1, bukan klaim sudah dibangun pada repo. Pin versi Flutter/Dart/Android SDK/plugin tepat setelah kompatibilitas dicek di M05.

## Struktur

```text
mobile/lib/
  app/             # router, bootstrap, theme, dependency wiring
  core/
    network/       # HTTP client, error model, auth interceptor
    storage/       # secure token storage, nonsensitive preferences
    widgets/       # empty/loading/error, inputs, status labels
  features/
    auth/ household/ complaints/ aspirations/
    community/ dues/ cash/ notifications/
    admin/ content/
      data/        # service API, DTO, repository
      presentation/# pages, viewmodel/state, feature widgets
```

State management v1: Riverpod; router: go_router; HTTP: Dio; secure storage plugin; kamera/galeri: image_picker; push: firebase_messaging. Verifikasi versi/maintenance/plugin permissions sebelum lockfile. Tidak perlu domain layer/use case class untuk setiap GET sederhana; business rule tetap di server.

Gunakan repository interface untuk fake fixtures dan API nyata; fake adapter hanya local/test flavor dan diberi indikator. Production build tidak boleh diam-diam fallback ke fake data.

## Layar warga

| Kelompok | Layar |
| --- | --- |
| Akses | Splash/session check, login, register utama/tambahan, pending approval, lupa/reset password |
| Beranda | Ringkasan iuran, agenda dekat, pengumuman, akses layanan |
| Keluarga | Data rumah/keluarga/anggota, edit akun utama, profil/password |
| Pengaduan | Feed, filter, detail/histori, form kategori/foto/privasi, laporan sendiri |
| Aspirasi | Feed, detail/histori, form dan status |
| Komunitas | Kalender/list agenda, detail, add-to-calendar, pengumuman/detail |
| Keuangan | Iuran rumah, riwayat 12 bulan+, status transparansi rumah, ringkasan kas |
| Notifikasi | Inbox/detail/read state, pengaturan push |
| Informasi | Privasi, tentang aplikasi, versi/update |

Bottom navigation warga: Beranda, Layanan, Agenda, Iuran, Akun. Notifikasi dari app bar. Aspirasi/pengaduan punya tab jelas, bukan satu form dengan status ambigu.

## Layar pengurus

Dashboard; rumah/keluarga/warga; review akun tambahan; token lihat/salin/rotasi; pengaduan/aspirasi dan status; agenda/pengumuman; iuran/rekap/tarif; pencatatan pembayaran beberapa bulan; kas/pengeluaran/koreksi; ekspor; konten publik/fasilitas; profil.

Navigasi admin: Ringkasan, Warga, Layanan, Keuangan, Lainnya. Form finance menampilkan preview nominal/periode sebelum submit. Reverse/correct memerlukan alasan dan konfirmasi. Tidak menambah pembatasan hak bendahara dibanding admin lain.

## Perilaku UI

- Loading, empty, error/retry, successful submit, unavailable dependency, dan session expired ada pada setiap alur.
- Warna status disertai teks/ikon; unpaid merah, paid hijau; label belum dicatat jelas.
- Tombol submit tidak dapat diketuk ganda ketika request aktif.
- Timeout finance mempertahankan Idempotency-Key dan menyatakan hasil belum diketahui; cek hasil sebelum retry.
- Server selalu menentukan tarif, tunggakan, saldo, role, dan status. Client hanya menampilkan respons.
- Privasi identitas tersanitasi server; UI tidak menerima author data lalu sekadar menyembunyikannya.
- UTC di DTO, Asia/Jakarta untuk kalender dan periode.
- Kamera/galeri meminta izin saat aksi pengguna; penolakan menyediakan alternatif atau pesan.
- Kehilangan koneksi menampilkan data terakhir yang aman/indikator stale; tidak mengantre mutation offline v1.
- Data keluarga/sensitif tidak disimpan plaintext offline. Token di OS secure storage; logout menghapus cache pribadi dan device binding.

## Integrasi

dev/staging/prod flavors dengan API_BASE_URL melalui compile-time configuration nonsensitive. Tidak ada secret Neon/Redis/SMTP/FCM server di --dart-define. API host menggunakan HTTPS valid; release tidak mempercayai semua sertifikat.

401 →hapus session token lokal/arah login; 403 inactive/pending →tampilkan status; 409 →refetch dan beri pilihan; 422→field errors; 429→retry-after; 503→retry manual. Interceptor tidak auto-retry POST finance dengan key baru.

Push membawa entity ID/type; navigation deep link memanggil API dan policy lagi. Notifikasi yang tidak lagi authorized tidak membuka data dari payload push.

## Release

APK release signed dari CI atau mesin developer. Signing keystore/password disimpan secret di luar repo, backup aman. Generate checksum, version code/name, minimum version, dan release notes. Distribusi awal melalui HTTPS download page pada VPS; install diuji HP nyata.

VPS menjalankan backend dan menyediakan file distribusi, bukan menjalankan Flutter application milik warga. iOS/Play Store dapat menjadi milestone lanjutan setelah v1 tanpa mengubah database/API.

## Pengujian

flutter analyze; unit DTO/repository/state; widget form/error; integration register→login→report→admin update→notification; finance double-tap/timeout replay; perangkat Android nyata untuk kamera, file, push foreground/background/terminated dan permission denial.
