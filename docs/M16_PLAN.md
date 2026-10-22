# Rencana M16 — Rilis ke warga (milestone terakhir)

Disusun 22 Oktober 2026 atas permintaan pemilik: M16 adalah persiapan terakhir sebelum warga memakai sistem. Setelah M16
passed, pekerjaan berikutnya hanya revisi dan tambahan kecil, bukan milestone baru. Sumber gate tetap
[ROADMAP.md](ROADMAP.md) bagian M16. Hasil riset eksternal di bawah dicek pada 22 Okt 2026 dan bisa berubah. Cek ulang
sebelum membayar atau mendaftar.

## 1. Tujuan dan definisi selesai

Warga awam bisa menemukan website RT05 TAKEDA di Google dan mengunduh aplikasi dari situs resmi. Mereka memasangnya tanpa
peringatan "aplikasi berbahaya" (selain izin bawaan Android untuk aplikasi di luar toko, dijelaskan di halaman unduh), lalu memakainya dengan data nyata yang aman. Pengurus bisa mengelola semuanya tanpa
bantuan agent.

M16 **passed** bila semua butir ini terbukti, bukan hanya berupa kode:

| # | Gate | Bukti |
| --- | --- | --- |
| G1 | Production live di domain resmi dengan HTTPS, bisa dibuka dari jaringan luar (termasuk ISP rumah pemilik tanpa WARP) | Hasil cek dari luar (check-host.net) + smoke production |
| G2 | API, website, cron tick, outbox, foto, push, dan email reset bekerja dengan provider nyata | `staging-runtime-smoke.sh` versi production + inbox nyata |
| G3 | Backup terenkripsi offsite berjalan, restore drill RPO ≤24 jam/RTO ≤4 jam lulus, rollback drill lulus | Log drill di `docs/evidence/m16/` |
| G4 | PostgreSQL cadangan (ADR29): satu siklus pindah dan kembali tanpa tulisan hilang | Jumlah baris + rekonsiliasi identik |
| G5 | APK production 1.0.0 bertanda tangan kunci tetap, terpasang dari halaman unduh resmi di HP nyata (64-bit + 32-bit); langkah pemasangan dan tampilan Play Protect terdokumentasi. Verifikasi developer **bukan gate** (ADR37) | Tangkapan layar HP pemilik |
| G6 | Halaman unduh resmi berisi versi, tanggal, ukuran, SHA-256, panduan pasang, izin aplikasi, dan Kebijakan Privasi | URL live |
| G7 | Kebijakan Privasi final: kontak resmi dan masa simpan nyata, URL publik | Teks di app + website |
| G8 | Google Search Console terverifikasi, sitemap diterima, beranda terindeks, dan pencarian "RT05 Takeda" menampilkan situs | Tangkapan layar GSC + hasil pencarian |
| G9 | Data nyata awal diimpor hanya yang sudah divalidasi pengurus; admin pertama dibuat dengan aman | Catatan impor tanpa data pribadi |
| G10 | Sisa M15: TalkBack manual, uji HP nyata (kamera, sinyal lambat, push 3 kondisi), pemulihan otomatis foto/push, issue #47 | PROGRESS + tes regresi |
| G11 | Insiden credential historis ditutup (rotasi/revokasi), tidak ada secret di repo | `check-repo.mjs` + catatan rotasi |
| G12 | SOP pengurus dan dokumen serah terima selesai, biaya perpanjangan tercatat | `docs/SOP_PENGURUS.md` + HANDOFF |
| G13 | Pilot warga terbatas lalu peluncuran; 14 hari pemantauan tanpa bug high/critical terbuka | PROGRESS |

## 2. Keputusan pemilik yang dibutuhkan lebih dulu

Agent bisa mulai Fase A tanpa jawaban ini. Fase B ke atas tertahan sampai keputusan diambil.

| # | Keputusan | Rekomendasi agent | Kenapa penting |
| --- | --- | --- | --- |
| K1 | **Nama domain production** (gratis .COM dari paket Biznet Large) | Nama yang sama dengan merek, mis. `rt05takeda.com` (cek ketersediaan saat checkout; agent tidak menebak dan tidak membeli) | Domain ini yang muncul di Google dan di halaman unduh. Nama yang cocok dengan pencarian "RT05 Takeda" paling membantu. `takeda.morriz.tech` (P13) adalah domain pribadi pemilik dan terlihat kurang resmi bagi warga |
| K2 | **Nama paket aplikasi** tetap `tech.morriz.takeda` atau diganti sebelum didaftarkan | **Tetap.** Nama paket hampir tidak terlihat warga. Menggantinya berarti mendaftar ulang aplikasi Android di Firebase dan semua penguji memasang ulang | Nama paket yang sudah didaftarkan ke verifikasi developer bersifat permanen |
| K3 | ~~Jenis akun developer~~ — **ditunda (ADR37)**. Bila nanti didaftarkan: perorangan (identitas pemilik) | Perorangan. RT bukan badan hukum, sedangkan akun organisasi biasanya meminta nomor D-U-N-S | Nama developer terverifikasi tercatat di Google |
| K4 | Kontak resmi + masa simpan data (Kebijakan Privasi dan halaman Kontak) | Usulan masa simpan sebagai bahan diskusi: data akun dihapus atau dianonimkan 12 bulan setelah warga pindah; catatan keuangan disimpan sesuai kebutuhan pembukuan RT. **Angka final dari pemilik/Pak RT** | Wajib sebelum data warga asli masuk |
| K5 | Siapa admin pertama production, dan data rumah/warga mana yang diimpor | Pak RT + pemilik sebagai admin; impor daftar rumah dulu, warga mendaftar sendiri | Data asli tidak boleh dikarang atau disalin dari staging |
| K6 | Beli Biznet **setelah** jawaban preflight dari support Biznet (bagian 4) | Ya | Paket shared hosting bisa saja tidak mendukung `pdo_pgsql` atau koneksi keluar ke port 5432 |
| K7 | Ejaan lokasi: "Ciputat" (meta website, footer, kontak) vs "Kecamatan Pamulang" (teks profil Taman Kedaung) | Pakai "Taman Kedaung, Pamulang, Tangerang Selatan" di judul/deskripsi, dan sebut Ciputat di teks profil, karena warga masih mencarinya dengan kata itu. Perlu konfirmasi Pak RT | Google membaca ketidakkonsistenan sebagai sinyal yang lemah |
| K8 | Opsi Google Play Store (di luar M16 kecuali pemilik memintanya) | Tidak di M16; tetap unduhan langsung dari situs resmi + verifikasi developer (keputusan pemilik 17 Okt 2026, DECISIONS tabel risiko). Bisa dipertimbangkan setelah peluncuran | Akun Play perorangan baru wajib *closed testing* dengan banyak penguji selama berhari-hari sebelum boleh rilis publik |

## 3. Kepercayaan aplikasi Android (fokus mobile)

### 3.1 Fakta verifikasi developer (dicek 22 Okt 2026)

- Sejak **30 September 2026**, di Indonesia, Brasil, Singapura, dan Thailand, aplikasi yang dipasang lewat toko yang
  berpartisipasi di perangkat Android bersertifikat (Android 7+) harus terdaftar atas nama developer terverifikasi. Toko itu
  antara lain Google Play, Galaxy Store, Xiaomi GetApps, OPPO, vivo, HONOR, dan Transsion. Perluasan ke semua aplikasi di
  semua negara direncanakan pada **2027**. Aplikasi yang belum terdaftar hanya bisa dipasang lewat ADB atau "advanced
  flow" untuk pengguna mahir, jalur yang tidak bisa diharapkan dari warga awam.
- Developer yang hanya mendistribusikan di luar Play mendaftar lewat **Android Developer Console**
  (`android.google.com/developerconsole`). Akun gratis "limited distribution" hanya untuk maksimal 20 perangkat, jadi
  tidak cukup untuk satu RT. Pakai akun penuh dengan KTP pemilik. Biaya dan dokumen persis dilihat di konsol saat mendaftar.
- Artinya: unduhan langsung dari situs kita mungkin belum diperiksa pada fase pertama ini, tetapi akan diperiksa pada 2027.
  Mendaftar sekarang mencegah aplikasi tiba-tiba diblokir di HP warga saat perluasan berlaku.

Sumber: [developer.android.com/developer-verification](https://developer.android.com/developer-verification),
[Android Developers Blog, Maret 2026](https://android-developers.googleblog.com/2026/03/android-developer-verification-rolling-out-to-all-developers.html).

### 3.2 Langkah verifikasi — ditunda, opsi terakhir (ADR37)

Pemilik memutuskan pada 22 Okt 2026 untuk **tidak** mendaftar dulu. Langkah di bawah dipakai bila nanti diperlukan (mis. Google
mulai memeriksa unduhan langsung pada 2027). Sebelumnya, amankan Gmail dan GitHub (2FA, kode cadangan), pisahkan kata sandi
kunci rilis dari Drive, dan atur Pengelola Akun Tidak Aktif Google.

1. Pemilik membuat akun Android Developer Console perorangan dengan akun Google pribadi, KTP, dan nomor HP. Agent tidak
   pernah mengisi identitas atau pembayaran.
2. Daftarkan nama paket `tech.morriz.takeda` (K2) dan bukti kepemilikan kunci rilis. Sidik jari sertifikat ada di ADR31
   (`dba8bc3f…`). Bila konsol meminta APK atau berkas bukti yang ditandatangani, agent membuatnya lewat workflow CI
   `release` (kunci tidak pernah keluar dari GitHub environment).
3. Pasang APK production di HP pemilik + satu HP 32-bit dan catat tampilan layar pemasangan sebagai bukti G5.

### 3.3 Yang membuat warga percaya (dikerjakan agent)

| Butir | Isi |
| --- | --- |
| Versi rilis | Flavor `production` mengarah ke API production; `versionName` **1.0.0** (bukan 0.1.0), nomor build tetap naik. Staging tetap memakai nama "RT05 TAKEDA Staging" agar tidak tertukar |
| Halaman unduh resmi | `https://<domain>/aplikasi`, dari website, bukan Drive atau tautan acak. Isinya: logo, nama resmi, versi, tanggal rilis, ukuran, SHA-256, tombol unduh 64-bit dan 32-bit (otomatis menyarankan arm64), panduan pasang bergambar (izin "sumber tidak dikenal" untuk browser), daftar izin dan alasannya (Internet, Kamera untuk foto pengaduan/bukti, Notifikasi), tautan Kebijakan Privasi, dan pesan "pastikan Anda mengunduh hanya dari situs ini" |
| Metadata versi | `/aplikasi/versi.json` (versi, build, SHA-256, tanggal, catatan rilis) dihasilkan CI dari artefak yang sama |
| Info di aplikasi | Menu "Tentang aplikasi": versi, pengelola (RT 05 RW 07 Taman Kedaung), tautan situs resmi dan Kebijakan Privasi. Pemberitahuan versi baru membuka halaman unduh resmi |
| Izin minimal | Manifest hanya meminta Internet, Kamera, dan Notifikasi (sudah). Jangan menambah izin tanpa alasan |
| Kebijakan Privasi publik | Halaman `/kebijakan-privasi` di website, isinya sama dengan di aplikasi (K4) |
| Materi sosialisasi | Poster/pesan WhatsApp grup RT dengan QR ke halaman unduh, langkah pasang singkat, dan nomor pengurus untuk bertanya |
| Pilot | 5–10 rumah dulu, perbaiki temuan, baru diumumkan ke semua warga |

## 4. Hosting production (Biznet NEO Web Hosting Personal Large)

Dicek 22 Okt 2026 di [halaman produk](https://www.biznetgio.com/product/neo-web-hosting-personal): Large Rp399.000 untuk
tahun pertama (harga normal Rp981.000/tahun), SSD 60 GB, RAM 1 GB, domain gratis .COM/.ID, LiteSpeed Enterprise,
SSL gratis, Acronis Backup, PHP Selector. Yang **tidak tercantum**: versi/ekstensi PHP, PostgreSQL, cron, SSH, dan
koneksi keluar. Karena itu, sebelum membeli, pemilik mengirim pertanyaan ini ke support/sales Biznet (teks siap kirim
disiapkan agent):

1. Apakah PHP 8.3 atau lebih baru (syarat composer.json `^8.3`) tersedia di web **dan** CLI, dengan ekstensi `pdo_pgsql`, `gd` (JPEG/PNG/WebP),
   `intl`, `mbstring`, `fileinfo`, `openssl`, `curl`?
2. Apakah koneksi keluar ke port **5432** (Neon, Supabase) dan 443 (FCM, Resend) diizinkan?
3. Apakah ada cron di cPanel dengan interval 15 menit, dan berapa batas waktu prosesnya?
4. Apakah ada akses SSH/Terminal? Berapa batas *entry process*, CPU, dan IO?
5. Bisakah document root diarahkan ke folder `public` (bukan seluruh `public_html`)?
6. Harga total checkout termasuk pajak, harga perpanjangan hosting **dan** domain tahun kedua, serta ketentuan refund.

Bila salah satu dari butir 1–3 tidak bisa, jangan membeli. Opsi cadangan (keputusan pemilik): tetap di VPS staging untuk
production sementara, atau mencari paket lain yang masih dalam anggaran ≤Rp500.000.

Setelah membeli: ikuti [SHARED_HOSTING.md](SHARED_HOSTING.md) bagian "Persiapan staging dan rollout" dan tabel
acceptance. Tambahan khusus production:

- **Neon project production terpisah** dari staging/dev. Kuota Free 100 CU-jam berlaku per project, sedangkan project
  `rt05-takeda` saat ini dipakai bersama oleh staging, production, dan dev.
- Paket Resend HTTP (`resend/resend-php`) dipasang dan inbox nyata diuji, karena shared hosting sering memblokir SMTP.
- Backup terenkripsi DB + media ke tujuan offsite (usulan: Google Drive pemilik lewat rclone di mesin operator, atau
  storage gratis lain; keputusan pemilik). Acronis Biznet hanya menjadi lapisan tambahan.
- Pemantau uptime gratis untuk `/up` dan heartbeat (usulan: UptimeRobot Free, keputusan pemilik karena butuh akun).
- Staging tetap di VPS untuk uji rilis berikutnya. Production tidak bergantung pada VPS (ADR23).

## 5. Website ditemukan di Google (SEO + Search Console)

Kondisi saat ini: `website/vercel.json` mengirim `X-Robots-Tag: noindex` untuk semua halaman (benar untuk preview).
Belum ada `sitemap.xml`, canonical, `og:url`, `og:site_name`, maupun data terstruktur. `robots.txt` membolehkan semua
halaman, termasuk `/pengurus`.

Pekerjaan agent (Fase A, bisa diuji lokal dan di staging):

1. `noindex` tetap di Vercel preview dan staging. Production **tanpa** noindex, kecuali `/pengurus*` (noindex +
   `Disallow` di robots.txt).
2. `sitemap.xml` otomatis: halaman publik, artikel, dan album terbit beserta `lastmod`, plus `Sitemap:` di robots.txt.
3. Tag di `layouts/app.blade.php`: `<link rel="canonical">`, `og:url`, `og:site_name`, `og:description`, judul unik per
   halaman dengan pola "Judul — RT05 TAKEDA".
4. JSON-LD di beranda: `WebSite` dengan `name` "RT05 TAKEDA" dan `alternateName` ("RT 05 Taman Kedaung", "RT05 Takeda",
   "RT 05 RW 07 Taman Kedaung"), serta `Organization` (logo gapura, url, area). Tidak mencantumkan alamat/kontak yang belum
   diumumkan. Artikel memakai `Article` (judul, tanggal, gambar).
5. Selaraskan teks lokasi (K7) dan pastikan kata "RT05 TAKEDA", "Taman Kedaung", "RT 05 RW 07" muncul wajar di judul dan
   teks halaman, tanpa *keyword stuffing* dan tanpa mengubah desain yang sudah diterima.
6. Tes otomatis: production tanpa noindex, preview dengan noindex, sitemap valid, JSON-LD valid.

Langkah pemilik (dipandu, Fase C setelah domain live):

1. Login [Google Search Console](https://search.google.com/search-console) dengan akun Google pemilik, tambah **properti
   Domain**, lalu salin record TXT verifikasi ke DNS domain di panel Biznet. Agent bisa memandu, tetapi DNS diubah atas izin
   pemilik.
2. Kirim `https://<domain>/sitemap.xml`, lalu Periksa URL beranda → "Minta pengindeksan".
3. Opsional: Bing Webmaster Tools (bisa impor dari GSC); Google Business Profile untuk sekretariat RT bila Pak RT setuju
   lokasinya dipublikasikan.

Ekspektasi jujur: pencarian nama merek ("RT05 Takeda", "RT 05 Taman Kedaung") biasanya muncul beberapa hari sampai beberapa
minggu setelah terindeks. Kata umum seperti "RT 05" tidak bisa dijamin. Tautan dari grup WhatsApp, poster QR, dan
(bila ada) situs kelurahan membantu Google mengenali situs ini.

Sumber: [Google — Provide a site name](https://developers.google.com/search/docs/appearance/site-names).

## 6. Urutan kerja

| Fase | Siapa | Isi | Butuh |
| --- | --- | --- | --- |
| **A. Persiapan tanpa production** | Agent | SEO bagian 5 (1–6); flavor production + versi 1.0.0; halaman `/aplikasi`, `versi.json`, menu Tentang aplikasi, halaman `/kebijakan-privasi` (teks menunggu K4); pemulihan otomatis foto/push tanpa menunggu sweep per jam; issue #47; teks pertanyaan Biznet; skrip backup/restore + runbook; draf SOP pengurus | Tidak ada. Rilis APK uji baru diberitahukan seperti biasa |
| **B. Keputusan & pembelian** | Pemilik | K1–K7; kirim pertanyaan Biznet; beli bila lolos; daftar Android Developer Console | Jawaban Biznet |
| **C. Setup production** | Agent (+ izin pemilik untuk DNS/hosting) | Neon project production + role; deploy shared profile; Resend; cron; backup offsite; drill restore/rollback; drill DB cadangan (G4); smoke dari jaringan luar; GSC | Akses hosting dari pemilik |
| **D. APK 1.0.0** | Pemilik + agent | Build APK production; uji HP nyata + 32-bit + TalkBack; halaman unduh live (verifikasi developer ditunda, ADR37) | Production live |
| **E. Data nyata & pilot** | Pengurus + agent | Admin pertama; impor rumah yang tervalidasi; pilot 5–10 rumah; perbaiki temuan | K4, K5 |
| **F. Peluncuran & serah terima** | Pengurus | Pengumuman + poster QR; pantau 14 hari; serah terima (SOP, biaya perpanjangan, kontak darurat); M16 passed | G1–G13 |

Setelah M16 passed, permintaan baru dicatat sebagai revisi (ADR + PR biasa) tanpa membuka milestone baru, kecuali
pemilik memutuskan lain.

## 6a. Status Fase A (22 Okt 2026)

Selesai di branch `codex/m16-fase-a`: SEO (bagian 5 butir 1–4, 6), `/aplikasi` + `versi.json`, `/kebijakan-privasi`
(teks menunggu K4), kartu Tentang aplikasi + versi 1.0.0, pemulihan foto/push, penjelasan 404 issue #47, teks pertanyaan
Biznet, runbook, draf SOP. Butir 5 (ejaan lokasi, K7) menunggu konfirmasi Pak RT. Gate P95 staging belum lulus
(lihat PROGRESS).

## 7. Batas yang tetap berlaku

- Agent tidak membeli, tidak mengisi KTP atau pembayaran, dan tidak membuat akun atas nama pemilik.
- DNS, hosting production, dan deploy production hanya dikerjakan atas izin eksplisit per langkah.
- Data warga asli tidak masuk Git atau log. Staging tidak dipakai untuk data asli. Production tidak dipakai untuk tes atau seed.
- Tidak menambah layanan berbayar tanpa persetujuan. Layanan gratis yang butuh akun (UptimeRobot, tujuan backup) tetap
  keputusan pemilik.
