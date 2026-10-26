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
| P20 | Database tetap PostgreSQL. Tambah satu PostgreSQL gratis kedua (penyedia lain selain Neon dengan plugin di Claude; dipilih Supabase Free, jeda 7 hari dimitigasi sinkron + pemantau) sebagai cadangan agar kuota/gangguan Neon tidak mematikan aplikasi. Risiko yang sepadan diterima. Perawatan arsitektur ini diserahkan ke agent (Claude Code dan agent berikutnya). | Instruksi langsung pemilik 18 Oktober 2026; dirinci di ADR29 |

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

### ADR29 — PostgreSQL cadangan aktif-pasif di luar Neon

Tanggal: 18 Oktober 2026. Status: **keputusan arsitektur pemilik (P20) diterima; implementasi belum dimulai**. Tidak mengubah BR, kontrak API, atau kelulusan M01–M13.

Keputusan sebelumnya: ADR02 "tanpa database bisnis kedua"; ADR13/ADR23 hanya Neon. Pemilik pernah kehilangan akses proyek lain saat CU-hours Neon Free habis (compute disuspend sampai bulan berikutnya) dan meminta cadangan PostgreSQL di penyedia lain.

Keputusan baru:

1. **Satu penulis.** Pada satu waktu hanya satu database yang menerima tulisan: `primary` (Neon) atau `standby`. Tidak ada aktif-aktif, sinkron dua arah, sharding, atau membagi tabel antar penyedia. Ini menjaga ADR05 (keuangan atomik, kunci baris, idempotensi). Standby bukan database bisnis kedua menurut ADR02, melainkan salinan database yang sama.
2. **Penyedia standby: Supabase Free (PostgreSQL), dipilih pemilik 18 Oktober 2026.** Plugin/MCP Supabase sudah tersambung bagi agent (org `morrispes5`). Alasan: tanpa kuota jam compute, jadi justru menutup kelemahan Neon (CU-hours); ada region Singapura; gratis. Kuota per riset 18 Oktober 2026 dari sumber pihak ketiga (konfirmasi di halaman resmi sebelum provision): DB 500 MB/project (data RT <100 MB/tahun), maksimal 2 project aktif per org, **project dijeda setelah 7 hari tidak aktif**.
   - **Mitigasi jeda (wajib):** sinkron berkala (butir 3) menjaga aktivitas; PoC harus membuktikan sinkron dihitung sebagai aktivitas oleh Supabase. Pemantau harian mengecek status project. Bila dijeda, project dinyalakan lagi lewat plugin (`restore_project`) atau dasbor **selagi Neon masih primary**, dan tidak ada pindah sebelum standby menyala dan tersinkron.
   - Koneksi dari shared hosting memakai pooler Supavisor IPv4 bila koneksi langsung hanya IPv6. Tanpa named prepared statement (ADR14), TLS verify-full (ADR15), role migrator/runtime terpisah (ADR13/16): verifikasi saat PoC.
   - Opsi lain yang dibandingkan dengan syarat ada plugin di Claude: project Neon kedua (gratis, satu penyedia dengan primary; boleh ditambahkan nanti lewat ADR susulan bila Supabase gagal PoC), PlanetScale Postgres (~$5/bulan, berbayar), Railway (kredit gratis $1/bulan, praktis berbayar), Render (Postgres gratis dihapus setelah 30 hari). Tanpa plugin: Aiven Free (CLI), PostgreSQL cPanel. Database non-PostgreSQL tidak memenuhi syarat karena aplikasi memakai `jsonb`, `gen_random_uuid`, antrean `SKIP LOCKED`, dan kunci baris. Mengganti penyedia memerlukan ADR susulan, bukan diam-diam.
3. **Sinkron satu arah primary → standby** secara berkala (rancangan awal tiap 6 jam dan setelah tutup buku harian). Data kecil (<100 MB/tahun), jadi salinan utuh (dump/restore atau salin per tabel) lebih sederhana daripada logical replication. Setiap sinkron membangunkan Neon, jadi intervalnya ikut anggaran CU-hours. Shared hosting biasanya tidak punya `pg_dump`/`psql`. Pilih pelaksana (perintah artisan PHP di hosting, workflow terjadwal CI, atau mesin operator) saat implementasi. Dump tidak boleh menjadi artifact atau commit di repo publik, dan isinya dienkripsi bila disimpan.
4. **Pindah terencana sebelum kuota habis.** Pantau konsumsi compute Neon lewat API Neon (field konsumsi diverifikasi saat implementasi). Pada ambang sekitar 85% kuota bulanan: mode perawatan singkat (tulis ditolak 503 dengan pesan jelas) → salinan akhir primary → standby → verifikasi jumlah baris dan rekonsiliasi keuangan (`rt05:rekonsiliasi-keuangan`) → koneksi aktif dipindah ke standby → mode perawatan dicabut. Tanpa kehilangan data, karena tidak ada tulisan selama penyalinan.
5. **Kembali ke Neon** setelah kuota bulan baru pulih, dengan langkah yang sama ke arah sebaliknya (standby → Neon, salinan utuh). Tidak perlu penggabungan, karena Neon tidak menerima tulisan selama standby aktif.
6. **Pindah darurat** (Neon mati mendadak): standby dipakai dari salinan terakhir. Tulisan sejak salinan terakhir hilang (RPO = interval sinkron). Pembayaran tetap offline dengan bukti fisik, jadi pengurus mencatat ulang. Setelah Neon pulih, kembali seperti butir 5 dari standby, bukan menggabungkan dua database.
7. **Penanda database aktif** disimpan di satu tempat yang dibaca semua proses API (web, setelah response, cron), misalnya file privat di hosting atau env + `config:cache`. Semua proses wajib berpindah bersama. Proses yang masih menulis ke database lama adalah bug kritis.
8. **Kurangi bangun Neon tetap prioritas pertama**, karena ADR ini adalah jaring, bukan alasan boros. Temuan 18 Oktober 2026: `rt05:shared-tick` menjalankan sweep (`rt05:media-recover`, `rt05:push-recover`) setiap 900 detik walau idle, sehingga Neon bangun sekitar 96×/hari (≈60 CU-hours/bulan menurut perkiraan SHARED_HOSTING.md). Ini tidak sejalan dengan kalimat ADR23 "tick yang idle tidak membuka database" untuk tick yang jatuh di jendela sweep. Tambahkan juga cache baca di hosting (konten publik) dan di aplikasi Flutter (tampilkan data terakhir, muat ulang terbatas).
9. Backup offsite terenkripsi harian (DB + media) tetap wajib dan terpisah dari standby. Standby menangani ketersediaan; backup menangani kehilangan/kerusakan data.

Alasan/tradeoff: risiko yang diterima pemilik adalah perawatan 1–2 menit beberapa kali per bulan, RPO beberapa jam hanya pada pindah darurat, serta tambahan satu akun, satu script, dan prosedur yang harus dilatih. Imbalannya: kuota Neon habis tidak lagi mematikan aplikasi. Biaya tetap Rp0 selama kuota gratis cukup. Tidak menambah layanan berbayar.

Terdampak: config database `rest-api`, perintah artisan baru (sinkron, pantau kuota, pindah, kembali), mode perawatan tulis, SHARED_HOSTING.md (preflight + acceptance), DEPLOYMENT/runbook M16, PROGRESS.md. Website dan mobile tetap tidak terhubung langsung ke database mana pun.

Rencana pengujian (gate sebelum production): Postgres lokal dua instance untuk tes otomatis pindah/kembali. Selama perawatan tidak ada tulisan yang lolos. Setelah pindah, jumlah baris dan rekonsiliasi keuangan identik, sequence/UUID tidak bentrok, antrean `jobs`/outbox/media yang tertunda tetap diproses sekali. Latihan di staging minimal satu siklus pindah + kembali, dan pengukuran CU-hours 7 hari. Tanpa bukti ini status tetap blocked.

Sumber instruksi pemilik: sesi Claude Code 18 Oktober 2026 ("database kita intinya tetap PostgreSQL… yang satunya Neon, satunya lagi apa… yang penting ada di pluginnya… gue serahin agent lu untuk ngerawatnya"), lalu sempat menolak Supabase karena jeda 7 hari, lalu setelah membandingkan lima opsi yang punya plugin: "backupnya pakai supabase aja… yang lainnya pada berbayar".

### ADR30 — M14: website membaca API konten; CMS web memakai sesi cookie API

Tanggal: 18–19 Oktober 2026. Status: **diterapkan di branch `codex/m14-website-api`**. Tidak mengubah BR; menambah satu perilaku kontrak kecil (302 foto statis pada pratinjau admin).

1. **Website hanya membaca snapshot terbit.** `website/app/Support/SiteContent.php` mengambil `/api/v1/public/articles` dan `/api/v1/public/albums` dari `RT05_CONTENT_API` (staging: `http://api` di jaringan Docker; shared hosting: origin publik sendiri). Hasil disimpan sebagai *fresh* 60 detik dan *last-good* tanpa batas di cache file website. API gagal → penanda gagal 30 detik (tidak menunggu timeout tiap request) → last-good → JSON baked di `resources/data`. Website tidak pernah membaca draf, database, atau Redis. Ini juga cache baca konten publik yang diminta ADR29 butir 8: Neon hanya dibangunkan paling sering sekali per menit oleh website, dan hanya saat ada pengunjung.
2. **Tanpa `RT05_CONTENT_API` perilaku lama tetap** (preview statis Vercel, tes baseline): JSON baked dan CMS mode demo IndexedDB. Mode dipilih di Blade (`data-cms-mode`), bukan oleh JavaScript, sehingga preview statis tidak pernah mencoba login.
3. **CMS web mode server** (`resources/js/content-api.js`) memakai antarmuka yang sama dengan `PreviewContentRepository`. Login `/pengurus/masuk` → `GET /sanctum/csrf-cookie` → `POST /api/v1/auth/web-login` (M06: cookie HttpOnly, hanya pengurus aktif, pesan generik). Mutation mengirim `X-XSRF-TOKEN` dari cookie `rt05_api_xsrf`; 419 diulang sekali dengan token baru. Tidak ada token di JavaScript atau localStorage. 401 → kembali ke login dengan `kembali=` yang hanya menerima path `/pengurus…`.
4. **Simpan eksplisit, bukan autosave**, karena validasi server ketat (panjang minimum, https). Batas input editor diselaraskan dengan aturan `Contents::rules`. Foto diperkecil di browser (≤1600 px, WebP) lalu diunggah ke `POST /admin/content-media` saat draf disimpan; server tetap memvalidasi dan membuang metadata. Versi (`versi`) dilacak per konten; versi basi → 409 dengan pesan “diubah di perangkat lain”, isian tidak dibuang. Tombol Terbitkan dan Arsipkan memakai konfirmasi dan pesan berhasil/gagal.
5. **Foto statis website pada editor**: `GET /admin/content-media/{id}/file` untuk media `statis` menjawab 302 ke path `/images/…` (sebelumnya 404), agar album impor baseline bisa ditampilkan dan diedit di CMS web.

Alasan: implementasi paling sederhana yang memenuhi gate M14 tanpa mengganti UI yang diterima dan tanpa layanan baru. Tradeoff: perubahan terbit muncul di website paling lambat ±60 detik (cache fresh); diterima demi mengurangi bangun Neon.

Terdampak: `website/` (SiteContent, cms-preview.js, content-api.js, pengurus.blade.php, export-preview.php), `infra/compose.production.yml` (env web), `rest-api` ContentController + tes M14, `docs/openapi.yaml`, `docs/website/CONTENT_PREVIEW.md`.

### ADR31 — M15: APK rilis dengan kunci tetap, pemulihan draf kamera, dan anggaran latensi Neon

Tanggal: 19 Oktober 2026. Status: **diterapkan di branch `codex/m15-uat-release`**.

1. **Kunci rilis tetap.** Keystore PKCS12 RSA 4096 (alias `rt05-takeda-release`, berlaku s.d. 2056) dibuat agent atas
   persetujuan pemilik. Disimpan hanya di dua tempat: secret GitHub environment `release` (dipakai CI, tidak bisa dibaca
   ulang) dan folder Google Drive pribadi pemilik "RT05 TAKEDA - Kunci rilis Android (RAHASIA, jangan dibagikan)" (izin:
   hanya pemilik; salinan diverifikasi hash `f5e3a3a5…`). Tidak ada salinan di PC atau repo. Sidik jari sertifikat publik
   `DB:A8:BC:3F:61:79:CB:74:7A:AC:E1:B9:BA:DA:7A:BB:C5:68:58:3D:26:BB:17:64:93:74:F6:B7:59:E4:06:9A` disimpan sebagai
   repository variable `RT05_RELEASE_CERT_SHA256` dan dipakai untuk verifikasi developer Android (pemilik).
2. **Workflow `Mobile release APK`** (`.github/workflows/mobile-release.yml`) hanya `workflow_dispatch` (staging/prod),
   menjalankan analyze + test, menulis `key.properties` sementara, build `--release` dengan versionCode `1000 + run`,
   menghapus kunci dari runner, lalu memverifikasi skema v2 dan sertifikat == variabel di atas sebelum mengunggah artifact.
   Gradle memakai kunci debug bila `key.properties` tidak ada (build lokal); APK seperti itu gagal di verifikasi CI.
   APK rilis tidak bisa menimpa APK debug yang sudah terpasang (tanda tangan berbeda): pasang rilis setelah uninstall sekali.
3. **Pemulihan draf kamera** (ditunda dari M08): sebelum kamera dibuka, isian pengaduan baru disimpan terenkripsi
   (`flutter_secure_storage`), terikat ID akun. Bila Android menutup aplikasi, formulir memulihkan isian + foto
   (`ImagePicker.retrieveLostData`) dan beranda menampilkan kartu "Pengaduan belum terkirim". Draf akun lain dihapus,
   tidak ditampilkan. Tidak ada data dikirim ke server sebelum pengguna menekan Kirim.
4. **Anggaran latensi.** Diukur 19 Oktober 2026 dari container API staging: koneksi baru ke Neon 130–440 ms, tiap query
   ±55–65 ms (pooled dan direct sama; TCP 17 ms; jarak Indonesia→Singapura + proxy Neon). Latensi API = koneksi + jumlah
   query × ±60 ms. Keputusan: (a) Sanctum tidak menulis `last_used_at` per request (kolom tidak dipakai; satu UPDATE
   hilang dari setiap request bearer); (b) jumlah query per layar dijaga tes anggaran `M15PerformanceBudgetTest`;
   (c) **tidak** memakai koneksi persisten PDO: transaksi yang terputus bisa terbawa ke request berikutnya (risiko ADR05),
   jadi biaya koneksi diterima.
5. **Header keamanan website** dari middleware aplikasi (`SecurityHeaders`), bukan gateway, agar sama di VPS dan shared
   hosting: `X-Frame-Options: DENY` (CMS tidak bisa dibingkai), `Permissions-Policy`, dan HSTS 1 tahun tanpa
   `includeSubDomains` hanya lewat HTTPS.
6. **Neon boleh tidur saat idle (profil VPS).** 19 Oktober 2026 branch `staging` tercatat aktif ±95% waktu (48 CU-jam
   dalam 8,4 hari) karena `rt05:outbox-dispatch` tiap menit, `media-recover`/`push-recover` tiap 10 menit, dan
   healthcheck website `/` (merender beranda → baca konten API). Kuota Free 100 CU-jam/bulan per **project** dipakai
   bersama branch `production`, jadi laju itu menghabiskan kuota ±28 Oktober. Keputusan: `IdleGuard` (penanda Redis
   10 menit dari `Outbox::record` setelah commit) — sweep database jalan tiap menit hanya saat aktif, selain itu sweep
   pengaman tiap jam; cache gagal = dianggap aktif. Healthcheck website memakai `/up`. Notifikasi baru tetap ≤60 detik.
   Profil shared tetap memakai mekanisme `shared-tick` (ADR23). Pengurangan bangun yang tersisa (pengingat agenda dan
   rotasi token tiap jam) diterima: ±1 bangun per jam.
7. **Mutex scheduler berumur pendek.** Insiden staging 19 Oktober 2026: deploy `0717e51` menghentikan scheduler lama di
   tengah `rt05:outbox-dispatch`; mutex `withoutOverlapping()` bawaan Laravel (24 jam, di Redis cache yang persisten)
   tertinggal, sehingga outbox tidak dikirim (notifikasi uji tidak sampai 120 dtk; event 03:10 dan 03:25 UTC tertahan).
   Dipulihkan dengan `php artisan schedule:clear-cache` (event terkirim 03:30). Keputusan: semua task memakai
   `withoutOverlapping(N)` — outbox 5 menit, recover 10 menit, lainnya 60 menit — juga di profil shared (proses yang
   dibunuh timeout hosting). Risiko ini sudah ada sebelum IdleGuard; terlihat karena uji latensi M15.

### ADR32 — UAT M15: edit foto pengaduan dan batas unggah per akun

Tanggal: 19 Oktober 2026 (UAT tim pemilik, 4 penguji). Status: **diterapkan di branch `codex/m15-uat-fixes`**.
Instruksi pemilik: banyak keluhan foto pengaduan tidak bisa diedit; batasi ukuran dan spam unggahan
("1 orang iseng upload foto 20 MB × 100"). Aspirasi tetap teks saja (pemilik memilih tidak menambah foto aspirasi).

1. **Mengubah ADR21 butir "foto hanya saat laporan dibuat".** Pelapor dapat menambah (`POST /complaints/{id}/photos`)
   dan menghapus (`DELETE /complaints/{id}/photos/{photoId}?versi=`) foto selama pengaduan **diajukan** dan belum
   diarsip, total maks. 5. Aturan versi sama dengan edit teks (409 bila basi, 403 `COMPLAINT_LOCKED` selain pemilik/
   status). Foto baru melewati jalur ADR21 yang sama (stage privat → worker-media → siap/gagal, metadata dibuang);
   berkas foto yang dihapus ikut dihapus setelah commit. Audit `pengaduan.foto_ditambah` / `pengaduan.foto_dihapus`.
2. **Batas unggah semua profil** (sebelumnya batas harian hanya profil shared, ADR23): per akun per hari WIB maks.
   5 pengaduan dan 25 foto (termasuk foto tambahan), dikunci baris akun agar unggahan paralel tidak lolos; ukuran
   maks. 5 MB per foto di VPS (shared tetap 4 MB); throttle `laporan` 20/jam juga berlaku untuk tambah foto.
   Aplikasi sudah memperkecil foto ke ≤1600 px kualitas 80, jadi batas 5 MB hanya menolak unggahan di luar aplikasi.
3. Keluhan "halaman kas admin putih" berasal dari APK M10: memanggil `/api/v1/cash/summary` yang dipindah ke
   `/api/v1/admin/cash/summary` (ADR26). APK M15+ memakai endpoint baru; tes `m15_finance_uat_test` memakai bentuk
   respons staging pada lebar 320–360 dp dan teks 2×.

### ADR33 — UAT M15 putaran 2: ekspor CSV di aplikasi, tagihan langsung, blok wajib, revisi permohonan akun

Tanggal: 20 Oktober 2026 (laporan UAT tim pemilik). Status: **diterapkan di branch `codex/m15-uat-fixes-2`**.

1. **Ekspor CSV di aplikasi pengurus** (permintaan klien): tombol *Ekspor CSV* di tab Pembayaran, Kas, dan Rekap
   (rekap = `exports/iuran?periode=YYYY-MM`). Ekspor baru `pembayaran` (nomor bukti, rumah, tanggal, periode, total,
   status, catatan, alasan pembatalan). CSV UTF-8 ber-BOM agar langsung terbuka di Excel/Google Sheets; disimpan
   lewat dialog "Simpan sebagai" Android (`file_picker`). Sel formula tetap diberi prefiks (ADR27).
2. **Tagihan langsung**: rumah baru (pengurus), perubahan tagihan rumah, dan registrasi akun utama menjalankan
   `Dues::catchUp` setelah commit (idempotent, gagal hanya dicatat log), sehingga warga baru langsung melihat tagihan
   bulan berjalan tanpa menunggu generator harian/Rekap. *Mulai tagih* dipilih lewat dialog bulan, bukan diketik.
3. **Blok wajib** untuk rumah baru (pengurus) dan registrasi akun utama; akun tambahan tetap opsional karena
   dicocokkan ke rumah lama yang mungkin tanpa blok.
4. **Revisi permohonan akun tambahan**: status baru `perlu_revisi` (migrasi aditif). Pengurus *Minta perbaikan*
   dengan catatan wajib; pemohon melihat catatan di layar status dan mengirim ulang nama/hubungan/alamat
   (`PATCH /auth/additional-account-request`), rumah dicocokkan ulang diam-diam, pengurus diberi tahu. Tolak tetap
   tersedia dari `diajukan` maupun `perlu_revisi`.
5. **Dokumen usulan**: PDF dan TXT maks. 1 MB (disimpan apa adanya); foto maks. 8 MB karena diperkecil ulang.
6. **CMS web**: tampilan *Lihat arsip* (hanya baca), pesan jelas bahwa draf konten terbit belum tampil sampai
   *Terbitkan*, dan cache konten website 15 detik (sebelumnya 60).
7. **Foto sampul artikel** (temuan E, dikonfirmasi 20 Okt dari data staging: sampul asli tersimpan dan terbit, tetapi
   website tetap menggambar SVG): bila artikel punya sampul, website menampilkan foto asli itu di kartu, artikel
   pilihan, dan halaman baca (`partials/article-visual`); tanpa sampul tetap ilustrasi SVG yang diterima. Tidak ada
   gambar AI. Katalog baked/Vercel tanpa sampul, sehingga tampilannya tidak berubah.

### ADR34 — UAT M15 putaran 3: pulihkan arsip, tombol Kembali, jam selesai manual, validasi akun tambahan

Tanggal: 20 Oktober 2026 (laporan UAT tim pemilik pada APK 1008). Status: **diterapkan di branch `codex/m15-restore-archive`**.
Instruksi pemilik: arsip harus bisa dikembalikan; jam selesai usulan tidak boleh terisi sendiri; tombol Kembali meminta
konfirmasi; akun tambahan divalidasi dari nama, hubungan, dan nama kepala keluarga dengan alamat opsional.

1. **Pulihkan arsip** mengubah ADR33 butir 6 (arsip hanya baca) dan teks "Arsip bersifat final" pada akun. Endpoint admin
   `POST {articles|albums|facilities}/{id}/restore` (versi wajib), `POST houses|families|accounts/{id}/restore` (alasan
   wajib, teraudit `*.dipulihkan`). Bukan arsip → 409 `NOT_ARCHIVED`.
   - **Konten** kembali **draf**, bukan terbit: pengurus meninjau lalu menekan Terbitkan. Slug arsip tidak pernah dilepas,
     jadi tidak bisa bentrok. Tanpa event outbox karena draf tidak tampil publik.
   - **Rumah**: `archived_at` dikosongkan. `tagihan_aktif` tetap false (arsip mematikannya); pengurus menyalakan lewat Ubah
     tagihan supaya tagihan tidak muncul tanpa sengaja. Hunian yang ditutup saat arsip tidak dibuka lagi.
   - **Akun**: kembali **nonaktif** (belum bisa masuk) sampai pengurus mengaktifkan. Tautan keluarga tidak dibuka otomatis;
     pengurus menautkan ulang. Tidak ada pemulihan akun sendiri.
   - **Keluarga**: aktif lagi bersama anggota yang ikut diarsipkan olehnya (keanggotaan berakhir `diarsipkan` dalam jendela
     satu menit sebelum `archived_at` keluarga; status `pindah` yang dipasang arsip kembali `aktif`). Anggota yang sudah pindah
     atau meninggal lebih dulu tidak ikut. Hunian rumah dan tautan akun tidak dibuka otomatis. Versi keluarga naik.
   - Pengaduan, aspirasi, agenda, dan pengumuman belum punya layar arsip di aplikasi, jadi belum diberi Pulihkan.
2. **Tombol Kembali (mobile)**: `HomeShell` memakai `PopScope`. Dari tab selain yang pertama kembali ke tab pertama
   (dashboard); dari tab pertama muncul dialog "Keluar dari aplikasi?" lalu `SystemNavigator.pop` (sesi tidak dikeluarkan).
   Halaman yang di-push tetap menutup lebih dulu.
3. **Jam selesai usulan** tidak pernah terisi otomatis. Mengganti jam mulai mempertahankan jam selesai hanya bila masih di
   hari yang sama dan setelah jam mulai; selain itu dikosongkan. Pemilih jam selesai mulai dari jam mulai (bukan +2 jam).
4. **Akun tambahan**: wajib `nama`, `hubungan_keluarga`, `nama_kepala`; `blok`/`jalan`/`nomor` opsional (kolom aditif
   `permohonan_akun.nama_kepala_diajukan`; `alamat_diajukan` boleh null). Rumah dicocokkan diam-diam: alamat lengkap bila
   diisi dan cocok; selain itu nama kepala (tanpa beda huruf besar/kecil dan spasi ganda) hanya bila tepat satu rumah aktif
   berpenghuni; ganda atau tidak ada → `rumah_cocok` null dan pengurus memilih rumah saat menyetujui. Pemohon tidak diberi
   tahu hasil pencocokan. Berlaku juga untuk perbaikan permohonan (`PATCH /auth/additional-account-request`).
5. **Tidak diubah**: aturan minimal 10 karakter untuk ringkasan, pembuka, dan isi bagian artikel serta deskripsi album
   (validasi M13). Bila pemilik ingin melonggarkannya, itu keputusan terpisah.

### ADR35 — Permintaan Pak RT (rapat 20 Okt 2026): Profil RT05, iuran tanpa transparansi, bukti transfer, WhatsApp, NIK/KK opsional, Kebijakan Privasi

Tanggal: 21 Oktober 2026 (instruksi pemilik setelah rapat dengan Ketua RT, 20 Okt 20.00 WIB). Status: **diterapkan di branch
`codex/m15b-permintaan-rt`** (milestone usulan M15b, di antara M15 dan M16). Instruksi pemilik terbaru mengubah keputusan
lama yang disebut di bawah.

1. **Profil RT05 (website).** Menu dan judul "Profil RT" menjadi "Profil RT05". Halaman `/profil` tidak lagi menulis
   pengurus secara mati di Blade: website membaca `/api/v1/public/site-profile` (cache dan cadangan sama dengan artikel;
   belum terbit/404/API mati → `resources/data/profil.json`, salinan byte-identik seeder). Profil mendapat kolom
   `tentang` (≤3000, paragraf dipisah baris kosong) berisi cerita Taman Kedaung yang ditulis agent dari fakta terverifikasi
   (Kelurahan Kedaung, Kecamatan Pamulang, Kota Tangerang Selatan; dulu bagian Kecamatan Ciputat) tanpa angka atau tahun
   karangan. Migrasi data `2026_10_21_000200` mengisi `tentang` hanya bila belum ada. Pengurus mengedit wilayah,
   ringkasan, cerita, dan daftar pengurus (tambah/hapus, 1–12 orang) di **CMS web `/pengurus/profil`** dan di aplikasi.
   Ini memperluas batas "login web hanya Artikel dan Dokumentasi" khusus untuk profil RT, atas permintaan pemilik.
   Aplikasi lama yang tidak mengirim `tentang` tidak menghapus cerita yang ada.
2. **Transparansi iuran dihapus dari warga.** Mengubah ADR26/BR15/FR16: `GET /dues/transparency` kini khusus pengurus
   (warga 403) dan kartu transparansi hilang dari aplikasi warga. Warga hanya melihat tagihan rumahnya sendiri.
3. **Konfirmasi pembayaran.** Warga dapat mengirim foto bukti transfer (`POST /dues/confirmations`, tabel
   `konfirmasi_iuran`, ditranskode langsung seperti foto pengaduan, maks 5/hari) atau chat bendahara lewat WhatsApp.
   Ini **bukan** pembayaran: status tagihan tidak berubah sampai pengurus mencatat pembayaran offline; pengurus lalu
   menandai bukti selesai atau menolaknya dengan catatan. Tetap tanpa pembayaran online, QRIS, atau checkout.
4. **WhatsApp tanpa API.** Semua tombol memakai tautan `https://wa.me/<nomor>` (nomor dinormalisasi ke 62…). Nomor
   bendahara disimpan di `system_settings` (`kontak.pengurus`), diubah pengurus lewat aplikasi, dibaca semua akun aktif.
   Nomor uji dari pemilik **tidak** disimpan di Git; diisi lewat aplikasi di staging. Pengurus juga mendapat tombol
   WhatsApp di samping setiap anggota keluarga yang nomornya terisi.
5. **NIK dan nomor KK opsional.** Mengubah BUSINESS_RULES D, SECURITY "Data pribadi", PRD §10, dan ERD "tidak
   dikoleksi": `warga.nik` dan `keluarga.nomor_kk` (16 digit) boleh diisi akun utama untuk keluarganya atau pengurus
   untuk keluarga mana pun; tidak pernah wajib dan tidak diminta saat daftar. Disimpan dengan cast `encrypted` (APP_KEY),
   API hanya mengirim bentuk tersamar (`3674••••••••0001`), audit hanya mencatat nama kolom. Akun tambahan hanya membaca
   bentuk tersamar. Impor dari Excel RT dan ekspor berisi NIK belum dibuat (perlu keputusan dan dasar hukum terpisah).
   Konsekuensi: rotasi APP_KEY production wajib menyertakan migrasi ulang enkripsi kolom ini.
6. **Kebijakan Privasi dan lengkapi data.** Setelah masuk, akun aktif wajib membaca dan menyetujui Kebijakan Privasi
   versi terbaru (`akun.privasi_versi`, `POST /auth/privacy-consent`, versi di `App\Support\Privacy::VERSION`);
   akun menunggu tetap ke halaman menunggu. Teks di aplikasi (`privacy_policy.dart`) dan `docs/PRIVACY_NOTICE.md`.
   Kontak resmi, masa simpan setelah pindah, dan batas waktu tanggapan belum diputuskan pemilik, jadi teks merujuk ke
   pengurus lewat aplikasi tanpa nomor/email karangan. Beranda akun utama menampilkan kartu opsional "Lengkapi data
   keluarga" bila WhatsApp, NIK, atau nomor KK kepala keluarga masih kosong ("Nanti saja" berlaku per sesi).
7. **Tombol Kembali** tetap seperti ADR34; syarat daftar tidak berubah (email, kata sandi, token, alamat; WhatsApp
   opsional).

### ADR36 — Penutupan M15 atas keputusan pemilik; sisa gate dipindah ke M16

Tanggal: 22 Oktober 2026 (jawaban pemilik di sesi Claude). Status: **diputuskan**.

1. **M15 passed** dengan bukti UAT berupa: uji tim pemilik putaran 1–3 (19–21 Okt; temuan diperbaiki ADR32–ADR35), demo aplikasi
   ke Ketua RT pada 20 Okt 2026 pukul 20.00 WIB (pemilik melaporkan "bagus"; menghasilkan permintaan M15b), dan uji agent di emulator
   pada APK 1010 dan 1011 dengan akun sintetis staging. **Tidak ada nilai kemudahan 1–5 per peserta** yang dicatat; angka tidak
   dikarang. Target TESTING.md "≥80% nilai 4–5" tidak diukur dan diterima pemilik sebagai risiko.
2. Gate lain M15 terpenuhi: alur kritis lulus (tes otomatis rest-api 340, Flutter 267, website 21; live CMS 21/21 dan 9/9), tidak ada
   bug high/critical terbuka, provider nyata bekerja di staging (Neon, FCM, email), APK bertanda tangan kunci tetap terpasang (1011).
3. **Dipindah ke M16** (bukan dibatalkan): verifikasi developer Android (identitas pemilik), kontak resmi dan masa simpan di Kebijakan
   Privasi, latihan pindah/kembali ke PostgreSQL cadangan (ADR29), uji pada hosting production Biznet (profil shared; preflight
   SHARED_HOSTING sebelum membeli), uji aksesibilitas manual TalkBack, dan pemulihan otomatis foto/push yang masih menunggu sweep per
   jam. Issue #47 (bukti performa 404) ikut ke M16.
4. Permintaan pemilik untuk rilis berikutnya: setiap versi APK baru langsung diberitahukan (nomor, tanggal, jam, tautan), dan bug yang
   ditemukan di versi sebelumnya wajib sudah diperbaiki serta dikunci tes regresi sebelum rilis.

### ADR37 — M16 tanpa verifikasi developer Android; verifikasi menjadi opsi terakhir

Tanggal: 22 Oktober 2026 (jawaban pemilik di sesi Claude). Status: **diputuskan**.

1. Pemilik menunda pendaftaran Android Developer Console (biaya US$25 sekali bayar, KTP + bukti alamat, kartu Visa/Mastercard)
   setelah membahas risiko akun Gmail diretas dan ketergantungan pada identitas pribadi. Verifikasi menjadi **opsi terakhir**,
   bukan gate M16.
2. Dasar: aturan yang berlaku di Indonesia sejak 30 September 2026 hanya memeriksa pemasangan lewat toko yang berpartisipasi.
   Unduhan langsung dari situs resmi belum diperiksa (FAQ Google, dicek 22 Okt 2026). Warga tetap melihat izin bawaan Android
   "pasang aplikasi tidak dikenal" dan mungkin pemindaian Play Protect. Kepercayaan dibangun lewat halaman unduh resmi, SHA-256,
   panduan pasang, versi 1.0.0, dan sosialisasi pengurus.
3. Risiko yang diterima: Google merencanakan pemeriksaan semua aplikasi pada 2027. Bila berlaku, aplikasi tanpa developer
   terverifikasi hanya bisa dipasang lewat "advanced flow". Pantau pengumuman Google. Bila diperlukan, daftar memakai nama paket
   `tech.morriz.takeda` dan kunci rilis yang sama (ADR31), jadi warga tidak perlu memasang ulang.
4. Sebelum pendaftaran (bila nanti dilakukan): amankan Gmail dan GitHub dengan 2FA dan kode cadangan, pisahkan kata sandi kunci
   rilis dari Drive yang sama, dan siapkan Pengelola Akun Tidak Aktif Google. Dicatat di [M16_PLAN.md](M16_PLAN.md) bagian 3.

### ADR38 — Keputusan pemilik M16 (22 Okt 2026) dan perbaikan audit keamanan bagian kode

Tanggal: 22 Oktober 2026 (jawaban pemilik di sesi Claude). Status: **diputuskan**.

1. **Admin pertama (K5):** sementara memakai staging dengan email RT `takedatamankedaung@gmail.com` (akun pengurus dibuat
   agent lewat `rt05:pengurus buat`, kata sandi diberikan ke pemilik lewat berkas catatan lokal, tidak di Git/chat). Production
   dibuat ulang saat live.
2. **Backup offsite:** Google Drive pemilik (penyimpanan masih lega). Dump tetap terenkripsi sebelum keluar mesin operator.
3. **Lokasi (K7):** tetap "Ciputat" di judul, deskripsi, footer, dan kontak; teks profil boleh menjelaskan Kecamatan Pamulang.
4. **Belum diputuskan:** domain production (K1) dan kontak resmi + masa simpan privasi (K4) belum didiskusikan dengan pengurus.
5. **Audit keamanan (sesi "Menjalankan aplikasi Android tanpa RAM", 22 Okt), bagian kode yang dikerjakan:** token
   registrasi diganti otomatis setelah `RegistrationToken::MAX_FAILURES` (30) salah tebak lintas IP (kolom
   `token_registrasi.percobaan_gagal`, event `token.dirotasi_otomatis` ke inbox pengurus); event `akun.registrasi_utama` kini
   memberi tahu pengurus; ekspor CSV Kas dan Pembayaran menghormati `?periode` (tanggal transaksi/bayar); `/health/ready`
   menyimpan hasil cek database 10 detik; `mask_bindings_in_exception_messages` aktif. Butir VPS (SSH tanpa password),
   setelan GitHub (reviewer environment `release`, proteksi `main`), dan rotasi kredensial lama menunggu izin eksplisit pemilik.
6. **Jawaban lanjutan 22 Okt ±10.15 WIB:** setelan GitHub **dibiarkan seperti sekarang** (tanpa proteksi `main` dan tanpa reviewer
   environment `release`); akun kolaborator `takeda05` adalah akun pemilik sendiri khusus RT05. SSH VPS lama tidak diubah sampai
   habis 4 Nov; VPS baru (2 Nov) langsung login kunci saja + firewall/fail2ban setelah konsol browser Hostinger dipastikan bisa
   masuk, dengan cadangan kunci SSH di Drive pribadi pemilik (PC pemilik hanya satu). Rotasi kredensial lama saat pindah VPS.
7. **Performa staging (uji beban 22 Okt):** penyebab P95 naik dari 781 ms (M15) ke 1,7 dtk: (a) website menunggu tiga panggilan
   API setiap jendela fresh 15 dtk habis dan banyak pengunjung melakukannya bersamaan; (b) FrankenPHP bawaan hanya 2 thread per
   CPU (4 di VPS 2 CPU), padahal tiap request API menunggu koneksi baru ke Neon ±184 ms + ±18 ms per query. Perbaikan: website
   menyajikan last-good lalu memperbarui setelah response (satu proses, kunci file); `FRANKENPHP_CONFIG` api `num_threads 16`,
   web `num_threads 8` (bisa diganti `RT05_API_FRANKENPHP_CONFIG`/`RT05_WEB_FRANKENPHP_CONFIG`). Koneksi persisten ke Neon
   sengaja tidak dipakai (dapat menahan compute tetap bangun, ADR31).
8. **Sisa audit bagian kode (22 Okt siang):** tagihan, pembayaran, dan alokasi dikunci trigger database (migrasi
   `2026_10_22_000200_kunci_tagihan_pembayaran`): tagihan hanya boleh berubah status; pembayaran hanya tercatat → dibatalkan
   sekali dan tidak boleh dihapus; alokasi hanya dibatalkan sekali dan tidak boleh dihapus. Tagihan tanpa alokasi masih boleh
   dihapus (FK alokasi menolak yang pernah dibayar). CMS `/pengurus` memakai CSP ketat (`script-src 'self'`, kecuali env local).
   Aplikasi menghapus salinan foto di folder cache setelah pengaduan terkirim atau foto bukti/edit sudah dibaca ke memori.
   Belum: pin versi image + `--pull` saat deploy (mengubah skrip deploy immutable), enumerasi email saat pendaftaran (rendah).

### ADR39 — PostgreSQL cadangan memakai Neon project kedua (menggantikan pilihan Supabase di ADR29)

Tanggal: 22 Oktober 2026 (jawaban pemilik: "buat neon project kedua aja, biar ga ribet"). Status: **diputuskan**.

1. Supabase Free tidak bisa dipakai: akun pemilik sudah memakai dua project aktif (batas paket gratis). Alternatif gratis lain
   dinilai: Aiven Free (PostgreSQL asli, tetapi region tidak bisa dipilih dan dapat dimatikan bila tidak dipakai), Prisma Postgres
   (100 ribu operasi/bulan), CockroachDB (tidak kompatibel penuh: trigger plpgsql, advisory lock), Render (dihapus 30 hari),
   Railway/Koyeb (berbayar/jam terbatas).
2. Cadangan = Neon project **`rt05-takeda-cadangan`** (`long-star-75282377`, aws-ap-southeast-1, PostgreSQL 17, 0,25 CU,
   scale-to-zero). Kuota 100 CU-jam terpisah dari project utama, jadi kuota habis di project utama tidak mematikan cadangan.
   Risiko yang diterima: gangguan Neon atau akun Neon ikut mengenai cadangan; Aiven bisa ditambahkan nanti sebagai lapis kedua.
3. Role: `rt05_owner` (pemilik schema, migrasi/sinkron) dan `rt05_app` (runtime, DML saja) dibuat lewat SQL, bukan API Neon,
   karena role buatan API otomatis anggota `neon_superuser`. Kredensial hanya di berkas privat (PC operator `~/.rt05`, VPS
   `/root/.rt05/standby`), tidak di Git.
4. Sinkron satu arah utama → cadangan dengan `infra/scripts/standby-sync.sh` (pg_dump → pg_restore --clean, bandingkan baris +
   total rupiah). Prinsip ADR29 lain tetap: satu penulis, pindah terencana ~85% kuota, kembali dengan salinan utuh.
5. Bukti 22 Okt: sinkron staging → cadangan 19 dtk, 13 tabel cocok; API image `b1d080d` dengan role `rt05_app` di cadangan:
   `rt05:db-smoke` tulis/baca ok + DDL ditolak, `rt05:rekonsiliasi-keuangan` cocok (saldo sama). Belum: latihan pindah + kembali
   pada staging yang sedang dipakai (perlu jendela waktu yang disepakati pemilik) dan jadwal sinkron otomatis (setelah production).
6. Kuota project utama per 22 Okt: active_time 822.860 dtk (±229 jam × 0,25 CU ≈ 57 CU-jam dari 100), reset 1 Nov.

### ADR40 — Air Bersih KSM: calon fitur pengurus RT05, sementara hanya tautan

Tanggal: 23 Oktober 2026 (instruksi pemilik). Status: **diputuskan untuk tahap sementara; penggabungan menunggu diskusi tim**.

1. **Air Bersih KSM** = aplikasi pengurus KSM Banyu Bening (koperasi air warga RT 05 RW 07 Taman Kedaung): catat meter air,
   terbitkan tagihan air, catat pembayaran. Repo terpisah `morrispes5/air-bersih-ksm-claude` (folder lokal pemilik
   `Documents/AIR BERSIH KSM CLAUDE`), stack SvelteKit 3 + Drizzle + WoWSQL + Better Auth, live di
   https://airbersih-ksm-claude.vercel.app dengan akun sendiri. Dokumen kanonik: `docs/PRD.md` di repo itu (Fase 6).
2. Keputusan pemilik: Air Bersih KSM **bukan aplikasi yang berdiri sendiri selamanya**, melainkan **calon fitur/subfitur RT05**
   di bagian pengurus (admin), web dan mobile. Penggabungan kode menunggu diskusi pemilik dengan tim capstone.
3. **Sementara hanya tautan** (tidak ada data air bersih di database RT05, tidak ada API, tidak ada login bersama):
   - Website: Area Pengurus (`/pengurus`, sidebar) → "Tagihan air KSM", tab baru, URL dari
     `config('services.air_bersih_ksm.url')` (env `AIR_BERSIH_KSM_URL`, bawaan URL Vercel di atas).
   - Mobile: pengurus → Lainnya → kartu "Air bersih KSM" → "Tagihan air KSM" (`lib/features/pengurus/air_bersih_link.dart`),
     dibuka di browser. Gagal membuka → pesan, tanpa crash.
4. Saat penggabungan dibuka nanti (milestone baru, bukan diam-diam): data & aturan bisnis pindah ke `rest-api`/Neon sesuai
   AGENTS (bukan WoWSQL); layar pengurus di aplikasi mobile; perlu hak terpisah **pengurus KSM** karena BR07 membuat semua admin RT
   setara sementara tidak semua pengurus RT adalah pengurus KSM; perhitungkan kuota Neon (100 CU-jam/bulan). Aturan tagihan air
   (tarif per m³ + biaya admin, pasif = admin saja, nomor KSM-001, jatuh tempo +8 hari, foto meter 6 bulan) mengikuti PRD Air
   Bersih KSM; data dapat dipindah dari file "Unduh cadangan" Excel aplikasi itu. Tautan sementara (butir 3) dihapus setelah
   fitur asli tersedia.

### ADR41 — Air Bersih KSM tetap aplikasi terpisah; menu berlogo khusus pengurus (menggantikan butir 2–4 ADR40)

Tanggal: 24 Oktober 2026 (instruksi pemilik). Status: **diputuskan**.

1. Air Bersih KSM **tidak digabung** ke RT05 dan **tidak ada API/login bersama**. Aplikasi itu tetap berdiri sendiri
   (SvelteKit + WoWSQL + Vercel) dengan login sendiri; akun air bersih hanya diberikan pemilik kepada Ketua RT. Rencana
   penggabungan/porting di ADR40 butir 2 dan 4 dibatalkan. Tidak ada tabel, endpoint, atau hak baru di `rest-api`.
2. RT05 hanya menyediakan **pintu masuk** "Air Bersih KSM" dengan **logo KSM Banyu Bening** (tanpa ikon panah) yang
   mengalihkan pengurus ke aplikasi itu; di sana pengurus login lagi.
3. **Hanya pengurus** (semua admin RT05, BR07: hak setara) yang melihat menu itu. **Warga tidak boleh melihatnya**:
   - Mobile: kartu di Lainnya pengurus (`lib/features/pengurus/air_bersih_link.dart`); warga tidak punya tab itu (diuji
     dengan aplikasi utuh untuk kedua peran).
   - Web: sidebar Area Pengurus hanya di mode server, dirender `hidden` dan dibuka `cms-preview.js` setelah `/me`
     terbukti `peran = pengurus`. Pratinjau statis Vercel (tanpa login) dan halaman publik tidak memuatnya sama sekali.
4. Logo: `website/public/images/logo/air-bersih-ksm.png` = salinan byte-identik `static/logo-ksm.png` repo Air Bersih;
   `mobile/assets/brand/air-bersih-ksm.png` byte-identik dengan website (diuji).

### ADR42 — Fitur tambahan RT: tanggal lahir anggota keluarga dan statistik kelompok umur warga

Tanggal: 24 Oktober 2026 (permintaan Pak RT lewat pemilik; pemilik menyetujui kelompok usulan dan persetujuan ulang privasi).
Status: **diputuskan**. Fitur tambahan RT di luar M00–M16 (seperti M15b), rilis APK 1015.

1. `warga.tanggal_lahir` (date, nullable, migrasi aditif `2026_10_24_000100`). Umur **tidak disimpan**; dihitung tahun penuh
   per tanggal hari ini di WIB (`App\Domain\Residents\AgeGroups`, sama dengan `birth_date.dart` di aplikasi). Validasi
   1900-01-01 s.d. hari ini. Opsional di semua jalur.
2. Kelompok (dasar: siklus hidup Cek Kesehatan Gratis Kemenkes; lansia ≥60 sesuai UU 13/1998; dirinci untuk RT):
   balita 0–5, anak-anak 6–12, remaja 13–17, dewasa 18–59, lansia 60–69, lansia 70+ ≥70.
3. Pengisian: akun utama untuk keluarganya (tambah/ubah anggota, termasuk kepala lewat data anggota); **pengurus CRUD penuh**
   (tambah anggota, ubah, kosongkan, arsipkan) sehingga Pak RT dapat mengisi dari data manual RT bila warga tidak mengisi.
4. `GET /api/v1/admin/age-stats` khusus pengurus: total warga aktif, jumlah per kelompok, belum diisi, ringkasan per rumah,
   daftar warga yang dapat disaring kelompok, rentang umur bebas (`min`/`max`, mis. 60–65), atau `belum_diisi`. Aplikasi:
   tab Warga → **Statistik usia**; ketuk warga untuk mengubah datanya.
5. Privasi: tanggal lahir hanya untuk pengurus dan keluarga sendiri. Kebijakan Privasi versi **2026-10-24** menambah tanggal
   lahir dan tujuannya; semua akun diminta menyetujui ulang sekali (disetujui pemilik). Tanpa jenis kelamin dan tanpa impor
   Excel otomatis (belum diminta).

### ADR43 — Hasil rapat RT 24 Okt 2026: milestone M17–M20, M16 dijalankan sesudah domain diputuskan

Tanggal: 25 Oktober 2026 (dokumen pemilik "JAWABAN CLAUDE DAN MEETING RT 24 OKTOBER 2026" + jawaban pemilik di sesi Claude).
Status: **diputuskan**. Mengubah ADR36/M16 "milestone terakhir": masih ada M17–M20 sebelum rilis penuh.

**Jadwal:** soft launch **beta 8 Nov – Des 2026**, rilis penuh **paling lambat awal 2027**. Beta memakai data warga asli, jadi
harus di **production** (bukan staging, AGENTS); domain diputuskan awal November, lalu M16 production. Urutan kerja:
M17 → M18 → (2 Nov pindah VPS staging) → M16 production + beta → M19 → M20.

**Fakta dan keputusan yang mengikat (sumber: pemilik/Pak RT):**
1. Kontak resmi pengurus: **Ketua RT Agus Ferdiansyah**; nomor WhatsApp-nya boleh dipublikasikan di halaman Kontak dan Kebijakan Privasi. Nomor diisi lewat
   konten/pengaturan (database), **tidak ditulis di Git** (repo publik).
2. Bendahara **Mas Wirawan** boleh dipublikasikan; nomor WhatsApp bendahara diisi di pengaturan aplikasi (database), bukan Git.
   Sekretaris tetap belum dipublikasikan.
3. Semua pengurus setara hak bisnis (BR07 tetap). **Super admin = akun `takedatamankedaung@gmail.com`**: satu-satunya akun yang
   boleh mengangkat/menurunkan warga menjadi pengurus dari aplikasi (pengurus terakhir tidak bisa diturunkan; diaudit).
   Kata sandi diberikan ke pemilik lewat berkas catatan lokal, tidak di Git/chat. Akun uji sintetis M06 tetap dijaga.
4. Alamat: jalan RT 05 = **Mawar 8 s.d. Mawar 14**; penulisan dibakukan (pilihan jalan, blok, nomor).
5. Kepala keluarga meninggal → pengurus memindahkan kepala ke istri/anak (fitur sudah ada). Akun anak/istri yang mendaftar
   dengan alamat/kepala yang sama otomatis dicocokkan dan masuk keluarga itu **setelah disetujui pengurus** (aturan tetap).
6. **Keuangan (M18, kritis):** iuran bawaan Rp75.000; nominal bayar boleh berbeda.
   - Bayar kurang: bendahara memilih saat menyetujui — **lunas dengan keringanan** (sisa dibebaskan, alasan wajib) atau **kurang
     bayar** (sisa tetap tunggakan).
   - Rumah kedua / rumah jarang dipakai: **tarif khusus per rumah** (nominal, mulai bulan, alasan) diatur pengurus.
   - Bayar lebih: bendahara memilih — **sumbangan ke kas** atau **titipan bulan berikutnya**.
   - Bukti transfer warga memuat nominal (75.000 bawaan atau nominal lain); bendahara menyetujui dengan nominal yang dapat
     dikoreksi; kas bertambah sebesar uang yang benar-benar diterima. Semua nominal di halaman pengurus dapat dikoreksi lewat
     jalur koreksi (pembalikan + rekaman pengganti), tidak pernah diedit diam-diam.
   - **Saldo kas transparan ke warga**: saldo + ringkasan masuk/keluar per bulan (tanpa nama rumah). Status iuran rumah lain
     tetap tertutup (mengubah sebagian ADR26/ADR35).
   - Laporan bulanan **dan tahunan**; Excel `.xlsx` rapi: sheet per bulan, rekap tahunan, tabel rumah × bulan.
7. Masa simpan data setelah warga pindah/meninggal: **12 bulan lalu dianonimkan**; catatan iuran/kas tetap sebagai bukti.
8. **Tombol panik (M19, uji coba):** aktivasi 3 langkah (tekan → baca ketentuan + jenis darurat: kebakaran, kemalingan,
   penyerangan, darurat serius lain → konfirmasi Ya/Batal). Penerima: **semua warga dan pengurus**; **tidak boleh anonim**
   (nama, rumah, jenis). Bunyi seperti alarm di Android (kanal notifikasi alarm, izin pengguna). Penyalahgunaan: peringatan,
   **3 kali → tombol panik akun itu dinonaktifkan**. Bukan pengganti 112/damkar/polisi.
9. **iPhone (M20):** aplikasi iOS asli butuh Apple Developer berbayar (±US$99/tahun, kartu) dan Mac; pemilik tidak punya kartu
   kredit/NPWP. Jalan gratis: **versi web yang dipasang di layar utama iPhone (PWA)** dari kode yang sama/website, diuji di iPhone
   nyata lewat Safari (tanpa emulator). Fitur yang bergantung Android (alarm panik) terbatas di iPhone.
10. Teknis: hosting Biznet NEO Personal Large menunggu jawaban 6 pertanyaan (sudah dikirim); VPS staging baru 2 Nov; backup
    otomatis lewat **GitHub Actions ke Drive pemilik** (bukan PC lokal); pemantau uptime gratis (pemilik diajari); hanya layanan
    gratis (tanpa kartu kredit/NPWP).

**Penerimaan M17, 25 Oktober 2026 (sesi Codex):** pemilik memilih “Ya, emulator cukup” untuk gate penutupan M17,
setelah tes otomatis dan checklist APK 1018 lulus. Bukti dicatat sebagai emulator/staging, bukan HP nyata atau UAT mitra.
Hasil verifikasi di [VERIFICATION_1018.md](evidence/m17/VERIFICATION_1018.md). Gate M16 tetap terpisah.

### ADR44 — M18: pembayaran fleksibel, keringanan, titipan manual, kas dan laporan

Tanggal: 25 Oktober 2026. **Status: diputuskan, disetujui pemilik 25 Oktober 2026.**
Spesifikasi dan tes: [M18_PLAN.md](M18_PLAN.md). Belum mengubah kontrak API/skema/perilaku aktif M17.

**Sudah diputuskan pemilik:** titipan digunakan **atas pilihan pengurus**, bukan otomatis. Tidak perlu meminta
keputusan itu lagi. Semua pengurus setara tetap mengikat (BR07). Pemilik meminta lanjut implementasi M18 setelah rencana disampaikan, dengan banyak pengujian dan gate keuangan ketat.

**Keputusan implementasi:**

1. Tarif khusus per rumah memakai riwayat versi dan mulai bulan. Tagihan menyimpan snapshot; tarif baru tidak
   mengubah tagihan existing. Salah input tarif/keringanan/nominal dikoreksi tercatat, tidak UPDATE nilai asal.
   Koreksi tagihan existing berupa penyesuaian beralasan, pembalikan + pengganti; snapshot asal dan kas tidak diubah.
2. Pembayaran boleh sebagian, berulang, atau berlebih. Kekurangan wajib dipilih sebagai tunggakan atau keringanan
   beralasan; kelebihan sebagai sumbangan atau titipan. Alokasi ke periode terpilih mengikuti bulan tertua.
3. Satu penerimaan uang membuat satu pemasukan kas sebesar uang nyata. Keringanan bukan kas. Penerimaan titipan
   menambah kas sekali; penggunaan manual titipan tidak membuat pembayaran/kas baru. Titipan melekat pada rumah,
   saldo tidak negatif, tanpa pemindahan antarrumah atau pencairan otomatis.
4. Bukti transfer menyimpan nominal pengajuan asli. Persetujuan bernominal terkoreksi membuat pembayaran dan kas
   dalam satu transaksi, alasan koreksi wajib, tautan bukti unik. Bukti historis tidak dipasangkan secara perkiraan.
5. Koreksi = pembalikan + pengganti atomik, alasan wajib, histori asal tetap. Titipan yang sudah dipakai harus
   dibalik penggunaannya dahulu sebelum sumber uang dikoreksi; tidak membatalkan pelunasan lain diam-diam.
6. Integer rupiah, CHECK/FK/unique dan trigger menjaga ledger; satu advisory lock keuangan + row lock berurutan,
   idempotensi existing dan fingerprint pratinjau mencegah retry/concurrency mengubah arti transaksi. Kas bertanda
   existing dipertahankan. Rekonsiliasi diperluas untuk seluruh persamaan dan harus menghasilkan 0 masalah.
7. Warga aktif melihat tagihan/tunggakan/titipan rumah sendiri serta kas agregat bulanan tanpa identitas rumah
   lain. Mengubah sebagian ADR26/35, tidak membuka `/dues/transparency` ke warga. Kebijakan privasi harus diperbarui
   dan persetujuan memvalidasi versi teks yang benar-benar ditampilkan client.
   Implementasi: teks M18 versi 26 Oktober 2026, aktif bersama flag keuangan v2. Saat flag nonaktif server tetap
   meminta versi 25 Oktober; APK baru membawa kedua teks. Versi server yang tidak dikenali APK tidak dapat disetujui
   dengan hanya menyalin nomornya. Endpoint kas warga menolak akses sebelum persetujuan teks M18.
8. Laporan bulanan/tahunan dan XLSX memakai satu layanan perhitungan. Tahun kalender, WIB, 14 sheet (rekap tahun,
   rumah×bulan, 12 bulan), angka sama dengan API, koreksi lintas bulan terpisah. OpenSpout 4.32.0 diusulkan setelah
   dry-run dependency berhasil; belum ditambahkan ke repo.
9. Implementasi PR kecil: DB/domain → API/OpenAPI/ERD → mobile pengurus → transparansi warga → laporan/Excel.
   Flag default off; setelah aktif, mutasi keuangan perlu client kontrak v2. Jangan rollback ke kode M17 sesudah
   transaksi v2 tercatat. Staging sintetis + emulator + rekonsiliasi dahulu, satu APK M18 yang dibagikan sesudah lulus.

**Persetujuan:** instruksi pemilik 25 Okt “lanjut ke milestone 18 ... harus banyak pengujian”. Implementasi boleh dimulai; semua angka PASS M17 bukan bukti penerimaan M18.
