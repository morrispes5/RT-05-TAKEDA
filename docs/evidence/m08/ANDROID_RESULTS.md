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
