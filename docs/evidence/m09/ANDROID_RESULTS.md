# M09 — penerimaan Android (staging, sintetis)

13 Oktober 2026, sekitar 22.07–22.15 WIB (15.07–15.15 UTC). Pelaksana: Claude.

| Item | Nilai |
| --- | --- |
| Source API | `617e1790c9d415831b33d5c3caabd9683f4b0e31` (semua service staging; live 48/48, [REPORT.md](REPORT.md)) |
| APK | CI run `38415151167`, `rt05-takeda-staging-debug.apk`, SHA-256 `56a457518351c9a23d97b08170e835ee1e879845cde55ad01f838f90f2780305` = file `.sha256` artefak |
| Perangkat | `emulator-5554` Pixel 8a (AVD milik pengembang, konfigurasi tidak diubah), headless `-memory 2048 -cores 4 -gpu host`, 960x2133 density 480, font 1,0 lalu 2,0 pada langkah terakhir |
| Pemasangan | `INSTALL_FAILED_UPDATE_INCOMPATIBLE` dihindari dengan mencopot hanya paket `tech.morriz.takeda` lalu memasang APK baru; tanpa wipe; aplikasi lain tidak disentuh |
| Aktor | `a1` pengirim, `a3` warga lain, admin uji M06 (fixture privat di luar Git) |

| Waktu WIB | Aksi | Hasil |
| --- | --- | --- |
| 22.07 | `a1` → Layanan → tab Aspirasi | Filter status memakai Ditinjau (bukan Diproses); tombol "Kirim aspirasi". [Feed](screenshots/m09-android-aspirasi-feed.png) |
| 22.08 | Kirim aspirasi | Form hanya judul, isi, sembunyikan identitas; teks "pengirim"; tanpa kategori dan foto. [Form](screenshots/m09-android-aspirasi-form.png) |
| 22.08 | Isi form (anonim aktif), matikan Wi-Fi dan data, `Kirim` | Pesan "Tidak dapat terhubung ke server"; form tetap terbuka, isian utuh; **tanpa konfirmasi palsu**. [Offline](screenshots/m09-android-offline-submit-kept.png) |
| 22.08 | Jaringan dinyalakan, `Kirim` | Form tertutup, kembali ke Layanan. Server: tepat **satu** aspirasi baru (versi 1) tanpa duplikat dari percobaan offline. [Terkirim](screenshots/m09-android-submitted.png) |
| 22.09 | Tab Aspirasi saya → detail | Status Diajukan, "Identitas disembunyikan dari warga lain", tombol "Perbaiki aspirasi". [Saya](screenshots/m09-android-aspirasi-saya.png), [Detail](screenshots/m09-android-detail-owner.png) |
| 22.09 | Perbaiki aspirasi; versi dinaikkan dari "perangkat lain" lewat API (1→2); `Simpan perubahan` | 409 di form, teks tetap. [409](screenshots/m09-android-edit-409-draft-kept.png) |
| 22.10 | `Simpan perubahan` lagi | Kembali ke detail; API versi 3 (satu tulis). [Tersimpan](screenshots/m09-android-edit-resubmitted.png) |
| 22.10 | Keluar; login `a3` → Aspirasi | Feed dan detail: "Identitas disembunyikan", riwayat "Warga", tanpa tombol perbaiki. [Feed](screenshots/m09-android-other-resident-feed.png), [Detail](screenshots/m09-android-other-resident-detail-anonymous.png) |
| 22.11 | Login admin → Layanan → Aspirasi | Nama pengirim terlihat di feed dan detail; filter "Termasuk arsip". [Feed](screenshots/m09-android-admin-feed.png), [Detail](screenshots/m09-android-admin-detail-sender-visible.png) |
| 22.12 | `Ubah ke Ditinjau` + catatan; `Ubah ke Selesai` + catatan | Status Ditinjau lalu Selesai; riwayat memuat catatan. [Ditinjau](screenshots/m09-android-admin-status-reviewed.png) |
| 22.12 | `Buka ulang (Ditinjau)` tanpa alasan, lalu dengan alasan | Tanpa alasan tidak berubah (tetap Selesai); dengan alasan Ditinjau (API versi 6). [Dibuka ulang](screenshots/m09-android-admin-reopened.png) |
| 22.13 | Arsipkan aspirasi hasil skrip live (`F21B1E`) dengan alasan | Banner "Aspirasi diarsipkan: tidak tampil untuk warga. Riwayat tetap tersimpan."; tombol Arsipkan, Ubah ke, dan Buka ulang hilang (**membuktikan perbaikan `archived_at`**). [Arsip](screenshots/m09-android-admin-archived-banner.png) |
| 22.14 | Keluar admin; login `a1` → Notifikasi | Tiga notifikasi untuk aspirasi baru: Ditinjau, Selesai, Ditinjau, tanpa duplikat. [Inbox](screenshots/m09-android-owner-inbox.png) |
| 22.14 | Ketuk notifikasi | Detail aspirasi terbuka |
| 22.15 | Font 2,0 pada detail | Judul tampil utuh per kata; chip "Ditinjau" di bawah judul (**perbaikan temuan M08**). [2,0](screenshots/m09-android-detail-font2.png) |

Catatan: "untuck" pada teks isian adalah artefak injeksi `adb input`, bukan bug aplikasi. Label pelapor pada
keluaran helper API privat berasal dari skrip pengaduan lama, bukan dari aspirasi.

## Akhir sesi

Font 1,0, ukuran dan density di-reset; emulator dihentikan; staging sehat (`/health/ready` ready).
Data aspirasi uji tertinggal sebagai data sintetis di staging (tanpa cleanup).
