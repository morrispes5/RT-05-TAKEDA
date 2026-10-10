# Frontend website: baseline dan integrasi

## Baseline terinspeksi

Repository: https://github.com/morrispes5/RT-05-TAKEDA.
Snapshot paket menyebut 34e9b212a3948a52e5f705a561691fac10103ae9 sebagai "Git tree". Inspeksi M01 (10 Oktober 2026) membuktikan nilai itu adalah **commit** HEAD `main` (PR #9, ditunjuk tag release); Git tree commit tersebut adalah 37510826bfedb447ea572f0b72cd5577f7bd0270.
Commit desain diterima menurut handoff: 0658d5d4351eb6d1409baab5835cdc5e137533db.
Release baseline: web-frontend-approved-2026-10-10.

Saat M01, HEAD `main` = 34e9b21 tanpa perubahan sesudah tag; branch remote lain (`claude/hopeful-ptolemy-acq64b`) tidak memuat commit tambahan. Sejak M01 source website berada di `website/` dan dokumentasi frontend di `docs/website/`.

Website menggunakan Laravel 13, Blade, Tailwind 4, Vite, JS, JSON katalog, dan foto WebP. Preview Vercel berupa ekspor statis 19 halaman + 404. Itu bukti frontend; bukan runtime PHP atau bisnis production.

## Rute yang dipertahankan

/, /profil, /fasilitas, /dokumentasi, /dokumentasi/{slug}, /artikel, /artikel/{slug}, /kontak, /pengurus, /pengurus/masuk, /pengurus/artikel, /pengurus/artikel/editor, /pengurus/dokumentasi, /pengurus/dokumentasi/editor, /pengurus/pratinjau. Redirect /pengurus/konten dipertahankan.

## Desain

Navy #123e65, kuning #f2cd5a, sky #eef5fa; Bricolage Grotesque/Public Sans; logo gapura; foto asli; hero lengkung. Pertahankan layout yang diterima. Mobile aplikasi menerjemahkan token brand dengan komponen native yang nyaman, bukan mengubah website.

Agus Ferdiansyah sebagai ketua; sekretaris/bendahara belum dipublikasikan. Album Perayaan 17 Agustus tanpa tahun. Nomor kontak dan jam layanan tidak dibuat-buat.

Hero autoplay 3 detik, empat indikator, reduced motion, dan visibility behavior tercatat sebagai pilihan desain pemilik. Tidak mengubah kontrol diam-diam pada M01. Pada M15 uji aksesibilitas manual; temuan serta perubahan yang memengaruhi desain dicatat untuk keputusan pemilik. Kelulusan Axe tidak otomatis membuktikan seluruh perilaku autoplay memenuhi aksesibilitas.

## Preview sekarang

PreviewContentRepository menggunakan IndexedDB untuk articles/albums/media. save/get/list/archive/media/reset adalah interface asinkron UI. Draf hanya berada pada browser/origin tersebut; menghapus data situs dapat menghilangkannya. Clone repo tidak mengambil draf browser.

SiteContent membaca JSON artikel/album/foto. Source editor adalah resources, bukan preview HTML ekspor. Jangan mengedit preview/ manual.

## Integrasi operasional M14

1. Buat ContentRepository interface yang memisahkan demo dan production.
2. Demo: IndexedDB dengan indikator jelas; tidak memiliki tombol publish production.
3. Production: HTTP adapter ke /api/v1/admin/articles/albums/media; Sanctum cookie dan CSRF.
4. UI login pengurus membuat session API; gagal role/akun inactive ditolak.
5. SSR public SiteContent memakai published API melalui network internal, timeout singkat dan last-good public cache.
6. Tambah upload progress/error/retry, draft autosave concurrency, optimistic version/If-Match, dan publish confirmation.
7. Draft/archived media tidak tersedia dari public URL.
8. Data publik muncul pada browser kedua setelah publish/cache invalidation.
9. Import katalog JSON/foto baseline lewat command idempotent; simpan mapping ID/slug agar URL tetap.
10. Jangan otomatis memublikasikan draf IndexedDB. Bila diperlukan, export/import eksplisit dengan preview dan audit admin.

CMS web hanya artikel/dokumentasi. Pengelolaan fasilitas/profil publik oleh pengurus berada di mobile; backend mengizinkan CRUD domain yang sesuai.

## Static preview vs production

Vercel tetap berguna sebagai preview frontend selama pengembangan. Production Hostinger menjalankan web PHP sehingga publikasi tidak memerlukan commit/build ulang setiap admin mengubah artikel.

M01 tidak mematikan preview live. M14 menambahkan runtime API-backed; M16 mengalihkan akses resmi ke domain production setelah UAT. Tidak mengekspor konten privat, session, token, atau draf ke preview/.

## Pengujian

Build/export consistency baseline, route/anchor/filter/lightbox, responsive widths, browser error, keyboard, image loading, safe text rendering. Integrasi: auth/CSRF, publish antarbrowser, stale edit conflict, archive, media ownership, API unavailable fallback, dan draft tidak bocor.
