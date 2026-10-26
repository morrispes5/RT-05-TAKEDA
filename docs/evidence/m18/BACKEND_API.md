# M18 — bukti backend dan API lokal

Tanggal: 26 Oktober 2026. Data seluruhnya sintetis. M18 **in_progress**.

## Lingkup

Rencana/ADR44 disetujui pemilik; PR #82 merged `f8135e6`.
PR #83 fondasi ledger merged `209e44e`, CI 5/5 PASS: API verification (termasuk drill worker Redis), build, hygiene, Vercel, komentar preview.
Kelanjutan API di branch `codex/m18-api-keuangan`, worktree `m17-verifikasi-1018`.
Flag keuangan v2 **false**; tidak ada deploy, APK M18, atau perubahan production.

## Hasil yang dijalankan

| Pemeriksaan | Hasil |
| --- | --- |
| DB/domain sebelum API — php artisan test | 374/374, 3.913 assertions |
| Final DB/domain subset setelah canonical hash/preflight | 48/48, 292 assertions |
| API — php artisan test sebelum proteksi pengajuan | 386/386, 4.034 assertions |
| API + proteksi pengajuan + skenario rapat terpisah — php artisan test | 389/389, 4.053 assertions |
| Tambahan terakhir profil versi_keuangan + tautan bukti lintas rumah — subset M18FinanceApiTest, OpenApiContractTest, AuthTest, M15bRequestsTest | 23/23, 590 assertions |
| Preview koreksi hipotetis (query saja, fingerprint keadaan saat ini) — API + kontrak | 16/16, 500 assertions |
| php vendor/bin/pint --test / --dirty (setelah format) | PASS |
| npx.cmd --yes @redocly/cli@2 lint docs/openapi.yaml | Valid, tanpa warning |
| node infra/scripts/check-repo.mjs; git diff --check | PASS |

PostgreSQL embedded 17 lokal port 54329, database `rt05_test`; migration role dan runtime role berbeda.
Tidak menggunakan Neon, `--env=testing`, atau data warga sebenarnya.
Bootstrap tes memverifikasi database lokal *_test sebelum membuat ulang schema.
Tes transaksi memaksa constraint deferred dieksekusi sebelum rollback; tes race memakai proses PHP/PostgreSQL independen.

## Perilaku penting

- 8 rumah tarif umum + 2 rumah tarif khusus: tagihan November dan kas Rp700.000; snapshot Oktober tetap Rp750.000.
- 10 tagihan Rp75.000, dua bayar Rp50.000: kas Rp700.000; pilihan keringanan menghasilkan pembebasan Rp50.000, pilihan kurang bayar menyisakan utang Rp50.000.
- Cicilan Rp50.000 + Rp25.000, serta penerimaan Rp75.000 lalu Rp25.000 dengan pilihan sumbangan/titipan.
- Titipan dipakai manual tanpa kas kedua; pemakaian bersamaan tidak membuat saldo negatif.
- Koreksi/pembalikan pembayaran, titipan, keringanan, penyesuaian dan kas atomik; kegagalan pengganti rollback seluruhnya.
- Pembayaran sumber titipan yang telah dipakai ditolak sampai pemakaian dibalik.
- Fingerprint saldo basi ditolak; retry key sama memberi satu pembayaran dan satu lot titipan.
- Dua pengurus menyetujui satu bukti: satu pembayaran; nominal pengajuan tetap, nominal penerimaan terkoreksi memerlukan alasan.
- SQL runtime gagal saat mengubah nominal/hapus ledger, membentuk penerimaan tanpa kas, saldo titipan negatif, sumber lintas rumah, atau status lunas palsu.
- Pengajuan bukti dikunci; tautan pembayaran lintas rumah ditolak constraint deferred.
- Warga tidak dapat membuka preview/tarif/titipan pengurus; rincian tagihan sendiri memakai sisa aktual.
- Rekonsiliasi memeriksa ledger baru dan kaitan konfirmasi; skenario valid menghasilkan nol masalah.

## Temuan yang diperbaiki selama pengujian

Cleanup concurrency semula mengaktifkan trigger ketika deferred events masih tertunda. Cleanup sekarang menyelesaikan constraint sebelum ALTER TABLE.
Tes rollback M07 dibatasi pada migrasi M07 karena ledger M18 forward-only.
Fungsi/view PostgreSQL perlu CREATE OR REPLACE agar bootstrap schema tes berulang berhasil.
Fixture lama yang mengedit tarif langsung diganti dengan versi tarif baru; snapshot keuangan tetap terkunci.

## Gate yang belum dibuktikan

Mobile M18, privasi baru/transparansi kas warga, laporan/CSV/XLSX, upgrade data staging, staging menyeluruh, emulator pengurus/warga,
upgrade APK lama dan distribusi satu APK M18. Bukti lokal ini tidak menutup gate tersebut dan tidak membuktikan kesiapan production.
