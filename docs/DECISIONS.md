# Keputusan dan ADR

## Prioritas sumber

1. Instruksi langsung terbaru pemilik.
2. Kebutuhan bisnis yang terverifikasi dari diskusi/logbook.
3. Baseline frontend yang diterima dan handoff terbaru.
4. Keputusan rekayasa v1 dalam paket ini.
5. Dokumen/asisten lama yang belum terverifikasi.

Konflik tidak diselesaikan dengan menyalin keputusan lama yang tampak paling teknis. SQLite/Drizzle/web-only pernah muncul dalam rancangan lama, tetapi paket kini mengikuti permintaan eksplisit website+mobile+REST API+Neon+Redis+Hostinger.

## Kebutuhan produk dipertahankan

| ID | Keputusan | Dasar |
| --- | --- | --- |
| P01 | Nama RT05 TAKEDA | Nama final proyek |
| P02 | Website publik tanpa akun, layanan warga mobile | Logbook 2 dan diskusi |
| P03 | Iuran Rp75.000/rumah/bulan, offline, tanpa gateway | Koreksi eksplisit pembayaran |
| P04 | Pengurus admin setara | Logbook 2/diskusi |
| P05 | Akun tambahan approval dan link keluarga/rumah | Logbook 2 |
| P06 | Token 6 digit acak dan rotasi | Logbook 2/diskusi |
| P07 | Anonim ke warga, terlihat admin | Logbook 2/diskusi |
| P08 | Arsip/audit/transparansi/histori ≥12 bulan | Logbook 2/diskusi |
| P09 | Approved frontend dipertahankan; CMS Artikel/Dokumentasi | Handoff repo terbaru |
| P10 | Paket konteks dan milestone dikirim di chat, tidak edit GitHub pada sesi desain | Instruksi 10 Oktober 2026 |
| P11 | Domain morriz.tech | Koreksi typo pemilik 10 Oktober 2026 |
| P12 | Jumlah developer tidak membatasi rancangan | Koreksi pemilik |
| P13 | Vercel tetap preview; production di subdomain morriz.tech (rancangan takeda.morriz.tech) pada VPS Hostinger | Instruksi pemilik di sesi M01, 10 Oktober 2026 |
| P14 | Pemilik menawarkan akses panel Hostinger/DNS lewat Chrome dan emulator Android untuk tahap berikutnya | Instruksi pemilik di sesi M01; dipakai pada M04/M05 dengan konfirmasi per tindakan eksternal |
| P15 | Kerjakan semampunya lintas milestone dengan izin VPS/Docker/PC; hemat SSD; desain mobile boleh kolaborasi Google Stitch | Instruksi pemilik sesi 3, 10 Oktober 2026 |
| P16 | Codex mengoordinasikan Claude Code, Grok, dan Kimi dengan ownership terpisah; selesaikan satu tahap dan gate sebelum maju, checkpoint bila limit atau listrik mati | Instruksi pemilik 11 Oktober 2026; mempersempit eksekusi serentak P15. Rencana di COORDINATION.md |
| P17 | Target lanjutan sampai M05 secara bertahap; cocokkan dengan planning dan Drive capstone, laporkan setiap perbedaan; tidak membuka seluruh roadmap dalam satu hari | Instruksi pemilik terbaru 11 Oktober 2026 |
| P18 | Buka M06 lalu M07 setelah M06 passed; akun utama langsung aktif dengan keluarga belum_verifikasi, tambahan menunggu persetujuan; rumah existing tidak auto-merge; email menggunakan password aplikasi tanpa Google OAuth; album tambahan sekarang; laporan pada satu folder Drive; SMTP harus terbukti sampai inbox; production/data nyata tertahan insiden credential | Rencana pemilik yang diminta diimplementasikan12 Oktober2026; memperluas batas P17 secara bertahap |
| P19 | Lanjutkan sampai M06 dan M07 melalui Orca; andalkan Claude Code untuk backend/database serta Grok untuk mobile/build/infrastruktur, Codex membantu Chrome dan koordinasi. Pemilik mengotorisasi pengelolaan akun existing GitHub, Vercel, Drive, dan VPS Hostinger dalam lingkup ini. Kimi Code memperbarui dokumentasi manusia di folder Drive Capstone/Tugas dan Project existing: user flow, use case, diagram, layar nyata, alasan dan perbandingan perubahan. Credential tetap privat; gate M06 sebelum M07 dan batas data sintetis/production tetap berlaku. | Instruksi langsung pemilik setelah pemulihan sesi12 Oktober2026 |

## ADR rekayasa v1

### ADR01 — Modular monolith dan empat komponen

Status: dipilih untuk eksekusi v1.
website/mobile/rest-api/docs pada satu Git repository; infra/ untuk dukungan.
Laravel API memiliki domain/database; web mempunyai shell renderer saja.
Alasan: mempertahankan frontend, mengurangi duplikasi bisnis, deploy terpisah tanpa microservices.

### ADR02 — Stack

Laravel 13/Eloquent/PostgreSQL Neon, Blade/Tailwind existing, Flutter Android.
Flutter adalah pilihan rekayasa paket ini; handoff sebelumnya belum menetapkan framework.
Redis queue/cache dan Docker Hostinger. Tidak memakai Express/Nest atau database bisnis kedua.
Versi pasti dipin setelah kompatibilitas diverifikasi; tidak mengubah lockfile accepted untuk mengejar versi terbaru.

### ADR03 — Same-origin API

Host takeda.morriz.tech, API /api/v1, csrf route ke API.
Alasan: menyederhanakan cookie/CSRF/CORS CMS dan konfigurasi mobile.
Staging host/data/secrets terpisah. Host ini rancangan; belum klaim DNS telah aktif.

### ADR04 — Auth

Sanctum bearer per device mobile; cookie session+CSRF CMS.
API role/status policy selalu menjadi otoritas. Tidak menggunakan Neon Auth tambahan pada v1; Neon hanya database untuk arsitektur ini.
Token registrasi bukan API token.

### ADR05 — Keuangan sinkron dan immutable

DB transaction/row locks/constraints/idempotency untuk offline payment.
V1 full-month settlement; partial/overpay ditolak karena kebijakannya belum disepakati.
Koreksi reversal/replacement, kas signed entries, audit.
Alasan: menjaga rekam asal dan mencegah retry/concurrency menggandakan uang.

### ADR06 — Redis+outbox

Antrean dan cache terpisah. Outbox durable di Postgres, jobs at-least-once, consumer idempotent.
Payment commit tidak bergantung push/email sukses.
Polling outbox tiap menit dapat menahan compute Neon tetap aktif; evaluasi biaya/interval setelah pengukuran. Jangan menjanjikan scale-to-zero sambil rutin mengakses DB.

### ADR07 — Storage

VPS persistent volume + offsite encrypted backup; filesystem adapter memudahkan pindah ke S3 bila kebutuhan muncul.
Private upload terlebih dahulu; publik hanya published media. Tidak menambah MinIO/S3 berbayar untuk mulai.

### ADR08 — Android first

Satu aplikasi warga/pengurus; APK signed melalui HTTPS. iOS/Play Store lanjutan.
Runtime Flutter berada di HP; VPS menyediakan layanan dan artifact download.

### ADR09 — Iterasi dan staging awal

M00 baseline +16 milestone lanjutan. Staging fondasi M04, production M16.
Gate berdasarkan perilaku nyata, bukan jumlah developer/jumlah LOC.

### ADR10 — Batas administratif v1

Akun utama dapat mengedit keluarga sendiri, tambahan memakai layanan setelah approval.
Grant admin hanya operator command; seluruh admin aplikasi hak setara.
House billing start/enabled manual; tidak menghapus tunggakan ketika penghuni pindah.
Detail ini dipilih sebagai default terbatas, bukan diklaim persetujuan mitra baru.

### ADR11 — Migrasi monorepo dengan git mv di repository asal

Tanggal/status: 10 Oktober 2026, diterapkan pada M01.
Keputusan: frontend dipindah ke `website/` dengan `git mv` di repository `morrispes5/RT-05-TAKEDA`, bukan ZIP/subtree ke repo baru. Dokumentasi frontend ke `docs/website/`, workflow ke `.github/workflows/website.yml` (working-directory `website`), pemeriksaan repo di `.github/workflows/repo.yml`.
Alasan: Git history dan release tag tetap satu; `git log --follow` tetap bekerja; tidak ada `.git` bersarang.
Terdampak: semua perintah web dijalankan dari `website/`; `scripts/package-frontend.ps1` kini mengarsipkan subtree `website/` dengan prefix ZIP `web/` yang sama.

### ADR12 — Jembatan vercel.json root selama transisi

Tanggal/status: 10 Oktober 2026, sementara.
Keputusan: `website/vercel.json` kanonik (`outputDirectory: preview`). `vercel.json` root (`outputDirectory: website/preview`) dipertahankan sampai pemilik mengubah Root Directory proyek Vercel menjadi `website`; `infra/scripts/check-repo.mjs` menjaga kesetaraan keduanya.
Alasan: M01 tidak boleh mengubah integrasi Vercel eksternal, tetapi merge ke `main` tidak boleh memutus preview live.
Penghapusan: setelah Root Directory diubah dan `check-live` lulus, hapus file root dalam PR terpisah. Instruksi: docs/website/VERCEL_ROOT.md.

### ADR13 — Neon: satu project, branch per lingkungan, role runtime via SQL

Tanggal/status: 10 Oktober 2026, diterapkan M02 (dev).
Keputusan: project Neon `rt05-takeda` (id raspy-sky-23605923, aws-ap-southeast-1, Postgres 17, paket gratis) dibuat atas persetujuan pemilik di sesi M02. Branch `production` (default, kosong sampai M16) dan `dev` (sintetis). Staging dibuat sebagai branch terpisah pada M04. Role `rt05_owner` = pemilik schema/migration (endpoint direct); role `rt05_app` = runtime DML (endpoint pooled), dibuat lewat SQL agar bukan anggota `neon_superuser`. Hak runtime berasal dari satu sumber `infra/sql/grant-runtime-role.sql`.
Alasan: isolasi data/credential per branch tanpa layanan berbayar; least privilege runtime. Production dapat dipindah ke project terpisah lewat ADR bila kuota/isolasi menuntut.
Catatan: Neon menolak verifier SCRAM pada CREATE ROLE ("only supports plaintext passwords"); password acak dikirim lewat TLS dan tidak dicetak.

### ADR14 — Pooler Neon tanpa named prepared statement

Tanggal/status: 10 Oktober 2026, diterapkan M02.
Trigger: `rt05:db-smoke` di endpoint pooled gagal 25P02 — pdo_pgsql mengirim DEALLOCATE SQL yang ditolak PgBouncer mode transaksi.
Keputusan: `PGSQL_ATTR_DISABLE_PREPARES=true` untuk kedua koneksi; parameter tetap terpisah (PQexecParams), bukan emulasi string. Tes CI memastikan `pg_prepared_statements` tetap 0 pada koneksi direct.

### ADR15 — TLS verify-full dan CA bundle

Tanggal/status: 10 Oktober 2026, diterapkan M02.
Keputusan: staging/production wajib `sslmode=verify-full` + `sslrootcert`; `/health/ready` (503 misconfigured) dan `rt05:db-smoke` gagal tertutup bila tidak. Parameter URL Neon mengalahkan env, sehingga kebijakan diperiksa pada konfigurasi akhir (`TlsPolicy`). PHP Windows tidak mendukung `sslrootcert=system`; dev Windows memakai salinan CA bundle pada path tanpa spasi.
Bukti: CA asli tersambung; CA palsu ditolak "certificate verify failed"; tanpa TLS ditolak Neon.

### ADR17 — Nama tabel domain mengikuti ERD v1.1 tim

Tanggal/status: 10 Oktober 2026, diterapkan M06–M12.
Trigger: ERD v1.1 (31 tabel, Drive "ERD RT 05 TAKEDA (CAPSTONE PROJECT)") ditemukan; DATABASE.md mewajibkan rekonsiliasi.
Keputusan: tabel domain memakai nama ERD v1.1 (akun, rumah, keluarga, warga, penghunian, pengaduan, aspirasi, tagihan_iuran, pembayaran_iuran, alokasi_pembayaran, transaksi_kas, notifikasi, dst). Penyesuaian rekayasa: rupiah bigint (bukan decimal), status tagihan eksplisit, pembalikan pembayaran (bukan edit), sequence nomor bukti, token terenkripsi + digest, akun.jenis utama/tambahan, permohonan_akun menyimpan nama/alamat diajukan. Tabel operasi (audit_logs, outbox_events, idempotency_requests, system_settings, failed_jobs) tetap nama Inggris; audit_logs ≙ audit_log ERD. Konten publik (profil_rt, pengurus_rt, fasilitas, artikel, album_galeri, foto_galeri) menyusul M13. Path API mengikuti docs/API.md (Inggris), field JSON mengikuti kolom (Indonesia).

### ADR18 — Deploy VPS di belakang proxy Coolify existing

Tanggal/status: 10 Oktober 2026.
Keputusan: stack RT05 (gateway Caddy, api/worker/scheduler/migrate FrankenPHP, web FrankenPHP, Redis) dijalankan sebagai project Compose sendiri yang menempel ke Traefik Coolify lewat label (jaringan `coolify`), bukan proxy kedua di 80/443. Dipicu panel Coolify :8000 tidak terjangkau dan akses shell agent ditolak; deploy dijalankan pemilik dengan `infra/scripts/deploy-vps.sh`. Credential tidak pernah diketik agent ke form web.
Media upload memakai volume `api-storage` (backup offsite masih M16).

### ADR19 — Hemat disk PC pengembang

Tanggal/status: 10 Oktober 2026, instruksi pemilik.
Keputusan: tidak memasang WSL2/Docker di PC; tes Postgres/Redis/Docker di GitHub Actions. Flutter SDK ramping (Android saja, 1,4 GB) di C:\Users\USER\dev\flutter; APK dibangun di CI (artifact) lalu dipasang ke emulator dengan adb.

### ADR16 — Tabel users ditunda ke M06

Tanggal/status: 10 Oktober 2026, diterapkan M02.
Keputusan: migration default Laravel (users/sessions/cache/jobs bigint) dihapus. users UUID dengan role/status dibuat M06; `audit_logs.actor_id` dan `idempotency_requests.actor_id` mendapat FK pada migration M06. Sesi/cache/queue memakai Redis (M03), bukan tabel database.

### ADR21 — Foto pengaduan M08: unggah bersama laporan, proses di worker-media

Tanggal/status: 14 Oktober 2026, diterapkan pada branch M08.
Trigger: gate M08 (worker mencapai ready, retry aman, spoof/oversized/foreign-parent ditolak).
Keputusan sebelumnya: API.md merancang `POST /media` lalu `media_ids` pada create; source awal
mentranskode sinkron di request tanpa status.
Keputusan baru: foto tetap multipart pada `POST /complaints` (kontrak dan APK existing), tetapi
request hanya memvalidasi dan menyimpan berkas mentah privat; `ProcessComplaintPhoto` di antrean
`media` mentranskode menjadi `siap`/`gagal`. Tabel `foto_pengaduan` diperluas additive (status,
percobaan, kode_gagal, updated_at); baris lama default `siap`. `/media` dan `media_assets` tetap
planned untuk konten M13.
Alasan/tradeoff: tidak ada referensi media dari client, sehingga owner/purpose/parent ditetapkan
server saat create dan referensi asing tidak mungkin; satu request untuk warga. Biaya: foto tidak
dapat ditambah setelah laporan dibuat (v1 tidak memintanya).
Kompatibilitas: image M07 tetap dapat membaca/menulis (default `siap`). Tes `M08ComplaintMediaTest`.
Sumber instruksi pemilik: handoff M08 13 Oktober 2026.

### ADR24 — Usulan kegiatan bukan agenda kedua

Tanggal/status: 15 Oktober 2026, instruksi pemilik; direvisi 16 Oktober 2026 atas instruksi pemilik (requirement [USULAN_KEGIATAN.md](USULAN_KEGIATAN.md)).
Keputusan: tabel `usulan_kegiatan` menyimpan pengajuan warga dan keputusan pengurus. Tabel `agenda` tetap satu-satunya jadwal resmi. **Persetujuan langsung menyalin usulan sekali ke `agenda`** (`agenda_id` unik) dalam transaksi yang sama dan memberi tahu semua warga aktif lewat `agenda.dibuat`; tidak ada langkah "masukkan ke agenda" terpisah (endpoint `schedule` dari draf 15 Oktober dihapus sebelum rilis). Suntingan agenda setelah itu tidak menulis ulang usulan.
Form: jenis, nama, waktu mulai/selesai (paling cepat 24 jam, maksimal 12 jam), lokasi, deskripsi, perkiraan peserta, kebutuhan dari RT, dan pernyataan penanggung jawab (`pernyataan_at`). Batas 24 jam dan 12 jam adalah default yang dapat diubah pemilik.
Dokumen: privat di disk `local`, kunci acak, tanpa nama berkas asli. PDF dicek magic bytes; TXT hanya `.txt` UTF-8 tanpa kontrol/markup; gambar menjadi JPEG di request yang sama. DOCX tidak diterima (diminta ekspor PDF). Aplikasi membuka dokumen lewat tautan relatif bertanda tangan 5 menit agar tidak perlu plugin penampil; tautan dibuat hanya untuk pengusul/pengurus.
Status: `diajukan`, `ditinjau`, `perlu_revisi`, `disetujui`, `ditolak`. Catatan wajib untuk `perlu_revisi` dan `ditolak`; persetujuan untuk waktu yang sudah lewat ditolak. Riwayat dan versi optimistik disimpan. Warga hanya melihat usulan sendiri; warga lain mendapat 404, termasuk unduhan. Akun menunggu tidak boleh mengajukan. Pengurus melihat peringatan jadwal bentrok (bukan pemblokir). Bukan feed publik dan bukan halaman website.
Notifikasi: `usulan.diajukan`/`usulan.diperbaiki` ke pengurus, `usulan.status_berubah` ke pengusul, `agenda.dibuat` ke semua warga aktif.
Setiap tombol aksi memakai standar alert yang sama (konfirmasi, spinner, alert sukses/gagal/konflik, dialog hasil) — §7 requirement.

### ADR25 — Push M10: FCM HTTP v1 tanpa SDK, sama di shared hosting dan VPS

Tanggal/status: 17 Oktober 2026, instruksi pemilik ("jangan andalkan VPS, buat fleksibel"). Implementasi; live FCM menunggu project Firebase pemilik.
Keputusan: push memakai FCM HTTP v1 lewat Laravel HTTP client + ext-openssl (JWT service account → token OAuth, cache terenkripsi ±55 menit). Tidak ada paket Composer, daemon, atau Redis tambahan, sehingga jalur yang sama berjalan di shared hosting (antrean database setelah response + `rt05:shared-tick`) dan VPS (Redis + worker). Kredensial hanya dari env (`FCM_CREDENTIALS_PATH` di luar web root atau `FCM_CREDENTIALS_BASE64`); tanpa kredensial `NullGateway`: inbox tetap, `notifikasi.push_status=dilewati`.
Alur: `ProcessOutboxEvent` menulis inbox dan menandai event selesai, **baru kemudian** mengantrekan `SendPush` (event ID). `SendPush` mengunci notifikasi `FOR UPDATE SKIP LOCKED` per 50 penerima, mengirim paralel 20, maksimal 15 detik per putaran lalu melanjutkan dengan job baru. Hasil: terkirim, dilewati (preferensi mati, tanpa HP, kedaluwarsa >24 jam, tanpa kredensial), gagal (ditolak provider atau 4 percobaan habis). 429/5xx/jaringan → release 30/120/600 detik. Token UNREGISTERED/404 dihapus. Event outbox dan inbox tidak pernah diubah oleh kegagalan push. `rt05:push-recover` (VPS tiap 10 menit, shared di sweep 15 menit) mengantrekan ulang push yang hilang.
Perangkat: tabel `perangkat_push` (token terenkripsi APP_KEY, `token_hash` unik, FK cascade ke `personal_access_tokens`). Push hanya ke akun aktif dengan sesi mobile berlaku.
Pengingat H-1: dikirim saat agenda masuk jendela 24 jam (catch-up setelah downtime selama agenda belum mulai), dedup dengan kunci baris + `pengingat_terkirim_at`, dilewati bila warga sudah diberi tahu di dalam jendela (`agenda.dibuat`/`agenda.diubah`); pengingat basi (waktu berubah atau agenda sudah mulai) tidak membuat inbox.
Jadwal shared: ekspresi tetap (`hourly`, `dailyAt`) bisa terlewat bila cron 15 menit terlambat. Profil shared memeriksa tiap task setiap tick dan menjalankannya bila jatuh tempo (`SharedHosting::dueEvery/dueSlot`, penanda di cache file, tanpa query database saat belum jatuh tempo). Profil VPS tidak berubah.
Mobile: `firebase_messaging` dengan `FirebaseOptions` dari `--dart-define` (nilai klien, bukan secret; CI dari repository variables `FIREBASE_*` tervalidasi). Tanpa nilai itu `NoPushClient`. Data push hanya petunjuk: jenis dari daftar tetap dan UUID, detail dimuat ulang dari API.
Batas: belum ada bukti provider nyata, HP foreground/background/terminated, dan project Firebase. Bukan WhatsApp otomatis atau sinkronisasi Google Calendar.

### ADR26 — Kas RT khusus pengurus; transparansi iuran tersembunyi secara bawaan

Tanggal/status: 17 Oktober 2026, instruksi pemilik (jalan tengah dengan permintaan Ketua RT). Mengubah BR15/FR16.
Keputusan: ringkasan kas RT (saldo, pemasukan/pengeluaran bulan ini, mutasi) **hanya untuk pengurus**. Endpoint dipindah dari `GET /cash/summary` (warga) ke `GET /admin/cash/summary` (pengurus); warga 403, path lama 404. Penolakan ditegakkan server, bukan hanya disembunyikan di aplikasi. Karena kas tidak lagi dibaca warga, keterangan mutasi tidak lagi disanitasi (`Present::kas` selalu detail).
Tab Iuran warga: satu halaman, **iuran rumah sendiri lebih dulu**; bagian "Transparansi iuran warga" tersembunyi setiap kali aplikasi dibuka. Data rumah lain baru diminta dari API setelah warga menekan "Lihat transparansi iuran" dan mengonfirmasi dialog ("hanya kode rumah, nominal, status; jangan disebarkan atau dipakai menagih"), lalu bisa disembunyikan lagi. Isi transparansi tetap BR15: kode rumah, periode, nominal, status, tanpa nama/kontak. Pengurus tetap memiliki tab Pembayaran, Kas, dan Transparansi tanpa gerbang.
Alasan: pemilik menilai kas tidak perlu dibuka ke warga; RT meminta transparansi iuran tetap ada, jadi tampil hanya atas pilihan sadar warga.

### ADR27 — Keuangan M12: rekonsiliasi harian, konfirmasi saldo negatif, ekspor CSV stream

Tanggal/status: 17 Oktober 2026, implementasi M12.
Rekonsiliasi: `rt05:rekonsiliasi-keuangan` (domain `Reconciliation`) memeriksa tagihan ↔ alokasi aktif ↔ pembayaran ↔ buku kas (lunas tanpa alokasi, belum bayar beralokasi, alokasi beda nominal/lintas rumah, pembayaran tercatat tanpa kas atau kas dibalik, pembayaran batal masih beralokasi atau tanpa pembalikan, kas iuran tanpa pembayaran, pembalikan tidak netral, saldo awal ganda). Hanya membaca dan mengeluarkan angka tanpa kode rumah/nama; exit 1 + log warning bila tidak cocok. Tidak memperbaiki otomatis: koreksi selalu lewat pembalikan oleh pengurus. Jadwal VPS `dailyAt 01:30 WIB`; shared sekali per tanggal WIB setelah 01.00 (dicatat walau gagal agar tidak berulang tiap tick).
Saldo negatif: kas manual memakai advisory lock transaksi agar saldo yang diperiksa tidak basi; hasil negatif tanpa `konfirmasi_saldo_negatif` → 422 tanpa tulisan; kunci idempotensi yang sama boleh dipakai ulang karena reservasi ikut dibatalkan.
Ekspor: CSV iuran/kas/rumah tetap di-stream sinkron untuk pengurus (`Cache-Control: no-store`, audit `ekspor.csv`, sel formula diberi prefiks). Skala satu RT (ratusan baris) kecil, sesuai catatan profil shared "ekspor kecil boleh stream". Ekspor besar via worker/unduhan privat kedaluwarsa (FR19) ditunda sampai ada kebutuhan volume; bukan blocker M12.
Mobile pengurus: pembatalan pembayaran lewat tombol "Batalkan" yang terlihat (bukan tekan lama), kas manual bisa dibalik (`dapat_dibalik`), alasan minimal 5 karakter, konfirmasi bahaya, dialog hasil. Warga tidak melihat kas (ADR26), jadi bagian ROADMAP "warga … cash" digantikan keputusan itu.

### ADR28 — Konten publik M13: satu tabel berjenis, draf berversi, snapshot terbit

Tanggal/status: 17 Oktober 2026, implementasi M13.
Keputusan: artikel, album, fasilitas, dan profil situs memakai satu tabel `konten` (jenis + slug unik per jenis) dengan `draf` jsonb yang diedit berversi (409 VERSION_CONFLICT) dan `terbit` jsonb sebagai snapshot terpisah. Pengunjung hanya membaca snapshot, jadi draf tidak pernah bocor dan edit draf tidak mengubah website sampai diterbitkan. Ini lebih sederhana daripada empat tabel ERD rancangan (articles, albums, album_photos, facilities) dengan perilaku yang sama: aturan payload per jenis divalidasi server, dan urutan/alt/caption foto album disimpan di payload.
Terbit: validasi penuh; semua media yang dirujuk harus ada dan berstatus siap (422 MEDIA_NOT_READY); snapshot memuat URL media yang sudah diselesaikan. Mengganti slug lalu terbit menyimpan slug lama di `konten_slug_lama` → GET publik slug lama 301 ke slug baru; slug lama tidak bisa dipakai konten lain (409 SLUG_TAKEN). Arsip menghapus dari publik (404) dan mengunci edit (409 CONTENT_ARCHIVED). Terbit/arsip mencatat outbox `konten.diterbitkan/diarsipkan` untuk invalidasi cache website (dikonsumsi M14; v1 tanpa inbox).
Media: unggahan pengurus diproses di request (magic bytes, encode ulang JPEG ≤1600 px, EXIF dibuang), disimpan privat dengan nama acak; publik hanya bila dirujuk snapshot terbit (`/public/media/{id}`), pratinjau draf hanya pengurus (no-store). Foto website yang sudah diterima dirujuk lewat path `/images/…` (sumber statis), tidak disalin.
Impor: `rt05:konten-impor` dari `rest-api/database/seeders/konten/` — artikel.json dan album.json salinan byte-identik website (diuji), fasilitas.json dan profil.json diekstrak kata per kata dari Blade website (diuji). Idempotent; slug yang ada atau pernah diganti tidak ditimpa. Tidak mengarang fakta: sekretaris/bendahara tetap "Nama belum dipublikasikan", album 17 Agustus tanpa tahun.
Publik: `GET /public/{articles|albums|facilities}[/{slug}]`, `/public/site-profile`; tanpa akun, throttle 120/menit, `Cache-Control: public, max-age=60`, ETag per versi terbit, tanpa ID akun pembuat.

## Input operasional yang belum tersedia

### ADR22 — Aspirasi M09: domain terpisah, anonimitas sama dengan pengaduan, tiga status

Tanggal/status: 13 Oktober 2026, diterapkan pada branch M09.
Trigger: gate M09 dan permintaan pemilik agar M09 tetap selaras dengan capstone.
Keputusan sebelumnya: BUSINESS_RULES BR08 (pengaduan dan aspirasi terpisah) dan status aspirasi
`submitted → reviewed → completed`; ERD Tugas 03 menggambarkan tabel `aspirasi` tanpa kolom
`sembunyikan_identitas` dan tanpa `versi`, sedangkan draf kelompok lama menyebut empat status.
Keputusan baru: tabel `aspirasi` dan `riwayat_aspirasi` tetap terpisah dari pengaduan. Kolom
`sembunyikan_identitas` dan `versi` dipertahankan (BR09 mewajibkan uji anonimitas untuk laporan
dan aspirasi; `versi` untuk 409). Status tetap tiga (`diajukan`, `ditinjau`, `selesai`) sesuai BUSINESS_RULES;
empat status pada draf lama adalah dokumen historis. Tanpa kategori dan foto, sesuai ERD capstone.
Alasan/tradeoff: satu aturan anonimitas dan satu mekanisme versi untuk kedua modul mencegah
perilaku berbeda; selisih terhadap ERD akademik dilaporkan di CAPSTONE_ALIGNMENT, bukan disembunyikan.
Dokumen/endpoint/migration/mobile/web terdampak: API.md, openapi.yaml (skema Aspiration*), ERD.md,
MOBILE.md; tanpa migration baru; `archived_at` ditambahkan pada respons aspirasi.
Rencana kompatibilitas dan pengujian: kontrak lama kompatibel (bidang baru, filter status tidak valid
kini 422); `M09AspirationTest` dan `m09_aspirations_test.dart`.
Sumber instruksi pemilik: "lanjutkan ke milestone 9 ... sesuaikan lagi dengan capstone project".

### ADR20 — SMTP Resend untuk staging M06

13 Oktober2026. Pemilik melanjutkan pekerjaan sesudah login Resend. Domain
pengirim existing terverifikasi; coordinator membuat key khusus RT05 dengan
izin Sending/domain, Claude memasang hanya MAIL_* pada API/worker-default
staging. Tidak memakai key proyek lain atau mengubah DB/APP_KEY/DNS/production.
Ini integrasi SMTP pengirim; autentikasi warga tetap email/sandi aplikasi.

Coordinator memilih Gmail pemilik yang sudah terhubung sebagai default uji
sintetis setelah pertanyaan preferensi dan asumsi diumumkan; pilihan itu bukan
persetujuan RT atas kontak resmi baru. Akun/fixture penerima lama dipertahankan
privat. Bukti INBOX/browser/Android dan verifier A/B4/4 menerima gate M06.
Credential/recipient/kode tetap privat, bukan Git atau dokumentasi publik.

### Otorisasi lanjutan pemilik 11 Oktober 2026

Pemilik menyediakan Chrome pada Hostinger dan mengizinkan penggunaan terminal,
emulator Android, serta akses yang diperlukan untuk deployment. Coordinator
boleh melanjutkan staging setelah gate tahap aktif lulus tanpa meminta ulang
izin akses tersebut. Urutan bertahap P16 tetap berlaku; otorisasi akses tidak
menjadi bukti gate production/UAT/signing/credential telah selesai. Penolakan
pengaman sebelumnya tetap dicatat dan tidak diakali.

| Input | Ditangani pada | Dampak |
| --- | --- | --- |
| Neon project/branches/credentials actual | M02/M04 | Tersedia untuk dev sejak M02 (ADR13); staging/production branch belum dibuat |
| VPS access/proxy/resources actual | M04 | Images/runbook bisa dibuat; deploy live perlu akses |
| DNS morriz.tech dan otorisasi record | M04/M16 | Tidak menebak IP atau mengubah apex |
| SMTP credentials | M06/M10 | Adapter local tersedia; reset real belum passed |
| FCM project/service account | M10 | Tersedia 17 Oktober 2026: project `rt05-takeda` (Spark). Service account hanya di env privat server; production memakai service account sendiri saat M16 |
| Offsite backup destination/key | M16 | Production recovery gate belum passed |
| Android app ID/signing key | M05/M15 | Pin app ID tanpa konflik; release perlu key aman. Keputusan pemilik 17 Oktober 2026: distribusi warga **tanpa Play Store** dan tanpa peringatan Play Protect → kunci rilis tetap + verifikasi developer Android + pendaftaran aplikasi (identitas pemilik, M15) |
| Kontak nyata/privacy retention mitra | M15 | Tidak memasukkan data nyata sebelum notice final |
| ERD/prototype lama lengkap | M02 | Reconcile bila tersedia; tidak mengarang isi file |
| Flutter SDK di PC pengembang | M05 | Tidak ditemukan pada inspeksi M01; Android SDK, AVD Pixel_7a/Pixel_8a, dan JBR Android Studio tersedia |
| Akses proyek Vercel preview dari connector agent | M01/M14 | Proyek tidak terlihat di team `morriz`; perubahan Root Directory dilakukan pemilik di dashboard |

## Template perubahan

ADR-NN — Judul
Tanggal/status:
Trigger:
Keputusan sebelumnya:
Keputusan baru:
Alasan/tradeoff:
Dokumen/endpoint/migration/mobile/web terdampak:
Rencana kompatibilitas dan pengujian:
Sumber instruksi pemilik:

Catat sumber apa adanya; keputusan agent tidak dilabeli sebagai persetujuan RT yang belum diberikan.


### ADR23 — Profil shared hosting untuk anggaran tahun pertama

Tanggal: 15 Oktober 2026. Status: keputusan desain diterapkan pada branch; penerimaan live blocked.
Sumber: pemilik meminta perencanaan dan eksekusi penyesuaian milestone, tetap Laravel/PostgreSQL, memperbolehkan promo tahun pertama. Tidak mengubah BR, desain M00, atau kelulusan historis.

Target biaya hosting+domain ≤Rp500.000 untuk tahun pertama; kandidat Biznet Personal Large Rp399.000 (belum total checkout). Tidak membeli sebelum PHP/CLI/cron/pdo_pgsql/TLS/outbound/resource/routing lolos. Renewal dicatat saat serah terima, bukan diasumsikan sama dengan promo. Domain produksi .com RT belum dipilih.

Profil `vps` tetap default. Profil `shared` memakai queue PostgreSQL additive, file cache/session, dan file lock (satu host, gagal tertutup). Jalur utama pekerjaan adalah setelah response terkirim, bukan daemon dan bukan cron tiap menit. Cron 10–15 menit hanya jaring: outbox, foto tertahan, dan jadwal. Tick yang idle tidak membuka database. Penanda file bukan sumber kebenaran.

Produksi Neon dipisah project dari dev/staging; belum provision. Neon Free per 15 Oktober 2026: 100 CU-hours, 1 GB per project (naik dari 0,5 GB pada 2 Oktober 2026), suspend 5 menit. Media tetap disk lokal privat. R2 gratis tetap minta metode pembayaran saat aktivasi, jadi bukan default. Kuota harian profil shared: 5 pengaduan dan 25 foto per akun. Resend HTTP adalah transport yang dituju karena SMTP sering diblokir; paket `resend/resend-php` belum dipasang dan SMTP staging yang sudah lulus tidak diganti diam-diam. FCM tetap gate M10. Backup offsite/restore tetap wajib.

Lihat SHARED_HOSTING.md untuk acceptance, dampak M00–M16, rollback, dan blocker. Menyesuaikan ADR13/16/18 untuk profil shared saja. M09 PR24 tetap independen; tidak merge/rebase/ubah branch rekan.

Koreksi 15 Oktober 2026, masih pada ADR ini. Email reset masuk antrean database dan tidak menulis outbox, jadi listener `JobQueued` ikut mendaftarkan pengurasan setelah response (satu kali per proses). Reservasi `jobs` menolak pemrosesan ganda. Heartbeat `shared-tick` ditulis hanya setelah tick selesai. Bila `RT05_HEARTBEAT_MAX_AGE` kosong, umur sehat adalah 900 detik interval cron ditambah 300 detik keterlambatan, bukan 300 detik. Tanpa `pcntl` tidak ada timeout keras: `--max-time` tetap antar-job, dan yang dipasang adalah batas SMTP, soket, `statement_timeout`, serta waktu CPU. Job yang macet di luar batas itu tidak dihentikan paksa.
