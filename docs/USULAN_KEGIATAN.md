# Requirement — Usulan kegiatan warga dan standar alert aksi

Status: disusun 16 Oktober 2026 atas instruksi pemilik; mengikat untuk PR #27 (BR23, ADR24).
Bagian bertanda **default** adalah keputusan awal yang dapat diubah pemilik tanpa mengubah alur.

## 1. Tujuan

Warga dapat mengajukan kegiatan kepada pengurus RT dari aplikasi mobile. Pengurus menilai pengajuan.
Pengajuan yang **disetujui langsung menjadi agenda resmi RT**, dan **semua warga aktif menerima notifikasi**.
Agenda tetap satu-satunya jadwal resmi; pengajuan tidak pernah tampil sebagai agenda sebelum disetujui.

Contoh: Ibu Sri (akun warga aktif, rumah Blok D5 Jalan Mawar) ingin mengadakan "Badminton bersama di
lapangan RT 05" pada 23 Oktober 2026 pukul 16.00–18.00. Ia mengisi form di tab Agenda → "Ajukan kegiatan",
melampirkan susunan acara (PDF/TXT/foto), lalu mengirim. Pengurus menerima notifikasi, meninjau, lalu
menyetujui. Agenda "Badminton bersama di lapangan RT 05" muncul di tab Agenda semua warga dan setiap warga
aktif menerima notifikasi "Agenda baru". Pengingat H-1 berjalan seperti agenda lain.

## 2. Aktor dan hak

| Aktor | Boleh | Ditolak |
| --- | --- | --- |
| Warga aktif (pengusul) | Mengajukan; melihat dan mengunduh dokumen usulan sendiri; memperbaiki saat Diajukan atau Perlu revisi | Melihat usulan warga lain (404); mengubah setelah ditinjau/diputuskan |
| Warga aktif lain | Melihat agenda hasil persetujuan | Melihat usulan, pengusul, atau dokumen orang lain |
| Akun menunggu/nonaktif | — | Semua endpoint usulan (403/401) |
| Pengurus | Melihat semua usulan, pengusul, rumah, dokumen; meninjau, meminta revisi, menyetujui, menolak | Mengubah keputusan setelah usulan menjadi agenda |

## 3. Form pengajuan (mobile, warga)

Lokasi menu: tab **Agenda** → tombol utama **Ajukan kegiatan** dan daftar **Usulan saya**.

| Field | Wajib | Aturan | Bantuan di layar |
| --- | --- | --- | --- |
| Pengusul | otomatis | Nama akun dan kode rumah dari server; tidak dapat diketik | "Diajukan atas nama ... (rumah ...)" |
| Jenis kegiatan | ya | Olahraga, Kerja bakti, Keagamaan, Sosial/perayaan, Rapat/musyawarah, Lainnya | — |
| Nama kegiatan | ya | 5–160 karakter | Mis. "Badminton bersama di lapangan RT 05" |
| Tanggal | ya | **default**: paling cepat 24 jam dari sekarang, paling lambat 365 hari | Pemilih tanggal |
| Jam mulai | ya | WIB | Pemilih jam |
| Jam selesai | ya | Setelah jam mulai, maksimal 12 jam setelah mulai | Pemilih jam |
| Lokasi | ya | 3–200 karakter, teks bebas (tidak ada daftar fasilitas yang dikarang) | Mis. "Lapangan RT 05" |
| Deskripsi dan susunan acara | ya | 20–5000 karakter | Tujuan, peserta, susunan acara |
| Perkiraan peserta | tidak | 1–1000 | — |
| Kebutuhan dari RT | tidak | ≤1000 karakter | Mis. izin pakai lapangan, pengumuman, peminjaman meja |
| Dokumen pendukung | tidak | Maks. 3 berkas: PDF, JPG/PNG/WebP, atau TXT; maks. 8 MB per berkas, TXT maks. 1 MB | Tombol **Pilih file** (PDF/TXT/gambar) dan **Foto** (kamera/galeri) |
| Pernyataan | ya | Harus dicentang | "Saya penanggung jawab kegiatan ini dan akan menjaga kebersihan, ketertiban, serta keamanan lingkungan." |

Validasi tampil di dekat field yang salah sebelum request dikirim. Server memvalidasi ulang semua aturan.

## 4. Dokumen pendukung

- Jenis ditentukan dari isi berkas (bukan nama/ekstensi): PDF (`%PDF-`), JPEG/PNG/WebP, atau TXT (UTF-8 valid tanpa byte NUL).
- Gambar ditranskode ulang menjadi JPEG ≤1600 px tanpa EXIF/GPS; PDF dan TXT disimpan apa adanya.
- Disimpan privat dengan nama acak; nama berkas asli tidak disimpan. Unduhan `attachment`, `nosniff`, `no-store`.
- Hanya pengusul dan pengurus yang dapat mengunduh. Dokumen tidak ikut ke agenda.
- DOCX/XLSX tidak diterima pada v1 (risiko makro dan validasi). Warga diminta mengekspor ke PDF.

## 5. Status dan keputusan

```
Diajukan ──► Ditinjau ──► Disetujui (otomatis menjadi agenda resmi)
   │            │──────► Perlu revisi ──(pengusul memperbaiki)──► Diajukan
   │            └──────► Ditolak
   ├──► Perlu revisi
   ├──► Disetujui
   └──► Ditolak
```

- Catatan wajib untuk **Perlu revisi** dan **Ditolak**; opsional untuk Ditinjau dan Disetujui.
- **Disetujui** dalam satu transaksi: status berubah, satu baris agenda dibuat (nama, deskripsi, lokasi, mulai,
  selesai disalin sekali), riwayat dan audit dicatat, event outbox `agenda.dibuat` dan `usulan.dijadwalkan` ditulis.
  Tidak ada langkah "masukkan ke agenda" terpisah.
- Persetujuan ditolak (422 `PROPOSAL_DATE_PASSED`) bila waktu mulai sudah lewat; pengurus meminta revisi tanggal.
- Ditolak dan Disetujui adalah akhir. Usulan yang sudah menjadi agenda tidak dapat diubah statusnya (409).
- Versi optimistis: keputusan atau perbaikan dengan versi basi → 409, isian di layar tetap.
- Perbaikan saat Perlu revisi mengembalikan status ke Diajukan dan memberi tahu pengurus.
- Pengurus melihat peringatan **jadwal bentrok** bila ada agenda aktif lain yang waktunya beririsan (bukan pemblokir).

## 6. Notifikasi (inbox; push FCM menyusul M10)

| Kejadian | Penerima | Isi |
| --- | --- | --- |
| Usulan dikirim / diperbaiki dari Perlu revisi | Semua pengurus aktif | "Usulan kegiatan baru" / "Usulan kegiatan diperbaiki" — tanpa isi pribadi |
| Ditinjau, Perlu revisi, Ditolak | Pengusul | "Usulan \"…\" kini berstatus …" (catatan dibaca di detail) |
| Disetujui | Pengusul | "Usulan \"…\" disetujui dan masuk agenda RT." |
| Disetujui → agenda dibuat | Semua warga aktif | "Agenda baru: … — hari, tanggal jam WIB, lokasi." |
| H-1 sebelum agenda | Semua warga aktif | Pengingat agenda yang sudah ada |

Notifikasi dibuat sesudah commit lewat outbox; replay tidak menggandakan (unik event+penerima).

## 7. Standar alert untuk setiap tombol aksi (seluruh aplikasi)

Setiap tombol yang mengubah data atau mengirim sesuatu wajib memberi umpan balik yang jelas:

| Keadaan | Wajib | Komponen |
| --- | --- | --- |
| Sebelum aksi yang tidak bisa dibatalkan atau berdampak ke banyak orang (kirim usulan, setujui, tolak, arsip, catat pembayaran, logout semua) | Dialog konfirmasi berisi akibatnya, tombol utama bertuliskan aksinya | `confirm()` |
| Sedang diproses | Tombol menampilkan spinner, tidak dapat ditekan dua kali | `BusyButton` |
| Berhasil | Alert hijau dengan ikon centang, judul singkat dan kalimat akibatnya; untuk hasil penting (usulan terkirim, usulan disetujui) dialog hasil dengan langkah berikutnya | `showMessage(..., kind: success)`, `showResultDialog` |
| Gagal validasi | Pesan di dekat field + alert merah ringkas | inline + `showMessage(error)` |
| Gagal server / jaringan | Alert merah dengan ikon, pesan yang bisa ditindaklanjuti, isian tidak hilang | `showMessage(error)` |
| Konflik versi 409 | Alert kuning "Data sudah berubah", data dimuat ulang, isian tetap | `showMessage(warning)` |

Aturan tampilan: alert melayang di atas navigasi bawah, ikon + warna + teks (tidak hanya warna), kontras
memenuhi WCAG AA, terbaca pada skala teks 2,0, dapat ditutup, dan diumumkan pembaca layar. Pesan sukses
tidak pernah tampil sebelum server menjawab sukses.

## 8. Kriteria penerimaan

1. Warga aktif mengirim usulan lengkap dengan PDF, TXT, dan foto; berkas spoof/terlalu besar ditolak tanpa data parsial.
2. Validasi tanggal (<24 jam, selesai sebelum mulai, >12 jam), field wajib, dan pernyataan ditolak 422 dengan pesan per field.
3. Warga lain mendapat 404 untuk usulan dan dokumen orang lain; akun menunggu/nonaktif ditolak.
4. Pengurus meminta revisi (catatan wajib) → pengusul memperbaiki → kembali Diajukan → pengurus diberi tahu.
5. Pengurus menyetujui → tepat satu agenda dibuat, usulan terkunci, persetujuan ulang 409.
6. Semua warga aktif menerima tepat satu notifikasi agenda; pengusul menerima notifikasi persetujuan.
7. Persetujuan untuk tanggal yang sudah lewat ditolak 422; jadwal bentrok tampil sebagai peringatan.
8. Setiap tombol aksi pada alur ini menampilkan konfirmasi (bila relevan), spinner, alert sukses, dan alert gagal sesuai bagian 7; diuji widget test.
9. Kontrak OpenAPI, ERD, BUSINESS_RULES, dan PROGRESS diperbarui; CI hijau.

## 9. Di luar lingkup v1

Pendaftaran peserta/RSVP, anggaran dan dana kegiatan (keuangan tetap modul kas), pemesanan fasilitas,
komentar diskusi, push FCM (M10), publikasi agenda di website publik.
