# QA penyempurnaan desain dan editor konten

Tanggal: 10 Oktober 2026, Asia/Jakarta. Sumber: Blade, Tailwind, JavaScript pada branch `codex/stitch-content-preview`, dari desain Kimi `c1bbac3`.

## Pemeriksaan lokal

| Pemeriksaan | Bukti |
| --- | --- |
| Laravel | 9 tes, 155 assertions, PASS |
| Pint | PASS |
| Build dan ekspor | 19 rute + 404; tanpa database |
| Responsivitas | 95 kasus: 19 rute × 360/390/768/1024/1440 px |
| Axe | 41 kasus WCAG 2 A/AA dan 2.1 AA; termasuk editor berisi draf dan pratinjau |
| Navigasi | 33 target internal/anchor; menu HP, Escape, pengembalian fokus |
| Artikel publik | Pencarian judul, kombinasi filter kategori, hasil kosong |
| Dokumentasi | Tab Kegiatan/Lingkungan, keyboard panah/Home, filter foto, dialog perbesar, berikutnya/Escape |
| Motion dan zoom | Reduced motion; reflow 720 CSS px setara jendela 1440 px pada zoom 200% |
| Slideshow Beranda | 4 foto; batas 2.999/3.000 ms, putaran kembali, caption, tanpa tombol Jeda/Putar, pilihan foto diikuti autoplay, autoplay saat keyboard/hover, di luar viewport, reduced motion awal/perubahan preferensi, fallback tanpa JavaScript |
| Artikel Pengurus | Validasi, tambah/urut/hapus bagian, sampul dan sumber, simpan, muat ulang, pratinjau |
| Album Pengurus | Validasi album kosong/jenis berkas, unggah dua foto, caption/alt, sampul, urut, muat ulang, pratinjau/dialog |
| Kesalahan simpan | Simulasi QuotaExceededError; isian utuh, peringatan sebelum keluar, dan simpan ulang berhasil |
| Keamanan teks | Teks menyerupai HTML tetap berupa teks, tidak menjadi elemen DOM |
| Isolasi | Browser lain tidak menemukan draf; daftar artikel publik tidak memuat draf |
| Jaringan | 0 permintaan tulis ketika mencoba alur editor/foto; halaman akses tidak menerima kredensial |
| Reset/hapus | Draf dan foto lokal dapat dihapus; contoh publik tetap tersedia |

`scripts/check-preview.mjs` menyimpan laporan di `artifacts/qa-report.json` dan 95 screenshot halaman/viewport di `artifacts/screenshots/`. Editor berisi data dan hasil draf mempunyai screenshot tambahan. Foto dipaksa selesai decode sebelum screenshot; ini menghindari kotak kosong dari decoding gambar di luar viewport.

Penyempurnaan slideshow pada `codex/hero-photo-slideshow`, 10 Oktober 2026: seluruh 95 kasus responsif, 41 pemeriksaan Axe, 9 tes Laravel/155 assertions, Pint, dan alur editor tetap lulus. `scripts/check-hero-slideshow.mjs` menguji interval dan interaksi slideshow dengan jam browser terkontrol; `heroSlideshow` dalam laporan mencatat hasilnya. Crop dan kontrol keempat foto diperiksa pada 390 dan 1440 px melalui `artifacts/hero-slideshow-contact.png`. Screenshot beranda yang diperbarui di `docs/website/screenshots/` memakai reduced motion sehingga foto pertama tetap stabil.

Penyesuaian `codex/hero-autoplay-only`: tombol Jeda/Putar beserta CSS dan handler-nya dihapus. Uji slideshow diperbarui untuk memastikan autoplay berlanjut ketika hover, fokus keyboard, dan setelah pilihan manual. Screenshot beranda diperbarui tanpa tombol jeda; reduced motion dan fallback tanpa JavaScript tetap diperiksa.

Screenshot diperiksa untuk crop foto, keterbacaan, jarak, dekorasi, dan konsistensi. Sampel terkurasi tersedia dalam `docs/website/screenshots/`; seluruh bukti browser diunggah CI sebagai `frontend-evidence`. Pemeriksaan otomatis memakai Chromium; perangkat fisik, Safari/Firefox, screen reader, serta zoom melalui kontrol browser secara manual belum diverifikasi. Reflow diuji melalui ukuran viewport ekuivalen, bukan properti CSS `zoom`.

## Konten dan batas

Foto album berasal dari Drive milik pengguna, diperiksa visual sebelum katalog publik, lalu dikonversi menjadi WebP tanpa EXIF. Album Perayaan 17 Agustus tidak menampilkan tahun. Identitas Agus Ferdiansyah dipertahankan; sekretaris/bendahara anonim. Kontak dan jam pelayanan tidak dikarang dari referensi Stitch.

Pratinjau bukan layanan autentikasi atau publikasi online. Browser menyimpan draf di IndexedDB pada origin masing-masing. Menghapus data situs akan menghapus draf. Backend Laravel/VPS, database, dan migrasi belum diaktifkan pada tahap ini.

## Rilis

- PR: [#5 — Pertajam desain Takeda dan editor artikel/dokumentasi](https://github.com/morrispes5/RT-05-TAKEDA/pull/5).
- Pemeriksaan CI: [Frontend verification](https://github.com/morrispes5/RT-05-TAKEDA/actions/workflows/frontend.yml).
- Alias hasil integrasi Git Vercel: [rt05takeda.vercel.app](https://rt05takeda.vercel.app).

PR dan status deploy dicatat setelah pemeriksaan CI. `scripts/check-live.mjs` membandingkan seluruh HTML dan aset pada alias Vercel dengan ekspor lokal, kemudian melakukan smoke browser HP. Hasil live disimpan di `artifacts/live-report.json`; build lokal saja tidak membuktikan alias sudah diperbarui.
