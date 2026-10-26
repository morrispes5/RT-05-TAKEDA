# M18 — penerimaan staging dan APK 1020 (lulus)

26 Oktober 2026. PR #87 laporan merged `6b44763`, PR #88 konsistensi kas merged `afe4ebb`; masing-masing CI 9/9 PASS.
Backend lokal terakhir 406/406 (4.316 assertions), Flutter 308/308, analyze bersih, format 96/0, Pint PASS.

Backup sebelum migrasi: `rt05-20261026T0443Z.dump.gpg`, AES256, 180 KB, SHA-256
`72038a19cc8c544df9129d9b6b7fd3dc44a0030bc4fd409a86ed23a2728934c6`.
Restore PostgreSQL 17 terisolasi cocok pada 12 tabel, total 10 detik. Salinan terenkripsi offsite di Drive privat
`RT05 TAKEDA - Backup/staging/`; MD5 Drive sama dengan berkas lokal. Kunci hanya di berkas privat operator, bukan Git/Drive.

Baseline rekonsiliasi nol, saldo Rp2.180.000. Deploy immutable `6b447638ca8b53559c4b63af768e3a130a4d811e` dengan flag false:
dua migrasi berhasil (852,78 / 63,37 ms), empat heartbeat sehat, volume dan container lain terjaga.
Pemeriksaan HTTP: versi client 1/privasi 25 Okt, saldo lama tetap, laporan v2 404, ready dan APK publik masih 1018.
Ekstensi XLSX tersedia, view pelanggaran kosong, rekonsiliasi nol. APK 1018 emulator tetap login/data lama terbaca.

Deploy immutable berikutnya `afe4ebbbda25207f541bea1ad750cc8ae48d8c66` selesai dengan `RT05_FINANCE_V2=true` hanya staging.
Skrip deploy server tetap SHA-256 `aa50ca1a629ba5a7b0c29b7c24ac357118a96f5ded6246a35f55e693b6c52c75`.
Empat belas akun/rumah khusus `UJI18` dibuat melalui domain registrasi untuk penerimaan; kredensial dan ID hanya privat.
API, tiga worker dan scheduler diverifikasi memakai v2=true, ekstensi XLSX tersedia, pelanggaran nol. Setelah transaksi
v2 ini jangan deploy kode M17 atau mematikan v2; perbaiki maju bila perlu.

## Skenario HTTP dan PostgreSQL staging yang sudah lulus

- Delapan rumah membayar Rp75.000 dan dua Rp50.000: kas naik **Rp700.000**; satu diberi keringanan Rp25.000,
  satu tetap tunggakan Rp25.000. Setiap pembayaran diulang key sama, hanya satu penerimaan tercatat.
- Rumah membayar Rp75.000 lalu Rp25.000 pada bulan sama; penerimaan kedua dialokasikan sebagai sumbangan.
- Penerimaan Rp100.000 menjadi alokasi Rp75.000 + titipan Rp25.000. Titipan dipakai manual untuk November,
  saldo titipan nol, **tidak menambah kas**. Pembalikan sumber ditolak saat titipan dipakai.
- Tarif khusus dua rumah Rp50.000 mulai November melalui API. Generator domain dibatasi dengan scope sementara
  hanya 14 ID rumah sintetis (tidak menagih bulan depan rumah lain): 14 tagihan baru, ulang nol, total 10 rumah
  November Rp700.000, snapshot Oktober tetap Rp750.000. Penerimaan November menambah kas Rp700.000.
- Foto bukti sintetis bernominal Rp75.000 disetujui Rp50.000 + keringanan Rp25.000 dengan alasan; nominal pengajuan
  tetap Rp75.000. Koreksi menjadi Rp60.000 + keringanan Rp15.000 membalik asal dan membuat pengganti.
- Warga ditolak dari laporan admin, rumah lain dan transparansi status iuran. Respons kas hanya tujuh field agregat;
  saldo rumah sendiri/tunggakan Rp25.000 terbaca. Ringkasan pengurus, warga dan laporan identik.
- CSV dan XLSX staging diunduh privat/no-store; dibaca ulang lokal memakai OpenSpout. Seluruh 12 baris bulanan sama
  dengan JSON, workbook **14 sheet**, tanpa formula. File hasil berisi data staging dan disimpan hanya di folder privat.
- Dua request HTTP bersamaan dengan key sama menghasilkan dua respons 201 dengan satu ID dan kas +Rp10.000 sekali.
  Dua key berbeda dengan fingerprint sama menghasilkan satu 201 dan satu 409 `FINANCE_PREVIEW_STALE`, tanpa uang ganda.
  Kedua penerimaan uji ini kemudian dibalik; saldo kembali ke posisi sebelum uji konkurensi.
- Rekonsiliasi sesudah skenario utama: **0 masalah**, saldo Rp3.840.000 (baseline Rp2.180.000 + penerimaan neto
  sintetis Rp1.660.000). Bukti cleanup akhir belum dicatat; ledger tidak akan dihapus.

APK 1018 emulator menampilkan teks privasi 25 Okt setelah server meminta versi 26 Okt. Upaya persetujuan lama
tidak mengubah status persetujuan (diperiksa via API). Upgrade harus membawa teks baru; tidak menyalin versi server saja.

## Emulator dan APK rilis

Kandidat 1019 dibatalkan sebelum distribusi untuk perbaikan kas. **1020** dari `afe4ebb` lulus workflow
[40283518119](https://github.com/morrispes5/RT-05-TAKEDA/actions/runs/40283518119), selesai 26 Okt **14.11.44 WIB**.
Sertifikat SHA-256 sama dengan 1018. Paket tetap `tech.morriz.takeda`, versi 1.0.0, versionCode arm64 **3020** / arm32 **2020**.
Kunci rilis hanya di runner, tidak dibuat/disimpan lokal. Kedua checksum artifact cocok.

Pixel_8a headless RAM 2 GB, tanpa wipe/uninstall: `adb install -r` **3018 → 3020** berhasil.
`firstInstallTime` tetap 21 Okt 20.45.35; sesi akun tambahan sintetis, avatar Keluarga dan umur 7 tahun tetap terbaca.
Teks privasi lokal 26 Okt tampil, checkbox awal belum dicentang, persetujuan berhasil tanpa login ulang.
Ringkasan kas warga hanya agregat, navigasi bulan berjalan; tidak ada nama/kode rumah lain.

Pengurus pada rumah khusus UJI18-14: memilih Oktober, mengisi Rp50.000, preview Rp25.000 kurang,
pilih lunas dengan keringanan. Alasan kosong ditolak; setelah alasan diisi, konfirmasi memisahkan uang diterima
Rp50.000 dan keringanan Rp25.000, pencatatan berhasil. November Rp100.000: preview alokasi Rp75.000,
lebih Rp25.000; pilih titipan, pencatatan berhasil. Pemeriksaan API independen menemukan tepat dua penerimaan
aktif total Rp150.000, keringanan Rp25.000, titipan Rp25.000, saldo naik tepat Rp150.000.
Login warga rumah itu memperlihatkan Oktober lunas dengan keringanan dan titipan Rp25.000, tanpa rumah lain.

Pengurus membuka Laporan & Excel, menyimpan workbook melalui dialog Android ke Downloads.
Berkas 49.549 byte ditarik kembali dan dibaca memakai OpenSpout: **14 sheet dan 12 baris bulanan identik dengan API**
sesudah transaksi emulator. Ini membuktikan unduhan/penyimpanan perangkat, bukan hanya mock picker widget.
Emulator dihentikan setelah selesai; AVD tetap utuh. PostgreSQL lokal juga dihentikan; pgdata sementara tetap lokal.

## Cleanup dan pemulihan

Semua pemakaian titipan uji dibalik dahulu, lalu penerimaan sintetis dibalik lewat API; sumber dan audit tetap ada.
Empat belas saldo titipan nol, kas kembali baseline **Rp2.180.000**, 14 rumah dan akun uji diarsipkan (tidak dihapus).
Rekonsiliasi akhir **0 masalah**. Ringkasan periode staging mempertahankan arus bruto/koreksi uji sebagai histori,
sehingga masuk/koreksi bruto berubah sementara kas neto kembali sama.

Backup pasca-v2 `rt05-20261026T0728Z.dump.gpg`: dump 388 KB, restore ke PostgreSQL 17 terisolasi total **12 detik**.
Pemeriksaan `backup-drill.sh` diperluas pada salinan operator untuk menambah alokasi, keringanan, penyesuaian,
penggunaan/mutasi titipan, tarif rumah dan view pelanggaran: **19 tabel/view cocok**, pelanggaran nol dan
mutasi titipan neto nol. Tidak menulis ke DB sumber. Berkas AES256 disalin ke Drive privat backup staging;
passphrase hanya privat operator. Ini drill staging, bukan bukti backup production terjadwal.
SHA-256 backup pasca-v2: `95c93220af826e0b7e67a4678bf8fc8a5069acbdec941bb25cabfb493b43765e`;
MD5 berkas lokal dan Drive sama (`1a31662646ad8c6730cc91826f930142`).

## Distribusi 26 Oktober 2026

Kedua APK diinstal ke volume unduh lalu `versi.json` terakhir pada **14.28.29 WIB**. Unduhan HTTP kedua ABI
dibandingkan ukuran/SHA-256 dengan artifact, halaman aplikasi dan tiga baris catatan rilis cocok.
Drive diupdate ke nama lama lalu direname ke 1020 pada **14.28 WIB**; file ID **1G3YAstTNabAD_Eo44QinoRdM9ZYfiixr** tetap,
ukuran arm64 **22.940.309 byte**. Link [Drive](https://drive.google.com/file/d/1G3YAstTNabAD_Eo44QinoRdM9ZYfiixr/view)
dan [unduh resmi](https://takeda-staging.morriz.tech/aplikasi).

| ABI | SHA-256 |
| --- | --- |
| arm64-v8a | `8a5bd737e6f06bf5deee828a329264142144064bdd559b73e433b3483c9fa203` |
| armeabi-v7a | `46c6c2b0d5e3621a0da31dd17eb7c320180a27bf4be634deb6737ddbe867c227` |

Smoke publik terakhir `/health/ready`, kontak publik (allowlist/cocok referensi privat), Kontak Ketua dan Profil Wirawan:
**4/4 PASS** 26 Okt 14.26 WIB. Nomor telepon/kredensial tidak dicatat.

Bukti layar hanya kebijakan publik, rumah khusus uji dan agregat: [sebelum upgrade](1018-privacy-before-upgrade.png),
[privasi baru](1020-privacy-after-upgrade.png), [konfirmasi pengurus](1020-payment-relief-confirmation.png),
[iuran rumah sendiri](1020-own-dues.png), [kas warga](1020-resident-cash.png), [laporan](1020-report.png).

M18 **passed untuk staging sintetis dan emulator**. Production tidak disentuh. Uji HP nyata/formal UAT mitra,
TalkBack, perpindahan VPS dan gate production M16 tetap terpisah; tidak dinyatakan selesai oleh bukti ini.
