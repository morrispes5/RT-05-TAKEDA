# DRAF Pemberitahuan Privasi — RT05 TAKEDA

> **Digantikan [PRIVACY_NOTICE.md](PRIVACY_NOTICE.md) (ADR35, 21 Okt 2026).** Draf ini disimpan sebagai riwayat; bagian
> "Tidak diminta: NIK, nomor KK" tidak berlaku lagi (NIK/KK kini opsional dan terenkripsi).

**Status: draf M15, belum berlaku.** Bagian bertanda **[DIISI PEMILIK/MITRA]** wajib diisi dengan fakta nyata dan
disetujui pengurus sebelum data warga asli dimasukkan (SECURITY.md, gate M15/M16). Agent tidak mengisi kontak, alamat,
atau jangka retensi yang belum diputuskan.

## 1. Pengelola

Aplikasi dan website RT05 TAKEDA dikelola oleh pengurus RT 05 RW 07 Taman Kedaung. Ketua RT: Agus Ferdiansyah.
Kontak untuk pertanyaan atau permintaan terkait data pribadi: **[DIISI PEMILIK/MITRA: nama peran, nomor/email resmi]**.

## 2. Data yang dikumpulkan dan tujuannya

| Data | Tujuan |
| --- | --- |
| Nama, email, kata sandi (disimpan dalam bentuk hash) | Akun dan masuk aplikasi |
| Kode/alamat rumah, anggota keluarga (nama, hubungan, tahun lahir opsional) | Administrasi keluarga dan rumah |
| Pengaduan, aspirasi, usulan kegiatan beserta foto/dokumen | Menindaklanjuti laporan warga |
| Pembayaran iuran (periode, nominal, tanggal, pencatat) dan kas RT | Pencatatan iuran offline dan laporan keuangan |
| Notifikasi dan token perangkat untuk push | Mengirim pemberitahuan status, agenda, pengumuman |
| Catatan audit (siapa mengubah apa dan kapan) | Keamanan dan pertanggungjawaban pengurus |

Tidak diminta: NIK, KTP, nomor KK, rekening bank, atau lokasi GPS. Tidak ada pembayaran online.

## 3. Siapa yang dapat melihat

- Warga hanya melihat data keluarga sendiri, iuran sendiri, serta laporan miliknya.
- Laporan dengan "sembunyikan identitas" tidak menampilkan nama pelapor kepada warga lain; **pengurus tetap dapat melihat
  pelapor** untuk tindak lanjut. Foto atau isi laporan tetap bisa mengungkap identitas; periksa sebelum mengirim.
- Transparansi iuran menampilkan kode rumah dan status pembayaran, tanpa nama, email, atau anggota keluarga, dan hanya
  untuk warga yang masuk. Kas RT hanya terlihat oleh pengurus.
- Website publik hanya menampilkan konten yang diterbitkan pengurus (artikel, album, fasilitas, profil).
- Data disimpan pada penyedia layanan: database PostgreSQL (Neon) dan server aplikasi
  **[DIISI PEMILIK: penyedia hosting production]**; push lewat Firebase Cloud Messaging (Google).

## 4. Penyimpanan dan penghapusan

Bawaan v1 (SECURITY.md): unggahan sementara dan berkas ekspor 24 jam, log aplikasi 14 hari, notifikasi minimal
12 bulan. Catatan keuangan dan audit tidak dihapus otomatis; koreksi dilakukan dengan pembalikan, bukan penghapusan.
Jangka simpan data akun/keluarga setelah warga pindah: **[DIISI PEMILIK/MITRA]**.

## 5. Hak warga

Warga dapat meminta salinan, koreksi, atau penonaktifan akun melalui kontak di bagian 1. Permintaan yang menyangkut
catatan keuangan ditangani dengan menyamarkan identitas bila memungkinkan tanpa merusak bukti keuangan.
Batas waktu tanggapan: **[DIISI PEMILIK/MITRA]**.

## 6. Perubahan

Perubahan pemberitahuan ini diumumkan melalui notifikasi aplikasi (BR21) dan tanggal berlaku dicatat di sini.
Berlaku sejak: **[DIISI saat disetujui]**.
