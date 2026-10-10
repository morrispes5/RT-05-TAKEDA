# PRD — RT05 TAKEDA

Versi 1.0 • 10 Oktober 2026 • Baseline eksekusi.

## 1. Overview

RT05 TAKEDA adalah sistem informasi dan layanan warga RT 05 Taman Kedaung, Ciputat. Website memperkenalkan lingkungan dan konten publik. Aplikasi mobile menjadi sarana warga dan pengurus untuk pendataan, layanan, komunikasi, iuran, serta kas. REST API menyediakan satu sumber data dan aturan bisnis.

Frontend website sudah diterima dan tersedia di repository morrispes5/RT-05-TAKEDA serta preview rt05takeda.vercel.app. Tahap lanjutan mempertahankan desain itu, membangun backend dan mobile, lalu mengoperasikan server pada VPS Hostinger. Domain milik pengguna: morriz.tech; host aplikasi yang dirancang: takeda.morriz.tech.

## 2. Masalah

Informasi RT bercampur dengan percakapan WhatsApp, data administrasi tersebar, riwayat laporan sulit ditelusuri, dokumentasi tidak terpusat, dan rekap iuran/kas memerlukan pemeriksaan manual beberapa sumber. Produk memusatkan pencatatan tanpa mengubah pembayaran iuran menjadi transaksi online.

## 3. Tujuan

- Informasi publik dapat ditemukan tanpa akun.
- Rumah, keluarga, penghuni, dan akun terhubung dengan jelas.
- Warga mengirim serta memantau pengaduan/aspirasi.
- Pengurus mengelola konten, layanan, agenda, pengumuman, dan data warga.
- Iuran offline, tunggakan, pemasukan, pengeluaran, dan saldo tercatat konsisten.
- Perubahan penting dapat ditelusuri dan data dapat diekspor.
- Mobile serta website menggunakan backend yang sama.

## 4. Pengguna

| Pengguna | Kebutuhan |
| --- | --- |
| Pengunjung publik | Profil, pengurus, fasilitas, artikel, album, kontak yang terverifikasi |
| Warga akun utama | Data rumah/keluarga, pengaduan, aspirasi, agenda, pengumuman, iuran, notifikasi |
| Warga akun tambahan | Layanan warga setelah disetujui; terhubung ke rumah/keluarga yang sama |
| Pengurus/admin | Hak setara untuk administrasi dan tindak lanjut |
| Pemelihara sistem | Deployment, backup, monitoring; bukan role aplikasi publik tambahan |

Satu aplikasi mobile menampilkan navigasi sesuai role. Jumlah developer tidak menjadi batas arsitektur.

## 5. Cakupan platform

### Website publik

Beranda, profil, struktur pengurus, fasilitas, dokumentasi/album, artikel/detail, kontak. Tanpa registrasi atau login bagi pembaca. Agenda dan pengumuman layanan warga tetap di mobile. Identitas dan angka belum tersedia harus ditandai belum diumumkan.

### Pengurus web

CMS operasional khusus Artikel dan Dokumentasi. Draf, unggah media, pratinjau, publikasi, arsip. Memerlukan autentikasi dan otorisasi backend pada tahap integrasi. Preview IndexedDB sekarang dipertahankan sebagai mode demo terpisah, tidak dianggap sumber data production.

### Mobile warga

Registrasi, login/logout, pemulihan akun, profil/keluarga, pengaduan, aspirasi, kalender agenda, pengumuman, status/histori iuran, transparansi kas/iuran, notifikasi, pengaturan privasi.

### Mobile pengurus

Ringkasan; CRUD/arsip data warga/rumah/keluarga; persetujuan akun tambahan; token registrasi; tindak lanjut pengaduan/aspirasi; agenda/pengumuman; iuran; kas; ekspor; pengelolaan konten publik dan fasilitas.

## 6. Kebutuhan fungsional

| ID | Kebutuhan dan penerimaan minimum |
| --- | --- |
| FR01 | Website mempertahankan rute/layout baseline dan terbaca di HP/desktop |
| FR02 | Konten published dapat dibaca pengunjung lain; draft/archived tidak bocor |
| FR03 | Registrasi email/password/token 6 digit valid menyimpan keluarga dan alamat dalam satu transaksi |
| FR04 | Token dapat dilihat/disalin admin, dirotasi manual, dan dirotasi mingguan oleh scheduler |
| FR05 | Akun tambahan pending hingga admin menyetujui; tidak membuat tagihan tambahan |
| FR06 | Rumah–keluarga–penghuni–akun tercatat dengan histori perpindahan |
| FR07 | Pengaduan menerima kategori, judul, deskripsi, foto, pilihan identitas privat |
| FR08 | Status pengaduan diajukan → diproses → selesai memiliki histori dan notifikasi |
| FR09 | Aspirasi terpisah dari pengaduan; kirim, tampil, tindak lanjut, histori |
| FR10 | Agenda memiliki tanggal/jam/lokasi; kalender mobile; tombol tambah ke Google Calendar |
| FR11 | Pengumuman dibuat/published/diarsipkan dan diberitahukan ke warga |
| FR12 | Tagihan Rp75.000/rumah/bulan; tarif efektif per bulan; generation idempotent |
| FR13 | Pembayaran offline dicatat admin untuk satu atau beberapa periode |
| FR14 | Belum bayar tetap merah sampai pencatatan berhasil; warna disertai teks |
| FR15 | Tunggakan, rekap lunas/belum, dan histori minimal 12 bulan tersedia |
| FR16 | Warga terdaftar melihat transparansi status rumah dan ringkasan kas, tanpa data pribadi berlebihan |
| FR17 | Kas memuat iuran yang tercatat, pemasukan lain, pengeluaran, dan pembalikan koreksi |
| FR18 | Perubahan warga/keuangan/token/konten dan tindakan admin memiliki audit |
| FR19 | Ekspor CSV sesuai hak akses; ekspor besar via worker dan unduhan privat |
| FR20 | Inbox notifikasi tersimpan; push untuk Android; penolakan izin push tidak memblokir layanan |
| FR21 | Kamera/galeri, batas file, penghapusan metadata, dan media privat diterapkan server |
| FR22 | Akun/data dapat dinonaktifkan/diarsipkan tanpa menghilangkan histori layanan/kas |
| FR23 | Sistem memiliki backup yang diuji restore, monitor, dan prosedur rollback |

## 7. Alur utama

### Registrasi utama

Warga menerima token RT → memasukkan email/password/token → data kepala keluarga, anggota, rumah, blok/jalan/nomor, WhatsApp opsional → server memeriksa token, duplikasi dan konsistensi → akun warga aktif bila pendaftaran rumah baru valid. Data ditandai belum diverifikasi pengurus. Benturan rumah/akun tidak dibereskan dengan auto-merge; diarahkan ke proses review.

### Pengaduan

Warga mengisi laporan/foto/privasi → API menyimpan laporan dan event → warga menerima konfirmasi → admin membaca dan mengubah status dengan catatan → histori tersimpan → inbox dan push memberi tahu pemilik laporan.

### Iuran offline

Warga membayar kepada pengurus di luar aplikasi → admin memilih rumah dan bulan → aplikasi menampilkan total sesuai tagihan historis → admin mencatat nominal/tanggal → transaksi menyimpan payment, allocation, kas, audit, outbox → status periode berubah → warga melihat histori. Tidak ada unggah bukti wajib, checkout, maupun verifikasi gateway.

### Publikasi

Admin masuk ke CMS → menyimpan draft ke API → upload diproses → preview privat → publish → halaman publik memakai published revision terbaru → cache dibersihkan setelah commit. Draft lokal demo tidak otomatis menjadi published content.

## 8. Kebutuhan nonfungsional

| Area | Target rancangan dan cara ukur |
| --- | --- |
| Usability | Bahasa Indonesia jelas, tombol sentuh ≥44 px, label status selain warna |
| Responsif | Web diuji 360/390/768/1024/1440 px; mobile diuji perangkat Android nyata |
| Aksesibilitas | Keyboard/fokus, label, kontras, reduced motion; autoplay diuji manual |
| Keamanan | Otorisasi per resource, hashing password, token aman, TLS, pembatasan abuse |
| Integritas | Transaksi Postgres, constraint unik, integer rupiah, idempotency keuangan |
| Kinerja | Target p95 API nonupload ≤1 detik pada staging hangat dengan 20 pengguna bersamaan; ukur dan laporkan cold start Neon terpisah |
| Notifikasi | Target inbox ≤60 detik; push bergantung jaringan/provider; bukan jaminan delivery |
| Operasi | Health checks, retry terbatas, dead-letter/failed jobs, audit dan alert |
| Pemulihan | Target RPO backup harian ≤24 jam, RTO drill ≤4 jam; release gate harus membuktikan |
| Konsistensi | Status/saldo berasal dari API; cache bukan sumber data final |
| Pemeliharaan | Kontrak versi v1, migration versioned, konfigurasi tarif tanpa ubah kode |

Angka di tabel adalah target rekayasa v1, bukan hasil pengukuran atau janji SLA berbayar.

## 9. Bukan cakupan v1

Payment gateway, e-wallet, pembayaran parsial/kredit saldo, marketplace, multi-RT SaaS, chat pribadi, AI chatbot, administrasi surat otomatis, GPS wajib, integrasi WhatsApp bot, sinkronisasi dua arah Google Calendar, offline write queue, dan rilis iOS/App Store. Tambahan memerlukan perubahan PRD/ADR.

Android adalah target rilis pertama. Flutter disusun agar perluasan platform tidak mengubah aturan bisnis.

## 10. Data dan privasi

Tidak meminta NIK/KTP/nomor KK sebagai default v1. Nama keluarga, alamat, dan kontak hanya untuk tujuan administrasi. Transparansi iuran menggunakan kode/label rumah, periode, nominal, dan status; email/WhatsApp/daftar anggota keluarga tidak ikut tampil. Pengunjung publik tidak boleh membaca tabel transparansi warga.

Arsip bukan janji menyimpan data pribadi selamanya. Retensi/privacy request dikelola lewat kebijakan SECURITY.md, tanpa menghapus bukti keuangan secara sembarang.

## 11. Ukuran keberhasilan

- Seluruh alur kritis registrasi, otorisasi, pengaduan, pencatatan iuran, kas, dan backup lulus sebelum production.
- Minimal 80% peserta UAT menilai sistem mudah digunakan/membantu; jumlah peserta dan jawaban dicatat nyata.
- Target minimum 80% fitur utama dari logbook tidak dipakai untuk meloloskan bug keamanan/keuangan; fitur belum selesai diumumkan.
- Warga dan admin melihat data yang konsisten setelah commit/refresh.
- Konten yang dipublikasikan tampil pada browser/perangkat lain.

## 12. Pengiriman

Ikuti M00–M16 pada ROADMAP.md. Rilis staging awal di M04 supaya integrasi diuji di Hostinger sejak fondasi; production penuh di M16 setelah gate keamanan, keuangan, notifikasi, dan restore lulus.

## 13. Input operasional yang diperlukan nanti

Credential Neon scoped dev/staging/prod, akses VPS yang diotorisasi, kontrol DNS morriz.tech, SMTP, akun/project FCM, lokasi backup di luar VPS, data RT tervalidasi, dan signing key Android. Agent menyiapkan adapter/config lokal dahulu; tidak mengarang input ini.
