# M18 lanjutan — Impor rekap iuran bendahara ("buku lama")

Tanggal: 29 Oktober 2026. Status: **rencana + implementasi di branch PR #103** (instruksi pemilik: digabung dengan M19R
agar merge bersama). Keputusan di ADR49. Fitur inti M18 (tarif khusus, keringanan, kurang bayar, sumbangan, titipan,
laporan Excel 14 sheet) sudah passed 26 Okt; dokumen ini hanya menambah jalur memasukkan data awal dari Excel bendahara.

## 1. Sumber data

Pemilik menerima `Rekapitulasi iuran warga RT 05` (xlsx) dari bendahara: sheet `IURAN 2024 (JAN)`, `IURAN 2025 (JAN)`,
`IURAN 2026`, `Lap Keu 2024`. Berkas berisi NIK, No KK, dan nama kepala keluarga. **Berkas dan hasil olahan apa pun yang
memuat alamat/status bayar rumah tidak masuk Git, CI, chat, maupun log.** Tes memakai berkas sintetis buatan tes.

Tata letak sheet iuran (sama di tiga tahun): baris 4 `JAN`…`DEC`, baris 5 `Iuran`/`Sumbangan`, data mulai baris 6.
Kolom F jalan, G blok, I nomor, P kondisi rumah, Q/S/U/…/AM iuran Jan–Des, R/T/…/AN sumbangan. Baris keterangan warna di
bawah tabel bukan rumah. Warna sel (merah kurang bayar, kuning belum bayar dari awal tahun, hijau, oranye/biru transfer)
**tidak dibaca**; yang menentukan hanya angka: sel kosong = belum dibayar.

Temuan baca 29 Okt (angka agregat, tanpa identitas): 187 rumah unik (blok+nomor tidak ganda), Mawar 8–14 blok D1–D7.
Sheet 2026 berisi Jan–Sep penuh, Okt berjalan, Nov–Des hanya rumah yang lunas setahun. Nominal sel: mayoritas 75.000,
juga 85.000, 55.000, 50.000, 35.000, 25.000. Kolom status/judul sheet tampak salinan antar tahun, jadi tidak dipakai.
`Lap Keu 2024` hanya sampai Okt 2024; saldo kas terbaru harus dari bendahara.

## 2. Model: "Lunas di buku lama" (tanpa kas)

Uang iuran sebelum aplikasi sudah ada di kas fisik dan akan masuk aplikasi sebagai **saldo awal kas** (fitur existing,
sekali, diisi bendahara). Karena itu pelunasan lama **tidak boleh** dicatat sebagai pembayaran: setiap pembayaran wajib
punya pemasukan kas sebesar total (invarian M18), sehingga kas akan terhitung dua kali.

Tabel baru `pelunasan_buku_lama`: satu baris aktif per tagihan, nominal positif, rujukan batch impor, dapat dibatalkan
sekali dengan alasan (append-only seperti keringanan). View `rt05_tagihan_saldo` mendapat kolom `terbayar_buku_lama`;
`sisa = efektif − uang − titipan − keringanan − buku_lama`. Semua layar/laporan yang memakai `sisa` otomatis benar.
API `terbayar` = uang + titipan + buku lama (seperti titipan, ada field rinci `terbayar_buku_lama`).
Label tagihan yang lunas hanya dari buku lama: **"Lunas (buku lama)"**. Kas, laporan arus kas, dan kas warga tidak berubah.

Tabel `impor_keuangan` mencatat batch: tahun (unik), sampai periode, sidik jari SHA-256 rencana, ringkasan angka,
pengurus pelaksana. Immutable.

## 3. Aturan impor (perintah `php artisan rt05:impor-buku-lama`)

Dua tahap. **Tanpa `--terapkan` tidak ada yang ditulis ke database.**

1. **Cek**: `rt05:impor-buku-lama <xlsx> --tahun=2026 --sampai=2026-10 [--keputusan=<json>] [--laporan=<xlsx>]`.
   Membaca sheet, memvalidasi header, menyusun rencana, mencetak ringkasan angka dan daftar hal yang perlu keputusan
   (kode rumah + bulan saja). `--laporan` menulis Excel tinjauan per rumah × bulan untuk dicek bendahara, di PC lokal.
2. **Terapkan**: perintah sama + `--terapkan --pengurus=<email pengurus aktif>`. Ditolak bila masih ada hal yang perlu
   keputusan, flag keuangan v2 mati, impor tahun itu sudah pernah ada, atau data rumah bentrok.

Per rumah (kunci = `Address::key(blok, jalan baku, nomor)`, kode = `Address::code`):

| Kondisi | Perlakuan |
| --- | --- |
| Rumah belum ada | Dibuat (`tagihan_aktif`, `mulai_tagih` = Januari tahun impor). Tanpa nama, NIK, KK, atau penghuni |
| Rumah sudah ada | Dipakai; `mulai_tagih` dimajukan ke Januari bila kosong/lebih lambat. Arsip/tagihan nonaktif → ditolak |
| Kode di daftar `tidak_ditagih` | Dilewati seluruhnya |
| Nominal bayar dominan ≠ tarif umum, tanpa keputusan | **Perlu keputusan** (tarif khusus atau tegaskan tarif umum). Tidak menebak |
| Keputusan tarif khusus | `tarif_rumah` khusus mulai Januari + alasan dari berkas keputusan |
| Bulan ≤ `--sampai` | Tagihan dibuat (hanya untuk rumah impor). Ada angka → lunas buku lama sebesar min(angka, tagihan) |
| Angka < tagihan | Sisa tetap = kurang bayar (bisa ditagih atau diberi keringanan lewat aplikasi) |
| Angka > tagihan | Kelebihan hanya dilaporkan (uangnya sudah di saldo awal kas); tidak jadi titipan/kas |
| Sel kosong ≤ `--sampai` | Belum dibayar (tunggakan, terlihat warga rumah itu) |
| Bulan > `--sampai` berisi angka | Lunas di muka: tagihan bulan itu dibuat untuk rumah itu saja lalu dilunasi buku lama |
| Tagihan bulan itu sudah ada | Dipakai. Nominal beda dari tarif keputusan → penyesuaian tagihan beralasan. Sudah ada pelunasan apa pun → ditolak |
| Rumah punya tarif khusus aktif | Ditolak (selesaikan manual) |

Tarif umum: bila belum ada tarif berlaku untuk Januari tahun impor, dibuat versi Rp75.000 mulai Januari dengan alasan
"rekap bendahara". Semua dalam satu transaksi dengan kunci `rt05:keuangan`; validasi invarian M18 berjalan saat commit.
Audit `keuangan.impor_buku_lama` hanya angka. Tidak ada notifikasi/push ke warga.

Berkas keputusan (JSON lokal, bukan Git):

```json
{
  "tarif": { "D1-12": { "nominal": 35000, "alasan": "Rumah kedua, disepakati pengurus" } },
  "tarif_umum": ["D2-5"],
  "tidak_ditagih": ["D6-9"]
}
```

## 4. Koreksi setelah impor

Pengurus membatalkan satu pelunasan buku lama yang salah lewat aplikasi (Keuangan rumah → bulan → **Batalkan catatan
buku lama**, alasan wajib). Tagihan kembali terbuka; sesudah itu bisa dicatat ulang sebagai pembayaran (bila uangnya
memang baru diterima), keringanan, atau dibiarkan sebagai tunggakan. API `POST /admin/old-book-settlements/{id}/cancel`.

## 5. Urutan pelaksanaan di production (butuh izin pemilik)

1. Bendahara menjawab pertanyaan tarif (85/55/50/35/25 rb, "LUNAS DK", tidak mampu, rumah kosong) → berkas keputusan.
2. Tentukan tanggal batas (`--sampai`) dan saldo kas resmi per tanggal itu.
3. Jalankan **cek** + laporan tinjauan; bendahara mencocokkan. Ulangi sampai tidak ada "perlu keputusan".
4. Uji **terapkan** pada branch Neon salinan production; rekonsiliasi harus 0.
5. Terapkan di production; bendahara mencatat saldo awal kas di aplikasi; rekonsiliasi 0; Excel lama jadi arsip.
6. Sejak tanggal batas, pencatatan hanya di aplikasi.

## 6. Tes

`M18OldBookImportTest` (berkas xlsx sintetis dibuat di tes): cek tanpa tulis DB; header salah ditolak; tarif berbeda
tanpa keputusan memblokir; impor lengkap (rumah baru/ada, tarif khusus, kurang bayar, lebih bayar, lunas di muka,
tidak ditagih, tunggakan), kas tidak berubah, rekonsiliasi 0; impor ulang ditolak; flag mati ditolak; bentrok pembayaran
ditolak tanpa tulis sebagian; pembatalan membuka sisa; label warga dan laporan tahunan memuat buku lama.
