# M08 — penerimaan Android (staging, sintetis)

13 Oktober 2026, 12:05–12:27 UTC (status bar screenshot memakai WIB, UTC+7). Pelaksana: Claude.

| Item | Nilai |
| --- | --- |
| Source API saat demonstrasi | `d790ec218b33be8fdc3357f868fa46240501c926` (live 42/42, [LIVE_RESULTS.md](LIVE_RESULTS.md)) |
| APK | CI run `38391645181` (head `d790ec2`), `rt05-takeda-staging-debug.apk`, SHA-256 `35d2c3d24ca045cff491240d3d22f482114f706654ebefdacd52be3733381792` = file `.sha256` artefak. `mobile/` identik di `6b6987c` |
| Perangkat | `emulator-5554` Pixel 8a (AVD milik pengembang), RAM emulator 1536 MiB, 960x2133 density 480 (320 dp), font 1,0 |
| Pemasangan | APK lama RT05 bertanda tangan debug CI lain ditolak update (`INSTALL_FAILED_UPDATE_INCOMPATIBLE`); hanya paket `tech.morriz.takeda` dicopot lalu APK baru dipasang (sama seperti M07). Aplikasi lain tidak disentuh; tanpa wipe |
| Interaksi | Tap/ketik ADB nyata; dump uiautomator segar sebelum setiap pembacaan. Kredensial diketik dari fixture privat tanpa dicetak |
| Foto | Kamera virtual emulator (adegan bawaan) dan satu gambar sintetis buatan generator di galeri; tanpa foto warga |

Aktor sama dengan live: `a1` pelapor (akun utama aktif), `a3` warga lain, admin uji M06.

## Alur dan bukti

| Waktu UTC | Aksi | Hasil |
| --- | --- | --- |
| 12:10 | `a1` → Layanan → Buat pengaduan; kategori, judul, isi, anonim aktif; `Kamera` | Dialog izin runtime Android tampil. [Prompt](screenshots/m08-android-camera-permission-prompt.png) |
| 12:11–12:12 | `Don't allow` (dua kali) | Snackbar: "Izin kamera ditolak. Anda tetap dapat memilih dari galeri … izinkan di Pengaturan > Aplikasi > RT05 TAKEDA > Izin." Isian tetap. [Ditolak](screenshots/m08-android-camera-denied-guidance.png) |
| 12:13 | Flag "jangan tanya lagi" dibersihkan (`pm clear-permission-flags`, setara pengguna mengizinkan lagi); `Kamera` → `While using the app` | Aplikasi kamera terbuka. [Kamera](screenshots/m08-android-camera-allowed-open.png) |
| 12:13:54 | Shutter → Done | **lowmemorykiller** Android mematikan proses RT05 (RSS ±522 MB, APK debug) saat kamera di depan; aplikasi kembali ke Beranda dan draf hilang. Lihat keterbatasan |
| 12:16–12:17 | Isi ulang form; `Kamera` (izin sudah diberikan) → foto → Done; `Galeri` → photo picker Android (tanpa izin penyimpanan) → pilih gambar sintetis | `Foto 1`, `Foto 2` terlampir, isian utuh. [Kamera diizinkan](screenshots/m08-android-camera-allowed-photo-attached.png), [Photo picker](screenshots/m08-android-gallery-photo-picker.png) |
| 12:18:05 | SSH: hanya container `rt05-staging-worker-media-1` dihentikan | Exited (0) |
| 12:18:13 | `Kirim` | Pengaduan terkirim. [Terkirim](screenshots/m08-android-submitted.png) |
| 12:18:34 | Pengaduan saya → detail | Dua foto **Sedang diproses**; "Identitas disembunyikan dari warga lain". [Diproses](screenshots/m08-android-detail-photos-processing.png) |
| 12:18:47 | SSH: worker-media dijalankan lagi; healthy, heartbeat exit 0 | Job yang tertahan di Redis diproses |
| 12:18:55 | Tarik untuk muat ulang | Kedua foto tampil (siap). [Siap](screenshots/m08-android-detail-photos-ready.png) |
| 12:19–12:20 | `Perbaiki laporan`, tambah teks; perubahan dari "perangkat lain" lewat API `a1` (versi 1→2); `Simpan perubahan` | 409 di form: "Data sudah berubah… Teks yang Anda ketik tetap ada…"; teks tetap. [409](screenshots/m08-android-edit-409-draft-kept.png) |
| 12:20:36 | `Simpan perubahan` lagi | Kembali ke detail; API: versi 3 (tepat satu tulis ulang). [Tersimpan](screenshots/m08-android-edit-resubmitted.png) |
| 12:21–12:22 | Keluar (perangkat ini); login `a3` → Layanan | Feed dan detail: "Identitas disembunyikan", riwayat menyebut "Warga", foto terlihat, tanpa tombol perbaiki. [Feed](screenshots/m08-android-other-resident-feed.png), [Detail](screenshots/m08-android-other-resident-detail-anonymous.png) |
| 12:23 | Login admin → Layanan → detail | Nama pelapor terlihat untuk pengurus. [Admin](screenshots/m08-android-admin-detail-reporter-visible.png) |
| 12:23:48 | `Ubah ke Diproses` + catatan | Status Diproses; riwayat memuat catatan (huruf tertukar "dijawdalkan" adalah artefak injeksi `adb input`, data sintetis). [Diproses](screenshots/m08-android-admin-status-processing.png) |
| 12:24 | Detail laporan live C25EF8 → `Arsipkan laporan` + alasan | Banner "Laporan diarsipkan…"; API admin 200, pemilik 404. [Arsip](screenshots/m08-android-admin-archived.png) |
| 12:26 | Login `a1` → Notifikasi → ketuk | Inbox "Status pengaduan diperbarui"; ketuk membuka detail dengan riwayat "Pengurus RT". [Inbox](screenshots/m08-android-owner-inbox.png), [Detail dari notifikasi](screenshots/m08-android-owner-notification-detail.png) |

Akun menunggu (`a2`) dan nonaktif dibuktikan di API live (C02/C03, C38–C41); tampilan pending Android sudah diterima M07.

## Temuan dan tindak lanjut

1. **Bug diperbaiki:** inbox `a1` menampilkan tiga notifikasi lama laporan C25EF8 semuanya "Diproses", padahal transisinya diproses → selesai → diproses. Consumer outbox membaca status terkini, bukan status event. Diperbaiki di `6b6987c` (`Notifications::eventStatus`, tes regresi gagal tanpa fix); dibuktikan ulang di [LIVE_RESULTS.md](LIVE_RESULTS.md).
2. **Keterbatasan — kematian proses saat kamera:** pada emulator 1536 MiB dengan APK debug (±522 MB RSS) Android membunuh aplikasi saat kamera terbuka, sehingga draf dan foto hilang. Percobaan kedua berhasil. Ponsel warga RAM kecil dapat mengalami hal serupa. Pemulihan (`ImagePicker.retrieveLostData` + draf tersimpan lokal) belum dibuat; dicatat untuk M15 bersama ukuran APK release.
3. Saat demonstrasi, tombol Back pada form setelah keyboard tertutup menutup form (draf belum terkirim, tidak ada data). Ini perilaku navigasi biasa, bukan kebocoran data.

## Akhir sesi

Font 1,0, ukuran/density tetap; aplikasi RT05 masuk sebagai `a1`. Worker-media berjalan dan healthy.
Emulator dihentikan setelah bukti selesai (lihat [REPORT.md](REPORT.md)).

---

# Putaran 2 — 13 Oktober 2026 (verifikasi ulang atas instruksi pemilik)

Sekitar 20.40–20.51 WIB (13.40–13.51 UTC). Pelaksana: Claude. Staging sintetis; tanpa production.

| Item | Nilai |
| --- | --- |
| Source API | `6b6987cd4c4d367e67742dc5f96d3a7bcf366c7b` (semua service staging; head PR `a3d80e5` hanya mengubah script uji) |
| APK | CI run `38398695961`, `rt05-takeda-staging-debug.apk`, SHA-256 `f78f314032000571080cde7084acaf17989fb936d4b52f93ee8bf1f621b85967` = file `.sha256` artefak |
| Perangkat | `emulator-5554` Pixel 8a (AVD milik pengembang, konfigurasi AVD tidak diubah), headless `-memory 2048 -cores 4 -gpu host`, 960x2133 density 480, font 1,0 lalu 2,0 pada langkah terakhir |
| Pemasangan | `INSTALL_FAILED_UPDATE_INCOMPATIBLE` (tanda tangan debug CI berbeda); hanya paket `tech.morriz.takeda` dicopot lalu APK baru dipasang; tanpa wipe; aplikasi lain tidak disentuh |
| Alasan headless | RAM bebas PC hanya sekitar 1,8 GB dari 15,8 GB; jendela emulator membuat PC lag. Aplikasi dikendalikan lewat ADB dan screenshot |
| Aktor | `a1` pelapor, `a3` warga lain, admin uji M06 (fixture privat di luar Git); laporan "Uji emulator M08 putaran dua" |

| Waktu WIB | Aksi | Hasil |
| --- | --- | --- |
| 20.40 | `a1` → Buat pengaduan; kategori Kebersihan, judul, isi, anonim aktif; `Kamera` (izin dicabut dulu) | Dialog izin kamera tampil. [Prompt](screenshots/m08r2-android-camera-permission-prompt.png) |
| 20.40 | `Don't allow` | Snackbar panduan; isian tetap; Galeri tetap dapat dipakai. [Ditolak](screenshots/m08r2-android-camera-denied-guidance.png) |
| 20.40–20.41 | `Kamera` → `While using the app` → shutter → Done | Foto 1 dari kamera virtual terlampir, isian utuh, **proses tidak dimatikan Android**. [Terlampir](screenshots/m08r2-android-camera-allowed-photo-attached.png) |
| 20.41 | `Galeri` → photo picker Android (tanpa izin penyimpanan) | Foto 2 terlampir. [Picker](screenshots/m08r2-android-gallery-photo-picker.png) |
| 20.42 (13.42:21 UTC) | SSH: hanya `rt05-staging-worker-media-1` dihentikan; `Kirim` | Pengaduan terkirim, muncul di Pengaduan saya status Diajukan. [Terkirim](screenshots/m08r2-android-submitted.png) |
| 20.43 | Detail | Dua foto **Sedang diproses**; "Identitas disembunyikan dari warga lain". [Diproses](screenshots/m08r2-android-detail-photos-processing.png) |
| 20.43 (13.43:23 UTC) | SSH: worker-media dijalankan lagi (healthy); tarik muat ulang | Dua foto siap. [Siap](screenshots/m08r2-android-detail-photos-ready.png) |
| 20.44 | Perbaiki laporan, tambah teks; versi dinaikkan dari "perangkat lain" lewat API (1→2); `Simpan perubahan` | 409 di form, teks tetap. [409](screenshots/m08r2-android-edit-409-draft-kept.png) |
| 20.44 | `Simpan perubahan` lagi | Kembali ke detail; API versi 3 (satu tulis). [Tersimpan](screenshots/m08r2-android-edit-resubmitted.png) |
| 20.45 | Keluar; login `a3` → Layanan | Feed dan detail: "Identitas disembunyikan", riwayat "Warga", foto terlihat, tanpa tombol perbaiki. [Feed](screenshots/m08r2-android-other-resident-feed.png), [Detail](screenshots/m08r2-android-other-resident-detail-anonymous.png) |
| 20.46 | Login admin → Layanan → detail | Nama pelapor terlihat. [Admin](screenshots/m08r2-android-admin-detail-reporter-visible.png) |
| 20.47–20.48 | `Ubah ke Diproses` + catatan; `Ubah ke Selesai` + catatan; buka ulang tanpa alasan; buka ulang dengan alasan | Status Diproses, Selesai, tetap Selesai (alasan wajib), Diproses; riwayat memuat catatan; snackbar "pelapor menerima notifikasi" (API versi 6). [Diproses](screenshots/m08r2-android-admin-status-processing.png), [Dibuka ulang](screenshots/m08r2-android-admin-reopened.png) |
| 20.49 | Arsipkan laporan lama `CC3EAD` dengan alasan | Pemilik 404, admin 200 (API). [Arsip](screenshots/m08r2-android-admin-archived.png) |
| 20.50 | Keluar admin; login `a1` → Notifikasi | Tiga notifikasi untuk laporan baru: Diproses, Selesai, Diproses, urutan benar. [Inbox](screenshots/m08r2-android-owner-inbox.png) |
| 20.50 | Ketuk notifikasi | Detail laporan terbuka dengan riwayat. [Detail](screenshots/m08r2-android-owner-notification-detail.png) |
| 20.50–20.51 | Font 2,0 pada detail dan form Buat pengaduan | Teks tidak terpotong; judul panjang terpecah di tengah kata karena chip status. [Detail 2,0](screenshots/m08r2-android-detail-font2.png), [Form 2,0](screenshots/m08r2-android-create-font2.png) |

Akhir sesi: font 1,0, ukuran dan density di-reset, emulator dihentikan, worker-media berjalan dan
healthy, `/health/ready` ready. Laporan pengujian tertinggal sebagai data sintetis di staging (tanpa
cleanup).
