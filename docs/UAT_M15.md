# Paket UAT M15 — RT05 TAKEDA

Panduan menjalankan uji penerimaan pengguna (UAT) dengan pengurus dan warga yang disepakati mitra. Sumber tugas:
[TESTING.md](TESTING.md#uat). **Jumlah peserta dan semua hasil diisi dari sesi nyata; agent tidak mengisi nilai.**

## Persiapan (pemilik/fasilitator)

1. Lingkungan: **staging** `https://takeda-staging.morriz.tech` dengan data sintetis. Jangan memasukkan data warga asli
   sebelum privacy notice final ([PRIVACY_NOTICE_DRAFT.md](PRIVACY_NOTICE_DRAFT.md)) disetujui.
2. Aplikasi: APK rilis staging dari workflow **Mobile release APK** (artifact `rt05-takeda-staging-release-N`). HP yang
   sudah memasang APK debug harus uninstall dulu sekali (tanda tangan berbeda). Cocokkan SHA-256 file `.sha256`.
3. Akun: satu akun pengurus uji dan satu akun warga uji per peserta (dibuat pengurus lewat aplikasi/token registrasi).
   Kata sandi diberikan langsung, tidak lewat grup chat.
4. Persetujuan: jelaskan bahwa ini uji aplikasi, data yang dimasukkan adalah data contoh, sesi boleh dihentikan kapan
   saja, dan catatan disimpan tanpa nama (kode peserta P01, P02, …).
5. Siapkan kertas/Google Form pribadi dengan kolom pada lembar pencatatan di bawah.

## Tugas

Bacakan tujuan, bukan langkah. Fasilitator tidak membantu kecuali peserta macet >2 menit (catat sebagai "dibantu").

| ID | Peran | Tugas |
| --- | --- | --- |
| U01 | Publik | Tanpa login, temukan informasi fasilitas dan buka album Perayaan 17 Agustus di website. |
| U02 | Warga | Daftar dengan token rumah, masuk, lalu periksa data keluarga. |
| U03 | Warga | Kirim pengaduan dengan foto dari kamera, pilih sembunyikan identitas. |
| U04 | Pengurus | Temukan pengaduan U03, ubah status ke Diproses lalu Selesai dengan catatan. |
| U05 | Warga | Lihat status dan riwayat pengaduan U03; buka notifikasinya. |
| U06 | Warga | Kirim aspirasi; buka agenda terdekat dan tambahkan ke kalender; baca pengumuman. |
| U07 | Pengurus | Catat pembayaran iuran offline dua bulan untuk satu rumah. |
| U08 | Warga | Lihat status iuran sendiri untuk dua bulan tersebut. |
| U09 | Pengurus | Koreksi satu pembayaran yang salah (batalkan/balik) dan lihat kas. |
| U10 | Warga | Buka transparansi iuran (tersembunyi secara bawaan) dan pahami isinya. |
| U11 | Pengurus | Ekspor data iuran/kas. |
| U12 | Pengurus | Di website `/pengurus`, terbitkan satu artikel dan satu album; minta peserta lain membukanya di HP. |

## Lembar pencatatan (satu baris per peserta per tugas)

| Kode peserta | Peran | Tugas | Berhasil / gagal / dibantu | Waktu (menit) | Kebingungan (kutipan singkat) | Bug (langkah + layar) | Kemudahan 1–5 |
| --- | --- | --- | --- | --- | --- | --- | --- |
| | | | | | | | |

Skala kemudahan: 1 = sangat sulit, 2 = sulit, 3 = cukup, 4 = mudah, 5 = sangat mudah. **"Membantu/mudah" = nilai 4–5.**
Target TESTING.md: ≥80% peserta memberi nilai 4–5 (dihitung per peserta dari median nilai tugasnya). Bila target tidak
tercapai, catat apa adanya beserta tindak lanjutnya; jangan mengulang sesi hanya untuk memperbaiki angka.

## Setelah sesi

1. Masukkan lembar ke `docs/evidence/m15/uat/` sebagai CSV tanpa nama/email/nomor HP (hanya kode peserta).
2. Bug: beri tingkat **critical/high/medium/low**. Critical/high pada data atau keuangan wajib diperbaiki sebelum
   production (gate M15).
3. Ringkasan di `docs/evidence/m15/UAT_RESULTS.md`: jumlah peserta per peran, tingkat keberhasilan per tugas, persentase
   nilai 4–5, daftar bug dan statusnya, perubahan desain yang perlu arahan pemilik.
