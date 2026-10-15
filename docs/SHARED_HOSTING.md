# Profil shared hosting RT05 TAKEDA

15 Oktober 2026 — ADR23. Implementasi opt-in; **belum diterima untuk produksi**.

## Keputusan dan biaya

Laravel web + Laravel API + Flutter + PostgreSQL tetap. Website tidak mendapat DSN warga. Anggaran tahun pertama hosting/domain maksimal Rp500.000; promo diizinkan. Kandidat Biznet Personal Large Rp399.000/tahun pertama dengan domain gratis .com sesuai ketentuan. Pastikan pajak/checkout total. Harga pembanding halaman Rp981.000 tidak dianggap tagihan renewal final; catat renewal hosting/domain sebelum handover.

Neon Free: halaman resmi saat diperiksa 15 Oktober 2026 mencantumkan 100 CU-hours dan 1 GB database/project. Pisahkan project produksi dari pengujian, bukan membagi data RT ke banyak project demi kuota. Pada 0,25 CU, 100 CU-hours hanya 400 jam compute aktif. Recovery tiap 30 menit dengan idle tail 5 menit sekitar 30 CU-hours/30 hari TANPA trafik/tugas tambahan; bukan estimasi total atau jaminan gratis. Ukur minimal tujuh hari dan proyeksikan satu bulan dengan headroom.

Resend Free: 3.000 email/bulan dan 100/hari, prioritaskan reset. Integrasi staging yang telah lulus menggunakan SMTP (ADR20). Pertahankan dahulu; jika outbound SMTP ditolak, tambah SDK resmi Resend dan uji HTTP API sebelum mengganti transport. Gmail hanya penerima, tidak perlu OAuth Gmail. FCM tanpa biaya untuk push; belum ada bukti provider M10. Foto disk privat hosting; R2 opsional, bukan dependency wajib. Backup terenkripsi offsite dengan kredensial scoped dan retensi tetap wajib; tujuan belum dipilih. Jangan gunakan Git repo publik untuk backup.

Sumber harga/kuota (bisa berubah):
- https://www.biznetgio.com/product/neo-web-hosting-personal
- https://neon.com/pricing
- https://resend.com/pricing
- https://www.resend.com/blog/new-free-tier
- https://firebase.google.com/pricing

## Cara kerja

Profil default `vps` tidak berubah. Pilih `RT05_DEPLOYMENT_PROFILE=shared` hanya di staging yang lolos preflight. Tambahkan tabel `jobs` melalui koneksi migrator, tetap pisahkan credential DDL dari hosting. Config queue database memakai role runtime DML. Job reset tetap terenkripsi dan after-commit; outbox tetap persisten.

Cron tiap menit menjalankan `php artisan rt05:shared-tick` dengan PHP CLI yang sama versinya dengan web. File lock mencegah tick bersamaan. Tick menjalankan scheduler; outbox yang baru committed dan JobQueued menandai aktivitas pada file cache lokal. Saat idle, worker tidak polling PostgreSQL. Recovery tiap 30 menit menangkap penanda hilang/crash. Saat ada aktivitas/backlog, worker terbatas menguras queue. Gunakan storage persisten yang sama bagi CLI/web; jangan jalankan schedule:work/worker daemon bersamaan pada profil ini.

Target normal inbox/email mulai diproses ≤60 detik tetap target, bukan bukti; antrean, cron jitter, provider, dan job media dapat menambah waktu. Provider hanya mendukung cron 15 menit → **gagal kompatibilitas profil ini**, bukan menurunkan target diam-diam. Gangguan proses bisa menunda recovery sampai sweep berikutnya; failed job harus terlihat dan ditangani operator.

Foto shared dibatasi server ke 4 MiB dan 6 juta piksel, output ≤1600 px; batas VPS tetap. Ini batas operasional yang wajib diuji dengan APK existing (resize 2400 bisa menghasilkan foto >6 MP pada gambar persegi). Update kompresi mobile/web sebelum aktivasi bila diperlukan. Foto lama yang sedang diproses harus diselesaikan sebelum mengaktifkan batas baru. Metadata tetap dibuang server; jangan menganggap kompresi client cukup.

## Preflight wajib sebelum pembelian/aktivasi

1. Konfirmasi harga all-in, domain .com, kepemilikan RT, renewal dan ketentuan refund secara tertulis.
2. PHP memenuhi composer.lock (uji `composer check-platform-reqs` terhadap build), web+CLI; pdo_pgsql, GD JPEG/PNG/WebP, mbstring, intl, fileinfo, openssl, curl; pcntl CLI untuk timeout worker.
3. Outbound Neon 5432 dengan TLS verify-full dan CA bundle; uji DML pooled, runtime DDL ditolak. Jangan menurunkan ke sslmode=require.
4. Cron satu menit, batas waktu proses cukup, file lock bekerja; SMTP provider atau HTTP provider terverifikasi.
5. Document root hanya public, .env/vendor/storage privat tidak dapat diunduh. Web/API mempunyai APP_KEY/cookie berbeda; API CLI memakai key yang sama dengan API web.
6. Resource CPU/RAM/proses/IO diuji; jangan menilai hanya dari SSD. Batas foto dan upload PHP selaras; quota/alert disk wajib sebelum live.
7. Dua front controller same-origin: /api/v1, /sanctum/csrf-cookie, /health/*, serta GET/POST /reset-password menuju API; path lain website. PoC routing belum tersedia karena layout akun belum diketahui. Jangan menaruh seluruh repo dalam public_html.

## Persiapan staging dan rollout

- Integrasikan PR M09 secara terpisah setelah CI/bukti sesuai; pertahankan kerja lokal rekan.
- Build dependencies/assets di CI; jangan upload .env atau config cache mesin lain.
- Mulai dari contoh konfigurasi API yang ada, terapkan overlay `infra/shared/api.env.example`; isi secret di hosting secara privat, bukan repo. Website memakai file session/cache sendiri; tidak mendapat queue/DB bisnis.
- Migrasi additive memakai role migrator di lingkungan operator/CI yang diotorisasi. Jalankan grant-runtime-role untuk tabel/sequence baru. Hosting hanya DML pooled.
- Hubungkan storage persisten privat, isi URL nyata, config:cache setelah secret tersedia. Install satu cron tick.
- Lakukan acceptance di bawah. VPS staging tetap utuh sampai hasil shared diterima. Jangan menyalin credential historis terpapar.
- Rollback aplikasi ke rilis sebelumnya + profil VPS/Redis hanya pada lingkungan yang mempunyai Redis. Tabel jobs boleh dibiarkan; **jangan drop antrean yang belum habis**. Hentikan producer, drain/reconcile jobs/outbox/media, backup, baru pindah transport. Reset job terenkripsi hanya dapat dibaca dengan key yang sesuai; rotasi key harus direncanakan, bukan key:generate sembarangan.

## Acceptance dan bukti yang harus dikumpulkan

| Gate | Bukti wajib |
| --- | --- |
| Fondasi | CI PostgreSQL dan tes shared lulus; default VPS/Redis tetap lulus |
| Hemat idle | Tick tanpa aktivitas tidak membuat query DB; penggunaan Neon tujuh hari/proyeksi sebulan |
| Durability | Commit lalu proses mati; marker hilang; job duplicate/retry; satu inbox, tidak ada duplikat keuangan |
| Cron | Dua tick overlap ditolak; delayed job diproses lagi; lock dilepas setelah crash; heartbeat stale terdeteksi |
| M06 | Resend inbox nyata, reset Android/browser, sesi lama dicabut, kode reuse ditolak, CSRF/rate limit |
| M07–M09 | Pendataan/otorisasi, foto privat/spoof/EXIF/recovery, aspirasi/inbox |
| Media | Batas byte/piksel, kamera/galeri existing, RAM satu/banyak upload; quota dan alert kapasitas |
| M10–M14 | FCM nyata, reminder catch-up, finance critical tests, ekspor aman, same-origin CMS |
| Operasi | Backup terenkripsi DB+media konsisten, restore RPO≤24h/RTO≤4h, rollback |
| Production | Insiden R07 ditutup; credential dirotasi/revokasi; domain RT, APK signed, UAT, kontak support |

Tidak menandai milestone passed hanya karena config ada. Test CI tidak membuktikan hosting cocok. Tidak menjanjikan nol error.

## Status eksekusi sesi ini

Repo disalin dari main 840cf09. Workspace ini tidak mempunyai PHP/Composer/PostgreSQL; tes PHP harus diverifikasi lewat CI. Tidak ada credential hosting atau provider baru, sehingga preflight live, routing aktual, backup destination, provisioning dan penerimaan produksi masih blocked. Pemeriksaan repo/diff lokal dan hasil CI dicatat sebelum handoff.
