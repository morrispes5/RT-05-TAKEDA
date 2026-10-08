# RT 05 Takeda — website publik

Website publik RT 05 RW 07 Taman Kedaung (Takeda), Ciputat. Bagian dari Capstone Project PG194
Kelompok 8 "Layanan Pintar": website untuk informasi umum, aplikasi mobile untuk warga, dan dashboard
untuk pengurus. Repo ini saat ini berisi halaman Beranda.

Stack: Laravel 13 (Blade) + Tailwind CSS 4, sesuai Dokumen Perancangan Solusi.

## Menjalankan di lokal

Butuh PHP 8.3+, Composer, dan Node.js 20+.

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
npm run build        # atau `npm run dev` saat mengembangkan
php artisan serve    # buka http://127.0.0.1:8000
```

## Struktur penting

| Path | Isi |
|---|---|
| `resources/views/home.blade.php` | Halaman Beranda |
| `resources/views/components/foto.blade.php` | Komponen `<x-foto>`: gambar responsif (`srcset`, lazy load) |
| `resources/views/layouts`, `resources/views/partials` | Layout, navbar, footer |
| `resources/css/app.css` | Token desain (warna, font) dan komponen kecil |
| `resources/js/app.js` | Menu mobile dan lightbox foto |
| `resources/data/dokumentasi.json` | Data foto: alt text, keterangan, ukuran |
| `app/Http/Controllers/HomeController.php` | Membaca data foto untuk Beranda |

## Desain

Arah visual "civic modern" yang diambil dari lingkungan RT 05 sendiri: biru cat sekretariat
(`#0B3F8C`), kuning pipa pos (`#F2C21A`), dan abu beton lapangan. Font Schibsted Grotesk (judul) dan
Public Sans (teks), di-host sendiri lewat `@fontsource`.

Aturan desain mengikuti skill `frontend-design` dari [anthropics/skills](https://github.com/anthropics/skills)
(Apache-2.0) yang disimpan di `.claude/skills/frontend-design/`. Claude Code memakainya otomatis saat
mengubah tampilan.

## Foto dokumentasi

Foto asli diambil Kelompok 8 pada Oktober 2026 (Drive: `DOKUMENTASI RT 05`). Yang di-commit hanya
versi web di `public/images/dokumentasi/` (WebP 480/960/1600 px, metadata EXIF/GPS dibuang). Untuk
menambah atau mengganti foto:

1. Taruh foto asli di `storage/app/dokumentasi-src/` (folder ini tidak ikut git).
2. Tambahkan entrinya di `resources/data/dokumentasi.json` (nama berkas, alt text, keterangan).
3. Jalankan `node scripts/optimize-images.mjs`.

Sebelum memasang foto yang menampilkan wajah warga, terutama anak-anak, minta izin pengurus RT.

## Preview di Vercel

Vercel tidak menjalankan PHP, jadi `preview/` berisi hasil render statis Beranda yang dilayani lewat
`vercel.json` (dengan header `noindex`). Folder ini **bukan sumber kebenaran**: ubah
`resources/views`, lalu buat ulang dengan:

```bash
scripts/build-preview.sh
```

Saat deploy sungguhan (VPS + Docker sesuai rancangan), `preview/` dan `vercel.json` bisa dihapus.
