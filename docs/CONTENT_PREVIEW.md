# Artikel dan dokumentasi — pratinjau frontend

Website publik tetap memakai Laravel Blade, Tailwind CSS, dan hasil ekspor statis untuk Vercel. Area Pengurus web hanya menyediakan artikel dan album dokumentasi. Fitur warga, agenda, pengaduan, aspirasi, iuran, keuangan, dan token registrasi tetap berada pada aplikasi mobile.

Editor menggunakan `PreviewContentRepository` dengan IndexedDB di origin browser. Draf dan foto tidak dikirim ke server atau mengubah katalog publik. Tombol Lihat pratinjau memeriksa isian dan membuka hasil lokal. Login operasional, database server, serta publikasi langsung akan diaktifkan di Laravel pada VPS sesudah desain diterima.

Album Perayaan 17 Agustus memakai foto asli dari folder DOKUMENTASI RT 05, berkas Foto dari Mrz (`17le7M4kbhPQOZqReW_ay9LnewF-dsiRl`). Foto telah diperiksa secara visual. Versi WebP 480/960/1280 dibuat tanpa metadata EXIF; berkas asli tidak masuk Git. Tahun tidak ditampilkan sesuai arahan pemilik.

Ketua RT: Agus Ferdiansyah. Sekretaris dan bendahara tetap anonim. Informasi kontak tidak ditambah tanpa sumber.

## Mencoba editor

1. Buka `/pengurus`, lalu pilih Artikel atau Dokumentasi.
2. Edit contoh yang tersedia atau mulai konten baru. Artikel memakai judul, kategori, ringkasan, pembuka, bagian isi, sampul opsional, dan sumber bacaan.
3. Tambah atau pindahkan bagian/foto memakai tombol Naik dan Turun. Album menerima maksimal 20 foto JPG/PNG/WebP, masing-masing 12 MB. Foto diproses lokal menjadi WebP maksimal 1600 px dan tanpa metadata sumber.
4. Draf tersimpan otomatis setelah perubahan; tombol Simpan draf tersedia untuk mencoba lagi ketika penyimpanan gagal. Isian yang belum lengkap boleh disimpan sebagai draf.
5. Lihat pratinjau memeriksa kolom wajib dan membuka konfirmasi. Hasilnya hanya tersedia pada browser dan origin yang sama; tidak muncul di daftar publik.
6. Reset pratinjau di Ringkasan membersihkan semua draf dan foto percobaan. Foto perangkat asal dan katalog publik tidak dihapus.

Kolom file tidak mengunggah berkas lewat jaringan. Halaman akses `/pengurus/masuk` tidak memiliki kolom email atau kata sandi. Tidak ada POST/API publikasi pada tahap ini. Data di browser bukan cadangan permanen: membersihkan data situs atau berganti browser/origin membuat draf tidak tersedia.

## Pemisahan akses konten

`SiteContent` membaca artikel, album, dan manifest foto publik dari repo. Format artikel `title/category/summary/intro/sections/source/art` tetap dipakai; sampul opsional menambahkan `cover`. Album memakai `id/slug/title/description/date/coverId/photos`, dengan `date` boleh kosong dan setiap foto memiliki ID, alt, caption, dan dimensi.

UI Pengurus bergantung pada metode asinkron `list/get/save/archive/media/reset` dari `PreviewContentRepository`. Adapter sekarang memakai tiga object store IndexedDB (`articles`, `albums`, `media`), menyatukan data contoh dan draf, serta menyimpan konten/media dalam transaksi yang sama. Foto yang tidak lagi dirujuk dibuang saat menyimpan. Tombstone menyembunyikan contoh hanya pada browser tersebut.

Pada tahap VPS, repository dapat diganti adapter HTTP Laravel dengan autentikasi, otorisasi, penyimpanan berkas, dan tindakan publikasi yang sebenarnya. Perubahan tahap ini tidak memerlukan migrasi database, kredensial server, atau deployment VPS.

## Arah visual

Urutan beranda: hero → akses cepat → lingkungan → fasilitas → album → bacaan → kontak → footer. Jumlah artboard Stitch tidak menjadi jumlah section beranda. Garis kuning dan foto gapura lengkung Kimi dipertahankan; outline menjadi aksen desktop dan headline HP solid. Badge berhenti berputar, marquee disingkirkan dari tampilan, dan reduced motion menonaktifkan animasi pembuka/parallax.

Penyesuaian Beranda, 10 Oktober 2026: foto kecil taman bermain yang menumpuk di depan foto gapura beserta caption-nya dihapus sesuai screenshot arahan pemilik. Gaya posisi dan ukuran overlay yang sudah tidak dipakai juga dibersihkan.

Navy `#123e65`, kuning `#f2cd5a`, sky `#eef5fa`, Bricolage Grotesque, Public Sans, serta logo gapura menjadi fondasi seluruh halaman. Artikel mempunyai pencarian judul, filter kategori, dan isi baca 18 px. Dokumentasi memakai tab Kegiatan/Lingkungan, album editorial, caption, dan dialog perbesar dengan pengembalian fokus.
