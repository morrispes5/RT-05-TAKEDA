# Refactor frontend RT 05 Takeda

## Batas dan keputusan
- Arahan pemilik, 9 Oktober 2026: refactor aplikasi yang sama, bukan produk/branding V2.
- Stack dipertahankan: Laravel 13, Blade, Tailwind 4, Vite; Vercel tetap ekspor statis.
- Ketua RT 05: **Agus Ferdiansyah**, dikonfirmasi langsung pemilik pada chat ini.
- Sekretaris dan bendahara anonim: **Nama belum dipublikasikan**.
- Website publik tanpa akun. Layanan personal warga merupakan rencana aplikasi mobile.
- Area /pengurus hanya shell publik, tanpa data pribadi, CRUD, autentikasi palsu, database, token, atau transaksi.
- Iuran tetap offline; rancangan Rp75.000/rumah/bulan, tarif berlaku per periode.

## Audit awal
Baseline main: 8ad7915. Tidak ada PR terbuka saat penarikan repo. Branch kerja codex/frontend-refactor.
| Berkas | Kondisi awal | Perubahan |
| --- | --- | --- |
| routes/web.php | Hanya GET / | Halaman publik, detail artikel, shell pengurus |
| home.blade.php | Hero kiri, halaman tunggal | Hero tengah, kolase asli, alur editorial |
| app.css | Token dan komponen landing awal | Sistem visual bersama + layout publik/pengurus |
| app.js | Menu dan lightbox | Keyboard Escape, pengembalian fokus, filter |
| dokumentasi.json | 11 foto dengan varian WebP | Tetap sumber metadata dan alt |
| scripts/build-preview.sh | Curl satu halaman melalui server | Ekspor semua rute lewat kernel Laravel |
| vercel.json | Static preview, noindex, cleanUrls | Arsitektur dipertahankan |
| tests | Tes halaman dasar | Rute, batas read-only, foto, artikel, profil; browser QA |

## Arah desain: Halaman Bersama
Desain berangkat dari lingkungan yang benar-benar terlihat dalam dokumentasi: gapura, bangunan sekretariat berwarna biru dengan rangka kuning, lapangan, dan pepohonan di taman bermain. Foto gapura menjadi pusat kolase, diapit sudut taman dan sekretariat dengan ukuran serta potongan berbeda. Komposisi ini memperkenalkan tempat terlebih dahulu, sebelum fitur digitalnya. Judul berada di tengah dan memakai Bricolage Grotesque; Public Sans menjaga paragraf dan navigasi mudah dibaca. Latar putih serta biru pucat memberi ruang bernapas, sedangkan kuning muncul secukupnya pada penanda foto. Konten bergerak dari pengenalan lingkungan ke ruang bersama, lalu bacaan warga. Fasilitas memakai foto besar dan daftar editorial, sehingga halaman tidak menjadi tumpukan kartu yang sama. Galeri menyediakan filter subjek dan pembesaran foto. Artikel memakai ilustrasi SVG original, terpisah jelas dari dokumentasi lapangan. Area pengurus memakai token serupa, tetapi lebih padat, dengan navigasi modul, tahapan kerja, struktur tabel, serta status pratinjau yang jelas. Tidak ada angka statistik rekaan.

Token: navy #123e65, ink #183b50, sky #eef5fa, white #ffffff, yellow #f2cd5a, muted #536a79.

Wireframe:
- Navigasi logo | profil | fasilitas | dokumentasi | artikel | kontak
- Identitas lokasi, H1 tengah dua baris, pengantar, tautan profil
- Taman kecil | gapura besar + caption | sekretariat kecil
- Pengantar lingkungan dua kolom
- Foto lapangan besar | daftar fasilitas berfoto
- Tiga bacaan dengan ilustrasi SVG
- Penjelasan rencana aplikasi warga
- Footer sitemap + pintu Area Pengurus

## Sumber dan rekonsiliasi
- Brief lokal: RT05_TAKEDA_FRONTEND_REFACTOR_CODEX_V2.md, dibaca penuh.
- Skill repo .claude/skills/frontend-design/SKILL.md dibaca sebagai acuan kritik desain.
- Tugas 04 dan Logbook 4 dibaca melalui konektor Google Drive pada 9 Oktober 2026. Keduanya membahas perencanaan, bukan bukti fitur operasional. Logbook 4 menegaskan website publik tanpa login, mobile warga/pengurus, iuran offline tanpa gateway. Tidak ada dokumen Drive pribadi yang disalin ke repo.
- Foto dari katalog repo yang sudah dioptimasi dan dibuang metadata EXIF/GPS-nya. Tidak menambahkan foto Drive mentah, wajah baru, foto stok, atau gambar AI.
- Artikel cuci tangan merujuk panduan CDC: https://www.cdc.gov/clean-hands/about/index.html (diperiksa 9 Oktober 2026). Artikel lainnya merupakan bacaan editorial, bukan berita kejadian atau aturan RT baru.

## Cakupan
6 halaman publik, 5 detail artikel, 10 halaman pengurus (ringkasan + 9 modul): total 21 rute statis.
Filter galeri/artikel dan lightbox bekerja. Semua tindakan operasional pengurus dinonaktifkan.
Backend layanan, autentikasi/RBAC, manajemen data, publikasi konten, pengiriman notifikasi, riwayat pembayaran, dan aplikasi mobile tetap tahap berikutnya.

## Verifikasi
Hasil final dan bukti deployment dicatat di QA_FRONTEND.md.
