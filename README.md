# RT 05 Takeda — website publik

Website informasi RT 05 RW 07 Taman Kedaung, Ciputat. Laravel 13 + Blade + Tailwind CSS 4 + Vite.
Website publik tidak memerlukan akun. Area pengurus saat ini **pratinjau antarmuka**, bukan sistem operasional.

## Menjalankan secara lokal
PHP 8.3+, Composer, Node.js 22.12+ (atau 24+).

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm.cmd ci
npm.cmd run build
php artisan serve
```

Buka http://127.0.0.1:8000. Tidak perlu migrasi database untuk website publik dan shell ini.
Gunakan SESSION_DRIVER=file dan CACHE_STORE=file untuk penggunaan lokal (sudah di .env.example).
Jangan jalankan composer setup untuk sekadar frontend: script bawaan tersebut juga menjalankan migrasi.

## Halaman
- / — Beranda
- /profil — lingkungan dan pengurus; Agus Ferdiansyah sebagai ketua, sekretaris/bendahara anonim
- /fasilitas — sarana dengan foto asli
- /dokumentasi — Kegiatan dan Lingkungan; album serta 11 foto lingkungan
- /dokumentasi/perayaan-17-agustus — cerita album, foto asli, keterangan, dan lightbox
- /artikel + /artikel/{slug} — 5 bacaan edukasi dengan SVG original dan waktu baca terhitung
- /kontak — informasi wilayah dan status kontak yang belum diumumkan
- /pengurus — Ringkasan untuk Artikel dan Dokumentasi, dengan reset draf lokal
- /pengurus/artikel dan /pengurus/artikel/editor — daftar dan editor artikel
- /pengurus/dokumentasi dan /pengurus/dokumentasi/editor — daftar dan editor album
- /pengurus/pratinjau — hasil draf lokal seperti halaman publik
- /pengurus/masuk — penjelasan akses tanpa menerima kredensial

Editor dapat dicoba dengan data contoh dan foto perangkat. Draf dan foto tersimpan di IndexedDB
browser ini, tanpa dikirim ke server. Konten publik tetap berasal dari katalog repo.
Pendataan warga, pengaduan, aspirasi, agenda, iuran, keuangan, dan token registrasi milik aplikasi
mobile. Publikasi online dan login operasional menjadi tahap Laravel di VPS.

## Preview Vercel
Vercel menyajikan direktori statis preview/ yang di-commit. **Vercel tidak menjalankan Laravel/PHP**.
Sumber perubahan tetap Blade, CSS, JS, dan data; jangan edit HTML hasil ekspor secara manual.

```powershell
npm.cmd run build:preview
npm.cmd run preview
```

Preview lokal: http://127.0.0.1:8123. Ekspor mengunjungi semua rute melalui kernel Laravel tanpa server
background dan tanpa database. Output 19 halaman + 404, aset build, dan foto. Setiap URL menggunakan
cleanUrls pada Vercel, sehingga artikel, album, dan editor dapat dibuka langsung.
scripts/build-preview.sh adalah wrapper untuk lingkungan Bash.

Workflow GitHub memeriksa bahwa preview/ sesuai sumber. Push PR memicu deployment preview melalui integrasi
Vercel yang sudah ada; merge main memicu redeploy alias branch utama sesuai konfigurasi proyek Vercel.

## Pemeriksaan
```powershell
php artisan test
php vendor/bin/pint --test
npm.cmd run build:preview
npx.cmd playwright install chromium
npm.cmd run test:preview
npm.cmd audit
```

Tes browser memeriksa seluruh rute pada 360, 390, 768, 1024, dan 1440 px, overflow, gambar,
tautan internal/anchor, error browser, filter, lightbox, fokus, menu mobile, dan reduced motion.
Axe memeriksa WCAG A/AA di 390 dan 1440 px. Screenshot dan JSON disimpan di artifacts/ (diabaikan Git).
Pemeriksaan otomatis tidak menggantikan penilaian manusia atas aksesibilitas atau persetujuan konten mitra.

## Struktur
| Lokasi | Isi |
| --- | --- |
| app/Support/SiteContent.php | Katalog konten dan rute ekspor |
| app/Http/Controllers/PublicPageController.php | Halaman publik dan pratinjau pengurus |
| resources/views | Layout, halaman, komponen foto/ikon/SVG artikel |
| resources/data/dokumentasi.json | Manifest foto asli, dimensi, alt, caption |
| resources/data/artikel.json | Bacaan edukasi; tidak mengaku sebagai berita RT |
| resources/data/album.json | Album, sampul, foto, keterangan, dan sumber |
| resources/css/app.css | Token dan layout responsif publik/pengurus |
| resources/js/app.js | Menu, filter, dialog foto |
| resources/js/preview-content.js | Repository asinkron konten dan foto lokal |
| resources/js/cms-preview.js | Editor dan hasil pratinjau |
| scripts/export-preview.php | Ekspor semua rute dengan lingkungan tanpa database |
| scripts/check-preview.mjs | QA browser dan aksesibilitas |
| docs/FRONTEND_REFACTOR.md | Audit, keputusan produk, sumber, arah desain |
| docs/CONTENT_PREVIEW.md | Perilaku editor dan arah adapter Laravel |
| docs/STITCH_SCREENS.md | Inventaris layar Google Stitch |
| docs/QA_STITCH_PREVIEW.md | Bukti pemeriksaan terbaru |

## Media dan konten
Hanya foto asli dalam katalog repo yang digunakan. WebP memiliki varian 480/960/1600 sesuai manifest;
foto papan peraturan maksimal 960 px; album 17 Agustus memiliki 480/960/1280 px.
Foto asli tidak di-commit; versi web telah dibuang EXIF/GPS.
Jangan menambah foto wajah warga/anak tanpa izin. Untuk aset baru gunakan scripts/optimize-images.mjs
dan perbarui manifest. Ikon dan ilustrasi artikel adalah SVG; tidak ada gambar raster AI atau foto stok.

Arahan visual **Halaman Bersama** mempertahankan Bricolage Grotesque, Public Sans, dan logo gapura repo,
dengan hero dua kolom Kimi, garis kuning, dan foto gapura berbentuk lengkung. Font di-host sendiri.
Gerakan dekoratif berulang dihentikan dan headline HP memakai warna solid. Referensi Stitch
mempertajam hierarki konten, album editorial, dan editor dengan design system yang sama.
