# Kebijakan Privasi — RT05 TAKEDA

Versi **2026-10-26** (ADR44/M18; aktif bersama flag keuangan v2). Teks ini sama dengan yang tampil di aplikasi (`mobile/lib/features/auth/privacy_policy.dart`)
dan disetujui warga setelah masuk. Bila isinya berubah, naikkan versi aktif `App\Support\Privacy::version()` di rest-api agar warga
diminta setuju lagi.

Versi 25 Oktober tetap digunakan saat flag keuangan v2 nonaktif. Aplikasi menyimpan teks lokal kedua versi;
server meminta versi teks yang ditampilkan saat persetujuan M18. Nomor WhatsApp kontak resmi berasal dari database,
bukan dokumen/Git. Ringkasan kas M18 tidak memuat identitas atau status iuran rumah lain.

## Siapa pengelolanya

- Aplikasi dan website RT05 TAKEDA dikelola oleh pengurus RT 05 RW 07 Taman Kedaung (Ketua RT: Agus Ferdiansyah). Pertanyaan atau permintaan tentang data pribadi bisa disampaikan ke pengurus RT, langsung, lewat WhatsApp Ketua RT yang tercantum di halaman Kontak website, atau lewat menu Aspirasi di aplikasi.

## Data yang kami pakai

- Untuk masuk: nama, email, dan kata sandi (kata sandi disimpan teracak, tidak bisa dibaca siapa pun).
- Untuk administrasi rumah: alamat rumah (blok, jalan, nomor), anggota keluarga, dan hubungan keluarga.
- Opsional: nomor WhatsApp, NIK, nomor Kartu Keluarga, dan tanggal lahir anggota keluarga. Boleh dikosongkan; pengurus juga dapat membantu mengisinya sesuai data RT. Tanggal lahir dipakai pengurus untuk menghitung kelompok umur warga (misalnya balita, remaja, dan lansia).
- Laporan yang Anda kirim: pengaduan, aspirasi, usulan kegiatan, beserta foto atau dokumennya.
- Iuran: tagihan rumah Anda, pembayaran, keringanan, sumbangan atau titipan yang dicatat pengurus, dan foto bukti transfer bila Anda mengirimnya.
- Token perangkat untuk notifikasi, serta catatan siapa mengubah data dan kapan (untuk keamanan).
- Kami tidak meminta rekening bank, lokasi GPS, atau kontak di HP Anda, dan tidak ada pembayaran online.

## Siapa yang bisa melihat

- Alamat rumah, NIK, nomor KK, nomor WhatsApp, dan tanggal lahir hanya dapat dilihat oleh pengurus RT dan keluarga Anda sendiri. Warga lain tidak bisa melihatnya.
- NIK dan nomor KK disimpan terenkripsi dan di aplikasi hanya tampil tersamar (misalnya 3674••••••••0001).
- Warga hanya melihat tagihan, pembayaran, keringanan, tunggakan, dan titipan rumahnya sendiri. Warga dapat melihat saldo serta ringkasan kas RT per bulan tanpa nama atau kode rumah. Status iuran rumah lain, rincian transaksi, catatan, dan bukti transfer hanya terlihat oleh pengurus.
- Laporan yang disembunyikan identitasnya tidak menampilkan nama pelapor kepada warga lain, tetapi pengurus tetap dapat melihat pelapor untuk tindak lanjut.
- Website publik hanya menampilkan artikel, dokumentasi, fasilitas, dan profil RT yang diterbitkan pengurus.

## Data Anda tidak disebarkan

- Data Anda hanya dipakai untuk keperluan layanan RT 05: administrasi warga, iuran, dan tindak lanjut laporan. Data tidak dijual dan tidak dibagikan ke pihak lain di luar pengurus.
- Data disimpan di layanan database dan server yang dipakai aplikasi ini; notifikasi dikirim lewat Firebase Cloud Messaging (Google).

## Penyimpanan

- Data tetap tersimpan selama Anda tercatat sebagai warga. Bila pindah atau meninggal, data diarsipkan dan tidak tampil lagi. 12 bulan kemudian nama, nomor WhatsApp, NIK, nomor KK, dan tanggal lahir dianonimkan otomatis. Catatan iuran dan kas tidak dihapus karena menjadi bukti keuangan RT.
- Log teknis disimpan 14 hari, berkas ekspor 24 jam, dan notifikasi sekitar 12 bulan.

## Hak Anda

- Melihat dan memperbaiki data keluarga Anda (akun utama) atau meminta pengurus memperbaikinya.
- Mengosongkan NIK, nomor KK, atau nomor WhatsApp kapan saja.
- Meminta salinan data atau penonaktifan akun kepada pengurus.
- Bila kebijakan ini berubah, aplikasi akan meminta persetujuan Anda lagi.
