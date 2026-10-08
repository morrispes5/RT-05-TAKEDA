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
| `resources/views/components/ikon.blade.php` | Komponen `<x-ikon>`: ikon garis gaya Lucide |
| `resources/views/layouts`, `resources/views/partials` | Layout, navbar, footer |
| `resources/css/app.css` | Token desain (warna, font) dan komponen kecil |
| `resources/js/app.js` | Menu mobile dan lightbox foto |
| `resources/data/dokumentasi.json` | Data foto: alt text, keterangan, ukuran |
| `app/Http/Controllers/HomeController.php` | Membaca data foto untuk Beranda |

## Desain

Design system resmi ada di artifact Claude Design **RT 05 Takeda Design System**
(<https://claude.ai/artifact/UxTQtV5HPZbyFHkVciKYN9>): token warna terang/gelap, tipografi, spasi,
radius, bayangan, logo gapura, dan komponen `rt-` beserta aturannya. Ringkasnya:

- Biru muda (`surface-sky`, `sky-100`, `sky-200`) mengisi 20 sampai 70 persen elemen setiap layar.
- `brand` (`#0B3F8C`, biru cat sekretariat) untuk tombol dan tautan; `kuning` (pipa pos) satu aksen per layar.
- Judul Bricolage Grotesque, teks Public Sans, keduanya di-host sendiri lewat `@fontsource`.
- Kelas komponen (`rt-btn`, `rt-badge`, `rt-label`, `rt-service`, `rt-float`, `rt-photo`) ada di
  `resources/css/app.css` dan sama dengan di design system. Ikon garis lewat `<x-ikon nama="...">`.
- Logo: `public/images/logo/rt05-mark.svg` (latar terang) dan `rt05-mark-terang.svg` (latar gelap).

Skill `frontend-design` dari [anthropics/skills](https://github.com/anthropics/skills) (Apache-2.0)
disimpan di `.claude/skills/frontend-design/` sebagai aturan desain repo.

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
