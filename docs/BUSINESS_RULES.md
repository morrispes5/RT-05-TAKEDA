# Aturan bisnis — RT05 TAKEDA

Kode BR dipakai pada API, tes, issue, dan kriteria milestone. Bagian A mempertahankan kebutuhan proyek; bagian B adalah batas implementasi v1 untuk detail yang belum diputuskan dalam percakapan.

## A. Kebutuhan yang dipertahankan

| ID | Aturan |
| --- | --- |
| BR01 | Website profil publik tanpa akun; layanan warga pada mobile |
| BR02 | Pengurus web hanya CMS Artikel/Dokumentasi; administrasi lainnya pada mobile |
| BR03 | Warga mendaftar dengan email/password dan token RT 6 digit |
| BR04 | Token acak dapat dilihat/disalin admin; rotasi manual dan mingguan |
| BR05 | Satu rumah mempunyai satu keluarga utama aktif; akun tambahan terhubung ke keluarga/rumah dan disetujui admin |
| BR06 | Registrasi mengisi data dari awal; WhatsApp opsional |
| BR07 | Semua admin memiliki hak pengelolaan bisnis setara |
| BR08 | Pengaduan dan aspirasi terpisah; dapat dibaca warga terdaftar |
| BR09 | Privasi identitas laporan berlaku terhadap warga lain, tidak terhadap pengurus |
| BR10 | Iuran Rp75.000 per rumah per bulan, bukan per akun/orang |
| BR11 | Pembayaran offline; pencatatan admin; tidak ada payment gateway atau bukti wajib |
| BR12 | Status belum bayar berubah setelah transaksi pencatatan berhasil |
| BR13 | Menunggak dan membayar beberapa bulan sekaligus diperbolehkan |
| BR14 | Tarif dapat diubah untuk periode yang ditentukan; riwayat minimal 12 bulan |
| BR15 | Warga terdaftar melihat transparansi iuran/status dan kas |
| BR16 | Pemasukan iuran harus berhubungan dengan pembayaran yang dicatat |
| BR17 | Koreksi keuangan dapat dilakukan admin dan historinya tetap tersedia |
| BR18 | Hapus dari tampilan menggunakan arsip untuk data penting |
| BR19 | Agenda tersedia di kalender mobile dan dapat ditambahkan ke Google Calendar |
| BR20 | Pengumuman dan notifikasi layanan tersedia di mobile |
| BR21 | Notifikasi mencakup status laporan, agenda, iuran, pengumuman, kebijakan privasi, dan update aplikasi |
| BR22 | Data warga/keuangan penting dapat diekspor sesuai hak akses |

## B. Keputusan implementasi v1

### Rumah, keluarga, dan akun

- House adalah unit tagihan dengan kode stabil. Alamat dinormalisasi untuk mencegah duplikasi penulisan.
- Family adalah keluarga; Residents adalah anggota, termasuk yang tidak punya akun.
- Occupancy menyimpan family-house dan rentang hunian; is_primary menyatakan keluarga utama.
- Membership keluarga dan link akun mempunyai rentang waktu. Riwayat perpindahan tidak dihapus.
- Rumah kontrakan memakai occupancy_type=tenant; kepemilikan dicatat admin. Pemilik dan penghuni bukan otomatis entitas yang sama.
- Tagihan melekat pada rumah. Perpindahan keluarga tidak memindahkan tagihan ke rumah baru dan tidak menghapus tunggakan.
- Rumah kosong/baru tidak otomatis bebas iuran. Admin menetapkan billing_enabled dan billing_start_month; jangan membuat pengecualian tanpa catatan.
- Hanya akun utama dan admin mengubah data keluarga pada v1. Akun tambahan dapat membaca data keluarga sendiri dan memakai layanan.
- Persetujuan akun tambahan memberikan role resident, tidak admin. Grant/revoke admin hanya lewat command operator yang diaudit pada v1.
- Akun inactive/archived ditolak dan token/sesi dicabut. Pengurus masih melihat histori.

### Token dan registrasi

- Token disimpan sebagai string enam digit agar nol di depan tidak hilang.
- Gunakan generator kriptografis; tidak boleh token dari tanggal/nomor rumah.
- Satu token aktif untuk RT per waktu. Rotasi mencabut token lama segera.
- Token bukan OTP login, API bearer token, atau pengganti password.
- Simpan digest verifikasi dan nilai terenkripsi untuk kebutuhan lihat/salin admin; kunci tidak masuk Git.
- Registrasi utama valid dapat aktif dengan data unverified; duplikasi rumah tidak otomatis membuat keluarga utama kedua.
- Registrasi tambahan pending hanya mempunyai endpoint terbatas untuk status permohonan/profil/logout; tidak dapat melihat data seluruh warga.
- Token salah/kedaluwarsa menghasilkan pesan generik, dengan batas percobaan.

### Status laporan

| Modul | State | Transisi v1 |
| --- | --- | --- |
| Pengaduan | submitted, processing, resolved | submitted → processing → resolved |
| Aspirasi | submitted, reviewed, completed | submitted → reviewed → completed |

Admin dapat membuka ulang resolved/completed ke processing/reviewed dengan alasan wajib dan audit. Tidak ada delete permanen atau status baru tanpa ADR. Warga dapat memperbaiki laporan miliknya saat submitted; setelah diproses, perubahan isi memerlukan admin dan alasan. Riwayat menyimpan actor/time/note dan versi state.

Jika anonymous_to_residents=true, resource warga tidak memuat user_id, nama, kontak, atau link identitas. Pemilik laporan boleh melihat laporan miliknya pada layar sendiri. Semua anonimitas harus diuji pada detail, feed, notifikasi, ekspor, dan media.

### Iuran

- Periode berupa YYYY-MM yang disimpan sebagai date hari pertama bulan, bukan timestamp lokal.
- Setiap rumah/periode hanya punya satu charge. Generation boleh dijalankan ulang.
- Amount_due adalah snapshot tarif efektif saat charge dibuat. Tarif baru tidak mengubah charge lama otomatis.
- Setiap charge berstatus unpaid atau paid. Nilai uang integer rupiah positif.
- V1 hanya menerima pelunasan penuh untuk setiap bulan yang dipilih. Pembayaran sebagian/kelebihan ditolak dengan pesan jelas; tidak diam-diam dijadikan saldo.
- Satu payment dapat melunasi beberapa charge unpaid pada satu rumah; total payment harus sama dengan jumlah amount_due.
- Admin boleh mencatat tanggal bayar aktual serta catatan; server mencatat recorded_at dan recorded_by terpisah.
- Pembayaran diproses sinkron dalam satu transaksi: payment, allocations, cash entry, audit, outbox.
- Belum dicatat = belum bayar pada aplikasi, walaupun pembayaran offline mungkin sudah terjadi; tampilkan penjelasan singkat.
- Tidak membuat tunggakan sebelum billing_start_month atau sebelum tanggal go-live pencatatan tanpa impor terverifikasi.
- Tunggakan bulan lewat dihitung dari charge unpaid sebelum bulan berjalan; unpaid bulan berjalan ditampilkan terpisah.
- Arsip rumah tidak boleh menghapus charge/payments/kas historis.

### Koreksi keuangan

- Payment posted tidak diedit in-place. Tindakan koreksi membalik payment/allocations, membuat cash reversal, kemudian payment pengganti bila diperlukan.
- Reversal wajib alasan dan merujuk transaksi asal; satu reversal penuh per transaksi asal pada v1.
- Pembalikan membuka kembali charge unpaid, tetapi harus atomik dan menolak perubahan bersamaan yang konflik.
- Cash entry untuk iuran hanya dibuat melalui payment action. Endpoint kas manual tidak boleh menambah entry iuran kedua.
- Pemasukan lain/pengeluaran manual menggunakan entry immutable setelah posted; koreksi lewat reversal.
- Opening balance adalah entry khusus satu kali per go-live kas dengan sumber dan alasan, bukan penambahan saldo manual berkali-kali.
- Saldo = total pemasukan posted - pengeluaran posted + nilai reversal sesuai sign. Reversal tidak dihitung dua kali.
- Tidak menambahkan larangan saldo negatif tanpa kesepakatan; admin mendapat peringatan dan wajib alasan bila pencatatan membuat saldo negatif.

### Konten, agenda, notifikasi

- Draft/published/archived untuk konten; pengunjung hanya menerima published.
- Slug unik; perubahan slug perlu redirect agar URL lama tetap berguna.
- Media belum ready tidak boleh published. Konten published menunjuk revision immutable yang sudah tervalidasi.
- Agenda menggunakan timezone Asia/Jakarta; UTC pada penyimpanan timestamp. Rentang waktu harus valid.
- Add-to-calendar adalah aksi pengguna melalui intent/link/ICS; tidak memerlukan akses penuh kalender Google.
- Inbox notifikasi disimpan di Postgres. Push hanya membawa ID/type dan teks aman, bukan data keluarga atau isi sensitif.
- Pengingat iuran hanya ke akun utama rumah pada v1; agenda/pengumuman ke warga aktif; status laporan ke pembuatnya.
- Reminder agenda satu kali H-1; pengingat iuran tanggal 5 dan 20 untuk periode berjalan; masing-masing key deduplikasi berbeda.
- Preferences dapat mematikan push nonkritis; inbox riwayat layanan tetap ada.

## C. Matriks akses minimum

| Resource | Publik | Warga aktif | Akun pending | Admin |
| --- | --- | --- | --- | --- |
| Published profil/fasilitas/artikel/album | Baca | Baca | Baca | Kelola |
| Draft dan upload konten | Tidak | Tidak | Tidak | Kelola |
| Data keluarga | Tidak | Keluarga sendiri | Status permohonan saja | Kelola |
| Pengaduan/aspirasi | Tidak | Baca tersanitasi; buat/milik sendiri | Tidak | Kelola |
| Agenda/pengumuman | Tidak | Baca | Tidak | Kelola |
| Transparansi iuran/kas | Tidak | Ringkasan dan status rumah | Tidak | Detail/kelola |
| Token registrasi | Tidak | Tidak | Tidak | Lihat/rotasi |
| Audit/ekspor administrasi | Tidak | Tidak | Tidak | Baca/ekspor |

## D. Larangan pengembangan

Jangan menambah fitur pembayaran online, role bendahara khusus yang mengurangi kesetaraan admin, NIK/KTP wajib, penghapusan permanen finansial, anonymization yang juga menyembunyikan pelapor dari admin, akses database di client, atau perhitungan saldo terpisah dalam Flutter.

Detail B adalah keputusan rekayasa eksplisit v1 dan boleh direvisi pemilik lewat ADR. Tidak boleh dipresentasikan sebagai kutipan persetujuan mitra yang belum ada.
