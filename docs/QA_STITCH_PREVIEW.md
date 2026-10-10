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
| Artikel Pengurus | Validasi, tambah/urut/hapus bagian, sampul dan sumber, simpan, muat ulang, pratinjau |
| Album Pengurus | Validasi album kosong/jenis berkas, unggah dua foto, caption/alt, sampul, urut, muat ulang, pratinjau/dialog |
| Kesalahan simpan | Simulasi QuotaExceededError; isian utuh, peringatan sebelum keluar, dan simpan ulang berhasil |
| Keamanan teks | Teks menyerupai HTML tetap berupa teks, tidak menjadi elemen DOM |
| Isolasi | Browser lain tidak menemukan draf; daftar artikel publik tidak memuat draf |
| Jaringan | 0 permintaan tulis ketika mencoba alur editor/foto; halaman akses tidak menerima kredensial |
| Reset/hapus | Draf dan foto lokal dapat dihapus; contoh publik tetap tersedia |

`scripts/check-preview.mjs` menyimpan laporan di `artifacts/qa-report.json` dan 95 screenshot halaman/viewport di `artifacts/screenshots/`. Editor berisi data dan hasil draf mempunyai screenshot tambahan. Foto dipaksa selesai decode sebelum screenshot; ini menghindari kotak kosong dari decoding gambar di luar viewport.

Screenshot diperiksa untuk crop foto, keterbacaan, jarak, dekorasi, dan konsistensi. Sampel terkurasi tersedia dalam `docs/screenshots/`; seluruh bukti browser diunggah CI sebagai `frontend-evidence`. Pemeriksaan otomatis memakai Chromium; perangkat fisik, Safari/Firefox, screen reader, serta zoom melalui kontrol browser secara manual belum diverifikasi. Reflow diuji melalui ukuran viewport ekuivalen, bukan properti CSS `zoom`.

## Konten dan batas

Foto album berasal dari Drive milik pengguna, diperiksa visual sebelum katalog publik, lalu dikonversi menjadi WebP tanpa EXIF. Album Perayaan 17 Agustus tidak menampilkan tahun. Identitas Agus Ferdiansyah dipertahankan; sekretaris/bendahara anonim. Kontak dan jam pelayanan tidak dikarang dari referensi Stitch.

Pratinjau bukan layanan autentikasi atau publikasi online. Browser menyimpan draf di IndexedDB pada origin masing-masing. Menghapus data situs akan menghapus draf. Backend Laravel/VPS, database, dan migrasi belum diaktifkan pada tahap ini.

## Rilis

PR dan status deploy dicatat setelah pemeriksaan CI. `scripts/check-live.mjs` membandingkan seluruh HTML dan aset pada alias Vercel dengan ekspor lokal, kemudian melakukan smoke browser HP. Hasil live disimpan di `artifacts/live-report.json`; build lokal saja tidak membuktikan alias sudah diperbarui.
