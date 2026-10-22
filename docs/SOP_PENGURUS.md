# SOP pengurus — RT05 TAKEDA

**Draf 22 Oktober 2026 (M16, gate G12).** Disusun dari fitur aplikasi yang sudah diuji di staging. Diperiksa ulang bersama
pengurus saat pilot sebelum dianggap final. Aturan bisnis yang mengikat ada di [BUSINESS_RULES.md](BUSINESS_RULES.md).

## Prinsip

- Semua pengurus yang ditetapkan sebagai admin memiliki hak yang sama. Setiap perubahan tercatat (siapa dan kapan).
- Iuran Rp75.000 per rumah per bulan dibayar **offline** (tunai/transfer). Aplikasi hanya mencatat; tidak ada pembayaran
  online.
- Data yang salah **tidak dihapus**. Keuangan dikoreksi dengan pembalikan lalu dicatat ulang. Data lain diarsipkan dan
  bisa dipulihkan.
- Pelapor yang menyembunyikan identitas tetap terlihat oleh pengurus, tetapi jangan pernah disebut ke warga lain.
- Jangan meminta kata sandi atau kode OTP warga. Jangan membagikan NIK, nomor KK, atau nomor WhatsApp warga ke luar
  pengurus.

## Harian / saat ada notifikasi

| Pekerjaan | Di aplikasi | Catatan |
| --- | --- | --- |
| Menyetujui akun baru | Warga → Permohonan akun | Cocokkan nama, alamat/kepala keluarga dengan data RT. Bila kurang, pilih *Minta perbaikan*, jangan langsung menolak |
| Menanggapi pengaduan | Layanan | Ubah status sesuai tindak lanjut dan beri catatan singkat. Foto hanya untuk tindak lanjut |
| Menanggapi aspirasi dan usulan kegiatan | Layanan / Lainnya → Usulan kegiatan | Jawab dengan sopan, termasuk bila ditolak (isi alasan) |
| Memeriksa bukti transfer | Keuangan → Bukti pembayaran warga | Cocokkan dengan mutasi rekening bendahara. Bila sesuai, catat pembayaran. Bila tidak, tolak dengan alasan |

## Iuran dan kas

1. **Mencatat pembayaran:** Keuangan → Catat pembayaran iuran → pilih rumah dan bulan → simpan. Nomor bukti dibuat otomatis.
2. **Salah catat:** buka pembayaran itu → *Batalkan* (pembalikan) → catat ulang yang benar. Jangan membuat pembayaran
   tandingan.
3. **Kas RT:** Keuangan → Catat kas (pemasukan lain/pengeluaran). Bila saldo akan negatif, aplikasi meminta konfirmasi.
   Kesalahan dikoreksi dengan *Balik transaksi kas*.
4. **Rekap bulanan:** ekspor CSV Pembayaran/Kas/Rekap untuk laporan rapat. Berkas ekspor berisi data warga; simpan di
   perangkat pengurus saja dan hapus setelah dipakai.
5. **Tarif baru:** hanya untuk periode berikutnya. Tagihan bulan yang sudah ada tidak berubah.

## Warga dan rumah

- Rumah baru, perubahan anggota keluarga, dan WhatsApp warga diubah di menu Warga. NIK/KK opsional dan hanya diisi atas
  persetujuan warga.
- Warga pindah: arsipkan keluarga/rumah, jangan dihapus. Catatan iuran tetap tersimpan sebagai bukti keuangan.
- Salah arsip: buka daftar arsip → *Pulihkan*.

## Website (artikel, dokumentasi, profil)

- Kelola di website `/pengurus` (login pengurus) atau menu Konten website di aplikasi. Simpan draf, pratinjau, lalu terbitkan.
- Foto kegiatan: pastikan warga di foto tidak keberatan. Jangan mengunggah dokumen pribadi.
- Nama dan kontak pengurus di Profil RT05 hanya diisi setelah yang bersangkutan setuju dipublikasikan.

## Bulanan

- Rotasi token registrasi bila pernah dibagikan di luar grup warga (Lainnya → Token registrasi).
- Periksa daftar pengurus/admin: cabut akses pengurus yang sudah tidak menjabat.
- Pastikan nomor WhatsApp bendahara di pengaturan masih benar.

## Bila ada masalah

- Aplikasi tidak bisa dibuka atau data tidak muncul: cek internet, lalu kabari pengelola teknis (kontak di dokumen serah
  terima). Jangan memasang APK dari sumber lain.
- Ada warga yang menerima tautan aplikasi palsu: minta mereka hanya mengunduh dari halaman `/aplikasi` situs resmi.
- Data terhapus/terubah tanpa sengaja: jangan mencoba memperbaiki dengan mengisi ulang massal. Kabari pengelola teknis
  agar dicek dari catatan perubahan atau backup.
