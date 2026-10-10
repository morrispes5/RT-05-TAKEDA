# Deployment dan operasi — Hostinger / morriz.tech

## Target dan status

Target aplikasi: https://takeda.morriz.tech; staging https://takeda-staging.morriz.tech.
Domain utama morriz.tech adalah koreksi eksplisit pemilik pada 10 Oktober 2026.
VPS Hostinger disebut RAM 8 GB/storage 100 GB dalam konteks; kapasitas bebas/CPU/OS/service harus diinspeksi sebelum alokasi. Dokumen ini tidak menyatakan DNS atau service sudah dikonfigurasi.

Neon berada di luar VPS. Mobile berjalan di HP. VPS menjalankan website, API, worker, scheduler, Redis dan media; dapat menyajikan APK.

## Inspeksi sebelum deploy

Catat OS, Docker/Compose, reverse proxy existing, Coolify/panel jika ada, ports/networks, free RAM/disk, restart policy, service lain termasuk agent pengguna, backup destination, DNS records, dan hak deploy.

Jangan install ulang OS, stop service lain, flush firewall, atau menjalankan proxy kedua pada 80/443. Jika Coolify sudah terpasang, reuse routing/resource Compose yang didukung. Jika tidak ada orchestrator, Docker Compose + satu reverse proxy adalah default. Tidak membuat cluster/Kubernetes.

## Docker topology

| Service | Build/source | Catatan |
| --- | --- | --- |
| gateway | Config route/TLS | Reuse proxy existing bila tersedia |
| web-nginx | website public/static | FPM web upstream |
| web | website image PHP-FPM | Rendering Blade; tidak punya DB warga |
| api-nginx | API public/index.php | FPM API upstream |
| api | rest-api image PHP-FPM | HTTP runtime |
| worker-default | image API sama | Notifications/default |
| worker-media | image API sama | Transcode |
| worker-exports | image API sama | CSV |
| scheduler | image API sama | schedule:work single instance |
| redis-queue | Redis pinned supported image | AOF, volume, noeviction |
| redis-cache | Redis pinned supported image | Cache/session/limiter |
| migrate | image API sama, one-shot | Direct DB connection; no restart |

PHP image memakai ext pdo_pgsql, redis, mbstring, intl, fileinfo, pcntl untuk worker, image library yang dibutuhkan. Pin version/digest setelah build diuji. Node build stage menghasilkan Vite assets; composer install --no-dev di final image. Tidak vendor/node_modules dari mesin pengguna ke image.

web/API mempunyai APP_KEY sendiri. Worker/scheduler memakai APP_KEY dan config API yang sama. Queue/cache/session connection dipetakan eksplisit di config Laravel.

## Resource awal

Baseline planning, setelah mengukur headroom:

| Service kelompok | Batas awal rancangan |
| --- | --- |
| API FPM | 768 MiB; jumlah child disesuaikan memory |
| Web FPM | 384 MiB |
| Worker default/media/exports | 256/512/256 MiB, masing-masing satu proses |
| Scheduler | 192 MiB |
| Redis queue/cache containers | 384/256 MiB termasuk overhead |
| Nginx/proxy | Ukur pemakaian; limit sesuai host |

Sisakan headroom OS dan service existing. Angka bukan benchmark; memory tinggi pada foto/exports diukur M15. Set log rotation, disk alert >80%, dan quota staging/media agar storage 100 GB tidak habis oleh logs/build layers.

## Environment dan jaringan

Production dan staging tidak berbagi database branch, prefix Redis, volume media, APP_KEY, session cookie, device token, FCM project/topic, atau outbound recipient settings.

Staging push/email memakai test recipient/project; tidak mengirim notifikasi ke warga production. Staging host noindex dan access terbatas sesuai kebutuhan demo.

Hanya API/worker menyimpan database URL. Runtime pooled; migrate/backup direct role. FCM service account file mount read-only. Secret tidak dikirim sebagai build arg yang masuk layer image.

Health:

- live: proses HTTP sehat tanpa memanggil DB.
- ready: dependency tersedia dengan timeout, tidak memaparkan hostname/credential.
- workers: heartbeat updated oleh loop/job; scheduler heartbeat.
- public status endpoint minimal; detail operasi hanya internal.

## DNS dan TLS

1. Setelah akses DNS diotorisasi, buat A record takeda dan takeda-staging ke IP VPS yang benar.
2. Jangan mengubah apex morriz.tech/MX/domain service lain.
3. Jangan menambah AAAA kecuali IPv6 server dan routing sudah berfungsi.
4. Proxy menerbitkan sertifikat TLS host tersebut; uji renewal dan HTTPS redirect.
5. Verifikasi dari jaringan luar: public routes, /api/v1, csrf cookie, no internal ports.
6. Trusted proxy/forwarded headers dan secure cookie diuji di domain nyata.

Tidak mencantumkan IP/credential palsu dalam config. Agent menulis placeholder dan daftar input yang dibutuhkan.

## Staging awal M04

Deploy fondasi web/API/Redis/worker/scheduler dengan data sintetis. Uji health, koneksi Neon staging, job benar-benar dieksekusi, persist queue setelah restart, dan web baseline. Belum diumumkan sebagai sistem warga production.

Adapter FCM/SMTP dapat fake secara eksplisit sampai credential tersedia; test nyata wajib sebelum feature disebut operational.

## Pipeline rilis

1. CI lint/tests/contract/build mengeluarkan image immutable dengan tag commit SHA.
2. Deploy staging, run migration direct sekali, lalu smoke/e2e.
3. Catat artifact APK signed/checksum/version dan release notes bila mobile berubah.
4. Production: pastikan scope deploy sudah diotorisasi, backup terbaru/restore path, disk/headroom, dan schema compatibility.
5. Jalankan migration additive single instance; stop rollout jika gagal.
6. Start API/web image baru, readiness lulus, alihkan traffic; restart workers/scheduler ke image sama.
7. Run smoke finance tanpa mengarang pembayaran real; gunakan transaksi terkontrol yang ditetapkan mitra atau check read-only bila data real.
8. Pantau error rate, queue/outbox, latency, DB connections, saldo reconciliation.
9. Catat tag/image/migration/environment/results pada PROGRESS dan release log.

Migration bukan per-container startup bersamaan. Jangan menjalankan seed demo otomatis production. Bootstrap admin dilakukan command operator tanpa default password, secret interactive/runtime dan audit.

## Backup

Target v1: encrypted daily DB dump + media backup off-VPS, 7 harian dan 4 mingguan sebagai rancangan. Destination harus disediakan; folder lain di VPS sama bukan offsite backup.

Backup worker memakai direct URL, client pg_dump yang kompatibel server, akses read minimal dan exit checking. Secret tidak ditempel pada command yang tercatat shell history/log.

Simpan checksum, manifest timestamp/schema/app version, keberhasilan, dan lokasi privat. Backup konfigurasi/kunci encryption/signing dikelola terpisah aman; jangan memasukkan secrets ke arsip source.

Restore drill: restore DB ke branch/instance terisolasi, restore media, konfigurasi key yang relevan, verify row counts, sample attachments, charge/payment/ledger sum, auth/reset, published content. Catat durasi terhadap target RTO 4 jam dan kehilangan data terhadap RPO 24 jam.

Queue AOF membantu restart; bukan pengganti backup DB atau outbox. RPO nyata bergantung backup/PITR provider yang tersedia dan perlu dibuktikan.

## Rollback

- Simpan image release sebelumnya dan config compatible.
- Ganti traffic/image ke versi sebelumnya; restart worker ke versi compatible event schema.
- Jangan otomatis migrate:rollback pada production setelah ada transaksi baru.
- Skema additive memungkinkan aplikasi lama tetap berfungsi; destructive schema memerlukan release terpisah.
- Jika data rusak, hentikan mutation terkait, buat snapshot evidence, rencanakan restore/reconcile; jangan restore seluruh production hingga menghapus transaksi valid tanpa persetujuan.

## Runbook

| Gejala | Tindakan |
| --- | --- |
| Neon unavailable | Baca error aman; readiness gagal; jangan fallback ke DB kosong; mutation gagal jelas |
| Redis queue down | Domain committed tetap ada; recover Redis, replay pending/stale outbox |
| Cache down | Public read fallback terkontrol; login limiter fail closed; session mungkin perlu login ulang |
| Push/SMTP down | Retry terbatas; inbox tetap ada; jangan membalik pembayaran |
| Worker absent | Restart image benar, inspect failed jobs/heartbeat |
| Media disk penuh | Hentikan upload, alert; bersihkan orphan sesuai kebijakan; jangan hapus album referenced |
| Double payment suspect | Reconcile charge/allocations/ledger, jangan edit saldo manual |
| Credential leak | Revoke/rotate, audit akses, rebuild config; hapus dari source history dengan prosedur |

Dashboard minimum: uptime/public smoke, latency/error, queue/outbox age, worker/scheduler, failed jobs, CPU/RAM/disk, DB connections, backup success. Gunakan fasilitas existing dahulu; monitoring stack besar bukan syarat v1.

## Biaya

Neon, backup, email provider, push tooling/distribusi, domain, dan VPS dapat mempunyai limit/biaya berbeda. Paket ini tidak memesan layanan atau menjamin semua gratis. M04/M16 mencatat plan aktual, budget, dan alert biaya; layanan berbayar baru memerlukan instruksi pemilik.
