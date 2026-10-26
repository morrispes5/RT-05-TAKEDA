# M18 — bukti laporan dan ketahanan lokal

26 Oktober 2026, branch `codex/m18-laporan-excel`. Data sintetis PostgreSQL lokal `127.0.0.1:54329/rt05_test`;
tidak memakai Neon atau production. Ini bukti lokal, belum bukti staging/emulator/APK M18.

- `php artisan test`: **405/405, 4.302 assertions**, 164.841 ms. Termasuk regresi M01–M17, transaksi independen,
  skenario rapat, denial akses, invarian ledger dan rekonsiliasi. Bukan perintah `--env=testing`.
- `php vendor/bin/pint --test`: PASS. `composer validate --no-check-publish`: valid. `composer audit`: tanpa advisory.
  OpenSpout 4.32.0 satu dependency gratis baru; ekstensi PHP diperiksa lokal dan ditambahkan pada CI.
- `flutter test --no-pub --concurrency=2`: **307/307**, sekitar 58 detik. Layar laporan lebar 320 dan skala teks 2,
  pilihan tahun, unduhan XLSX melalui penyimpanan OS dan pembukaan rincian bulanan lulus.
- `flutter analyze --no-pub`: bersih; `dart format --output=none --set-exit-if-changed lib test`: **95 file, 0 berubah**.

`M18ReportsTest` membaca ulang 14 sheet XLSX: rekap, rumah × bulan, Januari–Desember. Angka bulanan sama dengan
JSON dan CSV; teks berawalan `= + - @`, tab/CR disimpan sebagai string, tidak ada formula. Pembalikan lintas tahun,
setoran dan pemakaian titipan/pembalikan lintas bulan, rumah arsip, tahun kosong, header privat/no-store, audit,
hapus berkas setelah kirim dan cleanup UUID kedaluwarsa diuji. CSV lama memakai saldo efektif dan nominal kas bertanda.

Arus kas mengikuti tanggal posting. Pelunasan tagihan adalah posisi saat laporan dibuat, bukan snapshot akhir bulan
lampau. Catatan ini terlihat di API dan workbook. Snapshot diambil dengan lock keuangan lalu dilepas sebelum ZIP dirender.

`M18DurabilityTest` memakai commit nyata: gangguan cache setelah commit dan antrean saat dispatch tidak menghilangkan
pembayaran/outbox. Koneksi lain melihat uang tersimpan, retry idempotensi menghasilkan satu pembayaran dan rekonsiliasi nol.

`M18ScaleTest`: **300 rumah × 12 bulan = 3.600 tagihan**, seluruh deferred constraint dipaksa diperiksa, pembayaran
dicatat dan laporan dibaca. Laporan **7 query** (batas 10). Waktu validasi constraint 14.657 ms pada tes terarah,
48.326 ms pada suite penuh yang juga berjalan bersama Flutter. Angka ini waktu bulk fixture lokal sintetis, bukan
latensi transaksi normal atau SLA staging. Tidak ada bypass constraint, trigger atau pengurangan pengaman.

Gate berikut: CI PR, pemeriksaan ekstensi image dan staging immutable, aktivasi flag terkendali, rekonsiliasi nol,
uji integrasi pengurus/warga, upgrade APK dan satu rilis M18. Production tetap di luar ruang lingkup.
