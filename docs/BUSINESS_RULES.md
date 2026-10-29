# Aturan bisnis — RT05 TAKEDA

## M19 — tombol panik (ADR43/45/48)

- Tidak anonim; setelah tiga langkah (tekan FAB → baca ketentuan sampai bawah → geser), nama/rumah dan jenis
  darurat (bila diisi) tersedia bagi warga/pengurus aktif penerima yang menyetujui kebijakan M19. Jenis tidak wajib
  sebelum alarm. Tidak ada GPS, panggilan otomatis atau integrasi instansi.
- Pengurus setara menangani selesai/palsu. Penilaian penyalahgunaan dipilih secara eksplisit dengan alasan;
  palsu tidak otomatis dihitung sebagai penyalahgunaan. Tiga penilaian menonaktifkan pengiriman akun itu,
  tanpa menghalangi penerimaan darurat atau layanan lain. Belum ada pemulihan/reset hitungan otomatis.
- Fitur dapat dimatikan pengurus; gate deployment tidak dapat dilewati pengaturan database. Retry/klik ganda
  tidak menggandakan kejadian, inbox, atau hitungan. Riwayat tidak dihapus atau ditulis ulang diam-diam.
- Bunyi mengikuti izin/pengaturan HP; harus diuji nyata. Tombol ini bukan pengganti 112/damkar/polisi.
- Default teknis dan batas uji tercatat di [M19_PLAN.md](M19_PLAN.md). Belum dinyatakan passed.

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
| BR10 | Tarif umum awal Rp75.000 per rumah per bulan, bukan per akun/orang; M18 mendukung tarif khusus beralasan mulai periode tertentu tanpa mengubah snapshot lama (ADR44) |
| BR11 | Pembayaran offline; pencatatan admin; tidak ada payment gateway atau bukti wajib |
| BR12 | Status belum bayar berubah setelah transaksi pencatatan berhasil |
| BR13 | Menunggak dan membayar beberapa bulan sekaligus diperbolehkan |
| BR14 | Tarif dapat diubah untuk periode yang ditentukan; riwayat minimal 12 bulan |
| BR15 | Warga melihat iuran/tunggakan/titipan rumah sendiri dan dapat mengirim bukti transfer atau chat bendahara. M18 membuka kas agregat bulanan tanpa identitas setelah persetujuan teks privasi terbaru; status iuran rumah lain dan detail transaksi tetap khusus pengurus (ADR44 mengubah sebagian ADR26/35) |
| BR16 | Pemasukan iuran harus berhubungan dengan pembayaran yang dicatat |
| BR17 | Koreksi keuangan dapat dilakukan admin dan historinya tetap tersedia |
| BR18 | Hapus dari tampilan menggunakan arsip untuk data penting; arsip dapat dipulihkan pengurus (ADR34) |
| BR19 | Agenda tersedia di kalender mobile dan dapat ditambahkan ke Google Calendar |
| BR20 | Pengumuman dan notifikasi layanan tersedia di mobile |
| BR21 | Notifikasi mencakup status laporan, agenda, iuran, pengumuman, kebijakan privasi, dan update aplikasi |
| BR22 | Data warga/keuangan penting dapat diekspor sesuai hak akses |
| BR23 | Usulan kegiatan warga terpisah dari aspirasi dan dari agenda. Usulan yang disetujui pengurus langsung disalin sekali menjadi agenda resmi dan semua warga aktif diberi tahu. Agenda tetap satu-satunya jadwal |

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
- Arsip dapat dipulihkan pengurus (ADR34): konten kembali draf, rumah kembali tanpa tagihan aktif, akun kembali nonaktif, keluarga kembali bersama anggota yang ikut diarsipkan. Hunian dan tautan akun tidak dibuka otomatis.
- Tanggal lahir anggota keluarga opsional; diisi akun utama atau pengurus. Umur dan kelompok umur (balita 0–5, anak 6–12, remaja
  13–17, dewasa 18–59, lansia 60–69, lansia 70+) dihitung otomatis per hari ini dan hanya terlihat oleh pengurus serta keluarga
  sendiri (ADR42).
- Permohonan akun tambahan wajib nama pemohon, hubungan, dan nama kepala keluarga; alamat opsional. Rumah dicocokkan otomatis dari kepala keluarga yang unik, selain itu pengurus memilih rumah saat menyetujui (ADR34).

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
- Sebelum M18 aktif, kontrak M12 hanya menerima pelunasan penuh bulan terpilih; parsial/lebih ditolak.
- M18: satu penerimaan dapat melunasi sebagian/beberapa tagihan rumah yang sama, tertua dari bulan dipilih dahulu.
  Kurang wajib dipilih sebagai tunggakan atau keringanan beralasan; lebih wajib sumbangan atau titipan.
  Kas masuk sama dengan uang diterima. Keringanan bukan kas; pemakaian titipan manual tidak menambah kas kedua.
  Nominal efektif = snapshot + penyesuaian; terbayar + keringanan + sisa = nominal efektif, saldo titipan tidak negatif.
- Admin boleh mencatat tanggal bayar aktual serta catatan; server mencatat recorded_at dan recorded_by terpisah.
- Pembayaran diproses sinkron dalam satu transaksi: payment, allocations, cash entry, audit, outbox.
- Belum dicatat = belum bayar pada aplikasi, walaupun pembayaran offline mungkin sudah terjadi; tampilkan penjelasan singkat.
- Tidak membuat tunggakan sebelum billing_start_month atau sebelum tanggal go-live pencatatan tanpa impor terverifikasi.
- Tunggakan bulan lewat dihitung dari sisa tagihan sebelum bulan berjalan; bulan berjalan ditampilkan terpisah.
- Arsip rumah tidak boleh menghapus charge/payments/kas historis.

### Koreksi keuangan

- Payment posted tidak diedit in-place. Tindakan koreksi membalik payment/allocations, membuat cash reversal, kemudian payment pengganti bila diperlukan.
- Reversal wajib alasan dan merujuk transaksi asal; satu reversal penuh per transaksi asal pada v1.
- Pembalikan membuka kembali charge unpaid, tetapi harus atomik dan menolak perubahan bersamaan yang konflik.
- Cash entry untuk iuran hanya dibuat melalui payment action. Endpoint kas manual tidak boleh menambah entry iuran kedua.
- Pemasukan lain/pengeluaran manual menggunakan entry immutable setelah posted; koreksi lewat reversal.
- Opening balance adalah entry khusus satu kali per go-live kas dengan sumber dan alasan, bukan penambahan saldo manual berkali-kali.
- Saldo = total pemasukan posted - pengeluaran posted + nilai reversal sesuai sign. Reversal tidak dihitung dua kali.
- Tidak menambahkan larangan saldo negatif tanpa kesepakatan; admin mendapat peringatan dan wajib alasan bila pencatatan membuat saldo negatif. Implementasi M12: server menolak dulu (422 `NEGATIVE_BALANCE_CONFIRMATION_REQUIRED`, tanpa tulisan), lalu menerima setelah konfirmasi eksplisit; keterangan minimal 5 karakter menjadi alasan.

### Konten, agenda, notifikasi

- Draft/published/archived untuk konten; pengunjung hanya menerima published.
- Slug unik; perubahan slug perlu redirect agar URL lama tetap berguna.
- Media belum ready tidak boleh published. Konten published menunjuk revision immutable yang sudah tervalidasi.
- Agenda menggunakan timezone Asia/Jakarta; UTC pada penyimpanan timestamp. Rentang waktu harus valid.
- Usulan kegiatan (BR23) disimpan terpisah (requirement [USULAN_KEGIATAN.md](USULAN_KEGIATAN.md)). Pengurus dapat meninjau, meminta revisi, menyetujui, atau menolak. Catatan keputusan wajib saat meminta revisi atau menolak, dan riwayatnya tetap ada. Persetujuan langsung membuat tepat satu agenda; waktu yang sudah lewat tidak dapat disetujui. Perubahan agenda setelah itu tidak mengubah pengajuan. Warga lain tidak melihat usulan maupun dokumennya; pengurus melihat pengusul. Dokumen pendukung privat (PDF/TXT/gambar), tanpa nama berkas asli.
- Add-to-calendar adalah aksi pengguna melalui intent/link/ICS; tidak memerlukan akses penuh kalender Google.
- Inbox notifikasi disimpan di Postgres. Push hanya membawa ID/type dan teks aman, bukan data keluarga atau isi sensitif.
- Pengingat iuran hanya ke akun utama rumah pada v1; agenda/pengumuman ke warga aktif; status laporan ke pembuatnya. Usulan baru dan perbaikan diberitahukan ke pengurus; keputusan diberitahukan ke pengusul; persetujuan memberi tahu semua warga aktif lewat agenda baru.
- Reminder agenda satu kali H-1; pengingat iuran tanggal 5 dan 20 untuk periode berjalan; masing-masing key deduplikasi berbeda.
- Preferences dapat mematikan push nonkritis; inbox riwayat layanan tetap ada.

**Impor rekap bendahara (ADR49, 29 Okt 2026).** Bulan yang lunas sebelum aplikasi dicatat sebagai pelunasan buku lama
tanpa kas; uangnya masuk sekali lewat saldo awal kas resmi. Nominal berbeda dari tarif umum tidak ditebak (perlu keputusan
tarif khusus atau tarif umum); kelebihan bayar lama hanya dilaporkan. NIK/KK/nama dari Excel tidak diimpor. Koreksi
per bulan dengan pembatalan beralasan; impor sekali per tahun.

## C. Matriks akses minimum

| Resource | Publik | Warga aktif | Akun pending | Admin |
| --- | --- | --- | --- | --- |
| Published profil/fasilitas/artikel/album | Baca | Baca | Baca | Kelola |
| Draft dan upload konten | Tidak | Tidak | Tidak | Kelola |
| Data keluarga | Tidak | Keluarga sendiri | Status permohonan saja | Kelola |
| Pengaduan/aspirasi | Tidak | Baca tersanitasi; buat/milik sendiri | Tidak | Kelola |
| Agenda/pengumuman | Tidak | Baca | Tidak | Kelola |
| Usulan kegiatan | Tidak | Buat dan baca milik sendiri; dokumen hanya milik sendiri | Tidak | Tinjau dan putuskan; persetujuan otomatis menjadi agenda |
| Transparansi iuran | Tidak | Status per kode rumah, setelah warga memilih menampilkannya | Tidak | Detail/kelola |
| Kas RT (saldo, mutasi) | Tidak | Tidak (ADR26) | Tidak | Detail/kelola |
| Token registrasi | Tidak | Tidak | Tidak | Lihat/rotasi |
| Audit/ekspor administrasi | Tidak | Tidak | Tidak | Baca/ekspor |

## D. Larangan pengembangan

Jangan menambah fitur pembayaran online, role bendahara khusus yang mengurangi kesetaraan admin, NIK/KTP wajib (NIK dan nomor KK opsional terenkripsi diizinkan ADR35), penghapusan permanen finansial, anonymization yang juga menyembunyikan pelapor dari admin, akses database di client, atau perhitungan saldo terpisah dalam Flutter.

Detail B adalah keputusan rekayasa eksplisit v1 dan boleh direvisi pemilik lewat ADR. Tidak boleh dipresentasikan sebagai kutipan persetujuan mitra yang belum ada.
