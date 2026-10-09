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
- /dokumentasi — 11 foto, filter subjek, dan lightbox
- /artikel + /artikel/{slug} — 5 bacaan edukasi dengan SVG original dan waktu baca terhitung
- /kontak — informasi wilayah dan status kontak yang belum diumumkan
- /pengurus — ringkasan; warga, pengaduan, aspirasi, agenda, pengumuman, iuran, keuangan, konten, pengaturan

Pengurus: tombol operasional benar-benar disabled; tidak ada formulir, autentikasi palsu, catatan warga,
angka saldo atau status pembayaran yang direkayasa. Pembayaran iuran direncanakan offline.

## Preview Vercel
Vercel menyajikan direktori statis preview/ yang di-commit. **Vercel tidak menjalankan Laravel/PHP**.
Sumber perubahan tetap Blade, CSS, JS, dan data; jangan edit HTML hasil ekspor secara manual.

```powershell
npm.cmd run build:preview
npm.cmd run preview
```

Preview lokal: http://127.0.0.1:8123. Ekspor mengunjungi semua rute melalui kernel Laravel tanpa server
background dan tanpa database. Output 21 halaman + 404, aset build, dan foto. Setiap URL menggunakan
cleanUrls pada Vercel, sehingga artikel dan modul pengurus dapat dibuka langsung.
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
| app/Support/SiteContent.php | Katalog konten, modul, dan rute ekspor |
| app/Http/Controllers/PublicPageController.php | Render halaman publik dan shell |
| resources/views | Layout, halaman, komponen foto/ikon/SVG artikel |
| resources/data/dokumentasi.json | Manifest foto asli, dimensi, alt, caption |
| resources/data/artikel.json | Bacaan edukasi; tidak mengaku sebagai berita RT |
| resources/css/app.css | Token dan layout responsif publik/pengurus |
| resources/js/app.js | Menu, filter, dialog foto |
| scripts/export-preview.php | Ekspor semua rute dengan lingkungan tanpa database |
| scripts/check-preview.mjs | QA browser dan aksesibilitas |
| docs/FRONTEND_REFACTOR.md | Audit, keputusan produk, sumber, arah desain |
| docs/QA_FRONTEND.md | Bukti pemeriksaan dan batas yang masih berlaku |

## Media dan konten
Hanya foto asli dalam katalog repo yang digunakan. WebP memiliki varian 480/960/1600 sesuai manifest;
foto papan peraturan memiliki maksimal 960 px. Foto asli tidak di-commit; versi web telah dibuang EXIF/GPS.
Jangan menambah foto wajah warga/anak tanpa izin. Untuk aset baru gunakan scripts/optimize-images.mjs
dan perbarui manifest. Ikon dan ilustrasi artikel adalah SVG; tidak ada gambar raster AI atau foto stok.

Arahan visual **Halaman Bersama** mempertahankan Bricolage Grotesque, Public Sans, dan logo gapura repo,
dengan hero tengah dan kolase lingkungan. Font di-host sendiri. Lihat dokumentasi refactor untuk detail.
