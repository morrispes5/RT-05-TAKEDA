# Runbook production — RT05 TAKEDA (M16)

Disusun 22 Oktober 2026 untuk Fase B–F di [M16_PLAN.md](M16_PLAN.md). Profil target: shared hosting Biznet NEO Web Hosting
Personal Large ([SHARED_HOSTING.md](SHARED_HOSTING.md), ADR23). Setiap langkah yang menyentuh hosting, DNS, atau database
production hanya dijalankan atas izin pemilik per langkah. Langkah yang belum bisa dijalankan ditandai **blocked**.

## 1. Pertanyaan ke Biznet sebelum membeli (pemilik mengirim)

Salin teks ini ke live chat atau tiket sales Biznet Gio:

> Halo, saya berencana membeli NEO Web Hosting Personal **Large** (Rp399.000 tahun pertama) untuk aplikasi Laravel.
> Mohon konfirmasi:
> 1. Apakah PHP 8.3 atau lebih baru tersedia untuk web **dan** command line (cron/terminal), dengan ekstensi `pdo_pgsql`,
>    `gd` (JPEG/PNG/WebP), `intl`, `mbstring`, `fileinfo`, `openssl`, dan `curl`?
> 2. Apakah koneksi keluar dari hosting ke port **5432** (PostgreSQL di Neon/Supabase) dan port 443 (HTTPS API) diizinkan?
> 3. Apakah cron job di cPanel bisa dijalankan tiap 15 menit? Berapa batas waktu eksekusi proses PHP CLI dan web?
> 4. Apakah tersedia akses SSH atau Terminal di cPanel? Berapa batas entry process, CPU, dan IO paket Large?
> 5. Apakah document root domain bisa diarahkan ke subfolder (misalnya `rt05/website/public`), bukan `public_html` langsung?
> 6. Berapa total yang dibayar saat checkout (termasuk PPN), berapa harga perpanjangan hosting **dan** domain .com tahun
>    kedua, dan bagaimana ketentuan refund bila ternyata tidak cocok?
>
> Terima kasih.

**Jangan membeli** bila jawaban butir 1–3 "tidak". Simpan tangkapan layar jawaban sebagai bukti preflight.

## 2. Setup production (Fase C, setelah membeli)

1. **Domain (K1):** domain gratis paket diarahkan ke hosting. Catat registrant dan tanggal perpanjangan.
2. **Neon project production terpisah.** Buat project baru khusus production (kuota 100 CU-jam per project), lalu dua role:
   migrator (DDL, hanya di mesin operator/CI) dan runtime (DML, di hosting). Jalankan `infra/sql/grant-runtime-role.sql`.
   Hosting hanya menerima URL pooled role runtime, `DB_SSLMODE=verify-full`, dan CA bundle.
3. **Build di mesin operator/CI** (bukan di hosting): `composer install --no-dev --optimize-autoloader` untuk rest-api dan
   website, `npm.cmd run build` untuk website. Unggah tanpa `.env`, `node_modules`, `tests`, atau `storage/logs` lama.
4. **Struktur folder hosting** (di luar document root kecuali `public`):
   `~/rt05/rest-api`, `~/rt05/website`, document root → `~/rt05/website/public` dengan `index-api.php` sesuai
   `infra/shared/public-htaccess.example`.
5. **Env privat:** API = `rest-api/.env.example` + overlay `infra/shared/api.env.example`. Website = `website/.env.example` +
   overlay `infra/shared/web.env.example` (`RT05_SEARCH_INDEXING=true`, `APP_URL` domain production). APP_KEY website dan
   API berbeda. Isi secret (APP_KEY, DATABASE_URL, mail, FCM) langsung di hosting, tidak di Git atau chat.
6. **Migrasi** dari mesin operator dengan URL direct role migrator: `php artisan migrate --force`, lalu grant runtime.
   Jangan pernah `migrate:fresh`, `db:wipe`, atau seed tes di production.
7. **Cron:** satu baris `*/15 * * * * cd ~/rt05/rest-api && php artisan rt05:shared-tick >> ~/rt05/cron.log 2>&1`.
8. `php artisan config:cache` dan `route:cache` di kedua aplikasi setelah env lengkap.
9. **Admin pertama (K5):** `php artisan rt05:pengurus buat <email-asli-pengurus> --nama="<nama>"` (perintah operator tanpa
   kata sandi bawaan, DEPLOYMENT.md). Kata sandi diatur sendiri oleh pengurus lewat reset, tidak dikirim lewat chat.
   Jangan menjalankan `rt05:demo-seed` di production.
10. **Smoke dari jaringan luar:** check-host.net untuk `/up` dan `/health/live`, lalu `infra/scripts/staging-runtime-smoke.sh`
    dengan origin production (hanya endpoint baca + akun admin pertama). Bukti di `docs/evidence/m16/`.

## 3. Rilis APK ke halaman unduh

1. Set repository variable `RT05_PROD_BASE_URL` = `https://<domain-production>` (sekali, setelah domain live).
2. Jalankan workflow **Mobile release APK** dengan `flavor=prod` dan isi `catatan` (baris pendek, tanpa data warga).
3. Unduh artifact: dua APK, `.sha256`, dan `rt05-takeda-prod-<build>-versi.json`.
4. Salin APK ke `public/unduh/` di hosting, lalu salin `...-versi.json` menjadi `public/unduh/versi.json` **terakhir**
   (supaya halaman tidak menautkan berkas yang belum lengkap). Untuk staging, folder yang sama adalah volume
   `rt05-staging_apk-downloads`.
5. Buka `https://<domain>/aplikasi`. Versi, tanggal, ukuran, dan SHA-256 harus sama dengan artifact. Aplikasi lama
   menampilkan "Versi baru tersedia" di menu Akun.
6. Beri tahu pemilik: nomor build, tanggal + jam WIB, tautan.

## 4. Google Search Console (setelah domain live)

1. Pastikan `https://<domain>/robots.txt` berisi `Sitemap:` dan tidak `Disallow: /`, serta `/sitemap.xml` terbuka.
2. Pemilik login [Search Console](https://search.google.com/search-console) → Tambah properti → **Domain** → salin record TXT.
3. Tambahkan TXT di DNS domain (panel Biznet), atas izin pemilik. Tunggu, lalu klik Verifikasi.
4. Sitemap → kirim `https://<domain>/sitemap.xml`. Inspeksi URL beranda → Minta pengindeksan.
5. Uji data terstruktur beranda dengan Rich Results Test. Catat tanggal pertama "RT05 Takeda" muncul di pencarian.

## 5. Backup dan restore drill (blocked: tujuan offsite belum dipilih pemilik)

- **Database:** dump harian dari mesin operator dengan URL direct (role pemilik schema, bukan runtime):
  `pg_dump --format=custom --no-owner "$DATABASE_URL_UNPOOLED" > rt05-YYYYMMDD.dump`, lalu enkripsi sebelum keluar mesin
  (mis. `age -r <kunci-publik-pemilik>`). Simpan 7 harian + 4 mingguan di tujuan offsite yang dipilih pemilik. **Jangan**
  ke repo Git (repo publik) dan jangan ke folder lain di hosting yang sama.
- **Media:** arsip `rest-api/storage/app/private` (foto pengaduan/bukti/konten) dengan enkripsi yang sama.
- **Restore drill:** pulihkan dump terbaru ke branch Neon kosong (bukan production), jalankan
  `php artisan rt05:rekonsiliasi-keuangan` terhadapnya, lalu bandingkan jumlah baris tabel utama. Catat RPO (umur dump) dan
  RTO (waktu sampai API membaca branch hasil restore). Target RPO ≤24 jam, RTO ≤4 jam.
- **Rollback aplikasi:** simpan folder rilis sebelumnya (`~/rt05/releases/<commit>`) dan tukar symlink/folder. Migrasi
  hanya aditif, jadi rilis lama tetap bisa membaca skema baru. Jangan menjatuhkan tabel `jobs` yang masih berisi antrean.

## 6. Setelah live

- Pantau `/up` dan heartbeat `shared-tick` (usulan UptimeRobot Free; perlu akun pemilik).
- Pantau kuota Neon production (CU-jam) mingguan. Siapkan pindah ke PostgreSQL cadangan sesuai ADR29 bila mendekati batas.
- Uji performa ulang dari jaringan luar pada host production. Bukti staging 22 Okt (lewat WARP dari PC pemilik) belum
  memenuhi P95 ≤1 dtk: [load-20u-staging-2026-10-22.json](evidence/m16/load-20u-staging-2026-10-22.json).
