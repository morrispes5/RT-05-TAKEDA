# Profil shared hosting RT05 TAKEDA

15 Oktober 2026 — ADR23. Implementasi opt-in; **belum diterima untuk produksi**.

## Keputusan dan biaya

Laravel web + Laravel API + Flutter + PostgreSQL tetap. Website tidak mendapat DSN warga. Anggaran tahun pertama hosting/domain maksimal Rp500.000; promo diizinkan. Kandidat Biznet Personal Large Rp399.000/tahun pertama dengan domain gratis .com sesuai ketentuan. Pastikan pajak/checkout total. Harga pembanding halaman Rp981.000 tidak dianggap tagihan renewal final; catat renewal hosting/domain sebelum handover.

**18 Oktober 2026 (ADR29):** kuota Neon yang habis tidak boleh mematikan aplikasi. Satu PostgreSQL cadangan di penyedia lain (Supabase Free, dipilih pemilik; jeda 7 hari dimitigasi sinkron berkala + pemantau) dipakai aktif-pasif dengan pindah terencana sebelum kuota habis. Cron sweep 15 menit saat ini membangunkan Neon walau idle; menguranginya adalah prioritas pertama.

Neon Free: halaman resmi saat diperiksa 15 Oktober 2026 mencantumkan 100 CU-hours dan 1 GB database/project. Changelog 2 Oktober 2026 menaikkan storage dari 0,5 GB ke 1 GB. Pisahkan project produksi dari pengujian, bukan membagi data RT ke banyak project demi kuota. Pada 0,25 CU, 100 CU-hours hanya 400 jam compute aktif. Jaring tiap 15 menit yang membangunkan compute lalu tidur lagi setelah 5 menit sekitar 60 CU-hours/30 hari TANPA trafik lain. Itu hitungan kasar, bukan jaminan. Ukur minimal tujuh hari sebelum produksi.

Resend Free (halaman harga resmi): 3.000 email/bulan, 100/hari (hari UTC), dan sampai 3 domain. Email terkirim maupun diterima dihitung; beberapa penerima pada satu pesan dihitung terpisah. Cukup untuk reset, bukan pengingat massal. Integrasi staging yang sudah lulus memakai SMTP (ADR20). Target profil shared adalah HTTP Resend karena shared hosting sering memblokir SMTP, tetapi paket `resend/resend-php` belum ada di composer.json: jangan mengganti MAIL_MAILER sebelum paket terpasang dan inbox nyata lulus. Gmail hanya penerima. FCM tanpa biaya untuk push; belum ada bukti provider M10. Foto di disk privat hosting. Aktivasi R2 meminta metode pembayaran walaupun ada kuota gratis, jadi R2 bukan default. Backup terenkripsi offsite tetap wajib; tujuan belum dipilih. Jangan gunakan Git repo publik untuk backup.

Sumber harga/kuota (bisa berubah):
- https://www.biznetgio.com/product/neo-web-hosting-personal
- https://neon.com/pricing
- https://resend.com/pricing
- https://www.resend.com/blog/new-free-tier
- https://firebase.google.com/pricing

## Cara kerja

Profil default `vps` tidak berubah. Pilih `RT05_DEPLOYMENT_PROFILE=shared` hanya di staging yang lolos preflight. Tambahkan tabel `jobs` melalui koneksi migrator, tetap pisahkan credential DDL dari hosting. Config queue database memakai role runtime DML. Job reset tetap terenkripsi dan after-commit; outbox tetap persisten.

Jalur utama, sesuai rancangan hemat: setelah response HTTP terkirim (`fastcgi_finish_request` atau `litespeed_finish_request`), proses yang sama menguras outbox yang baru commit dan mentranskode foto. Pengguna tidak menunggu worker. Neon sudah bangun karena request itu, jadi kerja ini tidak menambah jadwal polling. Bila proses mati sebelum selesai, baris outbox/foto tetap ada.

Cron `rt05:shared-tick` adalah jaring tiap 10–15 menit, bukan syarat tiap menit. File lock menolak tick bersamaan. Scheduler tetap jalan. Tick tanpa penanda dan di luar jendela 15 menit tidak membuka database. Jendela 15 menit mengirim ulang outbox, memulihkan foto tertahan, dan menguras antrean. Cron tiap menit boleh dipakai bila panel menyediakan; itu tidak wajib untuk fitur lama. **Sejak M19 (audit 28 Okt) production memakai cron tiap menit**: alarm panik yang gagal terkirim setelah respons dicoba lagi menit berikutnya, sedangkan alarm berumur lebih dari 10 menit tidak lagi berbunyi. Tick idle tetap tidak membuka database.

Job pada koneksi `database`, termasuk email reset yang tidak menulis outbox, mendaftarkan pengurasan yang sama setelah response. Pendaftaran satu kali per proses. Reservasi baris `jobs` menolak dua worker mengambil job yang sama, jadi callback yang terpanggil lagi tidak mengirim email dua kali. Outbox tetap lewat `Outbox::record` lalu dispatcher. Bila proses mati sebelum callback setelah response berjalan, pemulihan menunggu sweep berikutnya (paling lama sekitar 15 menit).

Heartbeat `shared-tick` ditulis hanya setelah tick selesai, bukan di awal. Tick yang macet tidak memperbarui penanda. Bila `RT05_HEARTBEAT_MAX_AGE` kosong, umur sehat adalah interval cron 900 detik ditambah 300 detik keterlambatan eksekusi (1200 detik). Beat berumur 900 detik masih sehat. Beat yang lebih tua dari 1200 detik, atau tidak ada, berarti cron berhenti atau tick tidak selesai. Lima menit itu menutup keterlambatan cron yang masih wajar tanpa menunggu satu putaran cron lagi; proses yang macet jadi basi sekitar lima menit setelah jadwal berikutnya terlewat. Mengisi 300 detik membuat cron 15 menit yang sehat terlihat gagal. Profil VPS tetap 300 detik karena workernya menulis heartbeat minimal tiap 30 detik. Nilai env yang diisi menimpa rumus ini.

`pcntl` tidak wajib dan bukan penghentian yang selalu ada. Bila `pcntl_alarm` serta `pcntl_async_signals` ada, worker memasang SIGALRM (`--timeout=40` bila job tidak menentukan timeout sendiri) dan proses yang melewati batas dihentikan. `--max-time` tetap hanya diperiksa di antara job, dengan atau tanpa `pcntl`; itu tidak menghentikan satu job yang sedang berjalan. Tanpa sinyal itu, `--timeout` dipasang 0 karena Laravel mengabaikan timeout positif bila `pcntl` tidak ada. Sebagai gantinya setiap job database dan transkode foto inline memasang batas kooperatif 20 detik: `default_socket_timeout`, `mail.mailers.smtp.timeout` hanya bila kosong (`MAIL_TIMEOUT` yang sudah diisi, misalnya 15, tidak ditimpa), `statement_timeout` Postgres 20000 ms, dan `set_time_limit` (waktu CPU skrip, bukan jam dinding). Batas ini tidak menghentikan job yang macet di GD atau di panggilan yang mengabaikan batas soket, SQL, dan CPU. Batas ukuran foto (4 MiB, 6 juta piksel, JPEG ≤1600) mengurangi risiko transkode inline. Sisa perlindungan adalah batas proses dari host atau cron. Jangan menganggap timeout keras sudah ada hanya karena `--max-time` terpasang.

Cache, session, heartbeat, dan batas token memakai store file. Lock file pada satu host gagal tertutup (429), bukan melewati batas. Jangan jalankan `schedule:work` atau daemon worker bersamaan.

Target inbox/email mulai diproses ≤60 detik dipenuhi jalur setelah response, bukan oleh cron 15 menit. Cron 15 menit yang lolos preflight #7 tetap kompatibel. Gangguan proses menunda pemulihan sampai sweep berikutnya; failed job harus terlihat operator.

Foto shared dibatasi server ke 4 MiB dan 6 juta piksel, output ≤1600 px; batas VPS tetap. Selain itu profil shared menolak akun yang sudah membuat 5 pengaduan atau akan melewati 25 foto pada hari yang sama (WIB). Picker Flutter membatasi kedua sisi ke 1600 px, kualitas 80; server tetap memvalidasi byte/piksel. Uji APK baru pada kamera/galeri nyata; kompresi web mengikuti M13/M14. Metadata tetap dibuang server. Ekspor CSV admin sudah di-stream di response (`OperationsController`); tidak memakai worker-exports.

## Preflight wajib sebelum pembelian/aktivasi

1. Konfirmasi harga all-in, domain .com, kepemilikan RT, renewal dan ketentuan refund secara tertulis.
2. PHP memenuhi composer.lock (uji `composer check-platform-reqs` terhadap build), web+CLI; pdo_pgsql, GD JPEG/PNG/WebP, mbstring, intl, fileinfo, openssl, curl. `pcntl` memberi SIGALRM per job. Tanpa `pcntl`, batas kooperatif SMTP/SQL/soket/CPU tetap dipasang, tetapi satu job yang macet di luar batas itu tidak bisa dihentikan paksa.
3. Outbound Neon 5432 dengan TLS verify-full dan CA bundle; uji DML pooled, runtime DDL ditolak. Jangan menurunkan ke sslmode=require.
4. Cron dengan interval 15 menit atau lebih sering, batas waktu proses cukup untuk kerja setelah response, file lock bekerja. `fastcgi_finish_request` atau `litespeed_finish_request` tersedia.
5. Document root hanya public, .env/vendor/storage privat tidak dapat diunduh. Web/API mempunyai APP_KEY/cookie berbeda; API CLI memakai key yang sama dengan API web.
6. Resource CPU/RAM/proses/IO diuji; jangan menilai hanya dari SSD. Batas foto, kuota harian, dan upload PHP selaras; alert disk wajib sebelum live.
7. Dua front controller same-origin: /api/v1, /sanctum/csrf-cookie, /health/*, serta GET/POST /reset-password menuju API; path lain website. Sketsa ada di `infra/shared/public-htaccess.example`. Sketsa itu belum diuji pada akun hosting. Jangan menaruh seluruh repo dalam public_html.
8. PostgreSQL cadangan Supabase (ADR29): koneksi TLS verify-full dari hosting ke pooler IPv4 Supabase, migrasi penuh lulus, latensi request API diukur. Bila hosting menyediakan PostgreSQL ≥13 di cPanel, catat versinya sebagai kandidat standby.

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
| Media | Batas byte/piksel, kuota harian, transkode setelah response, kamera/galeri, RAM; alert kapasitas |
| M10–M14 | FCM nyata, reminder catch-up, finance critical tests, ekspor aman, same-origin CMS. M10 kode (17 Okt): push lewat HTTP saja tanpa paket/daemon; inbox+push selesai setelah response diuji (`SharedRuntimeTest`); jadwal catch-up via cache file. FCM nyata dan PoC host belum. M12: rekonsiliasi harian per tanggal WIB; ekspor CSV kecil di-stream (ADR27) |
| Operasi | Backup terenkripsi DB+media konsisten, restore RPO≤24h/RTO≤4h, rollback |
| Cadangan DB (ADR29) | Pemantau kuota Neon; satu siklus pindah terencana + kembali di staging tanpa tulisan hilang; jumlah baris dan rekonsiliasi keuangan identik; antrean/outbox diproses sekali; pindah darurat dengan RPO tercatat; bukti sinkron mencegah jeda 7 hari Supabase dan pemantau mendeteksi project terjeda |
| Production | Insiden R07 ditutup; credential dirotasi/revokasi; domain RT, APK signed, UAT, kontak support |

Tidak menandai milestone passed hanya karena config ada. Test CI tidak membuktikan hosting cocok. Tidak menjanjikan nol error.

## Status eksekusi sesi ini

Repo disalin dari main 840cf09. Penyesuaian susulan menyelaraskan jalur utama ke setelah response, jaring cron 15 menit, dan kuota harian. Hosting belum dibeli. Paket Resend HTTP, bukti inbox, routing nyata, backup, dan pengukuran CU-hours tetap blocked. Pemeriksaan lokal 15 Oktober 2026: `php vendor/bin/pint --test` lulus; `php artisan test` pada Postgres lokal **242/242, 2272 assertion**. Itu bukan bukti hosting atau Neon produksi.

Koreksi susulan pada hari yang sama: email reset database ikut pengurasan setelah response, heartbeat shared 900+300 detik, dan tanpa `pcntl` hanya batas kooperatif (bukan timeout keras). `php vendor/bin/pint --dirty` lulus. `php artisan test` pada Postgres lokal `rt05_test` port 5432 **245/245, 2311 assertion**. `node infra/scripts/check-repo.mjs` OK (804 berkas). Bukan bukti hosting, Neon, atau inbox SMTP.
