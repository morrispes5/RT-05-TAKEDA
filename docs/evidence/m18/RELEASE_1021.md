# Rilis perbaikan QA — APK 1021

**Selesai untuk staging dan emulator.** Pemilik mengotorisasi merge/deploy/distribusi setelah
[QA_FIXES.md](QA_FIXES.md). [PR #90](https://github.com/morrispes5/RT-05-TAKEDA/pull/90) merged:
`b6e1c4b06602ad9899a984a597ac238c5bc7da85`; source patch `ab108b6`, sembilan pemeriksaan CI sukses.
Staging memakai image immutable SHA tersebut. Tidak mengubah production, DNS, firewall atau layanan VPS lain.

## Rilis dan distribusi

| Item | Bukti |
| --- | --- |
| Workflow rilis | [40324263431](https://github.com/morrispes5/RT-05-TAKEDA/actions/runs/40324263431), run 21, sukses; analyze/test/build dan verifikasi sertifikat lulus |
| Build selesai | **Senin, 26 Oktober 2026, 19.48.25 WIB** |
| Versi | **1.0.0 / build 1021**; package `tech.morriz.takeda`; versionCode arm64 **3021**, arm32 **2021** |
| `/unduh` | **Selasa, 27 Oktober 2026, 00.12.04 WIB**; kedua APK lebih dulu, `versi.json` terakhir secara atomik |
| Drive | **Selasa, 27 Oktober 2026, 00.07.29 WIB**; file ID tetap **1G3YAstTNabAD_Eo44QinoRdM9ZYfiixr**, nama diperbarui ke 1021 |
| Link | [Drive arm64](https://drive.google.com/file/d/1G3YAstTNabAD_Eo44QinoRdM9ZYfiixr/view), [unduh resmi kedua ABI](https://takeda-staging.morriz.tech/aplikasi) |

Build 1020 sudah pernah disebar, sehingga perbaikannya memakai nomor baru 1021 agar upgrade Android meningkat.
Kunci rilis tetap hanya digunakan di runner. Kedua APK diverifikasi lokal dan CI; sertifikat SHA-256 sama dengan 1020:
`dba8bc3f6179cb747aace1b9bada7abbc568583d26bb17649374f6b759e4069a`.

| ABI | Byte | SHA-256 |
| --- | ---: | --- |
| arm64-v8a | 22.940.309 | `164bf8a4e614dbe9ec6a9ecddea1b7605d29da4ab545d20659b43434d5f1f49a` |
| armeabi-v7a | 20.807.777 | `810fef1c767f832af39b9f9241e3609c1c2c6f6693551df62e541b42dd68ad5a` |

HTTP kedua APK, ukuran dan SHA-256 cocok artifact; manifest serta catatan di halaman aplikasi cocok.
Drive dibaca ulang sebagai binary 22.940.309 byte dan SHA-256 cocok arm64; metadata memastikan ID/folder tetap.
Rclone gagal 403 `rateLimitExceeded` pada proyek klien bersama sebelum mengganti isi. Fallback connector
`update_file(file_uri)` berhasil mengganti binary dan nama pada ID yang sama, tanpa mengubah sharing/parent.
Kemampuan upload connector saat ini sudah tersedia; catatan lama yang menyebut tidak bisa upload binary kedaluwarsa.

Isi rilis: budget/hash-work auth, guard sesi dokumen/push/inbox, antrean consent dengan push pending tetap bekerja,
nominal bukti cicilan berdasarkan sisa, dan teks pembalikan yang tepat. Tidak ada perubahan skema/ledger.

## Staging dan pemulihan

- SHA-256 skrip deploy diverifikasi penuh: `aa50ca1a629ba5a7b0c29b7c24ac357118a96f5ded6246a35f55e693b6c52c75`.
  Deploy hanya lewat skrip immutable yang diotorisasi.
- Backup sebelum deploy terenkripsi; restore ke PostgreSQL 17 terisolasi selesai 11 detik, **19 tabel/view cocok**.
  Salinan encrypted 237.792 byte diverifikasi metadata pada folder Drive backup **privat**, bukan folder APK publik.
  SHA-256: `d536129e5b757a79794ad22f9c2cb8c5e6ec2d7fd6780c3283453e01bec833b1`.
- `staging-runtime-smoke.sh` PASS: role pooled/direct, marker outbox bertahan setelah restart Redis antrean,
  heartbeat, image/volume dan container lain tidak berubah. Tidak mengosongkan antrean/cache.
- API, tiga worker dan scheduler terbukti memakai v2; ekstensi XLSX tersedia dan view pelanggaran kosong.
- HTTP `/health/ready` 200; public contacts hanya key `ketua_whatsapp`, profil/kontak memuat nama publik yang benar.
  Nomor kontak nyata tidak disalin ke Git.
- Recovery 20 email sintetis tak dikenal berurutan mendapat 202; request 21 memakai email lain ditolak 429 dengan
  Retry-After. Ini tes terbatas, bukan uji DoS atau bukti timing jaringan konstan.
- Satu rumah/dua akun sintetis khusus QA1021 dibuat. Pembayaran Rp50.000 atas tagihan Rp75.000 direplay idempoten
  dan membukukan tepat Rp50.000; sisa Rp25.000. Akun pending tetap menunggu, GET layanan iuran ditolak 403.
- Sesudah cleanup, uji baca staging 60 detik/20 pengguna: **895/895 expected, 0 unexpected, P50 339 ms,
  P95 710 ms**, gate <1.000 ms lulus. 843 respons200 dan52 respons404 yang eksplisit diharapkan untuk admin
  tanpa rumah, bukan klasifikasi semua4xx sebagai sukses. Metrik lengkap hanya di direktori privat operator.

## Emulator dan cleanup

Pixel_8a headless 2 GB, tanpa wipe/uninstall. APK 1020 direproduksi pada fixture cicilan: saran bukti **75000**.
`adb install -r` ke 1021 sukses; versionCode 3020→3021, `firstInstallTime` tetap **21 Okt 20.45.35**,
`lastUpdateTime` **27 Okt 00.02.53**. Sesi warga dan data keluarga/tagihan tetap terbaca tanpa login ulang.

APK 1021 menyarankan **25000**, dengan label **Sisa Rp25.000 dari Rp75.000**. Pengurus masuk dan membuka
dialog pembatalan: **Saldo tagihan Oktober 2026 dihitung ulang**. Dialog dibatalkan tanpa membukukan perubahan UI.
Bukti aman, seluruh data sintetis: [sebelum patch](1020-proof-before-qa-fix.png),
[bukti cicilan 1021](1021-partial-proof.png), [dialog pembalikan 1021](1021-cancellation-copy.png).

Setelah uji, penerimaan sintetis dibalik melalui API; permohonan sintetis diselesaikan, satu rumah dan dua akun
diarsipkan, tidak dihapus. Saldo staging kembali baseline **Rp2.180.000**, rekonsiliasi akhir **0 masalah**.
Emulator dihentikan dan AVD dipertahankan. PostgreSQL lokal tetap berhenti. Fixture/kredensial/token hanya di
direktori privat operator, tidak di Git. Perubahan MyRepublic pada checkout asli tetap utuh.

## Batas bukti

Regresi source: backend **413/413, 4.359 assertions**, Flutter **326/326**, website **40/40, 538 assertions**,
analyze/format 98/0/Pint/OpenAPI/hygiene PASS. Rincian dan review independen ada di QA_FIXES.
Race dokumen, push/consent dan token-refresh pending dibuktikan tes terkontrol, bukan klaim memicu semua
urutan race di Android nyata. Tidak ada uji HP fisik/formal UAT mitra atau production pada sesi ini.
M18 tetap passed staging sintetis/emulator; langkah berikutnya gate M16/UAT/pindah VPS sesuai ADR43.
