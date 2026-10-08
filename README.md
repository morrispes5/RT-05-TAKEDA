# RT-05-TAKEDA
ini website RT 05 landingpage

## Preview statis untuk Vercel

Vercel tidak menjalankan PHP/Laravel secara native, jadi folder `preview/` berisi hasil render
statis halaman Beranda (HTML + CSS + JS) yang dilayani lewat `vercel.json`. Ini hanya untuk preview
internal kelompok dan header `noindex` dipasang agar tidak diindeks mesin pencari. Folder ini
dibuat dari build Laravel, jadi **bukan sumber kebenaran**: ubah `resources/views` lalu render ulang.
Saat deploy sungguhan (VPS + Docker sesuai rancangan), folder ini dan `vercel.json` bisa dihapus.
