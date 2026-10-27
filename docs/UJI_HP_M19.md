# Uji HP nyata — tombol panik M19 (APK 1025)

Gate terakhir M19. Emulator sudah lulus; yang belum terbukti: **bunyi, getar, layar terkunci, aplikasi tertutup, dan
ketepatan waktu di HP Android asli**. Isi kolom Hasil, lalu kirim ke agent (foto/rekaman layar boleh, tanpa data warga).

## Persiapan

- **APK 1025** (Selasa 27 Okt 13.39 WIB) dari Drive ID tetap
  `https://drive.google.com/file/d/1G3YAstTNabAD_Eo44QinoRdM9ZYfiixr/view` atau `https://takeda-staging.morriz.tech/aplikasi`.
  Pasang menimpa versi lama.
- Dua akun uji (email + kata sandi di Desktop pemilik `RT05-akun-uji-M19-1023.txt`, bukan Git/chat):
  - **HP penerima** = akun `uji.m19.warga@contoh.test` → **HP asli pemilik** (yang diuji bunyinya).
  - **Pengirim** = akun `uji.m19.pengurus@contoh.test` → HP kedua (keluarga/tim) **atau emulator yang dijalankan agent**.
- Staging hanya mengirim alarm ke dua akun uji ini (restricted). Warga asli tidak menerima apa pun.
- Batas: 3 laporan per akun per jam, satu laporan aktif per akun. Pengurus menutup tiap laporan uji dengan alasan
  "pengujian" dan **tanpa** menandai penyalahgunaan.

## Checklist

| # | Langkah (di HP penerima kecuali disebut lain) | Yang diharapkan | Hasil |
| --- | --- | --- | --- |
| 1 | Pasang 1025, login akun warga uji | Diminta setuju Kebijakan Privasi versi 27 Okt (bila belum) | |
| 2 | Izinkan notifikasi saat diminta Android | Izin notifikasi aktif | |
| 3 | Beranda → Tombol panik → "Periksa izin dan bunyi darurat di HP" | Pengaturan kanal "Darurat RT05" terbuka; bunyi aktif | |
| 4 | Tombol panik → "Tambahkan shortcut di layar utama HP" | Dialog Android muncul; ikon shortcut ada di layar utama | |
| 5 | Ketuk shortcut | Hanya membuka halaman tombol panik, **tidak mengirim apa pun** | |
| 6 | Mulai laporan → centang ketentuan **tanpa** memilih jenis | Tombol "Lanjut ke konfirmasi" tetap abu-abu | |
| 7 | **Pengirim** kirim laporan "Kemalingan" saat aplikasi penerima **terbuka** | Penerima: notifikasi + bunyi alarm + getar ≤ 1 menit | |
| 8 | Ketuk notifikasi di penerima | Detail laporan: jenis, nama pelapor, rumah | |
| 9 | Pengurus tutup laporan (selesai, alasan "pengujian") | Status selesai; tidak ada penalti | |
| 10 | Penerima: aplikasi di **latar belakang** (tekan Home), pengirim kirim lagi | Bunyi + getar, ketuk notifikasi membuka detail | |
| 11 | Penerima: **layar terkunci**, pengirim kirim lagi | Bunyi + getar; isi di layar kunci disembunyikan | |
| 12 | Penerima: aplikasi **ditutup paksa** (swipe dari recent apps) | Masih berbunyi (catat bila tidak) | |
| 13 | Penerima: mode **senyap/getar** di HP | Catat: bunyi atau hanya getar | |
| 14 | Catat jeda kirim → bunyi (detik) untuk langkah 7, 10, 11, 12 | Target ≤ 60 detik | |
| 15 | Pelapor (pengirim) | **Tidak** menerima alarm laporannya sendiri | |
| 16 | Opsional pengurus: tandai satu laporan "palsu + penyalahgunaan", lalu "Batalkan penanda penyalahgunaan" | Hitungan penyalahgunaan kembali; pelapor mendapat notifikasi | |

Catat juga: merek/tipe HP, versi Android, mode hemat baterai (aktif/tidak), dan apakah bunyi berbunyi sekali atau berulang.

## Keputusan pemilik setelah uji (HANDOFF bagian 6 A–C)

- A. Warga yang belum membuka aplikasi versi baru tidak menerima alarm: cukup, atau perlu pengingat?
- B. Isi push darurat tetap umum (tanpa jenis) di layar kunci, atau tampilkan jenis kejadian?
- C. Bunyi sekali sudah cukup, atau dibuat berulang sampai ditekan?

## Setelah lulus

Agent mencatat hasil ke `docs/evidence/m19/`, menutup M19 di PROGRESS/HANDOFF bila semua baris kritis (7, 8, 10, 11,
14, 15) lulus, mengembalikan staging ke kondisi uji (laporan ditutup), lalu lanjut ke M16 (production Biznet).
