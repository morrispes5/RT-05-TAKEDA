# Flutter mobile — rancangan eksekusi

## Target

Satu Flutter/Dart application, Android first, layanan warga dan pengurus dalam aplikasi yang sama. UI berubah berdasarkan role/status dari API. Tidak membuat aplikasi admin terpisah atau memasukkan Laravel/Redis/Neon driver ke APK.

Implementasi fondasi M05 sedang diverifikasi pada Flutter 3.47.7 stable / Dart 3.13.5. Android memakai Java 17 di CI; versi aplikasi berasal dari `mobile/pubspec.yaml`. Keberadaan kode tidak membuktikan gate CI/APK/emulator: hasil aktual dicatat di PROGRESS.md dan docs/evidence/m05/.

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

Struktur di atas adalah rancangan domain v1. Fondasi aktual menggunakan `app/config.dart`, `router.dart`, `startup.dart`, `shell.dart`, `theme.dart` dan file sederhana `core/api.dart`, `health.dart`, `session.dart`, `widgets.dart`; belum memerlukan pemecahan folder/layer tambahan. Routing tingkat atas memakai go_router, detail fitur historis tetap boleh Navigator. Firebase/push adalah tahap M10, bukan dependensi gate M05.

Brand menggunakan SVG gapura resmi yang byte-identik dengan website, warna navy/kuning/sky, serta Public Sans dan Bricolage Grotesque terbundel beserta lisensi OFL. Tidak ada unduhan font runtime. Lokalisasi aplikasi Indonesia. Label lima tab navigasi dibatasi skala teks 1,3 agar muat dan label chip status 1,5; isi halaman tetap mengikuti skala sistem dan harus diuji pada 1,3 serta 2,0.

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
| Keuangan | Warga: iuran rumah dan riwayat 12 bulan+ lebih dulu; transparansi status rumah tersembunyi secara bawaan, dibuka lewat konfirmasi. Kas RT hanya pengurus (ADR26) |
| Notifikasi | Inbox/detail/read state, pengaturan push |
| Informasi | Privasi, tentang aplikasi, versi/update |

Bottom navigation warga: Beranda, Layanan, Agenda, Iuran, Akun. Notifikasi dari app bar. Aspirasi/pengaduan punya tab jelas, bukan satu form dengan status ambigu.

## Layar pengurus

Dashboard; rumah/keluarga/warga; review akun tambahan; token lihat/salin/rotasi; pengaduan/aspirasi dan status; agenda/pengumuman; iuran/rekap/tarif; pencatatan pembayaran beberapa bulan; kas/pengeluaran/koreksi; ekspor; konten publik/fasilitas; profil.

Navigasi admin: Ringkasan, Warga, Layanan, Keuangan (Pembayaran, Kas, Rekap bulanan dengan navigasi bulan dan tombol buat tagihan yang belum ada), Lainnya (termasuk Tarif iuran: riwayat dan tarif baru untuk bulan berikutnya). M13: Lainnya → **Konten website** (tab Artikel/Album/Fasilitas/Profil): daftar dengan status (draf, terbit, "ada draf baru", arsip), editor draf (slug dari judul, bagian artikel, foto dari galeri dengan teks alternatif wajib, sampul album), Simpan draf, Terbitkan (konfirmasi, menyimpan dulu bila berubah, dialog hasil), Arsipkan (konfirmasi bahaya); profil disimpan sekaligus terbit. M12: daftar pembayaran punya tombol **Batalkan** (alasan wajib, konfirmasi bahaya, dialog hasil); kas manual punya tombol **Balik**; Catat kas meminta konfirmasi bila saldo akan negatif lalu mengirim ulang dengan Idempotency-Key yang sama. Form finance menampilkan preview nominal/periode sebelum submit. Reverse/correct memerlukan alasan dan konfirmasi. Tidak menambah pembatasan hak bendahara dibanding admin lain.

## Perilaku UI

- Loading, empty, error/retry, successful submit, unavailable dependency, dan session expired ada pada setiap alur.
- Warna status disertai teks/ikon; unpaid merah, paid hijau; label belum dicatat jelas.
- Tombol submit tidak dapat diketuk ganda ketika request aktif.
- Timeout finance mempertahankan Idempotency-Key dan menyatakan hasil belum diketahui; cek hasil sebelum retry.
- Server selalu menentukan tarif, tunggakan, saldo, role, dan status. Client hanya menampilkan respons.
- Privasi identitas tersanitasi server; UI tidak menerima author data lalu sekadar menyembunyikannya.
- UTC di DTO, Asia/Jakarta untuk kalender dan periode.
- Kamera/galeri meminta izin saat aksi pengguna; penolakan menyediakan alternatif atau pesan.
- M08: manifest mendeklarasikan `CAMERA` (feature tidak wajib) sehingga image_picker meminta izin runtime saat tombol Kamera; `camera_access_denied` menampilkan panduan Pengaturan dan galeri tetap tersedia. Galeri memakai photo picker Android tanpa izin penyimpanan. Edit laporan sendiri hanya saat Diajukan; 409 mempertahankan isian lalu memuat versi terbaru; 403 terkunci menonaktifkan Simpan tanpa menghapus teks.
- M09: aspirasi memakai layar Layanan yang sama dengan pengaduan tetapi domain terpisah (`/aspirations`): form hanya judul, isi, dan sembunyikan identitas (tanpa kategori dan foto); konfirmasi "Aspirasi terkirim." hanya muncul setelah server membalas sukses, dan kegagalan mempertahankan isian. Pemilik dapat memperbaiki selama Diajukan (409 mempertahankan teks lalu memuat versi terbaru; 403 `ASPIRATION_LOCKED` menonaktifkan Simpan). Pengurus: Diajukan → Ditinjau → Selesai, buka ulang beralasan, arsip beralasan, filter "Termasuk arsip" dengan banner arsip dari `archived_at`. Teks layar menyebut "aspirasi"/"pengirim", bukan "laporan"/"pelapor".
- Usulan kegiatan (docs/USULAN_KEGIATAN.md): tab Agenda punya tombol **Ajukan kegiatan** dan **Usulan kegiatan saya**; pengurus meninjau dari Beranda/Lainnya. Form memvalidasi di dekat field (jenis, nama, tanggal/jam, lokasi, deskripsi, peserta, kebutuhan, pernyataan), lampiran lewat **Pilih file (PDF/TXT)** (`file_picker`) atau kamera/galeri, ringkasan dikonfirmasi sebelum kirim, dan dialog hasil setelah server menerima. Dokumen dibuka lewat tautan bertanda tangan di browser.
- Standar alert aksi (berlaku seluruh aplikasi): `showMessage` menampilkan alert melayang dengan ikon, judul, dan pesan per jenis (sukses hijau, gagal merah, konflik/peringatan kuning, info biru), dapat ditutup dan dibacakan pembaca layar; `showApiError` memetakan 409 ke peringatan "Data sudah berubah"; `confirm` untuk aksi tidak dapat dibatalkan (warna merah untuk aksi berbahaya); `showResultDialog` untuk hasil penting. Pesan sukses hanya setelah server menjawab sukses.
- Kehilangan koneksi menampilkan data terakhir yang aman/indikator stale; tidak mengantre mutation offline v1.
- Data keluarga/sensitif tidak disimpan plaintext offline. Token di OS secure storage; logout menghapus cache pribadi dan device binding.

## Integrasi

dev/staging/prod flavors dengan API_BASE_URL melalui compile-time configuration nonsensitive. Tidak ada secret Neon/Redis/SMTP/FCM server di --dart-define. API host menggunakan HTTPS valid; release tidak mempercayai semua sertifikat.

Fondasi M05 memvalidasi `FLAVOR=dev|staging|prod` dan origin root API (tanpa kredensial, query, fragment, atau path bisnis). Staging/prod wajib HTTPS; HTTP dev hanya `10.0.2.2` atau `127.0.0.1` sesuai kebijakan jaringan Android debug. Default dev `http://10.0.2.2:8077`; staging `https://takeda-staging.morriz.tech`. Konfigurasi tidak valid menampilkan layar kesalahan tanpa request jaringan.

Bootstrap memanggil `/health/live` tanpa token dengan timeout terbatas. Diagnostik nonproduction menampilkan flavor/host dan UUID request_id, tanpa nilai token. Pemulihan secure token memvalidasi `/auth/me`: hanya 401/logout menghapus token; offline/timeout/503/403 mempertahankannya, menampilkan unavailable/retry, dan tidak membuka data akun yang belum divalidasi. Respons sesi lama tidak boleh mengakhiri atau menimpa login baru.

Gate koneksi M05 cukup dengan request health publik nyata dari emulator/HP. Registrasi, login akun, iuran, SMTP, FCM, signed release dan UAT bisnis berada pada milestone berikutnya; APK debug CI tidak membuktikan gate tersebut.

Port eksplisit harus 1 sampai 65535; slash akhir origin dan port default dinormalisasi. Whitespace/control, backslash, empty userinfo/query/fragment, serta path yang bisa dinormalisasi parser tetap ditolak dari bentuk mentah. Manifest Android menetapkan `allowBackup=false` sesuai petunjuk [flutter_secure_storage](https://pub.dev/packages/flutter_secure_storage); token tetap memakai storage OS.

401 →hapus session token lokal/arah login; 403 inactive/pending →tampilkan status; 409 →refetch dan beri pilihan; 422→field errors; 429→retry-after; 503→retry manual. Interceptor tidak auto-retry POST finance dengan key baru.

Push membawa entity ID/type; navigation deep link memanggil API dan policy lagi. Notifikasi yang tidak lagi authorized tidak membuka data dari payload push.

M10: Firebase diinisialisasi dari `--dart-define` `FIREBASE_API_KEY`, `FIREBASE_APP_ID`, `FIREBASE_SENDER_ID`, `FIREBASE_PROJECT_ID` (nilai klien Android, bukan secret; CI dari repository variables `FIREBASE_*`, semua atau tidak sama sekali). Tanpa nilai itu aplikasi berjalan tanpa push dan halaman Pengaturan notifikasi menjelaskan "Push belum tersedia". Setelah sesi aktif/menunggu tervalidasi, aplikasi meminta izin (Android 13+) sekali lalu `PUT /devices/{id}` dengan ID instalasi acak dari secure storage (bukan ANDROID_ID). Izin ditolak: layanan tetap, ada panduan membuka pengaturan HP, dan lencana inbox dimuat ulang saat aplikasi kembali aktif. Foreground: alert info dan inbox dimuat ulang. Ketukan dari latar belakang/tertutup: tandai dibaca, buka inbox, lalu detail dimuat dari API; jenis/ID di luar daftar diabaikan, 404/403 tampil "tidak lagi tersedia". Pengurus: Lainnya → Agenda & pengumuman (buat/ubah/arsipkan dengan konfirmasi, dialog hasil, error per field).

## Release

APK release signed dari CI atau mesin developer. Signing keystore/password disimpan secret di luar repo, backup aman. Generate checksum, version code/name, minimum version, dan release notes. Distribusi awal melalui HTTPS download page pada VPS; install diuji HP nyata.

VPS menjalankan backend dan menyediakan file distribusi, bukan menjalankan Flutter application milik warga. iOS/Play Store dapat menjadi milestone lanjutan setelah v1 tanpa mengubah database/API.

## Pengujian

flutter analyze; unit DTO/repository/state; widget form/error; integration register→login→report→admin update→notification; finance double-tap/timeout replay; perangkat Android nyata untuk kamera, file, push foreground/background/terminated dan permission denial.
