# Serah terima frontend RT 05 Takeda

> **Catatan M01 (10 Oktober 2026):** dokumen ini historis untuk baseline frontend. Repository yang sama kini menjadi monorepo: modul web berada di `website/` (bukan `web/` seperti rencana di bawah), dokumentasi frontend di `docs/website/`, workflow di `.github/workflows/website.yml`, dan instruksi Vercel di [VERCEL_ROOT.md](VERCEL_ROOT.md). Opsi ZIP/subtree di bawah tidak dipakai karena migrasi dilakukan dengan `git mv` di repository asal agar history tetap utuh. Perintah website dijalankan dari `website/`.

## Status dan sumber utama

**Desain diterima pemilik pada 10 Oktober 2026.** Tahap website frontend selesai sementara. Pekerjaan berikutnya adalah membahas monorepo, aplikasi mobile, REST API, dan backend bersama agent berikutnya. Handoff ini tidak membangun modul-modul tersebut atau mengubah tampilan yang telah diterima.

- Repository: https://github.com/morrispes5/RT-05-TAKEDA
- Website terverifikasi: https://rt05takeda.vercel.app
- Baseline desain yang diterima: `0658d5d4351eb6d1409baab5835cdc5e137533db` (PR #8).
- Tag paket serah terima: `web-frontend-approved-2026-10-10`, menunjuk commit `main` yang juga memuat handoff ini.
- Halaman release: https://github.com/morrispes5/RT-05-TAKEDA/releases/tag/web-frontend-approved-2026-10-10
- ZIP release berisi folder **`web/`** dengan seluruh berkas Git, termasuk source, aset, lockfile, preview, dokumentasi, dan workflow. Manifest mencatat commit, Git tree, SHA-256 arsip, ukuran, serta hash setiap file.

Baseline desain berbeda dari commit paket karena paket menambahkan dokumen/bukti dan script arsip. Gunakan `sourceCommit` dalam manifest untuk mengetahui commit tepat yang diarsipkan. Tag ini merupakan snapshot frontend; `main` boleh berkembang untuk pekerjaan berikutnya.

## Yang sudah bekerja

Laravel 13, Blade, Tailwind 4, JavaScript, dan Vite digunakan untuk membangun website. Ekspor statis mempunyai **19 halaman + 404** dan disajikan Vercel. Website publik tanpa akun.

| Bagian | Perilaku final |
| --- | --- |
| Beranda | Hero dua kolom, garis kuning, foto lengkung, akses artikel/dokumentasi/kontak, lingkungan, fasilitas, album, bacaan, kontak/footer |
| Foto hero | Gapura → taman bermain → sekretariat → lapangan, otomatis 3 detik dengan fade 550 ms; caption mengikuti foto; empat indikator; tanpa Jeda/Putar |
| Motion | Hover/fokus dan pilihan manual tidak menghentikan autoplay; reduced motion mematikan autoplay/fade; tab atau hero tidak terlihat menunda pergantian |
| Profil | Ketua Agus Ferdiansyah; sekretaris/bendahara anonim |
| Fasilitas | Foto asli dan penjelasan ruang lingkungan |
| Dokumentasi | Tab Kegiatan/Lingkungan, filter, album, caption, perbesar/lightbox; album Perayaan 17 Agustus tanpa tahun |
| Artikel | Lima bacaan edukasi, pencarian judul, filter kategori, waktu baca, halaman membaca |
| Pengurus | Ringkasan, Artikel, Dokumentasi, kembali ke website; editor bagian artikel dan album/foto, urutan, sampul, pratinjau, reset |

Area Pengurus berlabel pratinjau. Draf dan foto percobaan tersimpan dalam IndexedDB pada browser/origin yang sama. Foto perangkat diproses lokal, tidak diunggah ke server. Draf tidak mengubah halaman yang dilihat pengunjung lain. `/pengurus/masuk` tidak menerima email/kata sandi atau memberikan akses aman.

## Batas website dan pekerjaan berikutnya

| Modul rencana | Tanggung jawab |
| --- | --- |
| `web/` | Website informasi publik dan UI Pengurus Artikel/Dokumentasi yang telah diterima |
| `mobile/` | Aplikasi warga/pengurus: pendataan, pengaduan, aspirasi, agenda, iuran, keuangan, token registrasi; implementasi dan framework belum ditetapkan dalam handoff ini |
| `rest-api/` | Backend operasional bersama: autentikasi/otorisasi, penyimpanan, publikasi konten, data mobile, dan kontrak HTTP; belum dibangun |

Folder `app`, `routes`, `config`, `bootstrap`, `database`, dan `artisan` saat ini adalah shell Laravel yang diperlukan untuk render Blade serta build. Migration/model User bawaan tidak berarti login atau backend bisnis sudah selesai. Jangan membuang shell tersebut ketika menyalin website, lalu berharap Blade masih bisa dibangun.

Pada tahap berikutnya, `PreviewContentRepository` dapat diganti adapter HTTP tanpa mendesain ulang editor. UI memakai metode asinkron `list/get/save/archive/media/reset`. `SiteContent` kini membaca JSON publik dari repo; integrasi server perlu mengganti akses konten dan proses publikasi secara eksplisit. Struktur konten dan batas draf dijelaskan di `CONTENT_PREVIEW.md`.

Belum ada endpoint REST bisnis, autentikasi operasional, upload media server, publikasi online, database produksi, sinkronisasi aplikasi mobile, atau deployment Laravel/VPS. Framework mobile, batas rendering web terhadap API, kontrak endpoint, otorisasi, database, storage, dan strategi deploy perlu dibahas berikutnya. Handoff ini tidak menetapkan keputusan tersebut secara diam-diam.

## Peta berkas yang perlu dibawa ke `web/`

| Lokasi | Fungsi |
| --- | --- |
| `resources/views/` | Layout, halaman, komponen Blade, SVG artikel/ikon |
| `resources/css/` | Token, layout publik/Pengurus, slideshow |
| `resources/js/` | Menu/filter/lightbox, slideshow, editor, repository IndexedDB |
| `resources/data/` | Artikel, album, metadata foto publik |
| `public/images/` | Logo, foto asli versi WebP, gambar OG |
| `app/Support/SiteContent.php` | Sumber konten dan daftar rute ekspor |
| `app/Http/Controllers/`, `routes/web.php` | Rendering halaman/detail dan shell Pengurus |
| `artisan`, `bootstrap/`, `config/`, `composer.*` | Runtime/build Laravel dan dependency terkunci |
| `package*.json`, `vite.config.js` | Dependency JS terkunci dan build |
| `preview/` | HTML/aset/font/media statis siap disajikan Vercel; hasil build, bukan sumber edit |
| `scripts/`, `tests/` | Ekspor, server preview, optimasi media, pemeriksaan, paket frontend |
| `.github/workflows/frontend.yml` | Pemeriksaan CI repo ini; perlu diletakkan/adaptasi di root monorepo |
| `vercel.json` | Vercel static preview, clean URLs, noindex, redirect lama |
| `.env.example`, `.gitignore`, `.gitattributes` | Template konfigurasi, batas file Git, normalisasi LF |
| `AGENTS.md`, `README.md`, `docs/` | Instruksi agent, cara menjalankan, keputusan, Stitch, screenshot, bukti QA |

Dependencies terpasang (`vendor/`, `node_modules/`), `.env`, cache/log, database lokal, dan `artifacts/` tidak dibawa. `public/build/` dapat dibuat ulang dari source/lockfile; versi build yang diperlukan untuk hosting sudah ada di `preview/build/`. Draf IndexedDB berada dalam browser, bukan dalam repo. Foto asli Drive tetap di Drive; foto web yang dipakai website sudah ada di GitHub.

File percobaan sekali pakai dalam `artifacts/` bukan sumber aplikasi. Pemeriksaan yang perlu dijalankan ulang tersedia dalam `scripts/`. Bukti QA terpilih dipindahkan ke `docs/website/evidence/frontend-approved/`; screenshot terkurasi ada di `docs/website/screenshots/`.

## Mengambil website sekarang

Untuk checkout website tersendiri:

```powershell
git clone https://github.com/morrispes5/RT-05-TAKEDA.git web
Set-Location web
git checkout web-frontend-approved-2026-10-10
```

Checkout tag menghasilkan detached HEAD untuk membaca snapshot. Buat branch sebelum mengedit. Untuk mengikuti perubahan terbaru repo, checkout `main` lalu `git pull --ff-only origin main`.

**Clone/pull mengambil kode; pull request mengusulkan perubahan kode.** Jika `web/` hendak menjadi bagian dari satu repo monorepo, gunakan ZIP release atau Git subtree di bawah. Clone di dalam repo lain menghasilkan `.git` bertingkat dan belum otomatis menjadi satu monorepo.

## Memasukkan ke monorepo yang baru

Struktur yang diminta pemilik:

```text
takeda/
  web/       # isi paket website ini
  mobile/    # pekerjaan berikutnya
  rest-api/  # backend/API berikutnya
  docs/      # kontrak dan keputusan lintas modul berikutnya
```

Pilihan sederhana: ekstrak ZIP release ke root monorepo. ZIP sudah mempunyai awalan `web/`, sehingga tidak perlu membuat `web/web/`. Hasil ekstrak tidak membawa `.git` tersembunyi.

Pilihan dengan hubungan ke repository asal, jalankan hanya pada monorepo baru atau branch yang disiapkan untuk impor, dengan `web/` belum ada:

```powershell
git init
git commit --allow-empty -m "Mulai monorepo RT 05 Takeda"
git remote add rt05-web https://github.com/morrispes5/RT-05-TAKEDA.git
git fetch rt05-web --tags
git subtree add --prefix=web rt05-web web-frontend-approved-2026-10-10 --squash
```

Kemudian, bila memang ingin menarik perubahan web berikutnya dari repo asal:

```powershell
git subtree pull --prefix=web rt05-web main --squash
```

Subtree membuat isi `web/` menjadi file normal dalam repo induk. PR berikutnya dibuat di repo monorepo pada branch kerja. Untuk mengirim perubahan kembali ke repo web asal, agent perlu memilih alur split/branch + PR atau menetapkan monorepo sebagai sumber utama; jangan menganggap kedua repo otomatis tersinkron.

Sesudah impor, adaptasi konfigurasi oleh agent berikutnya:

1. Jalankan perintah frontend dari `web/`. CI GitHub hanya membaca `.github/workflows` di root repository, sehingga workflow di `web/.github/` harus dipindahkan/adaptasi. Gunakan working directory `web`, lockfile cache `web/package-lock.json`, dan upload `web/artifacts/`.
2. Set root directory proyek Vercel menjadi `web`, dengan output `preview` relatif terhadap root tersebut. Integrasi Vercel saat ini masih menunjuk repo web tersendiri; impor ke monorepo tidak memindahkannya otomatis.
3. URL web tetap `/profil`, `/artikel`, dan seterusnya. Folder repo `web/` bukan prefix URL `/web`.
4. Simpan environment tiap modul secara terpisah dan gunakan URL API sesuai deployment yang nanti diputuskan. Jangan mencampur IndexedDB preview dengan data server sungguhan.

## Menjalankan dan membangun

PHP 8.3+, Composer, Node.js 22.12+ atau 24+. CI saat ini memakai PHP 8.4/Node 22. Jalankan dari root website (`web/` setelah impor):

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm.cmd ci
npm.cmd run build:preview
npm.cmd run preview
```

Buka http://127.0.0.1:8123. Jalur statis juga dapat dicoba langsung dari ZIP dengan `node scripts/serve-preview.mjs` jika Node tersedia, tanpa menginstal PHP/dependency untuk merender ulang.

Untuk menjalankan Blade melalui Laravel: `npm.cmd run build`, lalu `php artisan serve` dan buka http://127.0.0.1:8000. `.env.example` sudah memakai session/cache file. **Tidak perlu migrasi; `composer setup` menjalankan migrasi dan bukan perintah instalasi frontend ini.**

## Bukti dan pemeriksaan

Baseline desain diterima telah lulus: 9 tes Laravel/155 assertions, Pint, build/ekspor 19 rute + 404, 95 kasus responsif di 360/390/768/1024/1440 px, 41 pemeriksaan Axe, navigasi/pencarian/filter/lightbox, alur editor artikel/album, simpan ulang, isolasi draf, serta slideshow otomatis. Alias live dicocokkan: 19/19 HTML dan 26 aset sesuai build, browser HP lulus, tanpa error browser.

Sumber bukti baseline: `docs/website/evidence/frontend-approved/local-qa.json`, `live-web.json`, dan `live-slideshow.json`. File ini adalah snapshot pemeriksaan commit desain `0658d5d`, bukan janji bahwa deployment berikutnya akan tetap sama. CI release/handoff dan hasil pengecekan arsip dilampirkan pada release.

```powershell
php artisan test
php vendor/bin/pint --test
npm.cmd run build:preview
npx.cmd playwright install chromium
npm.cmd run test:preview
node scripts/check-live.mjs https://rt05takeda.vercel.app
```

`preview/` harus sesuai sumber. Workflow memeriksa diff hasil ekspor. `check-live` membutuhkan akses ke alias HTTPS yang menyajikan versi yang sama; build lokal saja tidak membuktikan deploy. Detail keterbatasan browser/perangkat terdapat di `QA_STITCH_PREVIEW.md`.

Untuk membuat ulang paket dari checkout Git yang bersih:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/package-frontend.ps1
```

Script mengarsipkan commit `HEAD`, memeriksa seluruh file arsip terhadap blob Git, dan menghasilkan ZIP, manifest JSON, serta checksum SHA-256 di `artifacts/releases/`. Tidak menyalin isi workspace secara sembarang. Checksum ZIP dapat dibandingkan dengan `Get-FileHash -Algorithm SHA256 <nama-zip>`.

## Dokumen lanjutan

- `CONTENT_PREVIEW.md`: perilaku editor, struktur konten, dan titik adapter server.
- `STITCH_SCREENS.md` / `stitch-screens.json`: 32 referensi layar Google Stitch.
- `DESIGN_FINETUNE_KIMI.md`: fondasi dan riwayat desain Kimi.
- `QA_STITCH_PREVIEW.md`: pemeriksaan dan keterbatasan QA.
- `FRONTEND_REFACTOR.md` / `QA_FRONTEND.md`: audit/hasil awal yang bersifat historis, bukan cakupan Pengurus terbaru.

Agent berikutnya dapat memulai diskusi mobile/API dari handoff ini sambil mempertahankan website yang sudah diterima. Draf pengguna dalam browser perlu proses ekspor/migrasi terpisah bila nantinya ingin menjadi konten server; clone atau ZIP tidak menyalinnya.
