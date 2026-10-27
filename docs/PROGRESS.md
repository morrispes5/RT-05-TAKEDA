# Progress — RT05 TAKEDA

## Berikutnya — 27 Oktober 2026 sore

Uji HP nyata M19 dengan APK 1025: [UJI_HP_M19.md](UJI_HP_M19.md). Setelah pemilik membeli Biznet: cek koneksi
`infra/shared/cek-hosting.php` (RUNBOOK_PRODUCTION 1a), lalu M16. Ringkasan serah terima: HANDOFF bagian 0.

## Audit M17–M19 sebelum M16 — 27 Oktober 2026 (Claude)

Diminta pemilik setelah sesi GPT menyelesaikan M17–M19. Ringkasan dan tabel lengkap:
[AUDIT_2026-10-27.md](evidence/m19/AUDIT_2026-10-27.md). Baseline `7d3187c` hijau (backend 431, Flutter 339, website 41).
Branch `codex/m19-audit-perbaikan` memperbaiki: flag M18/M19 + cron 1 menit di template/runbook production shared,
panik memakai **minimal** versi privasi (bukan sama persis), pelapor tidak menerima alarm sendiri, pembatalan penanda
penyalahgunaan append-only (migrasi `2026_10_28_000100`), jenis darurat wajib dipilih, validasi ledger sekali per
kelompok perubahan + indeks (migrasi `2026_10_28_000200`; tes skala 16.404 ms → 33 ms), tes alarm mode shared
30 penerima. Validasi: backend **435/435**, Flutter **341/341**, analyze/format/Pint/check-repo PASS.
M19 tetap in_progress: gate HP nyata belum. Tiga keputusan pemilik baru di HANDOFF bagian 6.
Lanjutan (PR #96, staging `613466b`): dashboard tanpa rumah arsip, jarak label panik. **APK 1025** dirilis Selasa 27 Okt
13.38 WIB (`/unduh`) dan 13.39 WIB (Drive, ID tetap); 1024 tidak disebar. Uji HP nyata M19 memakai 1025.

## M19 — Tombol panik — 27 Oktober 2026: **in_progress (staging terbatas; menunggu HP nyata)**

**Gate rilis 27 Oktober:** PR #92 merged menjadi `e9c5f5e`; CI source 10/10, staging immutable berhasil,
backup terenkripsi sebelum migrasi berhasil direstore (19 tabel/view cocok) dan salinan privat Drive terverifikasi.
Fitur staging aktif **restricted untuk dua akun sintetis**, bukan warga asli; rekonsiliasi tetap nol masalah.
APK signed 1022 berhasil dibangun dan upgrade emulator tanpa uninstall, tetapi **dibatalkan sebelum distribusi**:
emulator menemukan tanggal footer privasi masih 26 Oktober meski isi/kontrak sudah 27 Oktober.
Footer diperbaiki dan ditambah tes widget yang memeriksa tanggal serta payload persetujuan versi 27 Oktober.
PR #93 merged `8d356e44` setelah CI 7/7; tree identik source `873074a5`. Flutter **339/339**,
format 101/0 dan analyze bersih. Staging diperbarui ke SHA tersebut; runtime-smoke dan enam layanan PASS,
rekonsiliasi tetap **0 masalah / Rp2.180.000**. Workflow signed run `40448591468` / nomor 23 success:
**1023 (3023/2023) dirilis Selasa 27 Okt, `/unduh` 11.37.23 WIB, Drive 11.37.38 WIB (ID tetap)**.
Kedua ABI HTTPS dan unduh balik Drive byte/hash cocok artifact; sertifikat rilis tetap. Upgrade in-place,
consent tanggal benar, Home kedua peran, pin launcher, FCM foreground/background dan tap detail teruji di emulator.
Dua laporan sintetis selesai, retry satu rekaman, penalti nol, fitur restricted dipulihkan. Gejala tampilan hitam/beku
pada renderer lama dicatat; buka ulang, intent/shortcut dan notifikasi ulang lulus. Restart AVD tanpa wipe dengan
renderer software memulihkan sesi pengurus, Home dan pergantian akun normal; akar penyebab belum dipastikan.
Uji HP nyata wajib mengulang login/resume/tap serta bunyi/getar; **M19 belum passed**.
Backup pasca-migrasi direstore, **20 tabel/view cocok**,
salinan terenkripsi Drive privat terverifikasi. Bukti rilis: [RELEASE_1023.md](evidence/m19/RELEASE_1023.md).

### Riwayat implementasi lokal (sebelum rilis)

**Rilis uji diminta pemilik:** lanjut publikasi build terbaru ke unduh/Drive untuk uji HP. API GitHub dipakai tanpa
transport Git ke origin; staging hanya jalur immutable yang sudah ada, dengan penerima sintetis. Beranda pengurus
ditambah akses cepat panik, pilihan pin shortcut ke layar utama HP ditambahkan. Flutter **337/337**, analyze bersih,
format 101/0 dan build debug ulang PASS. APK bertanda tangan/deploy/distribusi belum diklaim sebelum verifikasi.
Penyempurnaan akses Home: tombol pengurus dipisahkan dari dashboard keuangan agar tetap tersedia saat dashboard
gagal; regresi baru lulus. Flutter terbaru **338/338**, analyze bersih. Source PR #92 memakai SHA identik lokal
melalui API, tanpa Git fetch/pull/push ke origin.

**Eksekusi diotorisasi pemilik:** “okee gass lgsg aja eksekusi”. Branch `codex/m19-tombol-panik` di worktree
`m17-verifikasi-1018`; tanpa fetch/pull/push/PR/deploy remote, checkout awal/MyRepublic tetap utuh.
DB/domain/API/kontrak, idempotensi/lock/trigger, pembatasan laju, penanganan/penyalahgunaan dan kendali fitur,
outbox/inbox/FCM, privasi 27 Oktober, alur mobile tiga langkah + shortcut/kanal Android telah ditulis.
Default deployment **nonaktif**, audience restricted kosong; tidak ada broadcast ke warga asli.

Regresi awal menemukan dan memperbaiki status bawaan respons baru, fungsi trigger saat migration tes ulang,
serta CHECK notifikasi yang harus mempertahankan bukti iuran M15b tanpa tagihan. Guard role segar dan cache
idempotensi ID saja mencegah catatan pengurus/identitas lama terbuka. Status provider dipisahkan dari bukti HP.
Hasil/gate akhir sesi: [LOCAL_IMPLEMENTATION.md](evidence/m19/LOCAL_IMPLEMENTATION.md).
Tes lokal final: backend **431/431, 4.518 assertions**, Flutter **335/335**, website **41/41, 549 assertions**;
analyze/Pint/format 101/0, OpenAPI dan kontrak Compose PASS. Build Android lokal dicatat di bukti.
M19 belum passed: belum staging, APK rilis, uji alarm HP nyata atau UAT. Rilis terakhir tetap APK 1021.

### Riwayat perencanaan 27 Oktober

Pemilik mengoreksi prioritas: **M17 → M18 → M19 → M16 → M20** (ADR45), tombol panik sebelum M16.
Ketentuan Claude sudah ada di ADR43 butir 8 dan ROADMAP M19. Rencana rinci baru:
[M19_PLAN.md](M19_PLAN.md), memisahkan ketentuan yang diputuskan, rancangan teknis, rincian terbuka dan matriks uji.
ROADMAP, HANDOFF, COORDINATION dan skill init diselaraskan. Sesi memakai branch lokal
`codex/m19-rencana-panik` di worktree `m17-verifikasi-1018`; checkout awal/MyRepublic tidak disentuh.
Instruksi terbaru: jangan fetch/pull/push atau mengakses remote `origin` otomatis; lanjut dari dokumentasi lokal.

Belum ada kode, migrasi, perubahan API, deploy, APK atau tes runtime M19. Alarm di HP nyata tetap gate;
angka tes M18 di bawah adalah riwayat baseline. Validasi dokumentasi: `node infra/scripts/check-repo.mjs`
**PASS (1.079 berkas)** dan `git diff --check` **PASS**. Perubahan sesi ini hanya tujuh berkas dokumentasi;
tidak ada fetch/pull/push, PR atau merge remote setelah instruksi kerja lokal.
Berikutnya inspeksi kode auth/privasi/inbox/outbox/FCM dan rincian teknis M19 sesuai plan; gate M16 tetap berlaku.

## M18 — Keuangan fleksibel — 26 Oktober 2026: **passed (staging sintetis + emulator)**

**Rilis QA 27 Okt selesai:** PR #90 merged `b6e1c4b`, CI source 9/9; staging deploy immutable SHA yang sama,
runtime-smoke PASS, v2 tetap aktif, rekonsiliasi akhir 0 masalah. APK **1021/1.0.0**, versionCode **3021/2021**,
workflow 40324263431 sukses **Senin 26 Okt 19.48.25 WIB**; Drive ID tetap diperbarui **Selasa 27 Okt 00.07.29 WIB**,
`/unduh` kedua ABI+metadata terakhir **00.12.04 WIB**. Hash artifact/HTTP/Drive cocok; sertifikat tetap sama.
Upgrade emulator 1020→1021 tanpa uninstall mempertahankan sesi/data/firstInstallTime; bukti cicilan Rp25.000
dan copy pembalikan baru benar. Satu transaksi uji dibalik, satu rumah/dua akun diarsipkan, kas kembali baseline.
Backup/restore 19 tabel/view cocok 11 detik dan encrypted offsite privat. Uji baca 20 pengguna: **895/895 expected,
0 unexpected, P95 710 ms**, gate lulus. Emulator/PG lokal berhenti.
Bukti/batas: [RELEASE_1021.md](evidence/m18/RELEASE_1021.md). Production/HP nyata/UAT mitra tetap gate M16.

**Perbaikan QA 26 Okt**, branch `codex/m18-perbaikan-qa`: QA-S1–S3 dan QA-F1–F3 diperbaiki pada kode lokal.
Backend **413/413, 4.359 assertions**, Flutter **326/326**, website **40/40, 538 assertions**; Pint/analyze/format 98/0,
OpenAPI/check-repo/diff-check PASS. Guard token/akun/epoch melindungi dokumen dan navigasi push/inbox;
consent tetap menahan layanan, pending tetap menerima push persetujuan. Recovery/login/reset memiliki budget IP
agregat dan kerja hash pembanding; cicilan memakai sisa. Tidak ada migrasi/perubahan ledger.
Bukti dan batas: [QA_FIXES.md](evidence/m18/QA_FIXES.md). Belum deploy/APK baru; staging/APK 1020 tetap versi sebelumnya.
PostgreSQL lokal dihentikan; pekerjaan MyRepublic pada checkout asli tetap utuh.

**Audit QA ulang 26 Okt (kode `1955d69`, `codex/m18-qa-audit`):** backend **406/406, 4.316 assertions**,
website **40/40, 538 assertions**, Flutter **308/308**, analyze/Pint/format/check-repo PASS; dependency runtime
tanpa advisory. Uji baca staging 20 pengguna: **899/899 expected**, 0 unexpected, **p95 656 ms**; 43 respons 404
adalah fixture admin tanpa rumah yang eksplisit diharapkan. Issue #47 masih terbuka, bukti teknis diperbarui.
**Lima bug baru direproduksi dengan tujuh PoC:** security medium budget recovery dan dokumen sesi lama,
security low hash-work akun, serta P2 nominal bukti cicilan dan push melewati consent privasi.
Tidak ditemukan high/critical atau uang hilang/ganda pada cakupan audit; manual parsial (141 file unik dibaca penuh).
Rincian: [QA_REVIEW.md](evidence/m18/QA_REVIEW.md). Implementasi belum diperbaiki;
**tangani sebelum gate production M16**. Passed staging/emulator di bawah bukan clearance production.
Audit tidak melakukan merge/deploy/rilis APK; PostgreSQL lokal dihentikan setelah uji.

**Penutupan 26 Okt:** kode implementasi main dan staging `afe4ebb` (PR #88 merged, CI 9/9 PASS), v2 aktif hanya staging;
PR #87 laporan merged `6b44763`, CI 9/9 PASS. Backend **406/406, 4.316 assertions**, Flutter **308/308**,
analyze bersih, format **96/0**, Pint PASS. Backup/restore/offsite pra-migrasi, kompatibilitas flag off, migrasi 2/2,
aktivasi seluruh service dan skenario uang HTTP staging lulus. Kas delapan penuh + dua Rp50.000 = Rp700.000;
titipan tidak menambah kas kedua, koreksi append-only, request bersamaan/idempotensi/stale preview aman,
JSON/CSV/XLSX 14 sheet cocok dan rekonsiliasi **0 masalah**. Bukti: [STAGING_ACCEPTANCE.md](evidence/m18/STAGING_ACCEPTANCE.md).
APK **1020** dari `afe4ebb` lulus workflow rilis, sertifikat sama dengan 1018 dan upgrade in-place **3018 → 3020**
tanpa uninstall mempertahankan sesi/avatar/data. Privasi 26 Okt disetujui dari teks lokal baru. Pengurus mencatat
Rp50.000 + keringanan Rp25.000 dan Rp100.000 dengan titipan Rp25.000; pemeriksaan API independen cocok, warga
melihat hasil rumah sendiri. Excel disimpan melalui Android dan dibaca ulang: 14 sheet/angka sama dengan API.
Seluruh transaksi sintetis dibalik, 14 rumah/akun uji diarsipkan; kas kembali **Rp2.180.000** dan rekonsiliasi akhir **0 masalah**.
Backup setelah v2 + restore terisolasi memeriksa **19 tabel/view**, cocok semua (12 detik), salinan terenkripsi offsite.
Emulator dan PostgreSQL lokal dihentikan; AVD/data tidak dihapus. Kedua APK dan metadata terakhir disebar di `/unduh`
**Senin 26 Okt 14.28 WIB** dan Drive **14.28 WIB**, ID file tetap. Ini satu build M18 yang disebar; 1019 dibatalkan.
Jangan rollback M17 atau menonaktifkan v2 setelah transaksi v2. Production dan UAT HP nyata belum diklaim.
Saat penutupan M18, berikutnya direncanakan M16/UAT mitra dan pindah VPS staging 2 Nov; **prioritas terbaru ADR45 mendahulukan M19**.
Catatan implementasi bertahap di bawah adalah riwayat pada tahap masing-masing.

Prasyarat M17 ditutup lewat [PR #81](https://github.com/morrispes5/RT-05-TAKEDA/pull/81), merge `0f3b251`,
CI penutupan 3/3 sukses (hygiene, Vercel, komentar preview); tes aplikasi tercatat pada bukti M17 di bawah.
Branch `codex/m18-rencana-keuangan`: [M18_PLAN.md](M18_PLAN.md) + ADR44 disetujui; PR #82 merged `f8135e6`.
Pemilik sudah memilih titipan **dipakai manual oleh pengurus**. Rencana memetakan kode/trigger/rekonsiliasi yang ada,
perubahan ledger dan API, pembalikan sumber titipan yang sudah dipakai, privasi, laporan/XLSX, migrasi dan rollback,
serta skenario uang wajib. Default bagian 9 diterima lewat persetujuan rencana.

Validasi perencanaan: sumber backend/migrasi/kontrak/Flutter dan composer dibaca langsung. Dry-run Composer pada
salinan konfigurasi sementara memilih OpenSpout **4.32.0** (1 paket baru, 0 update/removal), PHP **8.4.13** dengan
ext-dom/fileinfo/filter/libxml/xmlreader/zip tersedia. Tidak memasang dependency ke repo; ini bukan tes XLSX/M18.
`node infra/scripts/check-repo.mjs` dan `git diff --check` dijalankan untuk perubahan dokumen.
**25 Okt: pemilik menyetujui lanjut implementasi M18 dengan pengujian luas.** ADR44 menjadi diputuskan. Berikutnya PR DB/domain; belum ada deploy/APK M18.

**Checkpoint implementasi DB/domain (dilanjutkan 26 Okt):** worktree `m17-verifikasi-1018`, branch `codex/m18-db-domain` dari main `f8135e6` (PR #82 rencana disetujui/merged).
Migration ledger + `FlexibleDues` mencakup pembayaran parsial, keringanan, sumbangan/titipan manual, snapshot tarif khusus, koreksi/pembalikan, fingerprint saldo, lock transaksi, dan rekonsiliasi.
Flag v2 tetap nonaktif; belum endpoint/mobile/aktivasi staging. ERD diperbarui; kontrak HTTP belum berubah.
Pengujian awal: 14 tes M18 / 88 assertions lulus, termasuk tiga proses independen untuk retry idempoten dan fingerprint basi.
Full suite pertama menemukan kegagalan cleanup deferred trigger dan scope rollback M07, sudah diperbaiki; subset regresi 21/21 lulus.
Tes lengkap ulang: `php artisan test` **374/374, 3.913 assertions**, PostgreSQL lokal port 54329. Pint PASS.
Tambahan akhir canonical payload idempotensi dan preflight migrasi diuji ulang terarah sebelum commit.
Belum klaim M18 passed atau siap production. Kontrak HTTP belum berubah; production tidak disentuh.

**Checkpoint API 26 Okt:** PR #83 fondasi ledger merged `209e44e` setelah CI 5/5 PASS (API verification termasuk drill Redis, build, hygiene, Vercel).
Branch `codex/m18-api-keuangan` menambah preview/fingerprint, API pembayaran fleksibel, persetujuan bukti atomik,
tarif rumah/umum dan koreksinya, titipan/keringanan/penyesuaian/kas dengan pembalikan+pengganti, serta rekap sisa.
Semua mutasi keuangan v2 mensyaratkan versi client 2 dan idempotensi; flag tetap false. OpenAPI/API diperbarui.
Suite penuh sebelum proteksi tambahan pengajuan: **386/386, 4.034 assertions**; Pint PASS; OpenAPI lint tanpa warning.
Setelah proteksi nominal asli bukti/tautan pembayaran: subset **42/42, 720 assertions**. Tes skenario rapat tarif khusus dan
dua rumah keringanan/kurang bayar ditambah; suite lengkap akhir **389/389, 4.053 assertions**.
Tambahan informasi versi alur keuangan pada profil akun dan tes penolakan tautan bukti lintas rumah:
subset akhir **23/23, 590 assertions**; Pint, lint OpenAPI tanpa warning, check-repo dan diff-check PASS.
Rincian/batas bukti: [BACKEND_API.md](evidence/m18/BACKEND_API.md).
PR #84 API dibuat. Pemeriksaan alur mobile menemukan kebutuhan preview koreksi sebelum implementasi UI:
preview kini menerima referensi pembayaran/pemakaian asal dan menampilkan sisa setelah pembalikan secara query,
tanpa menulis ledger; fingerprint tetap keadaan saat ini. Subset koreksi/API/kontrak **16/16, 500 assertions** PASS.
Integrasi mobile menambah riwayat per rumah dan penggunaan titipan pada response yang sama, serta eager-loading saldo
agar rekap tidak membuat query per rumah. Snapshot ringkasan rumah dikunci selama dibaca. Tes 10 rumah membatasi query
rekap maksimal 15 dan memastikan riwayat rumah lain tidak terbuka; subset API/kontrak **17/17, 509 assertions** PASS.
Rincian penerimaan kini mencakup bulan yang seluruh sisanya dilunasi keringanan, agar koreksi berikutnya tidak kehilangan
bulan tersebut. Tes dua periode dengan pembayaran Rp50.000 + keringanan: subset API/kontrak **18/18, 515 assertions** PASS.
PR #84 API merged `ee18f55`, CI 5/5 PASS pada head `672f447`.

**Mobile pengurus 26 Okt**, branch `codex/m18-mobile-pengurus`: alur aktif mengikuti versi_keuangan dari profil server.
Form penerimaan/approval bukti/koreksi memakai preview, pilihan selisih, alasan, key tetap dan body terkunci saat hasil jaringan belum diketahui.
Layar keuangan rumah memuat tarif khusus/kembali umum, titipan manual dan riwayat, keringanan/penyesuaian serta koreksi/pembalikan;
detail penerimaan dan koreksi kas manual terhubung dari tab Keuangan. Alur M17 tetap tersedia saat flag nonaktif.
Tes Flutter awal **298/298**; tiga tes pengelolaan tambahan lulus. `flutter analyze --no-pub` bersih;
`dart format --output=none --set-exit-if-changed lib test` **91 file, 0 berubah**. Suite akhir **301/301 PASS** (konkurensi 2).
Belum bukti integrasi staging/emulator M18, belum APK. Privasi/transparansi warga dan laporan/XLSX berikutnya.

**Transparansi/privasi 26 Okt**, branch `codex/m18-transparansi-privasi`: PR #85 mobile pengurus merged `c68a7a1`, CI 7/7 PASS
termasuk build APK debug dev/staging (bukan rilis yang disebar). GET `/cash/summary` memberi allowlist agregat bulanan,
saldo awal/modal/masuk/keluar/koreksi/akhir tanpa identitas; kas warga memerlukan persetujuan teks M18.
Privasi 26 Okt aktif bersama flag v2; APK membawa teks 25/26 dan menolak versi asing. Server memvalidasi versi_teks,
bukan menerima nomor versi terbaru tanpa teks. Website/preview dan dokumen kebijakan diperbarui.
Validasi: backend **396/396, 4.136 assertions**; Flutter **306/306**; analyze bersih, format 93/0; website **40/40, 538 assertions**,
build:preview 24 rute dan Pint PASS; OpenAPI lint tanpa warning, check-repo/diff-check PASS. Flag masih false; belum deploy/APK M18.
Berikutnya laporan/CSV/XLSX satu layanan, kemudian verifikasi staging/emulator dan satu rilis M18.

**Laporan/Excel 26 Okt**, branch `codex/m18-laporan-excel`: PR #86 merged `1464239` setelah CI 10/10 PASS.
`FinanceReports`/`CashSummary` menjadi sumber JSON, CSV dan workbook; 14 sheet memakai OpenSpout **4.32.0**
(1 dependency gratis, audit tanpa advisory). CSV detail lama v2 memakai nominal efektif/sisa serta kas bertanda.
File XLSX privat, no-store, audit, dihapus setelah respons; jaring cleanup berkas UUID kedaluwarsa 24 jam.
Tes lintas tahun, koreksi/pemakaian titipan lintas bulan, rumah arsip, bulan kosong, formula injection, 14 sheet,
kesamaan total JSON/CSV/XLSX dan penghapusan berkas lulus. Gangguan cache/queue tidak membatalkan uang yang committed.
Suite backend akhir **405/405, 4.302 assertions**. Uji skala **300 rumah × 12 bulan = 3.600 tagihan** lulus;
validasi deferred lokal 14.657 ms terarah / 48.326 ms saat suite penuh, laporan tahunan **7 query**.
Ini pengukuran lokal sintetis, bukan latensi staging. Flutter akhir **307/307**, termasuk laporan/unduh XLSX dan
layar 320 skala 2; analyze bersih, format **95/0**. Pint dan audit Composer PASS.
Pemeriksaan deployment menemukan flag belum diteruskan Compose; ditambahkan ke env API bersama (worker/scheduler
mewarisi) dengan default false. Image memasang ekstensi XLSX eksplisit dan CI menulis workbook tanpa database.
Sisa gate: staging immutable + aktivasi flag terkendali, rekonsiliasi nol, emulator warga/pengurus, upgrade dan satu APK M18.
PR #87 laporan merged `6b44763`, CI 9/9 PASS. Backup staging terenkripsi dan restore drill terisolasi cocok pada
26 Okt (10 detik, 12 tabel pembanding); rekonsiliasi baseline nol. Deploy immutable `6b44763` dimulai dengan flag false.
Penelusuran integrasi menemukan ringkasan kas pengurus lama mengelompokkan pembalikan sebagai masuk/keluar baru.
Branch `codex/m18-konsistensi-kas` menyamakan agregat v2 dengan laporan/warga serta menampilkan modal dan koreksi
terpisah. Subset backend 4/4 (63 assertions), Flutter 5/5 lulus. Suite akhir **406/406, 4.316 assertions**;
Flutter **308/308**, analyze bersih, format **96/0**, Pint PASS. Deploy `6b44763` selesai, migrasi 2/2 berhasil,
heartbeat empat komponen sehat, volume dan container layanan lain terjaga. Flag masih false.
Build kandidat 1019 dibatalkan, tidak disebar; kandidat final mengikuti CI setelah perbaikan. Bukti lokal: [REPORTS.md](evidence/m18/REPORTS.md).

## M17 — Penyesuaian hasil rapat RT 24 Okt (ADR43) — 25 Oktober 2026: **passed**

**Penutupan 25 Oktober 2026 (Codex):** pemilik menerima uji emulator sebagai gate M17 (bukan klaim uji HP nyata).
Verifikasi ulang baseline `cd4a21f`: backend **358/358, 3.801 assertion**, Pint PASS; website **40/40, 538 assertion**;
Flutter **293 PASS**, analyze bersih, format 86 file/0 perubahan. Staging publik **4/4 PASS**. APK 1018 checksum cocok;
reinstall in-place pada Pixel_8a mempertahankan sesi, avatar tersimpan setelah restart, umur tampil, persetujuan privasi
25 Oktober berhasil, kontak Ketua cocok dengan referensi privat, tombol super admin terlihat tanpa mengubah peran.
Bukti dan keterbatasan: [VERIFICATION_1018.md](evidence/m17/VERIFICATION_1018.md). Emulator/Postgres sementara dihentikan;
penghapusan pgdata ditolak alat sehingga folder sementara tetap lokal. Tidak ada deploy/APK baru/production.
Pekerjaan dokumentasi di worktree `codex/m17-verifikasi-1018`; perubahan MyRepublic checkout awal tidak ikut.
Berikutnya **M18: rencana + usulan ADR44 dahulu**, persetujuan pemilik sebelum migrasi.

Branch `codex/m17-penyesuaian-rapat`. Isi (tanpa nomor telepon di Git; nomor diisi lewat aplikasi/API):

- **Kontak resmi Ketua RT:** pengaturan `ketua_whatsapp` di samping `bendahara_whatsapp`. `PUT /admin/settings/contacts`
  menerima salah satu/kedua kolom (yang tidak dikirim tidak berubah; payload kosong 422), audit hanya nama kolom.
  `GET /public/contacts` (tanpa login, cache 60 dtk) hanya mengirim nomor Ketua. Website halaman Kontak menampilkan
  "Ketua RT Agus Ferdiansyah · 08xx-xxxx-xxx" (tautan wa.me) bila terisi, selain itu "Belum diumumkan". Mobile pengurus:
  Lainnya → Kontak website → *Nomor WhatsApp Ketua RT*.
- **Bendahara:** profil website menampilkan "Wirawan" (`profil.json` website + seeder konten identik).
- **Super admin (ADR43 #8):** `RT05_SUPER_ADMIN_EMAIL` (bawaan akun RT resmi). `Akun::isSuperAdmin()` = pengurus aktif +
  email itu; payload akun memuat `super_admin`. `POST /admin/accounts/{id}/role` {peran, alasan}: selain super admin 403
  `SUPER_ADMIN_REQUIRED`, akun sendiri 422, akun tidak aktif tidak bisa dinaikkan, pengurus aktif terakhir dijaga
  (advisory lock). Sesi akun tujuan diakhiri, audit + notifikasi sistem. Mobile: tombol *Jadikan pengurus* / *Turunkan jadi
  warga* di Riwayat akun, hanya untuk super admin.
- **Jalan Mawar 8–14:** `Address::street()` membakukan "jl. mawar8" → "Mawar 8" saat disimpan (mutator `Rumah::jalan`);
  kunci alamat menyamakan "mawar8" dan "Mawar 8". Mobile: pilihan cepat Mawar 8–14 di semua kolom Jalan.
- **Masa simpan 12 bulan (ADR43 #7):** kolom `dianonimkan_at` (warga/keluarga/akun, aditif). `rt05:anonimkan-arsip`
  harian (VPS 02.15 WIB; profil shared lewat slot harian ≥02.00): warga arsip/pindah/meninggal >12 bulan → nama
  "Warga dianonimkan", WhatsApp/NIK/tanggal lahir kosong; keluarga arsip → nomor KK kosong; akun arsip → nama/email/sandi
  diganti. Catatan iuran/kas/audit tetap. Arsip yang sudah dianonimkan tidak bisa dipulihkan (409 `ANONYMIZED`).
- **Kebijakan Privasi v3 (`2026-10-25`):** menyebut Ketua RT sebagai pengelola + WhatsApp di halaman Kontak, dan anonimisasi
  12 bulan. Teks aplikasi, website `privasi.json`, dan `docs/PRIVACY_NOTICE.md` identik; warga diminta setuju ulang.
- **Temuan uji APK 1015:** umur anggota kini tampil juga di daftar keluarga warga (menu Akun).

Validasi lokal (25 Okt): rest-api `php artisan test` 356/356 (5 tes baru `M17MeetingTest`; regex milestone kontrak OpenAPI
diperluas ke M17–M20) + Pint bersih; website `php artisan test` 40/40, `npm.cmd run build:preview` 24 rute; Flutter
`flutter analyze` bersih, `flutter test` 291 lulus (7 baru `m17_meeting_test.dart`); `check-repo.mjs` OK.

PR #77 merged (`9507c4d`), CI 10/10 hijau; staging dideploy `9507c4d` (skrip immutable, health ready). Staging diisi lewat
API (akun pengurus sintetis): nomor Ketua RT + bendahara dari dokumen rapat pemilik (salinan privat `~/.rt05/m17-kontak.json`,
tidak di Git) dan profil terbit bendahara "Wirawan"; `/kontak` dan `/profil` staging menampilkan keduanya. Build APK 1016
(run 40174095835) **tidak disebar** karena permintaan avatar datang sebelum rilis; rilis berikutnya 1017.

- **Foto profil keluarga (permintaan pemilik 25 Okt, PR lanjutan):** 15 avatar SVG bertema keluarga (ayah, ibu, ayah berpeci,
  ibu berhijab, kakek, nenek, nenek berhijab, kakak laki-laki/perempuan/berhijab, adik laki-laki/perempuan, bayi, pasangan,
  keluarga) dibuat `mobile/tool/avatars.py` → `mobile/assets/avatars/`. Bukan foto galeri (tanpa unggahan wajah warga).
  Kolom `keluarga.avatar` (aditif, CHECK daftar kode), `PUT /household/avatar` {avatar|null} khusus akun utama, tidak menaikkan
  versi keluarga, teraudit; `avatar` ikut di GET /household dan daftar keluarga pengurus. Mobile: avatar di papan rumah menu
  Akun, ketuk → lembar 15 pilihan (atau hapus). Validasi: rest-api 358/358 (2 tes baru, termasuk kode = berkas aset), Flutter
  293 lulus, analyzer bersih. PR #78 merged (`d601c43`), staging dideploy `d601c43`.

**Uji emulator APK 1017 (staging `d601c43`, 25 Okt ±15.40 WIB, Pixel_8a headless):** super admin masuk → Kebijakan Privasi
versi 25 Okt diminta setuju ulang (teks Ketua RT benar) → Lainnya: menu *Nomor WhatsApp Ketua RT* menampilkan nomor tersimpan,
simpan berhasil → Riwayat akun "Tambahan Sintetis M06": *Jadikan pengurus* lalu *Turunkan jadi warga* berhasil, audit
`akun.peran_pengurus`/`akun.peran_warga` + alasan tercatat → Rumah baru dan formulir daftar akun tambahan: 7 chip Mawar,
ketuk Mawar 10 mengisi kolom → warga utama sintetis: umur anggota tampil ("Anak · 7 tahun"), avatar papan rumah → lembar 15
avatar → pilih *Keluarga* tersimpan. Temuan yang langsung diperbaiki (PR lanjutan): kartu profil Akun masih inisial (kini
memakai avatar keluarga bila dipilih) dan teks lama "Peran tidak diubah dari aplikasi" di daftar akun pengurus. APK 1017
**tidak disebar**; rilis ke HP = 1018 (PR #79, `8f8a1f1`): build 25 Okt 16.09 WIB, `/unduh` + `versi.json` staging 16.10, Drive
16.11 (file ID tetap); dipasang ulang di emulator, avatar tampil di kartu profil dan papan rumah. Catatan rilis yang diketik
dengan `\n` di form dispatch tidak terpecah → `versi.json` 1018 dipecah manual dan skrip build kini juga memecah `\n` teks
(self-test baru). Keterbatasan: nama jalan lama yang sudah tersimpan tidak diubah otomatis (pembakuan
berlaku saat rumah disimpan/diubah).

## M16 — Rilis ke warga (milestone terakhir) — 22 Oktober 2026: **in_progress (Fase A)**

Rencana [M16_PLAN.md](M16_PLAN.md) (gate G1–G13). ADR37: verifikasi developer Android ditunda (opsi terakhir), bukan gate.
Branch `codex/m16-fase-a` (di atas `codex/m16-rencana`). Fase A dikerjakan agent tanpa production:

- **Website siap Google:** `RT05_SEARCH_INDEXING` (hanya production) mengatur `X-Robots-Tag`/meta robots, `robots.txt` dinamis
  (staging/preview `Disallow: /`; production menutup `/pengurus` + `Sitemap:`), `sitemap.xml` (halaman publik, artikel,
  album), canonical/`og:url`/`og:image` dari `APP_URL` (bukan header Host), `og:site_name`, JSON-LD `WebSite` + `Organization`
  (nama "RT05 TAKEDA" + nama lain) dan `Article`. `public/robots.txt` statis dihapus; ekspor preview menulis robots.txt noindex.
- **Halaman `/aplikasi`:** membaca `versi.json` (path atau URL, `RT05_APK_META`), memvalidasi ABI, tautan `/unduh/…apk` atau
  HTTPS, ukuran, dan SHA-256. Tanpa rilis → "Aplikasi sedang disiapkan". Panduan pasang, izin aplikasi, cara memastikan berkas
  asli. **`/kebijakan-privasi`** publik dari `resources/data/privasi.json`, dites identik dengan teks aplikasi dan versi server.
  Tautan di footer dan halaman Kontak.
- **Mobile:** versi `1.0.0` (pubspec, dites sama dengan `AppConfig.appVersion`), `APP_BUILD` dari CI rilis, kartu *Tentang
  aplikasi* di menu Akun (warga dan pengurus): versi, pengelola, tautan halaman unduh resmi, dan pemberitahuan versi baru dari
  `/unduh/versi.json` tanpa token.
- **Workflow rilis:** `versi.json` dibuat dari APK yang sama (input `catatan`), domain prod dari variable `RT05_PROD_BASE_URL`.
  Compose staging: web membaca `versi.json` dari volume unduhan (read-only), `RT05_SEARCH_INDEXING=false`.
- **Sisa M15 #5:** `rt05:media-recover`/`rt05:push-recover` memperpanjang IdleGuard selama masih ada foto diproses atau push
  belum terproses (≤1 jam), sehingga pemulihan tidak menunggu pengaman per jam.
- **Issue #47:** sumber 404 terjelaskan: akun pengurus sintetis tanpa rumah → `GET /household` dan `GET /dues/my` 404
  `HOUSEHOLD_NOT_LINKED` (≈6,3% teoretis; 49/848 = 5,8% teramati), ditulis eksplisit di `EXPECT`. Ulang uji beban 20 pengguna
  60 dtk ke staging `51fced8` dari PC pemilik lewat WARP (22 Okt ±09.30 WIB): **0 respons tak terduga**, tetapi **gate P95
  gagal** (P95 total 1677 ms; per endpoint 967–2252 ms; TTFB `/health/live` ±0,2 dtk lewat WARP). Bukti
  [load-20u-staging-2026-10-22.json](evidence/m16/load-20u-staging-2026-10-22.json). Lebih lambat dari M15 (781 ms, 19 Okt);
  penyebab belum diselidiki. Gate performa diukur ulang di host production dari jaringan luar.
- **Dokumen:** [RUNBOOK_PRODUCTION.md](RUNBOOK_PRODUCTION.md) (pertanyaan Biznet siap kirim, setup shared, rilis APK, Search
  Console, backup/restore, rollback), draf [SOP_PENGURUS.md](SOP_PENGURUS.md), `infra/shared/web.env.example`, catatan MIME APK
  di `public-htaccess.example`.

Validasi lokal: website `php artisan test` 28/28 (7 tes baru M16), `npm.cmd run build:preview` 24 rute, `test:preview` 24 rute
tanpa error browser; rest-api `php artisan test` 342/342 (Postgres lokal 54329); Flutter `flutter analyze` bersih, `flutter
test` 272 lulus (5 baru); `check-mobile-build-config.mjs --self-test` OK; `m15-evidence-lib.test.mjs` OK;
`check-m03-compose.mjs` OK; `check-repo.mjs` OK. Belum: deploy staging, APK baru, uji di HP.

Blocked pada pemilik: K1 domain, K4 kontak/masa simpan privasi, K5 admin pertama, K6 jawaban Biznet + pembelian, tujuan
backup offsite, akun pemantau uptime.

### Rilis APK 1015 — statistik usia warga (24 Okt 2026)

PR #74 merge `9f0fdf9`, staging di-deploy (migrasi `2026_10_24_000100_warga_tanggal_lahir` DONE, selesai tanpa GAGAL), APK
**1015** (1.0.0) dibangun 15.54 WIB (run 40079348721), tersaji di `/unduh` + `versi.json` 15.55 WIB, link Drive tetap 15.56 WIB
(SHA-256 arm64 `699ce78e…`). Uji agent di emulator dengan staging asli (akun sintetis):
- Warga: setelah masuk muncul Kebijakan Privasi versi 24 Oktober 2026 (teks tanggal lahir terlihat), setuju lalu lanjut; Tambah
  anggota "Anak Uji Statistik" dengan kalender (10 Oktober 2019 → "7 tahun"), tersimpan dan terbaca lagi dari server.
- Pengurus: privasi disetujui ulang; Warga → tab ke-4 **Statistik usia**: total 43 warga aktif, 42 belum diisi, Anak-anak 1
  ("7 th • Anak-anak • Rumah UJIM06-1"); ketuk membuka form Anggota dengan tanggal lahir; Per rumah menampilkan isi tiap rumah.
- Catatan untuk rilis berikutnya (dikumpulkan, belum dirilis): daftar keluarga milik warga belum menampilkan umur di baris
  anggota (layar pengurus sudah).

### Fitur tambahan RT — statistik usia warga (24 Okt 2026, ADR42, branch `codex/m16-statistik-usia`)

- **Backend:** `warga.tanggal_lahir`, `AgeGroups`, validasi di `/household/members*` dan `/admin/families/{id}/members`,
  `/admin/residents/{id}`; `GET /admin/age-stats` (kelompok, rentang `min`/`max`, `belum_diisi`, per rumah); Member memuat
  `tanggal_lahir`, `umur`, `kelompok_usia`; Kebijakan Privasi versi `2026-10-24`. OpenAPI diperbarui.
- **Mobile:** `BirthDateField` (kalender, bisa dikosongkan) di anggota keluarga warga, tambah anggota pengurus, dan ubah warga
  pengurus; umur di baris anggota; tab Warga → **Statistik usia** (chip per kelompok, rentang bebas, per warga/per rumah,
  ketuk untuk ubah). Teks privasi aplikasi dan website disamakan.
- **Tes:** rest-api `M16AgeStatsTest` (3: batas ulang tahun, isi/kosongkan + tolak tanggal masa depan, statistik + saringan
  60–65 + 403 warga) → 351/351; Flutter `m16_age_stats_test` (5) + perbaikan 2 tes lama yang perlu `scrollToTop` karena
  formulir lebih panjang; website 39/39.

### M16 — deploy notifikasi bukti transfer + uji APK 1014 Air Bersih (24 Okt 2026)

PR #68 (notifikasi bukti transfer, migrasi `2026_10_22_000300`) di-deploy bersama `main` `1f23045` setelah sesi "Fase 6
eksekusi" selesai (deploy immutable 24 Okt ±14.15–14.25 WIB, selesai). APK 1014 diunggah ke link Drive tetap 14.25 WIB.
Uji agent:
- **Emulator, APK 1014 di atas 1013:** pengurus → Lainnya → kartu "Air Bersih KSM" (logo, tanpa panah) → Chrome membuka
  `airbersih-ksm-claude.vercel.app/masuk`; kartu Tentang aplikasi "Versi 1.0.0 (build 1014)". Warga (akun sintetis): lima tab
  digulir, 0 kemunculan Air Bersih/KSM, tanpa tab Lainnya.
- **Web live** `website/scripts/air-bersih-live.mjs` 4/4 PASS: tersembunyi sebelum login, tampil setelah login pengurus, tab baru
  ke halaman masuk Air Bersih KSM. Halaman publik tidak memuat tautan.
- **Notifikasi bukti transfer live:** bukti dari akun warga sintetis → event selesai, inbox 3 pengurus (push 1 terkirim, 2
  dilewati karena tanpa HP); pengurus menolak bukti uji → warga menerima "Bukti pembayaran diperiksa" dalam 31 dtk.

### M16 — database cadangan Neon project kedua (22 Okt 2026, ADR39)

Supabase penuh (2 project aktif) → pemilik memilih Neon project kedua `rt05-takeda-cadangan`. Sinkron staging → cadangan dan uji
aplikasi di cadangan lulus ([bukti](evidence/m16/STANDBY_NEON.md)). Belum: latihan pindah + kembali di staging, jadwal sinkron.

### M16 — rilis 1013 dan uji beban ulang (22 Okt 2026)

PR #66 merge `b1d080d` 10.4x WIB, staging deploy (0 GAGAL), APK **1013** (1.0.0) dibangun 10.57 WIB, tersaji 10.58 WIB
bersama `versi.json`. Uji beban 20 pengguna 60 dtk dari PC pemilik lewat WARP setelah perbaikan performa
([bukti](evidence/m16/load-20u-staging-2026-10-22-setelah-perbaikan.json)): 862 request, **0 tak terduga**, P50 332 ms,
**P95 total 884 ms** (sebelumnya 1677 ms); web `/` 156 ms, `/artikel` 215 ms, `/dokumentasi` 816 ms. Gate per endpoint masih
gagal untuk 5 endpoint API (P95 1160–1416 ms): sisa biaya membuka koneksi baru ke Neon (±184 ms) + jalur WARP. Ukur ulang
dari jaringan luar di host production; opsi berikutnya bila perlu: kurangi query per layar atau koneksi pooled lebih dekat.

### M16 — backup Drive + sisa audit (22 Okt 2026, branch `codex/m16-performa`)

- **Latihan backup + restore lulus** ([BACKUP_DRILL.md](evidence/m16/BACKUP_DRILL.md)): `infra/scripts/backup-drill.sh` di VPS,
  12 tabel utama cocok (baris + total rupiah), total 9 dtk, berkas terenkripsi AES256 di Drive pemilik
  `RT05 TAKEDA - Backup/staging/`. Kunci enkripsi di berkas catatan lokal pemilik.
- **Sisa audit (ADR38 butir 8):** trigger kunci tagihan/pembayaran/alokasi, CSP CMS, hapus salinan foto cache di aplikasi.
- Tes: rest-api `M16AuditFixesTest` +1 (kunci database + pembalikan resmi tetap jalan), website `SecurityHeadersTest` +1
  (30/30), Flutter `m16_picked_files_test` (273 lulus).

### M16 — performa staging (22 Okt 2026, branch `codex/m16-performa`)

Diagnosis dari VPS (tanpa jalur WARP): halaman web 30–40 ms saat cache segar; `/` pertama setelah cache habis 780 ms;
`/api/v1/public/articles` 250–390 ms; koneksi baru ke Neon `ap-southeast-1` 184 ms, query 18 ms. FrankenPHP bawaan 4 thread.
Perbaikan dan keputusan: ADR38 butir 7. Tes website 29/29 (+1 `test_expired_cache_serves_last_good_and_refreshes_after_the_response`),
preview tidak berubah. Uji beban ulang setelah deploy dicatat di bawah.

### M16 — perbaikan audit keamanan + keputusan pemilik (22 Okt 2026, branch `codex/m16-perbaikan-audit`)

ADR38. Kode: token registrasi diganti otomatis setelah 30 salah tebak lintas IP (migrasi aditif
`2026_10_22_000100_token_registrasi_percobaan_gagal`), pengurus diberi tahu (`token.dirotasi_otomatis`, `akun.registrasi_utama`);
ekspor CSV Kas/Pembayaran per bulan (`OperationsController::export`); `/health/ready` cache 10 dtk; binding SQL disamarkan di
pesan error. Tes baru: `M16AuditFixesTest` (2), `HealthTest` (+2), `M15UatFinanceTest` (+1). Admin staging email RT dibuat
(kata sandi di berkas catatan lokal pemilik). Belum: SSH VPS, setelan GitHub, rotasi kredensial (menunggu izin pemilik).


### M16 — pratinjau Vercel menaut ke halaman unduh staging (24 Okt 2026)

Pemilik: `https://rt05takeda.vercel.app/aplikasi` tetap pratinjau dan tidak menyimpan APK; tombol unduh di situ membuka
`https://takeda-staging.morriz.tech/aplikasi#unduh`. Staging yang punya `versi.json` tetap menampilkan tombol berkas
langsung (64-bit dan 32-bit), bukan tautan ke dirinya sendiri. `RT05_APK_PAGE` hanya diisi saat ekspor pratinjau, dan
hanya URL staging itu yang diterima. Validasi lokal: website `php artisan test` 39/39 (3 tes baru di `M16SearchAndAppTest`),
`npm run build:preview` 24 rute (hanya `preview/aplikasi.html` berubah), `test:preview` PASS (24 rute, 0 error browser),
`check-repo.mjs` OK. Klik tombol pratinjau di Chromium sampai ke staging dan menampilkan build 1014. PR #72 digabung ke `main`
`36d6bdc` pada 24 Okt 14.43 WIB. Deploy production Vercel tidak ikut dari commit penggabungan bot, jadi
commit ini memicu ulang.

### M16 — menu Air Bersih KSM khusus pengurus (24 Okt 2026, ADR41, branch `codex/m16-menu-air-bersih`)

Instruksi pemilik: Air Bersih KSM tetap aplikasi terpisah (login sendiri, akun hanya untuk Ketua RT); RT05 cukup punya menu
berlogo yang mengalihkan pengurus ke sana; warga tidak boleh melihatnya. Mobile: kartu "Air Bersih KSM" dengan logo KSM Banyu
Bening, tanpa ikon panah. Web: menu sidebar berlogo hanya di mode server dan baru tampil setelah sesi terbukti pengurus;
pratinjau statis tanpa login tidak lagi memuat tautan. Tanpa perubahan API/database. Validasi lokal: website `php artisan test`
36/36 (`AirBersihLinkTest` 6 tes), `build:preview` 24 rute, `test:preview` PASS; mobile `dart format` 0 berubah, `flutter analyze`
bersih, `flutter test` 279 lulus (`m16_air_bersih_link_test.dart` 6 tes: logo, tanpa panah, aplikasi utuh warga = tidak ada
menu, pengurus = ada); `check-repo` OK. PR #70 merge `e74f2e0` (CI 9/9). Staging di-deploy 24 Okt ±13.18–13.24 WIB (immutable,
tanpa GAGAL); uji live Chromium 7/7 PASS (tamu & warga: menu tersembunyi; halaman publik tanpa menu; pengurus: menu + logo
termuat, klik membuka https://airbersih-ksm-claude.vercel.app/masuk). APK **1014** (run 40069668317) selesai 13.25 WIB,
`sha256sum -c` OK, sertifikat = `RT05_RELEASE_CERT_SHA256`, tersaji di `/unduh` + `versi.json` 13.27 WIB; `/aplikasi` menampilkan
1014 dan tetap noindex.

### M16 — tautan Air Bersih KSM (23 Okt 2026, ADR40, branch `codex/m16-tautan-air-bersih`)

Instruksi pemilik: Air Bersih KSM (repo terpisah, tagihan air KSM) adalah calon fitur pengurus RT05; sementara hanya tautan.
Area Pengurus web (`/pengurus` sidebar, tab baru, `AIR_BERSIH_KSM_URL`) dan aplikasi mobile (pengurus → Lainnya → "Air bersih
KSM") menautkan https://airbersih-ksm-claude.vercel.app. Tidak ada perubahan API/database. Validasi lokal: website
`php artisan test` 33/33 (3 tes baru `AirBersihLinkTest`), `npm.cmd run build:preview` 24 rute (hanya 8 halaman pengurus
berubah), `test:preview` PASS; mobile `dart format` 0 berubah, `flutter analyze` bersih, `flutter test` 276 lulus (3 tes baru
`m16_air_bersih_link_test.dart`). Belum: merge, deploy staging, APK baru (butuh persetujuan pemilik).

## M15 ditutup — 22 Oktober 2026: **passed** atas keputusan pemilik (ADR36)

Bukti UAT: uji tim putaran 1–3, demo ke Ketua RT 20 Okt 20.00 WIB, uji agent di emulator (APK 1010/1011). Nilai kemudahan per
peserta tidak dicatat dan tidak dikarang. Sisa gate (verifikasi developer Android, kontak/masa simpan privasi, latihan DB
cadangan, uji hosting Biznet, TalkBack, pemulihan otomatis foto/push, issue #47) dipindah ke M16. M16 dibuka saat pemilik
siap; mulai dari preflight hosting Biznet dan verifikasi developer Android.

## M15b — Permintaan Pak RT (rapat 20 Oktober 2026) — 21 Oktober 2026: **passed 22 Oktober 2026 (APK 1011)**

Branch `codex/m15b-permintaan-rt` dari `main` 8b14ac2. Milestone usulan M15b (fitur baru setelah UAT, di antara M15 dan M16;
penempatan disampaikan ke pemilik). Keputusan dan perubahan aturan lama: [DECISIONS.md ADR35](DECISIONS.md). Kerja bantu
memakai subagent Haiku atas izin pemilik (pemetaan kode, riset lokasi, editor profil CMS web, UI iuran); rancangan, review,
dan pengujian oleh agent utama.

| Permintaan | Perubahan | Bukti |
| --- | --- | --- |
| Website "Profil RT" → "Profil RT05" + cerita Taman Kedaung yang bisa diedit | Menu/judul Profil RT05; `/profil` membaca `/api/v1/public/site-profile` (cadangan `resources/data/profil.json`); kolom `tentang` + migrasi data `2026_10_21_000200`; editor `/pengurus/profil` di CMS web (subagent, direview) dan aplikasi | `website/tests/Feature/ProfileRt05Test.php` 5 tes; `M15bRequestsTest` (profil, migrasi); M13 cek salinan `profil.json` |
| Pengurus RT bisa diedit dan ditambah | Daftar pengurus dari API, 1–12 orang, tambah/hapus di CMS web | idem |
| Transparansi iuran disembunyikan | Kartu dihapus dari aplikasi warga; `GET /dues/transparency` khusus pengurus (403 warga) | `FinanceTest` diperbarui; `dues_privacy_test` |
| Bukti transfer atau WhatsApp bendahara | `konfirmasi_iuran` + `POST /dues/confirmations`; pengurus memeriksa (selesai/tolak) tanpa mengubah status tagihan; nomor bendahara di `system_settings`, editable pengurus | `M15bRequestsTest` (bukti, kuota 5/hari, foto hanya pemilik/pengurus); `m15b_dues_confirmation_test` 9 tes |
| Tombol WhatsApp per warga (wa.me) | `core/whatsapp.dart`; ikon di anggota keluarga layar pengurus bila nomor terisi; manifest `queries` https | `m15b_privacy_identity_test` |
| Form NIK dan KK opsional | `warga.nik`, `keluarga.nomor_kk` terenkripsi, API tersamar; layar "Data kependudukan" untuk akun utama dan pengurus | `M15bRequestsTest` (enkripsi, tersamar, akses); `m15b_privacy_identity_test` |
| Kebijakan Privasi setelah masuk + lengkapi data | `akun.privasi_versi`, `POST /auth/privacy-consent`, gerbang router; teks di aplikasi dan `docs/PRIVACY_NOTICE.md`; kartu "Lengkapi data keluarga" di beranda | `M15bRequestsTest`; `m15b_privacy_identity_test` |

| Perintah | Hasil |
| --- | --- |
| `php artisan test` (Postgres 17 lokal 54329, cluster dibuat ulang 21 Okt karena data lama rusak) | **340/340, 3555 assertion** |
| `php vendor/bin/pint --dirty` | lulus |
| `flutter analyze lib test` | bersih |
| `flutter test` | **262 lulus** |
| website: `php artisan test`, `npm.cmd run build:preview`, `npm.cmd run test:preview` | 21/21; 22 rute diekspor; lulus |

Belum dibuktikan: deploy staging, uji live CMS profil, uji HP nyata (kamera bukti transfer, membuka WhatsApp), dan isi nomor
bendahara uji di staging (diisi lewat aplikasi setelah deploy, tidak di Git). Belum diputuskan pemilik: kontak resmi dan masa
simpan di Kebijakan Privasi; impor NIK/KK dari Excel RT (tidak dibuat). Tombol WhatsApp memakai ikon buatan (lingkaran hijau +
telepon), bukan logo resmi WhatsApp.

**Uji emulator APK 1010 (21–22 Okt, agent mengendalikan emulator Pixel_8a lewat adb, akun sintetis staging).** PR #60 merge
`2f781d7` 21 Okt 20.32 WIB, staging di-deploy (0 GAGAL), APK 1010 dibangun 20.40 WIB (run 39608984801; arm64 3010, armeabi-v7a
2010, sertifikat kunci tetap) dan disajikan di `/unduh` 20.45 WIB. Nomor bendahara uji pemilik diisi lewat API staging (tidak di
Git). Lulus: gerbang Kebijakan Privasi (tombol aktif setelah dicentang), kartu Lengkapi data menyesuaikan, NIK/KK tersimpan dan
tersamar, transparansi hilang dari warga, tombol WhatsApp bendahara membuka `wa.me` ke nomor uji, kirim bukti dari galeri
(nominal terisi otomatis, tagihan tetap "Belum dibayar"), pengurus melihat bukti dan fotonya, Catat pembayaran lalu Tandai
selesai, pengaturan nomor bendahara. Website `/profil` staging memakai Profil RT05. Editor Profil RT05 CMS web staging
`website/scripts/cms-profile-live.mjs`: **9/9 PASS** (pengurus uji tampil di `/profil` 10 dtk, dihapus lagi 15 dtk).

Temuan dari uji itu, diperbaiki di branch `codex/m15b-perbaikan-uji` (APK 1011): (1) detail rumah pengurus kini punya tombol
WhatsApp per anggota, Data kependudukan, dan Kelola anggota (sebelumnya hanya di Daftar keluarga); (2) izin notifikasi Android
baru diminta setelah Kebijakan Privasi disetujui; (3) tombol cepat warga "Kas RT" menjadi "Iuran"; (4) Catat pembayaran dari
bukti langsung mencentang bulan yang diklaim (hanya yang belum lunas); (5) nomor bendahara tampil `0877-7228-3010`; (6) label
anggota tidak lagi "kepala keluarga" dobel. Tes: `m15b_fixes_1011_test` 5 tes; `flutter test` **267 lulus**, analyze bersih.

**Rilis dan uji ulang APK 1011 (22 Okt).** PR #61 merge `f392612` 06.01 WIB (CI 9/9 setelah perbaikan `dart format` dan ulang job
website yang gagal karena sertifikat CDN Playwright), staging di-deploy (0 GAGAL), APK **1011** dibangun 06.08 WIB (run 39690611853;
arm64 3011, armeabi-v7a 2011, sertifikat kunci tetap) dan disajikan di `/unduh` 06.09 WIB. Uji ulang di emulator dengan akun
sintetis, semua lulus: nomor bendahara tampil `0877-7228-3010`; detail rumah pengurus punya Data kependudukan dan Kelola anggota,
label tidak dobel; pengurus mengisi WhatsApp warga lalu ikon WhatsApp muncul dan membuka `wa.me` ke nomor itu; setelah data
aplikasi direset, Kebijakan Privasi tampil tanpa dialog izin dan izin notifikasi baru muncul setelah setuju; tombol cepat warga
"Iuran"; bukti dari akun tambahan, lalu Catat pembayaran langsung mencentang Oktober. Untuk uji ulang, pembayaran uji
IUR-2026-000013 (rumah sintetis UJIM06-1) dibalik lewat fitur koreksi resmi lalu dicatat ulang; WhatsApp kepala sintetis diisi
nomor sintetis 0812 0000 1111.

## M15 — UAT tim putaran 3 (20 Oktober 2026 malam): pulihkan arsip, tombol Kembali, akun tambahan — **merge `9d9cf07`, staging di-deploy, APK 1009 dirilis**

Branch `codex/m15-restore-archive` dari `main` 57964c0. Sumber: laporan UAT tim pemilik pada APK 1008 (arsip tidak bisa dikembalikan di web dan
mobile; Kembali langsung menutup aplikasi; jam selesai usulan terisi otomatis; validasi akun tambahan). Keputusan dan batasannya ada di
[DECISIONS.md ADR34](DECISIONS.md). PR [#58](https://github.com/morrispes5/RT-05-TAKEDA/pull/58) merge 20 Okt ±19.54 WIB (CI 10/10), staging `9d9cf07` di-deploy ±19.57–19.59 WIB (migrasi aditif ikut), APK **1009** dibangun 20.03 WIB (run 39426206006, sertifikat = kunci tetap; arm64 versionCode 3009, armeabi-v7a 2009) dan disajikan di `/unduh` staging ±20.07 WIB. Belum ada uji HP.

| Temuan UAT | Perbaikan | Bukti |
| --- | --- | --- |
| Arsip satu arah: Artikel/Dokumentasi (web dan mobile), Rumah, Keluarga, Akun | `POST .../restore` untuk konten (kembali **draf**), rumah (tagihan tetap nonaktif), akun (kembali **nonaktif**), keluarga (bersama anggota yang ikut diarsipkan). Tombol Pulihkan di aplikasi pengurus dan CMS web. Hunian dan tautan akun tidak dibuka otomatis | `M15RestoreArchiveTest` 6 tes; `m15_restore_archive_test` 7 tes + 1 tes konten di `m13_content_admin_test`; OpenAPI +6 rute |
| "Diterbitkan lagi tidak bisa" (konten arsip) | Pulihkan lalu Terbitkan; slug tetap milik konten itu | Alur pulihkan → edit → terbit di `M15RestoreArchiveTest` |
| Kembali tidak meminta konfirmasi keluar; dari tab Kas harus ke dashboard dulu | `PopScope` di `HomeShell`: tab lain → tab pertama; tab pertama → dialog "Keluar dari aplikasi?" → `SystemNavigator.pop` | `m15_back_navigation_test` (warga dan pengurus) |
| Jam selesai usulan otomatis +2 jam | Jam selesai tidak pernah terisi sendiri; ganti jam mulai mengosongkannya bila tidak lagi valid | 2 tes baru + alur valid di `activity_proposal_flow_test` |
| Akun tambahan: wajib nama, hubungan, **nama kepala keluarga**; alamat opsional | Kolom `permohonan_akun.nama_kepala_diajukan` (migrasi aditif `2026_10_21_000100`); rumah dicocokkan diam-diam dari alamat lengkap bila ada, selain itu dari nama kepala yang tepat satu rumah aktif; ganda/tidak ada → pengurus memilih rumah saat menyetujui | `M15AdditionalAccountTest` 5 tes; tes form di `auth_access_test` dan `m15_uat_round2_test` |

| Perintah | Hasil |
| --- | --- |
| `php artisan test` (Postgres 17 lokal 54329) | **332/332, 3432 assertion** (termasuk `OpenApiContractTest`) |
| `flutter analyze lib test` | bersih |
| `flutter test` | **243 lulus** |
| `npm.cmd run build:preview`, `npm.cmd run test:preview`, `php artisan test` (website) | ekspor 21 rute; test:preview lulus; website 16/16 |
| `node infra/scripts/check-repo.mjs` | lulus |

Bukti live: `website/scripts/cms-ui-live.mjs` (diperluas C16–C20: Pulihkan → draf, tidak langsung tampil di website, arsipkan lagi) dijalankan ke staging `9d9cf07`: **21/21 PASS**.

Belum dikerjakan atau belum dibuktikan: (1) Pulihkan rumah/keluarga/akun, akun tambahan tanpa alamat, dan pencocokan rumah dari kepala keluarga hanya diuji lewat tes otomatis, **belum lewat API/aplikasi di staging**; `staging-runtime-smoke.sh` dan cek Vercel pasca-PR belum dijalankan; (2) semua perubahan mobile belum diuji di HP nyata; (3) aturan minimal 10 karakter
pada ringkasan, pembuka, dan isi bagian artikel (juga deskripsi album) tidak diubah: itu validasi M13 yang tetap berlaku; (4) pemulihan akun/keluarga
sengaja tidak membuka kembali hunian, tautan akun, atau tagihan; pengurus melakukannya eksplisit.

## M15 — Perbaikan temuan QA #46, #47, #48 — 19 Oktober 2026: **perbaikan lokal, menunggu CI/review**

Branch `codex/m15-qa-fixes` dari `main` 85b2ed6. Ketiga temuan Codex ([#46](https://github.com/morrispes5/RT-05-TAKEDA/issues/46),
[#47](https://github.com/morrispes5/RT-05-TAKEDA/issues/47), [#48](https://github.com/morrispes5/RT-05-TAKEDA/issues/48))
dikonfirmasi dari kode, direproduksi lewat tes yang gagal pada kode lama, lalu diperbaiki. Belum di-deploy, belum diuji di HP
nyata atau staging.

| Issue | Status | Perbaikan | Bukti |
| --- | --- | --- | --- |
| #46 IdleGuard menunda pemulihan outbox | terkonfirmasi, diperbaiki | `rt05:outbox-dispatch` memperpanjang guard selama masih ada event belum selesai (`completed_at`/`failed_at` kosong), bukan hanya bila ada yang diklaim. Query hanya jalan saat guard aktif atau sweep per jam, jadi idle tetap tanpa database. | `M15IdleGuardTest::test_pending_outbox_work_keeps_dispatcher_awake...` gagal pada kode lama (menit 10: dispatcher tidak dipantau padahal backoff 15 menit), lulus setelah perbaikan |
| #47 Bukti performa | terkonfirmasi, diperbaiki skripnya | Status yang diharapkan eksplisit per peran/endpoint (bawaan 200); P95 hanya dari respons sukses; exit 1 bila ada respons di luar harapan, P95 endpoint >1 dtk, atau notifikasi gagal dibuat/tidak sampai/>60 dtk. Logika di `m15-evidence-lib.mjs`, diuji `m15-evidence-lib.test.mjs` dan masuk CI infra. | 7 tes offline untuk 200/401/403/404/429/500/jaringan, P95, dan skenario notifikasi |
| #48 Draf pengaduan terhapus | terkonfirmasi, diperbaiki | Draf dihapus setelah berhasil dipasang ke formulir. Bila halaman ditutup saat menunggu, foto kamera yang sudah terbaca (API sekali konsumsi) disimpan kembali ke draf. | `m15_report_draft_test` kasus baru gagal pada kode lama, lulus setelah perbaikan |

| Perintah | Hasil |
| --- | --- |
| `php artisan test` (Postgres 17 lokal, DB terpisah `rt05_qa_test`) | **311/311, 3203 assertion** |
| `php vendor/bin/pint --test` | lulus |
| `node --test infra/scripts/m15-evidence-lib.test.mjs` | 7/7 |
| `flutter analyze`, `dart format --set-exit-if-changed lib test`, `flutter test` | bersih, 0 changed, **220 lulus** |

Batasan jujur: (1) **404 di bukti performa belum dijelaskan.** Berkas JSON lama tidak menyimpan status per endpoint; skrip kini
mencatatnya, dan ulang uji beban di staging akan GAGAL sampai penyebab 404 (dugaan: akun `admin` tanpa rumah di fixture)
diperiksa lalu dicatat di `EXPECT` atau fixture diperbaiki. Bukti `load-20u-*.json` lama tidak diubah. (2) Wiring exit code skrip
beban/latensi hanya diuji lewat fungsi penilai dan `node --check`, belum lewat run live terhadap staging. (3) `rt05:media-recover`
dan `rt05:push-recover` memakai guard yang sama dan masih menunggu sweep per jam bila job foto/push hilang saat idle; di luar
lingkup #46 (outbox), tercatat sebagai tindak lanjut.

## M15 — Integrasi, UAT, keamanan, kandidat rilis — 19 Oktober 2026: **passed 22 Oktober 2026 (ADR36)**

Branch `codex/m15-uat-release`. Keputusan: ADR31.

Selesai di sesi ini (lokal; staging/APK menyusul):
- APK rilis kunci tetap: `.github/workflows/mobile-release.yml`, `infra/scripts/check-mobile-build-config.mjs`
  (`--build-release`, `--verify-cert`, self-test diperluas), `mobile/android/app/build.gradle.kts` (signingConfig dari
  `key.properties`). Kunci dibuat 19 Okt atas persetujuan pemilik; secret environment `release` + variable
  `RT05_RELEASE_CERT_SHA256` terpasang; cadangan hanya di folder Drive pribadi pemilik (izin: owner saja, hash diverifikasi
  setelah unduh ulang); salinan lokal dihapus.
- Pemulihan draf pengaduan saat Android menutup aplikasi ketika kamera terbuka (ditunda dari M08):
  `mobile/lib/features/warga/report_draft.dart`, `warga_pages.dart`; tes `mobile/test/m15_report_draft_test.dart` (3).
- Keamanan: `composer audit` rest-api/website 0 advisory; `npm audit` website 0; secret scanning GitHub 0 alert terbuka;
  pemindaian riwayat Git untuk pola kredensial (Neon, private key, token GitHub, APP_KEY) 0 temuan. Repo publik;
  Dependabot nonaktif (rekomendasi untuk pemilik). Header live staging: tidak ada HSTS dan proteksi framing pada website →
  middleware `website/app/Http/Middleware/SecurityHeaders.php` + `SecurityHeadersTest`.
- Performa staging (`infra/scripts/m15-load-check.mjs`, akun sintetis, sebelum perubahan M15): 20 pengguna × 60 dtk,
  837 request, 0 error, P95 total 928 ms, P50 416 ms; per endpoint API P95 774–1160 ms; halaman website P95 107–143 ms.
  Satu pengguna: P50 API ±370 ms. Penyebab diukur di container API: koneksi Neon 130–440 ms, query ±60 ms (pooled = direct).
  Perbaikan: `config/sanctum.php` `last_used_at` dimatikan; `M15PerformanceBudgetTest` menjaga jumlah query per layar.
- Validasi lokal: rest-api `php artisan test` 307 tes / 3143 assertion; website 15 tes / 290 assertion, pint lulus;
  mobile `flutter analyze` bersih, `flutter test` 219 lulus, `dart format` bersih.

- Kuota Neon (high, operasional): project `rt05-takeda` dipakai branch staging + production; staging aktif ±95% waktu
  (48 CU-jam / 8,4 hari; kuota Free 100 CU-jam/bulan per project → habis ±28 Okt). Penyebab: sweep scheduler VPS tiap
  menit/10 menit + healthcheck website `/`. Perbaikan: `rest-api/app/Support/IdleGuard.php`, `routes/console.php`,
  `app/Support/Outbox.php`, `infra/docker/web.Dockerfile` (`/up`); tes `M15IdleGuardTest`. rest-api 309 tes / 3151.
- Workflow rilis: ekspresi `${{ 1000 + github.run_number }}` ditolak GitHub (tanpa aritmatika) → dihitung di shell.
  Run 39190119952 (branch `codex/m15-release-fix`): analyze, 219 tes, build, verifikasi v2 + sertifikat lulus;
  `rt05-takeda-staging-1004.apk` 63,8 MB, SHA-256 diverifikasi ulang di PC, sertifikat `dba8bc3f…069a`, package
  `tech.morriz.takeda`, versionCode 1004, tidak debuggable, `allowBackup=false`.
- Uji beban setelah deploy `6c7f864` (last_used_at mati): 844 request, 0 error, P95 total 781 ms (sebelumnya 928),
  semua endpoint API P95 702–989 ms (<1 dtk).

- Insiden staging (high, diperbaiki): setelah deploy `0717e51` outbox macet karena mutex `withoutOverlapping()` 24 jam
  tertinggal dari scheduler yang dihentikan di tengah run. `infra/scripts/m15-notify-latency.mjs` dari kondisi idle:
  notifikasi tidak muncul dalam 120 dtk (FAIL, 03:25 UTC). Dipulihkan `schedule:clear-cache`; perbaikan mutex pendek
  (ADR31 butir 7) + tes `M15IdleGuardTest` (mutex kedaluwarsa 5 menit). rest-api 310 tes / 3170.

- Staging `244a73c` (PR #45): notifikasi dari idle 6,3 dtk, aktif 44,4 dtk, ±2 menit pasca-deploy 50,1 dtk (≤60 ✓).
  Neon API: compute staging `idle`, `suspended_at` 04.15.02 UTC (±5 menit setelah aktivitas terakhir) — kuota aman.
  APK rilis per ABI (run 39191579464): arm64 22,4 MB terkirim ke pemilik, sertifikat = kunci rilis.
  Axe WCAG 2.1 A/AA staging 7 halaman (termasuk login CMS) desktop + 360 px: 0 pelanggaran; keyboard OK.
  Bukti: [docs/evidence/m15/REPORT.md](evidence/m15/REPORT.md).

- UAT tim pemilik 19 Okt malam (4 penguji, data staging; audit: 4 pengaduan, 2 pembayaran, 1 kas, usulan, agenda,
  registrasi; 0 error 5xx; rekonsiliasi cocok). Keluhan → hasil: (a) foto pengaduan tidak bisa diedit = batasan ADR21,
  diubah ADR32 (tambah/hapus selama diajukan); (b) kas admin layar putih = APK M10 lama memanggil `/cash/summary`
  (dipindah ADR26), APK M15 lulus `m15_finance_uat_test`; (c) batas unggah per akun semua profil (5 pengaduan, 25 foto/
  hari, 5 MB); (d) form usulan: crash jam selesai sebelum jam mulai dan usulan bertanggal lewat (regresi gagal pada kode
  lama, lulus setelah perbaikan). Branch `codex/m15-uat-fixes`, PR #51. rest-api 314 tes / 3211; mobile 226 tes.

- UAT putaran 2 (20 Okt, laporan tim poin B–F lulus; temuan → ADR33): ekspor CSV pembayaran/kas/rekap di aplikasi;
  tagihan rumah/warga baru langsung dibuat; *Mulai tagih* pakai pemilih bulan; blok wajib (rumah baru, akun utama);
  revisi permohonan akun tambahan (`perlu_revisi`); PDF/TXT usulan maks. 1 MB; CMS *Lihat arsip* + cache 15 dtk.
  Probe tanpa mutasi: seluruh payload editor CMS (8 konten) valid (409, bukan 422). Branch `codex/m15-uat-fixes-2`.
  rest-api 320 tes / 3255; mobile 229; website 15 + `test:preview` exit 0. Insiden: `artisan migrate --env=testing`
  tanpa `.env.testing` memakai `rest-api/.env` → migrasi aditif `2026_10_20_000100` ikut diterapkan ke Neon branch
  **dev** (bukan production/staging; tanpa ubah data); dicatat agar tidak terulang.
  PR #52 merge `71dc523`; staging `71dc523` Selesai 0 GAGAL (migrasi `perlu_revisi` jalan); APK rilis 1007
  (run 39361652243, versionCode 3007, sertifikat kunci rilis, SHA-256 `8186ac54…db4d`) diunggah sebagai versi baru di
  link Drive tim yang sama.

- Uji web 20 Okt (permintaan pemilik; [bukti](evidence/m15/web-20okt/README.md)): Vercel sama persis dengan `preview/`
  (21/21) dan `check-preview.mjs` terhadap Vercel PASS (105 kasus responsif, 45 a11y, editor demo, hero); staging API CMS
  21/21; UI CMS staging `website/scripts/cms-ui-live.mjs` 16/16 (edit artikel terbit → Terbitkan → tampil ±15 dtk;
  Arsipkan → *Lihat arsip*). Temuan E menunggu konfirmasi maksud pemilik.

- Rilis staging 1008 (20 Okt, [bukti](evidence/m15/release-1008/README.md)): temuan E dikonfirmasi dari data staging
  (foto sampul asli tersimpan dan terbit, website tetap SVG) → PR #55 merge 14.22 WIB (ADR33 butir 7); PR #50 (#46, #48,
  sebagian #47) digabung dengan `main`, lokal rest-api 321/321 + Flutter 230, merge `4376b44` 14.30; deploy staging
  14.30–14.32 0 GAGAL; APK 1008 (versionCode 3008) selesai 14.38, diunggah ke link Drive tim yang sama 14.42.
  Live: UI CMS 17/17 (termasuk foto sampul tampil), API CMS 21/21, Vercel 21/21, notifikasi 55,3 dtk. APK 1007 tidak
  sempat diuji tim (aktivitas terakhir 20 Okt 00.45 memakai 1006); uji tim berikutnya memakai 1008.

- **Serah terima sesi: [HANDOFF.md](HANDOFF.md)** — sisa gate M15, bug terbuka (#46–#48, PR #50 belum merge), pertanyaan
  untuk pemilik, runbook deploy/APK/Drive, dan jebakan lingkungan.

Belum: pemasangan APK rilis di HP pemilik (perlu uninstall APK debug sekali), uji Android nyata (kamera ditutup paksa, koneksi lambat, push), ulang uji beban setelah deploy + ukur Neon
dingin, UAT mitra (peserta nyata, tidak dikarang), privacy notice final (kontak/retensi dari pemilik/mitra), verifikasi
developer Android (identitas pemilik), aksesibilitas manual, latihan pindah PostgreSQL cadangan (ADR29, belum
diimplementasi).

## M14 — Website membaca API konten + CMS web nyata — 19 Oktober 2026: **passed** (lokal, CI, staging `08f2a1e`)

Branch `codex/m14-website-api`. Keputusan: ADR30.

File: `website/app/Support/SiteContent.php` (API → fresh 60 dtk → penanda gagal 30 dtk → last-good → JSON baked),
`website/resources/js/content-api.js` (repository HTTP + sesi cookie/CSRF), `website/resources/js/cms-preview.js`
(mode server: login, Simpan draf, Terbitkan, Arsipkan, pesan berhasil/gagal, 409), `website/resources/views/pengurus.blade.php`
(`data-cms-mode`), `website/scripts/export-preview.php` (boot kernel sebelum daftar path), `infra/compose.production.yml`
(`RT05_CONTENT_API` web → `http://api`), `rest-api/app/Http/Controllers/ContentController.php` (pratinjau foto statis 302),
tes `rest-api/tests/Feature/M14CmsWebTest.php`, `website/tests/Feature/{SiteContentApiTest,CmsServerModeTest}.php`.

Validasi lokal 19 Oktober 2026 (Postgres 17 lokal, bukan Neon):
- `php artisan test` rest-api: 306 tes / 3118 assertion lulus; `vendor/bin/pint --test` lulus (rest-api dan website).
- `php artisan test` website: 14 tes / 256 assertion lulus. `npm.cmd run build:preview` + `npm.cmd run test:preview` exit 0 (CMS demo, hero, a11y, tanpa error browser); preview tetap `data-cms-mode="demo"`.
- `npx @redocly/cli@2 lint docs/openapi.yaml` valid; `node infra/scripts/check-repo.mjs` OK; `git diff --check` bersih.
- E2E browser lokal (API + website + gateway satu origin di 127.0.0.1, DB lokal terpisah `rt05_m14e2e`, akun sintetis):
  belum login → dialihkan ke `/pengurus/masuk`; akun warga ditolak pesan generik; pengurus masuk; daftar dari API;
  artikel baru → Simpan draf (URL beralih ke ID server) → `/artikel` dan `/api/v1/public/articles/{slug}` 404 (draf terisolasi)
  → Terbitkan → halaman publik website menampilkan isi; album + unggah foto → muat ulang editor (foto dari server) → Terbitkan →
  halaman album + `/api/v1/public/media/{id}` 200 image/jpeg; foto statis album impor tampil lewat 302; Arsipkan → hilang dari daftar;
  perubahan dari perangkat lain → Simpan draf 409 dengan pesan, isian tetap; label "Terbit, ada perubahan belum terbit";
  API dimatikan → `/artikel` dan detail tetap 200 dari last-good, CMS menampilkan pesan server bermasalah; Keluar → `auth/me` 401.

CI PR #41 6/6 lulus, merge `08f2a1e`. Staging 19 Oktober 2026 ([LIVE_RESULTS](evidence/m14/LIVE_RESULTS.md)):
deploy immutable `08f2a1e` Selesai 0 GAGAL, 9 container sehat, web `RT05_CONTENT_API=http://api`;
`infra/scripts/m14-stage-check.mjs` **21/21 PASS** (sesi cookie + CSRF seperti browser: warga ditolak, tanpa CSRF 419,
origin asing 401, draf terisolasi, unggah foto, terbit → tampil di website browser lain ≤60 dtk, 409, arsip hilang,
keluar 401); drill `docker stop` api ±70 dtk → halaman website 200 ±40 ms dari last-good, API kembali healthy.

Gate ROADMAP M14: auth/CSRF/role deny ✓; draft isolation ✓; live server upload/publish ✓; API outage fallback ✓;
baseline navigasi/desain website (14 tes + `test:preview`) ✓. Keterbatasan: bukan production/UAT warga (M15),
shared hosting belum diuji, login UI staging dengan kata sandi tidak diketik agent (alur UI diperagakan pada stack lokal).

## Diagnosis jaringan MyRepublic — 18 Oktober 2026: **penyebab jalur dipersempit; perbaikan permanen belum**

Perluasan keluhan pemilik: portofolio PC `morriztech.cloud` juga gagal, bukan
hanya HP. Domain mengarah ke IP VPS RT05 yang sama, tanpa AAAA. Uji IPv4
Check-Host baru sekitar 23.58 WIB dari Jakarta/Singapura/Jerman: portofolio
3/3 HTTP 200 dan RT05 `/health/live` 3/3 HTTP 200, semuanya ke `187.77.113.136`.
PC rumah tetap IPv4 timeout; portofolio dipaksa IPv6 HTTP 200. Diagnosis kuat
ke jalur koneksi rumah ↔ Hostinger, lokasi operator penyebab belum pasti.
Percobaan WireGuard tidak berhasil; override dihapus, WARP kembali MASQUE
Connected/healthy. Pemilik meminta diagnosis ISP diutamakan, Cloudflare cadangan.

Pemeriksaan langsung dari PC pemilik dan SSH/panel Hostinger baca-saja:
WARP PC aktif, IPv4 ke staging timeout (juga tanpa WARP dengan DNS dilewati),
IPv6 melalui WARP `/health/live` dan `/health/ready` HTTP 200. Kontrol internet
umum IPv4 HTTP 200; DNS A/AAAA benar. Delapan container ber-healthcheck sehat,
gateway berjalan, HTTPS VPS sendiri IPv4/IPv6 normal. UFW inactive, tidak ada
aturan tambahan DOCKER-USER; panel Hostinger menunjukkan 0 firewall rule.
WARP diputus sementara untuk isolasi jaringan lalu dipulihkan dan diverifikasi
Connected/healthy serta `warp=on`. Bukan bukti lokasi persis gangguan di ISP/transit.

Pemilik menyatakan Wi-Fi rumah MyRepublic gagal, internet kafe berjalan, WARP
belum terpasang di HP. Uji WARP HP dan pemulihan jalur ISP masih menunggu hasil;
tidak mengklaim aplikasi HP sudah pulih. Tidak ada perubahan konfigurasi hosting,
DNS, database, atau kode aplikasi. Bukti, perintah, draf laporan ISP dan kandidat
proxy Cloudflare: [NETWORK_MYREPUBLIC.md](evidence/m14/NETWORK_MYREPUBLIC.md).
File perubahan hanya laporan tersebut dan PROGRESS; validasi dokumentasi
`node infra/scripts/check-repo.mjs` OK (895 berkas), `git diff --check` bersih.
M14 website tetap tahap aktif; diagnosis ini tidak menaikkan gate M14/M15/M16.
Catatan ini tertinggal tanpa commit di checkout lokal sejak 18 Okt; dimasukkan ke `main` apa adanya pada 27 Okt 2026
(branch `codex/m14-catatan-myrepublic`) sebagai riwayat. Staging kini diakses lewat WARP + AAAA (HANDOFF bagian 8).

## ADR29 — PostgreSQL cadangan aktif-pasif — 18 Oktober 2026: **keputusan dicatat; implementasi belum**

Pemilik meminta evaluasi deployment shared hosting untuk 100–200 pengguna dan anggaran ≤Rp500.000/tahun. Hasil evaluasi: storage
(<1 GB foto/tahun, DB <100 MB/tahun) dan RAM bukan hambatan. Risiko utama adalah kuota compute Neon Free (100 CU-hours; habis →
compute disuspend sampai bulan berikutnya). Sweep `rt05:shared-tick` tiap 900 detik membangunkan Neon walau idle (perkiraan ≈60
CU-hours/bulan), dan trafik siang bisa menambah sekitar 120. Pemilik memutuskan (P20): tetap PostgreSQL, tambah satu PostgreSQL gratis
di penyedia lain sebagai cadangan, risiko yang sepadan diterima, dan perawatan diserahkan ke agent. Dari lima opsi PostgreSQL yang punya
plugin di Claude (Supabase, Neon kedua, PlanetScale, Railway, Render), pemilik memilih **Supabase Free** karena gratis dan tanpa kuota jam
compute. Jeda 7 hari dimitigasi sinkron berkala + pemantau harian. Desain di ADR29 (DECISIONS.md).

| Berkas | Perubahan |
| --- | --- |
| docs/DECISIONS.md | P20, ADR29 |
| docs/SHARED_HOSTING.md | ringkasan ADR29, preflight #8, gate "Cadangan DB" |
| docs/ROADMAP.md | M15 latihan pindah/kembali, M16 cadangan aktif-pasif |

Belum: provision penyedia standby, kode pindah/kembali/sinkron/pemantau kuota, pengurangan sweep idle, tes, dan latihan staging.
Status cadangan DB: **blocked** sampai PoC penyedia dan tes pindah/kembali lulus. Hanya dokumen yang berubah; tidak ada akun dibuat,
deploy, atau DDL.

## M13 — Konten publik backend dan pengelolaan mobile — 17 Oktober 2026: **passed** (lokal, CI, staging `88c7302`)

Sebelumnya seluruh endpoint konten berstatus planned. Dibangun di branch `codex/m13-public-content` (ADR28).

| Gate ROADMAP M13 | Bukti |
| --- | --- |
| Impor ganda aman | `rt05:konten-impor` dua kali: 13 lalu 0 baru; suntingan pengurus tidak ditimpa |
| Draf tidak bocor | Draf/edit draf tidak muncul di daftar/detail publik; isi draf rahasia tidak ada di respons publik |
| Terbit hanya media siap | Media tidak ada → 422 MEDIA_NOT_READY; foto unggahan publik 404 sampai terbit dan lagi setelah arsip |
| Edit bersamaan 409 | Versi basi → 409 VERSION_CONFLICT (simpan dan terbit) |
| Snapshot stabil | Publik tetap versi terbit sampai diterbitkan ulang |
| Redirect slug | Slug lama 301 ke slug baru; slug lama tidak bisa dipakai konten lain; kembali ke slug lama sendiri boleh |
| Fakta diterima tetap | artikel/album salinan byte-identik website; deskripsi fasilitas kata per kata dari Blade; sekretaris/bendahara "Nama belum dipublikasikan"; album 17 Agustus tanpa tahun; urutan fasilitas sama |

Mobile pengurus: Lainnya → Konten website (Artikel/Album/Fasilitas/Profil) dengan editor draf, unggah foto + teks
alternatif, terbit/arsip berkonfirmasi, profil simpan = terbit. Tes menemukan tab "Profil" di luar layar 360 dp → tab
dibagi rata dengan skala label dibatasi 1,3×.

| Perintah | Hasil |
| --- | --- |
| `php artisan test` (Postgres 17.10 sementara) | **303/303** (+7 `M13ContentTest`; uji kontrak planned disesuaikan karena konten kini implemented) |
| `vendor/bin/pint`; Redocly lint | bersih; valid tanpa peringatan |
| `flutter analyze`; `dart format --set-exit-if-changed`; `flutter test` | bersih; 0 changed; **216 lulus** (+9 `m13_content_admin_test`) |

PR #37 merged `aa72ce1` (CI 9/9). **Deploy staging blocked** 17–18 Oktober karena **jalur jaringan ISP PC pemilik**
ke rentang IP Hostinger (VPS `187.77.113.136` dan konsol `dci.hostingervps.com` 153.92.14.35) putus: dari PC SSH/HTTPS/
ping time out, traceroute berhenti setelah hop ISP `103.75.97.250`. **VPS sehat**: check-host.net Jakarta, Singapura,
Jerman → `/health/ready` 200 dan port 22 terbuka; panel Hostinger CPU 14%, memori 23%, tanpa event DDoS. Agen sempat
menyimpulkan proxy mati (alat cek eksternal agen juga ditolak oleh situs Hostinger lain yang sehat, jadi tidak valid) lalu
me-reboot VPS dari panel atas instruksi pemilik (18 Oktober 11.52 UTC). Reboot ternyata tidak perlu; semua layanan kembali
200. Skrip deploy M13 belum pernah dimulai. Setelah jalur pulih (atau lewat jaringan lain): deploy `aa72ce1`,
`php artisan rt05:konten-impor`, `node infra/scripts/m13-stage-check.mjs`.

**Pemulihan 18 Oktober:** akses PC lewat Cloudflare WARP + IPv6 VPS (alias `rt05-vps-v6`). Deploy `88c7302` 0 GAGAL, impor 13 lalu 0, cek live **14/14 PASS** ([LIVE_RESULTS.md](evidence/m13/LIVE_RESULTS.md)).

Belum (M14): website membaca API ini (saat ini website masih Blade/JSON statis), CMS web, konsumsi outbox untuk
invalidasi cache.

## M12 — Pembayaran offline, kas, koreksi, ekspor — 17 Oktober 2026: **passed** (lokal, CI, staging `337a8bb`)

Dibuka atas instruksi pemilik setelah M11. Kode pembayaran/kas/pembalikan sudah ada sejak jalur layanan-inti; M12
mengaudit terhadap gate ROADMAP dan TESTING FIN-1..18, lalu melengkapi (branch `codex/m12-payments-cash`, ADR27).

| Gate ROADMAP M12 / TESTING | Bukti |
| --- | --- |
| Seluruh FIN critical | FIN-1..8, 11, 13–15, 18 (FinanceTest, AsyncOutboxTest); FIN-9/10 dan pembalikan bersamaan (M12ConcurrencyTest); FIN-12 (AsyncOutboxTest antrean mati setelah commit); FIN-16/17 (M12FinanceTest) |
| Independent concurrency | 3 proses PHP terpisah: dua pengurus membayar tagihan sama dengan kunci berbeda → 1×201, 2×409 `CHARGE_ALREADY_PAID`; 3 pembatalan bersamaan → 1 sukses, 2×409, satu baris pembalikan |
| Timeout replay | 3 proses dengan Idempotency-Key sama bersamaan → satu posting, semua respons sukses memakai nomor bukti yang sama, satu reservasi |
| Partial/overpay ditolak | FinanceTest (tanpa tulisan sebagian) |
| Queue/provider gagal setelah commit | AsyncOutboxTest + M10 (push gagal tidak menyentuh event) |
| Reversal sekali | FinanceTest + konkurensi + rekonsiliasi bersih |
| CSV aman dan batas akses | Formula diberi prefiks; total ekspor kas = saldo ledger; warga 403 untuk tiga ekspor; jenis tak dikenal 404; `no-store`; audit `ekspor.csv` |

Ditambahkan: `rt05:rekonsiliasi-keuangan` + jadwal harian (VPS/shared); konfirmasi saldo negatif (422 → kirim ulang
dengan flag, BUSINESS_RULES); `dapat_dibalik` di daftar kas. Mobile pengurus: tombol **Batalkan** pembayaran yang
terlihat (sebelumnya tekan lama tersembunyi), kas manual bisa **Balik**, alasan minimal 5 karakter, konfirmasi bahaya,
dialog hasil, `showApiError`; Catat kas dengan dialog saldo negatif.

| Perintah | Hasil |
| --- | --- |
| `php artisan test` (Postgres 17.10 sementara) | **296/296, 2917 assertion** (+5 `M12FinanceTest`, +3 `M12ConcurrencyTest`) |
| `vendor/bin/pint`; Redocly lint | bersih; valid |
| `flutter analyze`; `dart format --set-exit-if-changed`; `flutter test` | bersih; 0 changed; **207 lulus** (+8 `m12_finance_admin_test`) |

PR #35 merged `337a8bb`, deploy staging 0 GAGAL; rekonsiliasi data uji staging **0 ketidakcocokan** (saldo 605.000); cek live **7/7 PASS** ([LIVE_RESULTS.md](evidence/m12/LIVE_RESULTS.md)).
Keterbatasan: ekspor besar lewat worker/unduhan privat kedaluwarsa ditunda (skala RT kecil, ADR27). Warga tidak melihat
kas (ADR26). Uji HP pengurus untuk alur keuangan multi-bulan masuk UAT M15.

## M11 — Tarif, tagihan, tunggakan — 17 Oktober 2026: **passed** (lokal, CI, staging `0713226`)

Dibuka atas instruksi pemilik setelah M10 ditutup. Kode tarif/tagihan/catch-up sudah ada sejak jalur layanan-inti;
M11 mengaudit terhadap gate ROADMAP lalu melengkapi kekurangan (branch `codex/m11-dues`).

| Gate ROADMAP M11 | Bukti |
| --- | --- |
| Generator rerun/concurrent unique | `M11ConcurrencyTest`: 4 proses PHP terpisah (barrier advisory lock) membuat tagihan periode sama → total tepat 1 per rumah, rerun 0; UNIQUE(rumah_id, periode) + insertOrIgnore |
| Tarif lama stabil | Snapshot `nominal_tagihan`; tarif baru tidak boleh untuk periode yang sudah ditagih (422); FinanceTest + M11DuesTest |
| Tidak menagih sebelum mulai | `mulai_tagih` + `tagihan_aktif`; FinanceTest |
| Riwayat 12 bulan | `M11DuesTest`: 15 bulan lintas tarif 70.000→75.000; `?bulan=3` tetap 12 |
| Transparansi kode rumah tanpa PII | FinanceTest; rekap pengurus juga tanpa nama/email |
| Warna + label | StatusChip lunas hijau/belum merah dengan label teks dan ikon; tes skala 2,0 |
| Jadwal bulanan dan slot pengingat | VPS `dailyAt 00:10` + catch-up; shared jatuh tempo per tanggal WIB (M10); pengingat iuran dedup per tagihan+tanggal |

Ditambahkan: `GET /admin/dues-recap` (rekap bulanan per kode rumah, tunggakan bulan sebelumnya, tanggal bayar);
**bug diperbaiki**: tarif kedua untuk bulan yang sama sebelumnya 500 (unique violation) → 409 `RATE_PERIOD_EXISTS`,
termasuk simpan bersamaan. Mobile pengurus: Keuangan → **Rekap** (navigasi bulan, angka server, tombol "Buat tagihan
yang belum ada" dengan konfirmasi/alert) menggantikan tab Transparansi pengurus; Lainnya → **Tarif iuran** (tarif
berlaku, riwayat, form tarif baru bulan depan s.d. 24 bulan, bulan terpakai tidak bisa dipilih, konfirmasi, dialog hasil,
error server di field).

| Perintah | Hasil |
| --- | --- |
| `php artisan test` (Postgres 17.10 sementara) | **288/288, 2834 assertion** (+3 `M11DuesTest`, +1 `M11ConcurrencyTest`) |
| `vendor/bin/pint`; Redocly lint | bersih; valid |
| `flutter analyze`; `dart format --set-exit-if-changed`; `flutter test` | bersih; 0 changed; **199 lulus** (+7 `m11_dues_admin_test`) |

PR #33 merged `0713226`, deploy staging 0 GAGAL, cek live hanya-baca **7/7 PASS** ([LIVE_RESULTS.md](evidence/m11/LIVE_RESULTS.md)).
Tidak termasuk (ROADMAP): mencatat uang nyata (M12). Tidak membuat tarif sintetis di staging karena tarif tidak bisa
dihapus dari riwayat.

## Revisi iuran/kas — 17 Oktober 2026 (branch `codex/m10-kas-pengurus`, ADR26)

Instruksi pemilik saat uji HP M10: kas RT tidak boleh terlihat warga; tab Iuran warga menampilkan iuran sendiri lebih
dulu; transparansi warga tetap ada (permintaan Ketua RT) tetapi tersembunyi secara bawaan dan dibuka atas pilihan.
Server: `GET /cash/summary` → `GET /admin/cash/summary` (pengurus; warga 403, path lama 404). Mobile: tab Iuran warga
tanpa sub-tab Kas, transparansi tidak diminta ke API sebelum warga menekan "Lihat transparansi iuran" dan
mengonfirmasi; bisa disembunyikan lagi. Tes skala teks 2,0 menemukan **bug lama**: label status di riwayat tagihan dan
transparansi meluber di layar 320–360 dp (ListTile) → diganti tata letak Wrap.

| Perintah | Hasil |
| --- | --- |
| `php artisan test` (Postgres 17.10 sementara) | **284/284, 2778 assertion** (+1 akses kas pengurus-only) |
| `vendor/bin/pint`; `flutter analyze`; `dart format --set-exit-if-changed` | bersih |
| `flutter test` | **192 lulus** (+4 `dues_privacy_test`: urutan, tanpa kas, transparansi tidak dimuat sebelum diminta, batal/sembunyikan, skala 2,0 di 360/320) |

Uji HP M10 (staging `a86a497`, HP pemilik, akun warga sintetis): lihat penutupan M10 di bawah. Saat uji juga ditambahkan
panduan "Agar notifikasi tidak terlewat" (baterai/mulai otomatis) di Pengaturan notifikasi.

## Penutupan M10 — 17 Oktober 2026: **passed** (staging sintetis)

Project Firebase `rt05-takeda` dibuat di akun pemilik (Spark, Analytics mati, 2-Step Verification diaktifkan pemilik).
Nilai klien di GitHub repository variables, service account di env privat staging (base64, mode 600). PR #30, PR #31
(compose meneruskan `FCM_CREDENTIALS_BASE64`; pola `.gitignore` kunci Firebase diperbaiki), deploy staging `a86a497`
(0 GAGAL, `push_configured=true`). Uji di HP Android pemilik: [ANDROID_RESULTS.md](evidence/m10/ANDROID_RESULTS.md).

| Gate ROADMAP M10 | Bukti |
| --- | --- |
| HP foreground/background/terminated | HP nyata: alert; notifikasi → ketuk → detail; ditutup ±40 dtk → ketuk → detail |
| Permission denial | HP nyata: "Diblokir" + panduan, layanan dan inbox tetap |
| Stale/unauthorized deep link aman | API 404 + widget "tidak lagi tersedia" + validasi data push |
| Timezone | Tes lokal (zona wajib, UTC, ICS, Calendar, tanggal bayar WIB) |
| Reminder dedup/changed/downtime | Tes lokal |
| Provider gagal tidak menghapus event | Tes lokal (404/503/tanpa kredensial); staging: 11 akun tanpa HP "dilewati", event selesai |

Keterbatasan: satu HP, bukan UAT/production; profil shared belum di host nyata (M03/M04 blocked); APK debug diblokir
Play Protect → rilis warga tanpa Play Store mensyaratkan kunci rilis tetap dan verifikasi developer Android (M15/M16);
sebagian merek Android menahan push setelah aplikasi digeser (panduan di aplikasi). Pengumuman uji diarsipkan.

## M10 — Agenda, pengumuman, inbox, push — 17 Oktober 2026 (branch `codex/m10-community-push`)

Instruksi pemilik 17 Oktober: mulai M10 dan **jangan mengandalkan VPS**; target shared hosting, VPS opsi terakhir,
"buat fleksibel". Status **in_progress**: kode, tes lokal, dan kontrak selesai; FCM nyata dan uji HP **blocked**
menunggu project Firebase pemilik. Keputusan teknis: [ADR25](DECISIONS.md).

Yang dibangun:

- Push FCM HTTP v1 tanpa paket/daemon (Laravel HTTP + ext-openssl), jalur sama di shared (antrean database setelah
  response + tick cron) dan VPS (Redis + worker). Tanpa kredensial: inbox saja, `push_status=dilewati`.
- Job `SendPush` setelah inbox commit: preferensi per jenis, akun aktif dengan sesi mobile berlaku, batch 50 dan
  paralel 20, token UNREGISTERED dihapus, 429/5xx release dengan backoff, gagal tidak menyentuh event/inbox,
  `rt05:push-recover`.
- `PUT/DELETE /devices/{id}` (implemented): token terenkripsi, terikat token Sanctum (logout menghapus), pindah
  akun tanpa duplikat, akun menunggu boleh mendaftar.
- Pengingat H-1: jendela 24 jam, catch-up setelah downtime, dedup kunci baris, dilewati bila sudah diberi tahu,
  pengingat basi tidak membuat inbox. `agenda.diubah` untuk perubahan waktu/lokasi.
- Zona waktu: waktu agenda wajib berzona; **bug ditemukan dan diperbaiki**: (1) `Present::ts` memberi jam WIB
  berlabel `Z` pada respons create/update (model belum dimuat ulang); (2) tanggal bayar/kas `before_or_equal:today`
  memakai tanggal UTC sehingga pukul 00.00–07.00 WIB pembayaran bertanggal hari ini ditolak (tes Finance gagal saat
  dijalankan 06.12 WIB). Keduanya punya tes regresi.
- Jadwal shared catch-up: task dicek tiap tick dan jalan bila jatuh tempo (cache file, tanpa query saat idle);
  sebelumnya `hourly`/`dailyAt` terlewat bila cron 15 menit terlambat.
- `rt05:siaran-sistem` untuk notifikasi kebijakan/pembaruan aplikasi.
- Mobile: `firebase_messaging` opsional lewat `--dart-define`; izin Android 13; foreground alert; ketukan
  background/terminated → inbox → detail via API; tautan basi/tidak berhak "tidak lagi tersedia"; Pengaturan
  notifikasi (status izin + preferensi); inbox membuka agenda/pengumuman/iuran; pengurus Agenda & pengumuman
  (buat/ubah/arsipkan, waktu selesai, konfirmasi, dialog hasil, error per field). CI menerima variabel
  `FIREBASE_*` tervalidasi.

| Perintah | Hasil |
| --- | --- |
| `php artisan test` (Postgres 17.10 sementara, bukan Neon) | **283/283, 2768 assertion** (+13 `M10CommunityPushTest`, +1 Finance zona waktu, +1 shared inbox+push setelah response) |
| `vendor/bin/pint`; `npx @redocly/cli@2 lint docs/openapi.yaml` | bersih; valid |
| `flutter analyze`; `dart format --set-exit-if-changed lib test`; `flutter test` | No issues; 0 changed; **188 lulus** (+12 `m10_notifications_test`) |
| `node infra/scripts/check-mobile-build-config.mjs --self-test`; `node infra/scripts/check-repo.mjs` | OK; OK |

Gate ROADMAP M10:

| Gate | Status |
| --- | --- |
| Timezone | Tes lokal: tanpa zona 422, `+07:00` disimpan/ditampilkan benar, ICS UTC, Google Calendar `ctz=Asia/Jakarta`, tanggal bayar WIB |
| Reminder dedup / changed agenda / downtime | Tes lokal lulus (lihat di atas) |
| Provider gagal tidak menghapus event | Tes lokal (404/503/tanpa kredensial) lulus |
| Permission denial | Widget test (layanan tetap, panduan); HP nyata belum |
| Stale/unauthorized deep link aman | Tes API 404 + widget "tidak lagi tersedia" + validasi data push; HP nyata belum |
| Shared inbox ≤60 detik | `SharedRuntimeTest`: inbox+push selesai di `terminate` tanpa cron; host shared nyata belum |
| HP foreground/background/terminated, FCM nyata | **blocked**: butuh project Firebase pemilik |

Blocker (tidak ditebak): project Firebase Spark (gratis) dengan app Android `tech.morriz.takeda`; nilai klien
`FIREBASE_*` sebagai GitHub repository variables; service account JSON di env privat server (bukan Git). Uji HP
memakai HP pemilik via adb (bukan emulator, atas permintaan hemat RAM). Belum di-deploy ke staging.

## Usulan kegiatan warga — 16 Oktober 2026 (PR #27, merged dan staging)

Instruksi pemilik 16 Oktober: warga mengajukan kegiatan (contoh "Badminton bersama di lapangan RT 05") lewat form
dengan dokumen pendukung; pengurus menyetujui; **persetujuan langsung menjadi agenda resmi dan semua warga aktif
menerima notifikasi**; setiap tombol aksi wajib punya alert sukses/gagal yang jelas. Requirement:
[USULAN_KEGIATAN.md](USULAN_KEGIATAN.md) (BR23, ADR24 direvisi). Bukan penutupan M10; FCM tetap belum ada.

Perubahan dari draf Cursor 15 Oktober: form bertambah jenis, perkiraan peserta, kebutuhan dari RT, dan pernyataan
penanggung jawab; batas waktu (≥24 jam, ≤12 jam durasi); persetujuan langsung membuat agenda (endpoint `schedule`
dihapus); tolak persetujuan untuk tanggal lewat; lampiran TXT; tautan unduh bertanda tangan 5 menit; peringatan
jadwal bentrok untuk pengurus; notifikasi perbaikan ke pengurus; standar alert seluruh aplikasi (`showMessage`
berjenis, `showApiError`, `confirm`, `showResultDialog`); dropdown jenis meluber 23 px di 360 dp diperbaiki.

| Perintah (branch `cursor/usulan-kegiatan-90bf`, di-rebase ke main `54a6500` yang sudah memuat M09) | Hasil |
| --- | --- |
| `php artisan test` (Postgres 17.10 sementara, bukan Neon) | **268/268, 2636 assertion** (260 main + 8 `ActivityProposalTest`) |
| `vendor/bin/pint --test`; `npx @redocly/cli@2 lint docs/openapi.yaml` | passed; valid |
| `flutter analyze`; `dart format --set-exit-if-changed lib test`; `flutter test` | No issues; 0 changed; **176 lulus** (termasuk 8 tes alur usulan/alert dan 4 tes aturan form) |
| `node infra/scripts/check-repo.mjs` | OK (842 berkas) |

PR #24 (M09) merged `54a6500`; **PR #27 merged `ed6fc53`** (16 Oktober). Staging di-deploy `ed6fc53` (migration
additive `jobs` dan tabel usulan DONE, 0 GAGAL, 9 container sehat). Uji API live sintetis
`infra/scripts/usulan-stage-acceptance.mjs`: **24 kasus, 50 assertion PASS**, notifikasi 42 dtk
([LIVE_RESULTS.md](evidence/usulan/LIVE_RESULTS.md)). Belum: uji Android nyata (ditunda atas permintaan pemilik
agar hemat RAM) dan push FCM (M10). Nama git global pemilik diperbaiki menjadi `morrispes5`.

## Penyesuaian shared hosting — 15 Oktober 2026

Status tambahan M03/M04: **in_progress**, live **blocked**. Branch `codex/m03-shared-hosting-profile` dari `840cf09`. M07/M08 tetap passed pada baseline VPS. Dokumen branch PR24 menyatakan M09 gate staging passed, tetapi PR masih terpisah; tidak mengubah penerimaan main atau merge PR tersebut.

Koreksi antrean dan email reset (branch `cursor/shared-job-mail-90bf`, masih profil shared): job database termasuk email reset didaftarkan ke pengurasan setelah response, jadi tidak menunggu cron 15 menit. Heartbeat `shared-tick` tetap ditulis hanya setelah tick selesai; umur sehat bila env kosong adalah 900+300 detik. Tanpa `pcntl`, `--timeout` dipasang 0 dan `--max-time` tetap antar-job; yang dipasang adalah batas SMTP/soket/`statement_timeout`/CPU 20 detik, bukan penghentian paksa. Tes `SharedRuntimeTest` memakai `Kernel::handle` lalu `terminate` tanpa `rt05:shared-tick` dan tanpa sleep.

| Perintah | Hasil |
| --- | --- |
| `php vendor/bin/pint --dirty` (rest-api) | lulus |
| `php artisan test` (Postgres lokal `rt05_test` 127.0.0.1:5432, bukan Neon) | **245/245, 2311 assertion** |
| `node infra/scripts/check-repo.mjs` | OK (804 berkas) |

Bukan bukti hosting, inbox SMTP, atau Neon produksi.

Lingkup: ADR23, penyesuaian roadmap/PRD/runbook, queue PostgreSQL additive, tick cron yang hemat DB, konfigurasi media profil shared, dan tes regresi terfokus. Default VPS tetap. Bukti pemeriksaan dan blocker dicatat di [SHARED_HOSTING.md](SHARED_HOSTING.md). Belum menjalankan deploy/DDL pada Neon, mengubah DNS, membeli hosting, mengirim email, atau memasukkan data warga nyata.

Penyesuaian susulan (konteks diskusi hosting 14–15 Oktober): jalur utama profil shared adalah kerja setelah response, cron 15 menit hanya jaring, foto ditranskode di proses yang sama, dan kuota harian 5 pengaduan / 25 foto diberlakukan. Neon Free yang dicek ulang adalah 1 GB/project (bukan 0,5 GB). R2 tetap opsional karena aktivasi meminta metode pembayaran. Paket Resend HTTP belum dipasang. Hosting tetap belum dibeli dan belum diuji. `php artisan test` lokal **242/242, 2272 assertion**; `pint --test` lulus. Bukan bukti live.

Updated: 13 Oktober 2026 (malam WIB, M09).

## Penutupan M09 — 13 Oktober 2026

M09 (aspirasi) **passed** untuk staging sintetis (gate teknis) atas instruksi pemilik untuk lanjut ke M09
dan menyelaraskannya dengan PRD, dokumen `.md`, dan capstone. PR #24, source `617e179`. Rincian di
[evidence/m09/REPORT.md](evidence/m09/REPORT.md).

| Perintah / pemeriksaan | Hasil |
| --- | --- |
| `php artisan test` (Postgres 17.10 sementara, bukan Neon) | **245/245, 2414 assertion** (15 tes M09 baru) |
| `vendor/bin/pint --test`; `npx @redocly/cli@2 lint docs/openapi.yaml` | passed; valid |
| `flutter analyze`; `dart format --set-exit-if-changed lib test`; `flutter test` | No issues; 0 changed; **164 lulus** (7 tes M09 baru) |
| `node infra/scripts/check-repo.mjs` | OK (800 berkas) |
| CI head `617e179` | seluruh check SUCCESS; APK dev gagal sekali (`sdkmanager` runner), lulus pada rerun job yang sama |
| Rollout staging `617e179` (SSH, hanya project `rt05-staging`) | `Selesai`, 0 GAGAL, "Nothing to migrate", 9 container sehat, ready |
| `infra/scripts/m09-stage-acceptance.mjs --execute` (`Sintetis M09 F21B1E`) | **48 kasus, 138 assertion**; [ringkasan](evidence/m09/m09-live-summary-617e179.json) |
| Android emulator Pixel 8a, APK CI `38415151167` | SHA-256 `56a45751…0305` cocok; [ANDROID_RESULTS.md](evidence/m09/ANDROID_RESULTS.md) |

Rebase 16 Oktober 2026: riwayat Git ditulis ulang pada 15 Oktober (identitas commit), sehingga SHA
`617e179`/`ccebb9b` di atas kini bernama `148b2d6`/`719c70a` dengan isi yang sama. PR #24 di-rebase ke `main`
baru (profil shared hosting PR #25/#28). Hasil lokal setelah rebase: backend **260/260, 2492 assertion**
(245 main + 15 M09), Flutter **164**, Pint/analyzer/format/Redocly/check-repo lulus.

Cacat yang ditemukan lalu ditutup: respons aspirasi tanpa `archived_at` (pengurus tidak melihat banner arsip),
filter `status` tidak valid diterima, OpenAPI aspirasi generik, teks layar bernada pengaduan, judul terpecah
pada skala teks 2,0 (temuan M08). Selisih capstone (anonimitas aspirasi, tiga status, kolom `versi`) dicatat di
CAPSTONE_ALIGNMENT.md dan ADR22. Konfirmasi hanya sesudah commit dibuktikan juga di Android saat offline
(tanpa konfirmasi palsu, isian tetap, tanpa duplikat).

Keterbatasan: Google Doc native M09 belum dikerjakan (menunggu konfirmasi pemilik; bukan gate ROADMAP);
preferensi notifikasi per jenis dan push FCM menunggu M10; pemulihan draf kamera/proses ke M15; bukan
production, perangkat fisik, atau UAT mitra; insiden credential historis tetap terbuka untuk M16.
M10 tidak dibuka.

## Penutupan M08 — 13 Oktober 2026

M08 **passed** untuk staging sintetis atas instruksi pemilik (verifikasi ulang,
uji emulator, bagian Drive, lalu commit dan merge PR #23). Source runtime
`6b6987c`; head PR `a3d80e5` hanya mengubah script uji live. Bukti baru
dikumpulkan pada hari yang sama; rincian di
[evidence/m08/REPORT.md](evidence/m08/REPORT.md).

| Perintah / pemeriksaan | Hasil |
| --- | --- |
| `php artisan test` (rest-api, Postgres 17.10 sementara di scratchpad, bukan Neon) | **230/230, 2233 assertion** |
| `vendor/bin/pint --test`; `npx @redocly/cli@2 lint docs/openapi.yaml` | passed; valid |
| `flutter analyze`; `dart format --set-exit-if-changed lib test`; `flutter test` | No issues; 0 changed; **157 lulus** |
| `node infra/scripts/check-repo.mjs` | OK (776 berkas) |
| `gh pr checks 23` pada head `a3d80e5` | seluruh CI SUCCESS (API, Infra, Mobile verify + APK dev/staging, Repo, Vercel) |
| SSH baca-saja ke staging | semua image `rt05-*:6b6987c…`, container sehat, `/health/ready` ready |
| `infra/scripts/m08-stage-acceptance.mjs --execute` (run `Sintetis M08 615CCB`) | **42/42 kasus, 130 assertion**; foto siap 4 dtk. [Ringkasan](evidence/m08/m08-live-summary-rerun-615CCB.json) |
| Android emulator Pixel 8a, APK staging CI run `38398695961` | SHA-256 `f78f3140…5967` cocok `.sha256`; alur lengkap lulus ([ANDROID_RESULTS.md](evidence/m08/ANDROID_RESULTS.md) putaran 2) |
| Drive native | Bagian M08 disisipkan ke panduan dan laporan existing, dibaca ulang; lihat [DRIVE_DRAFT.md](evidence/m08/DRIVE_DRAFT.md) |

Kecocokan PRD (FR07, FR08, FR21, BR08) dan anonimitas: tabel di REPORT.md.
Drill worker-media diulang (hanya container `rt05-staging-worker-media-1`
dihentikan 13:42:21 UTC dan dijalankan lagi 13:43:23 UTC); foto `diproses`
lalu `siap`. Emulator dijalankan headless dengan RAM 2 GiB dan GPU host
karena RAM bebas PC hanya sekitar 1,8 GB; kamera tidak dimatikan Android
pada putaran ini.

Keterbatasan yang tetap: foto tidak dapat ditambah setelah laporan dibuat
(ADR21); pemulihan draf bila Android menutup aplikasi saat kamera terbuka
ditunda ke M15 (keputusan pemilik); judul panjang pada skala teks 2,0 terpecah
di tengah kata (teks tidak terpotong); push FCM menunggu M10; bukan production
atau UAT mitra; insiden credential historis tetap terbuka untuk M16. Data uji
sintetis tertinggal di staging (laporan putaran ini, beberapa diarsipkan).
Staging tidak di-deploy ulang. M09 tidak dibuka.

## Pembukaan M08 — 14 Oktober 2026 (instruksi pemilik)

*Catatan: status di bagian ini adalah checkpoint; hasil akhirnya ada pada
Penutupan M08 di atas.*

Instruksi pemilik terbaru ("biarkan Claude cooking") menggantikan catatan
sebelumnya untuk berhenti sesudah M07. Codex menyerahkan M08 ke Claude lewat
handoff terminal langsung (bukan dispatch Orca; capability M07 lama dicabut).
Branch `codex/m08-complaints-media` dari `80d8180`. Lingkup hanya M08; M09
tidak dibuka otomatis.

Checkpoint 13 Oktober (UTC): source `6b6987c` (PR #23). Lokal backend
**230/230, 2233 assertion**, Flutter **157**; seluruh CI SUCCESS pada
`d790ec2` dan `6b6987c`. Staging di-deploy additive (migrasi foto M08), API
live **42/42, 130 assertion**, drill worker-media berhenti/jalan, Android
(izin kamera tolak/izin, galeri, foto diproses→siap, 409 draf tetap, anonim,
pengurus, arsip, inbox). Penerimaan menemukan bug label notifikasi status
yang terlambat; diperbaiki dan dibuktikan ulang. **Drive native BLOCKED**:
connector Google Docs tidak tersedia; draf di evidence/m08/DRIVE_DRAFT.md,
Doc existing tidak diubah. Status tetap in_progress sampai coordinator/
pemilik menerima dan bagian Drive selesai. Rincian
[evidence/m08/REPORT.md](evidence/m08/REPORT.md). M09 tidak dibuka.

## Penutupan M07 — 13 Oktober 2026

Coordinator menerima **M07 passed**, sesudah M06 passed/PR21 merged.
Source dan image staging `979634fb7ae847f6c743f0ae7e11ec575b1fe722`:
220 tes backend/2125 assertion, 152 tes Flutter, seluruh sembilan CI SUCCESS.
Migrasi additive/backfill idempotent diterima; API live **77/77 kasus,
327/327 assertion**. APK checksum cocok; Android nyata membuktikan tambah
anggota, 409 mempertahankan isian/resubmit sekali, tambahan read-only,
pending terbatas, serta pengurus membuat keluarga/hunian. Tampilan error
diterima pada320dp/skala2. [Laporan lengkap](evidence/m07/REPORT.md).

Native Drive existing dibaca kembali: **31 gambar/4 tabel**, PDF37halaman;
laporan native5halaman. Lima diagram M07 dan lima layar Android ditambahkan;
halaman bukti konflik/pending dan laporan akhir dirender tanpa terpotong.
Kartu rumah perlu muat ulang sesudah membuat keluarga dan badge notifikasi
terpotong pada skala2 dicatat sebagai keterbatasan kecil. Penerimaan ini
untuk staging sintetis/emulator, bukan production atau UAT mitra.

Semua dispatch worker root selesai/dilepas; terminal Grok user_takeover dan
Claude external_terminal dipertahankan. Mailbox Orca kosong. Bukti akhir
dicommit terpisah; source rest-api/mobile/website sama dengan runtime yang
diterima. PR22 diselesaikan setelah CI head bukti lulus. Tidak membuka M08.

## Checkpoint historis M07 — 13 Oktober 2026

Checkpoint source final: backend **220/220, 2125 assertion** sesudah koreksi
akses bersamaan/backfill; Flutter **152 tes**, analyzer/formatter lulus dengan
capability household terbaru dan teks error benar-benar diperiksa pada320px/
skala2. Worker source selesai; CI/staging/Android tetap gate berikutnya.
Pemilik menyoroti penggunaan limit; root membatasi sisa kerja ke CI/deploy/
demonstrasi, tanpa review baru. Pilihan inbox Gmail utama dikonfirmasi pemilik
05:45UTC dan tidak mengulang uji M06 yang sudah lulus.

Source dipush `979634fb7ae847f6c743f0ae7e11ec575b1fe722`, PR #22 draft.
API CI38353725706, infra38353725776, mobile verify dan hygiene SUCCESS;
dua debugAPK masih dibangun saat checkpoint. Claude source/helper terminal
direuse ke `task_3201aad6b734`/`ctx_3781b7e77d8d` untuk rollout SHA dan
77kasus live melalui API sintetis. Rollout API dapat berjalan sesudah cek
API/infra/mobile-verify hijau sambil APK dibangun; uji perangkat menunggu
artefak staging SUCCESS dan checksum cocok. Tidak memakai production.
Release Grok final `ctx_979b8b302587` mengembalikan retained/user_takeover;
root mempertahankan terminal pengguna dan tidak menutupnya. Diagram Drive
native26gambar/4tabel, PDF32halaman; userflow/ERD dirender tanpa terpotong.

Checkpoint live: seluruh9CI termasuk dev/stagingAPK SUCCESS. Artefak staging
run38353725721 cocok SHA256 `6210638aadaa6bcb791ad414d9fa4849e02135f684c250bb1c731eee8d322297`.
Rollout979634f selesai06:12UTC, migration additive DONE, runtime DML rollback0/
DDL ditolak, direct job terisolasi, heartbeat4/4, env/volume/unrelated tetap.
Backfill ulang0/0 idempotent. Live API06:14:12–06:15:10 **77/77,327/327 PASS**
melalui HTTP nyata; tidak ada429 atau resume. Pengisian tagihan periode berjalan
menyentuh rumah staging aktif yang semuanya sintetis; M06 fixture tidak diubah.
Terminal Claude direuse ke Android `task_767258d6330a`/`ctx_999485d251a5`.
APK173MB terpasang setelah hanya aplikasi RT05 uji lama dicopot, cache Android
trim, lalu emulator owned Pixel8a menggunakan1536MiB. Tidak wipe userdata/
instal ulang aplikasi lain; size960x2133/density480=320dp, aplikasi STAGING.
Android merupakan gate terakhir sebelum penerimaan M07 coordinator.

- M06 ditutup pada commit `8eae8fc`, seluruh CI SUCCESS; PR #21 merged ke
  main `bc57b4fa5f5d52f500fa3a81ece8b37c083c5e63`. Branch aktif
  `codex/m07-resident-data` berasal dari main tersebut. Staging tetap M06
  `26a0b7e` sampai source M07 direview, dicommit dan CI lulus.
- Claude `task_7fddc750520f` / `ctx_e158a2b66bd5` menyelesaikan schema
  membership/link additive, backfill tanggal lama NULL, transaksi pendataan,
  kontrak API/OpenAPI/ERD. Laporan lokal: 212 tes / 2075 assertion PostgreSQL,
  Pint dan Redocly lulus. Worker selesai dan dilepas; review akses bersamaan
  diteruskan melalui `task_80f00e321932`. Belum bukti live M07.
- Grok tahap pertama 137 tes/analyzer lulus, tetapi beberapa aksi pengurus belum
  terpasang. Ownership terminal dialihkan ke `task_e7f9aa4f88e7` /
  `ctx_a5d09cdb5e3e` untuk melengkapi seluruh aksi, pagination, dan menjaga
  isian saat konflik versi. Root memegang Git/CI/deploy dan penerimaan perangkat.
- Follow-up Grok melengkapi aksi pengurus/pagination (146 tes/analyzer lulus),
  worker dilepas. Review root masih menemukan capability dari profil sesi
  yang dapat basi dan tes teks hanya memeriksa konfigurasi; task final
  `task_6d3d210196a6`/`ctx_979b8b302587` memperbaiki hak dari household terbaru
  dan memeriksa RenderParagraph/error pada320px/skala2 sebelum source freeze.
- Kimi menyelesaikan koreksi SMTP melalui task final `task_c22deef1be1a` /
  `ctx_68e40a169e90`; worker_done valid diterima dan terminal dilepas. Dua laporan
  dari dispatch sebelumnya ditolak karena capability dicabut, bukan gate baru.
  Panduan native Drive dibaca kembali: 21 gambar (11 diagram, 10 layar nyata),
  4 tabel. Diagram reset dan bukti browser/Android diperbarui dalam file existing;
  laporan native juga mendapat checkpoint M06 passed/M07 aktif. PDF dirender
  untuk memeriksa dua bukti baru; PDF akhir26halaman, halaman25/26 terbaca,
  halaman kosong akhir dibuang. DOCX historis
  tidak diklaim tersinkronisasi dengan native Doc.
- Kimi M07 provider403/limit5jam sebelum menulis output; terminal failed dilepas.
  Draft cadangan Claude `task_5a7d5ffb93eb`/`ctx_821e42a4cd20` menghasilkan
  5 PNG+prosa. Root meminta diagram pendamping dengan huruf minimal44px untuk
  halaman native425pt (detail teknis ukuran penuh tetap ditautkan), melalui
  reuse terminal `task_4eba058596a5`/`ctx_a7d0690c2d67`. Draft bukan gate live.
- Google Stitch edit mengembalikan koreksi teks/DOM pada referensi existing
  `e267c2b12d8b4021861da1cda87716e8`, tetapi screenshot/HTML unduhan masih
  menampilkan artefak lama saat diperiksa. Asumsi pending-primary/JWT/logo
  buatan tidak diterapkan; Grok mendapat aturan yang benar dan menjaga brand
  Flutter existing. Rancangan tidak menggantikan penerimaan aplikasi.

Kontrak [M07](evidence/m07/CONTRACT.md), hasil [Claude](evidence/m07/CLAUDE_RESULT.md)
dan [Grok](evidence/m07/GROK_RESULT.md). Gate pada checkpoint ini masih
menunggu live; hasil akhirnya **passed** sesuai penutupan di atas.

## Penutupan M06 — 13 Oktober 2026

- Coordinator menerima **M06 passed** pada runtime26a0b7e, byte aplikasi sama
  dengan HEAD2dfe4ba yang seluruh CI-nya SUCCESS: API184/1679, Flutter117/
  duaAPK, infra. Bukti52alur akun/17mekanika reset dan Android sebelumnya tetap.
- Key Resend khusus RT05 dibatasi Sending/domain terverifikasi. Claude mengubah
  hanya MAIL_* staging dan recreate API/worker-default pada tag yang sama,
  tanpa build/migration. DB/APP_KEY/Redis, ID service lain dan volume tetap.
  Recycle alami worker media/exports per jam bukan restart tambahan oleh task.
- Inbox lama tidak dapat diakses dari sesi tersedia; coordinator memakai Gmail
  pemilik yang terhubung sebagai default uji yang diumumkan setelah kesempatan
  memilih. Fixture/akun lama dipertahankan privat. Kontak resmi RT tidak berubah.
- **INBOX A02:53:25UTC**, tautan email asli/Chrome reset sukses; verifier A4/4:
  bearer lama401, sandi lama422, sandi baru200, kode ulang422. Fragment hilang.
- Android meminta kode03:09:56.910UTC; **INBOX B03:10:10UTC**, reset03:20:24.104,
  login Beranda03:22:55.348. Verifier B **4/4 PASS03:29:00.769UTC**. B awal dari
  helper digantikan; tidak dihitung sebagai reset akhir. Empat job DONE, nol
  FAIL/exception/pola kode atau fragment pada log yang diperiksa.
- Claude task_e6babca81a7e/ctx_0ec47366d23f dan Grok task_86000e8c6e85/
  ctx_13987cde954b completed; release mempertahankan terminal existing. Wait
  background Claude sempat dihentikan karena RAM PC kritis; root melanjutkan
  verify B langsung. Ini bukan kepastian penyebab penutupan CLI sesi sebelumnya.
- [INBOX_ACCEPTANCE.md](evidence/m06/INBOX_ACCEPTANCE.md) dan laporan/screenshots
  aman menjadi bukti penutupan. M07 boleh dimulai sesudah checkpoint tersimpan;
  belum terimplementasi. Insiden credential historis tetap open untuk M16.

## Pemulihan sesi12 Oktober malam — M06 lalu M07

- Pemilik meminta Claude Code backend/database, Grok mobile/build/infra,
  Codex koordinasi/Chrome, Kimi dokumentasi manusia di Drive. Otorisasi akun
  existing GitHub/Vercel/Drive/VPS Hostinger tercatat sebagai P19. Run Orca
  `run_d783036e0906`; tidak mengulang provisioning fondasi atau mereset data.
- Preflight konsol root srv2030339: Linux6.8.0-139, disk /opt tersedia79G,
  MemAvailable5454MB, env staging mode600, service existing sehat. Script
  immutable SHA256 `ad80d976f8b04f3a9d6969d9e8f70995c55b645170202cdcea7a340bbd75a096`
  sesuai source. Job2130943 menghasilkan footer **Selesai staging
  SHA26a0b7e22cfe96941266cb9c194072512eca707e** sekitar20.21WIB.
  Log privat `/root/rt05-m06-26a0b7e-deploy.log`; tidak memasukkan env ke Git.
  Smoke runtime kemudian **PASS** melalui SSH: marker
  `01a1305b-6267-72eb-970d-0023e9912c44` published→completed dengan attempts1
  sesudah restart Redis/worker-default; sentinel AOF bertahan, empat heartbeat
  sehat, DML pooled/direct rollback0, DDL runtime ditolak, volume dan container
  lain tetap. Hash script smoke
  `d74df7482c514c61812bd8153ba05ed5dbccce266d888b1f722f1997134aa93f`
  sesuai source. Hasil akun Claude/Grok dicatat pada checkpoint akhir di bawah.
- Key SSH publik existing ditambahkan idempotent lewat konsol root ke
  authorized_keys mode600; entry existing dipertahankan. Probe SSH strict
  host-key/BatchMode keluar0, hostname srv2030339 dan UID0. Tidak memindahkan
  password root atau menulis DB/SMTP secret store. Bootstrap sintetis dan
  penerimaan staging dialihkan ke Claude melalui akses SSH tersebut.
- Kimi menyusun panduan manusia16bagian,4tabel,6diagram dan6screenshot nyata.
  Folder Drive existing diverifikasi memiliki parent Tugas dan Project dalam
  `[Kamis] PG194 - Capstone Project`. Panduan native:
  https://docs.google.com/document/d/18yoO1-BY7-ylFyujpkDrpp_NkPQOwq-9-mTRPK_-KlM/edit
  Readback16heading/4tabel/12inlineImages; ekspor PDF16halaman. Pemeriksaan
  halaman4/6/8/14 menunjukkan diagram/layar terbaca; seluruh gambar di dalam
  batas halaman. Laporan existing ditambah checkpoint dan tautan panduan,
  tanpa mengubah dokumen akademik asli. Artefak sumber pada
  `docs/evidence/m06/drive/RT05-TAKEDA-Dokumentasi-Akses-M06.docx`.
- Stitch menghasilkan referensi M07 lalu koreksi aturan pada layar
  `e267c2b12d8b4021861da1cda87716e8` (MOBILE780×3256). Referensi belum menjadi
  bukti aplikasi M07; asumsi KK/SQLite dari keluaran awal tidak diterapkan.
- Claude rerun PostgreSQL lokal184tes/1679assertion dan Pint passed. Root
  `node infra/scripts/check-repo.mjs`653berkas dan diff-check lulus sebelum
  tambahan artefak berikutnya; pemeriksaan akhir dicatat sesudah semua worker.
  SMTP pengirim belum tersedia; pilihan pengirim ditanyakan sambil pekerjaan
  lain dilanjutkan. M06 belum passed; implementasi M07 tetap menunggu gate.
- Penerimaan backend live Claude:52/52alur akun,17/17mekanika reset dengan
  token buatan server (bukan inbox). Probe tambahan29/30 awal terkena pacing
  limiter; rerun terisolasi memenuhi respons yang diharapkan. OpenAPI kini
  menjelaskan kuota bersama mobile-login/web-login5permintaan/menit/IP+email,
  sesuai source dan uji live. Tidak ada perubahan implementasi backend.
- Android Grok membuktikan registrasi utama aktif/keluarga belum diverifikasi,
  tambahan pending, logout/login dan sesi offline→retry PID sama. Follow-up
  terbatas membuktikan ganti sandi/logout-all. Drill runtime Grok yang terpisah juga
  PASS dengan event `01a1305e-6dbe-71f3-bec4-ff4af89c01c9`; tidak menggabungkan
  event itu dengan event root atau menganggap dua drill sebagai satu event.
- Pengirim existing Resend di project lain diperiksa read-only untuk metadata,
  credential tetap di memori server dan tidak disalin/dipakai mengirim RT05:
  GET `/domains` HTTP401. Tidak membuat key atau mengubah env/service proyek
  lain. Pilihan akun Google Chrome tidak cocok dengan inbox uji privat.
  Dibutuhkan login akun pengirim yang tepat atau credential SMTP RT05 valid;
  helper inbox privat siap, tetapi belum dijalankan. Ini blocker akses/input
  nyata, terpisah dari insiden rotasi DB/APP_KEY historis yang tetap tidak diubah.
- Kimi menyelesaikan panduan utama dan koreksi atribusi sebelum quota403.
  Suplemen5diagram UML/sequence/ERD dikerjakan Claude, bukan diklaim karya Kimi.
  Review root meminta label kardinalitas M07 di luar kotak dan akses keluarga
  tambahan hanya setelah approval sebelum suplemen dipublikasikan.
- Suplemen tersebut sudah dipublikasikan ke native Doc yang sama, ditambah
  dua screenshot Android terautentikasi. Readback19inlineImages; ekspor23halaman
  sebelum direktori tautan PNG ukuran penuh ditambahkan. Root memeriksa halaman
  17/19/20/21/22: gambar/caption dan penjelasan berada dalam halaman. Lima PNG
  ukuran penuh terverifikasi MIME/parents pada folder existing; narasi plain
  Google Docs dibersihkan dari penanda Markdown/karakter rusak. Tidak menambah
  folder atau mengubah sharing/dokumen akademik asli.
- Checkpoint akhir: native panduan **24halaman/19gambar inline/4tabel** sesudah
  direktori tautan PNG ukuran penuh. Ekspor PDF terakhir menunjukkan nol gambar
  di luar halaman; halaman diagram, screenshot dan direktori diperiksa visual.
  Google Doc adalah versi terbaru; DOCX Kimi tetap sumber checkpoint sebelumnya.
- Android terakhir membuktikan sandi lama ditolak, sandi baru berhasil, bearer
  dicabut menghasilkan Sesi berakhir tanpa data akun, dan logout-all kembali
  ke form kosong. Sandi akun perangkat sintetis dipulihkan dan dibuktikan login.
  Login fixture bersama ditolak sekali dan tidak diulang; pemeriksaan memakai
  akun perangkat yang sudah terdaftar. Font1,0/jaringan pulih, emulator tetap.
- Prompt bootstrap milik sesi ini yang tertinggal dibersihkan: cmdline lengkap
  PID2138345 dicocokkan dengan perintah bootstrap sintetis sendiri, lalu SIGTERM
  hanya proses tersebut. Admin valid sudah dibuat lewat stdin SSH oleh Claude;
  tidak menghentikan service atau proses milik pekerjaan lain.
- Semua worker penerimaan/dokumentasi selesai. Tugas suplemen Kimi gagal quota
  provider403 dan dialihkan ke Claude tanpa pembelian/bypass. Gate M06 sekarang
  **blocked** pada input pengirim valid/inbox nyata, bukan izin VPS. M07 tetap
  planned. Langkah berikut: login pengirim atau konfigurasi SMTP RT05 valid,
  recreate API/worker-default sesuai runbook, lalu buktikan inbox browser dan
  Android sebelum membuka implementasi M07. Jangan mengulang rollout/drill PASS.
- Validasi penutupan bukti: `node infra/scripts/check-repo.mjs` PASS682berkas;
  `git diff --check` PASS; Redocly2 lint OpenAPI exit0. Perubahan sesi pemulihan
  hanya README/dokumentasi/deskripsi OpenAPI/artefak diagram dan screenshot;
  source rest-api/mobile/infra/website/workflow identik26a0b7e yang diuji.
  Laporan Drive existing menerima checkpoint akhir dan readback verified;
  PR21 tetap draft karena gate inbox belum lulus. Tidak mengklaim deploy kode
  baru hanya dari commit dokumentasi berikutnya.

## Status

Paket konteks diimpor pada M01. API/mobile dan staging telah dibangun pada sesi
berikutnya; hasil dan keterbatasan ada di checkpoint di bawah. Status gate tidak
ditentukan hanya oleh tersedianya kode.

| Milestone | Status | Bukti/ketergantungan |
| --- | --- | --- |
| M00 | accepted_baseline | Handoff repo menyatakan desain accepted; M01 rerun QA baseline pada 34e9b21 dan dari website/ (lihat M01) |
| M01 | passed | PR #10 merged (02d92a1); production alias rt05takeda.vercel.app 19/19 HTML + 26 aset cocok setelah merge |
| M02 | passed | PR #11 merged (321db0d) |
| M03 | passed | PR #15, kode 0d678bb: API CI 88 tes/727 assertion, recovery worker/Redis nyata dan build infra SUCCESS; review final Kimi PASS; docs/evidence/m03/CI_RESULTS.md |
| M04 | passed | Gate fungsional sebelumnya12b4642; patch isolasi credential6a8d4f8 diterapkan12 Oktober: runtime tanpa migrator, DML/DDLdenied, direct job rollback0, heartbeat dan volume/unrelated preserved. Insiden rotasi tetap open; production belum siap. Checkpoint keamanan di bawah. |
| M05 | passed | PR17, source13de24e: 77 tes/analyzer/dua APK CI SUCCESS, checksum cocok; emulator health HTTPS, offline/retry proses sama, ikon dan skala teks1,3/2,0; review final Kimi PASS. docs/evidence/m05/LIVE_RESULTS.md |
| M06 | passed | Staging26a0b7e; API52alur/17mekanika reset; Android akun/sesi; SMTP nyata, INBOX A/browser dan B/Android, verifier4/4 masing-masing. Penutupan13Oktober; INBOX_ACCEPTANCE |
| M07 | passed | Source979634f; 220tes/2125assertion, Flutter152, 9CI SUCCESS, deploy additive/backfill, API live77/327, Android nyata dan native Drive; evidence/m07/REPORT.md |
| M08 | passed | Source6b6987c (head PR a3d80e5): lokal230/2233, Flutter157, seluruh CI SUCCESS, staging live42/130 (rerun 13 Oktober), Android emulator nyata (putaran 2), bagian Drive native disisipkan dan dibaca ulang. Penerimaan staging sintetis; bukan production/UAT. evidence/m08/REPORT.md |
| M09 | passed | Source617e179 (PR #24): lokal245/2414, Flutter164, seluruh CI SUCCESS, staging live48/138, Android emulator nyata; Drive native belum. Penerimaan staging sintetis; bukan production/UAT. evidence/m09/REPORT.md |
| M10 | passed | Staging `a86a497` + HP Android pemilik: push foreground/background/terminated, izin ditolak; lokal 284/2778, Flutter 192; FCM `rt05-takeda`. Penerimaan staging sintetis; bukan production/UAT. evidence/m10/ANDROID_RESULTS.md |
| M11 | passed | 17 Oktober: audit gate + rekap bulanan pengurus, tarif admin mobile, generator bersamaan teruji, bug tarif ganda 500→409; lokal 288/2834, Flutter 199. Bagian "M11" teratas |
| M12 | passed | 17 Oktober: FIN critical + konkurensi antarproses + timeout replay + rekonsiliasi harian + konfirmasi saldo negatif + aksi pembalikan mobile; lokal 296/2917, Flutter 207. Bagian "M12" teratas |
| M13 | passed | 17–18 Oktober, staging `88c7302` live 14/14: API konten publik (draf/terbit/arsip, snapshot, slug redirect, media, impor katalog) + Konten website di mobile pengurus; lokal 303, Flutter 216. Bagian "M13" teratas |
| M14 | passed | Website membaca API + CMS web nyata (ADR30); staging `08f2a1e` 21/21 + drill API mati |
| M15 | **passed** (22 Okt, ADR36) | UAT tim putaran 1–3 + demo Ketua RT + uji emulator agent; APK 1011 `f392612`; sisa gate dipindah ke M16 |
| M15b | **passed** (22 Okt) | Permintaan Pak RT (ADR35): APK 1011, staging `f392612`, uji emulator + live CMS |
| M16 | in_progress | Fase A (22 Okt): SEO + `/aplikasi` + `/kebijakan-privasi`, versi 1.0.0, #47 dijelaskan; ADR37 tanpa verifikasi developer; production menunggu domain (awal Nov, ADR43) |
| M17 | in_progress (25 Okt) | Penyesuaian hasil rapat RT 24 Okt (ADR43): kontak resmi, bendahara, super admin, jalan Mawar 8–14, masa simpan. Bagian "M17" teratas |
| M18 | planned | Keuangan fleksibel (kritis): tarif khusus, nominal bebas, keringanan/kurang bayar, sumbangan/titipan, kas transparan, Excel tahunan |
| M19 | planned | Tombol panik uji coba: 3 langkah, semua warga + pengurus, tidak anonim, 3 penyalahgunaan → nonaktif |
| M20 | planned | iPhone lewat PWA (tanpa Apple Developer berbayar) |

M00 memakai label accepted_baseline khusus, bukan status passed hasil tes baru. M01–M16 menggunakan planned/in_progress/passed/blocked.

Catatan M10–M12: kode awal dari jalur layanan-inti sudah berada di `main`, dan
status `in_progress` di atas adalah catatan lama dari jalur itu. Belum ada
gate penerimaan (live, Android, dokumentasi) yang dijalankan untuk ketiganya.
M10–M13 dibuka dan ditutup 17 Oktober (bagian atas berkas ini).

## Riwayat pembukaan M06 (checkpoint 12 Oktober)

**M06 — registrasi dan akses akun**, dibuka oleh instruksi pemilik12 Oktober
melalui rencana M06–M07. Baseline `fb6e59f`; branch `codex/m06-account-access`.
M03/M04/M05 passed berdasarkan bukti existing. M07 belum dibuka untuk
implementasi baru sampai gate M06 lengkap. Rencana/kontrak dan matriks di
[CONTRACT.md](evidence/m06/CONTRACT.md) dan
[REQUIREMENTS.md](evidence/m06/REQUIREMENTS.md). Run Orca `run_f3422d73e850`:
Claude backend `task_3cdd792156f2`/`ctx_684922fbe969`, Grok Flutter
`task_3873a1c7e6bc`/`ctx_0b094886b9fe`; Codex integrasi/website/dokumen/CI.
Keputusan utama langsung aktif dengan keluarga belum_verifikasi dikonfirmasi
oleh rencana pemilik; capstone asli dipertahankan, selisih dilaporkan.

Checkpoint awal:
- PRD repo dan `PRD (2).md` induk identik SHA256
  `63c5888fa6005d3fbe893ddef66f7172a16800621dc21045bd3652a97d17fbac`.
- Folder laporan Drive dibuat pada Capstone/Tugas dan Project:
  https://drive.google.com/drive/folders/1_WweESw5u0iHaDlw6LUfFAuUgOzAstpj.
  Empat contact sheet diunggah; readback final dan laporan menyusul.
-40 foto ditinjau visual; satu duplikat persis; enam foto menjadi turunan
  WebP tanpa metadata. Dua album tambahan, album17Agustus bertambah dua foto;
  semua tanggal/tahun kosong. [ALBUM_REVIEW.md](evidence/m06/ALBUM_REVIEW.md).
- Website `npm.cmd run build:preview`:21 rute; `npm.cmd run test:preview`:
  105 kasus responsif,45 Axe +3 editor,37 link, nol error browser; interaksi,
  IndexedDB isolation/recovery dan slideshow3detik PASS. `php artisan test
  --compact`:9 tes/173 assertions passed. Ini QA lokal, bukan deploy online.
- OpenAPI M06 payload/auth envelopes diperinci; `npx.cmd --yes @redocly/cli@2
  lint docs/openapi.yaml` exit0. Gateway cookie/CSRF/reset dan session/mail
  env disiapkan; belum dibuktikan di server.
- SMTP host belum terlihat pada env lokal/staging yang diperiksa (hanya key
  dan status konfigurasi, tanpa mencetak nilai). Lokasi konfigurasi dan inbox
  uji ditanyakan; reset external belum passed. Insiden DB/APP_KEY tetap open;
  production dan data warga nyata tidak dibuka.

Checkpoint lanjutan12 Oktober:
- Album PR20 merged `9dd047f` setelah checks konten sukses. Deploy Git Vercel
  gagal mengambil informasi Git; snapshot statis committed `e472f7a` diterbitkan
  ke project existing. Alias publik terverifikasi **21/21HTML,32aset, mobile
  PASS,404rute asing,nol error** pada07:42UTC. [ALBUM_LIVE.json](evidence/m06/ALBUM_LIVE.json).
- Backend handoff pertama:162tes/1275assertions PostgreSQL17.10 lokal terisolasi,
  HTTP terpisah dan concurrency proses nyata; Pint passed. Flutter handoff
  pertama:analyze clean,98tes passed. Build lokal terhenti mengunduh NDK;
  APK/emulator dan CI tahap ini belum terbukti. [CLAUDE_RESULT.md](evidence/m06/CLAUDE_RESULT.md),
  [GROK_RESULT.md](evidence/m06/GROK_RESULT.md).
- Root meminta tindak lanjut sebelum review silang: guard mailer log di semua
  env, batas72byte UTF8 dan NUL sebelum bcrypt, status tambahan ditolak tetap
  terbatas, barrier rotasi deterministik, brand form reset, dan respons logout
  lama tidak menghapus sesi mobile baru. Task Claude `task_fb43b380b205`, Grok
  `task_f2c4204621e6`; hasil akhir/CI dicatat sesudah worker_done.
- Gateway `/auth-assets/*`, cookie API terpisah, mail scheme/from dan deploy
  immutable branch M06 disiapkan; Redocly valid, check-repo pass, kontrak infra
  pass, isolasi database self-test2positif+19negatif pass. Konsol Hostinger
  dapat dibuka; inspeksi read-only masih menunjukkan stack sehat SHA `6a8d4f8`.
  Belum ada rollout M06 atau klaim integration live baru.
- Folder Drive berisi15artefak yang di-readback: laporan/matriks/review/UI,
  enamPNGdiagram/sumberZIP,empatcontactsheet. Laporan final akan diperbarui
  pada fileID sama. Stitch listing masih40layar existing sesudah timeout;
  tidak ada layar baru terbukti.

Handoff final kode sebelum CI: Claude follow-up **171tes/1461assertions**
passed, Pint passed; race deterministik5run berurutan dan uji mutasi menemukan
regresi yang disengaja. Form reset Playwright lokal320–1280px tanpa overflow,
kedua font termuat, Axe0violation. Grok follow-up **107tes** passed/analyze
clean; epoch logout/401/storage/password response dan Unicode/NUL diuji.
[CLAUDE_FIXES.md](evidence/m06/CLAUDE_FIXES.md),
[GROK_FIXES.md](evidence/m06/GROK_FIXES.md). Ini belum membuktikan CI/Linux,
APK, staging baru atau inbox. SMTP staging dicek langsung dengan output hanya
nama key/status: mailer bukan smtp; HOST/PORT/USERNAME/PASSWORD tidak ada.
Konfigurasi pengirim belum tersedia; acceptance reset tetap blocked.

Checkpoint review12Oktober:
- Source `8d0b3df` CI seluruhnya SUCCESS: API171/1461, Flutter107/analyze/format
  dan kedua APK dev/staging, infra serta hygiene. [CI_RESULTS.md](evidence/m06/CI_RESULTS.md).
- Review silang Claude mobile/Grok backend selesai pada source tersebut.
  Grok memperbaiki bearer/epoch logout yang dipatok saat request dibuat,
  menahan kembali selama registrasi, dan judul/alasan permohonan ditolak;
  analyze clean dan114tes lokal passed. [GROK_REVIEW_FIXES.md](evidence/m06/GROK_REVIEW_FIXES.md).
- Claude menutup bootstrap fail-fast, kecocokan password lama persis, exception
  antrean sesudah commit dan limiter reset terpisah;179tes/1577assertions lokal
  passed. [CLAUDE_REVIEW_FIXES.md](evidence/m06/CLAUDE_REVIEW_FIXES.md).
  Review root menemukan race inisialisasi RateLimiter di implementasi reservasi
  token yang baru; follow-up terbatas `task_f684bcfbae8b`/`ctx_660bb74c1bce`
  menangani fresh/zero counter sebelum freeze kode final. Belum dianggap passed.
  Hasil suite sesudah koreksi184tes/1679assertions dan Pint pass; uji mutasi
  mendeteksi5reservasi lolos pada batas3 dalam versi vendor. CI baru menyusul.
- Pemilik menegaskan pengirim email belum pernah disiapkan. Asumsi SMTP existing
  pada rencana dicabut. Penerima uji sudah disimpan privat, bukan di Git.
  [SMTP_OPERATOR.md](evidence/m06/SMTP_OPERATOR.md) menyediakan langkah konkret.
  M06 belum passed dan implementasi baru M07 tetap terkunci.
- Folder Drive sekarang16artefak termasuk Google Doc native pada folder yang
  sama; isi/parents dan58paragraf readback verified. Final CI/live/screenshots
  akan diperbarui pada fileID yang sama.

Checkpoint source koreksi `15885cbf2d08b3e0f2b66b32899e9e6857ff63d1`:
- API CI38228867337 SUCCESS184tes/1679assertions, OpenAPI/Pint, Redis recovery
  dan role runtime/direct smoke; infra38228867353 dan hygiene38228867368 SUCCESS.
  Mobile38228867355 SUCCESS114tes/analyze/format dan kedua APK.
- Review delta Claude mobile/Grok backend selesai pada byte source yang sama;
  temuan sebelumnya tertutup. [CLAUDE_MOBILE_DELTA_REVIEW.md](evidence/m06/CLAUDE_MOBILE_DELTA_REVIEW.md),
  [GROK_BACKEND_DELTA_REVIEW.md](evidence/m06/GROK_BACKEND_DELTA_REVIEW.md).
- APK staging checksum cocok dan terpasang di emulator guest sintetis;
  fingerprint ada di CI_RESULTS. Layar masuk/validasi, daftar utama/tambahan,
  forgot generik untuk example.invalid dan form reset diperiksa. Form kosong
  tidak submit. Skala1,3/2,0 dapat scroll, tetapi error aturan password pada2,0
  terpotong; koreksi UI dibuka ke Grok `task_b2f9f3619c32`/`ctx_f84669a0ef50`.
  Tidak menganggap uji tampilan final passed sebelum APK koreksi diperiksa.
- Sesi konsol Hostinger sebelumnya kedaluwarsa; Chrome kini login Hostinger.
  Pemilik diminta membuka ulang Konsol VPS. Deploy script belum dieksekusi;
  health existing200/ready09:21UTC bukan bukti M06 terpasang. Bootstrap dan
  skrip browser/API sintetis siap privat, belum dijalankan ke staging.

Koreksi visual lanjutan: helper aturan password bersama dipakai daftar/reset/
ganti sandi; label pendek dan error16baris mengatasi ellipsis. Grok115tes awal
lalu116tes sesudah ganti sandi, analyze clean; tes320px/skala2,0 memeriksa
RenderParagraph utuh untuk aturan minimum dan pesan72byte. [GROK_EMULATOR_UI_FIX.md](evidence/m06/GROK_EMULATOR_UI_FIX.md).
CI/APK baru dan pemeriksaan emulator koreksi tetap menunggu. Guest offline/retry
source15885cb passed dengan PID sama; jaringan/font dikembalikan.
Koreksi terakhir: pengulangan reset terbukti terpotong pada 320px dan skala 2,0,
lalu `errorMaxLines: 16` dipasang pada error layar masuk, daftar, lupa sandi,
dan ganti sandi. Analyze bersih dan 117 tes lulus. Laporan
[GROK_EMULATOR_UI_FIX.md](evidence/m06/GROK_EMULATOR_UI_FIX.md) dan
[CLAUDE_PASSWORD_UI_REVIEW.md](evidence/m06/CLAUDE_PASSWORD_UI_REVIEW.md).
CI `7d42d56` lulus untuk repositori, API, infra, dan kedua APK. Di emulator,
skala 2,0, pesan pengulangan reset tampil utuh. [LIVE_RESULTS.md](evidence/m06/LIVE_RESULTS.md).
M06 belum passed. M07 tetap terkunci.
[LIVE_RESULTS.md](evidence/m06/LIVE_RESULTS.md) memisahkan bukti yang tersedia
dari gate registrasi/login/approval/keluarga dan sesi yang masih blocked.

### Checkpoint sebelumnya

**Penutupan patch keamanan fondasi M04**, PR18 merged8e2d8a5,
staging6a8d4f8 terverifikasi12 Oktober. Dokumen bukti pada branch
`codex/m04-security-evidence`; tidak membuka M06+.
Instruksi12 Oktober: prioritaskan insiden DB/APP_KEY dan isolasi migrator sebelum
data warga nyata. Gate M05 lulus source13de24e dan review final Kimi; PR17
sudah merged `ba3528d` pada11 Oktober12:02:22UTC. Seluruh delapan checks pada
head da42063 sukses, termasuk Mobile38137327953 (77 tes).
M04 sebelumnya merged `3a8be79`; rollout keamanan berikutnya menjalankan6a8d4f8.
Pemilik meminta pengerjaan bertahap dengan empat agent (P16), bukan membuka
seluruh roadmap serentak. [COORDINATION.md](COORDINATION.md) mencatat ownership,
gate, pemulihan setelah limit/listrik mati, dan urutan berikutnya.

PR #14 digabung pada 11 Oktober 2026 setelah lima cek SUCCESS dan review patch;
merge commit `c644b84`. `main` kini memuat routing/aset HTTPS yang sudah dipakai
staging. Tidak ada redeploy server dari aksi merge ini.

### M02 — Laravel API dan Neon foundation

- Status: passed (10 Oktober 2026), dengan catatan Postgres lokal di PC pemilik belum tersedia (lihat limitations).
- Branch/commit/PR: `codex/m02-api-neon-foundation` dari main 02d92a1; PR https://github.com/morrispes5/RT-05-TAKEDA/pull/11.
- Scope dan FR/BR: fondasi untuk FR18 (audit), FR23 (health), DATABASE.md/API.md/SECURITY.md konvensi; tidak ada fitur warga.
- Dependency verified: M01 merged; live preview cocok setelah merge (`check-live`: 19/19 HTML, 26 aset, HP PASS, 0 error).
- Runtime: Laravel 13.35.0 (sama dengan website), PHP 8.4.13 + pdo_pgsql/pgsql/intl/sodium (diaktifkan di php.ini lokal; backup `php.ini.bak-rt05-m02`), libpq 16.9, Postgres 17 (CI dan Neon 17.11).
- File berubah: `rest-api/` (skeleton Laravel dipangkas: tanpa frontend/Vite, users default, sessions/cache/jobs tables, rute storage otomatis), `docs/openapi.yaml`, `redocly.yaml`, `.github/workflows/api.yml`, `infra/compose.local.yml`, `infra/sql/grant-runtime-role.sql`, `infra/docker/postgres/init/01-rt05-roles.sql`, `infra/scripts/check-repo.mjs` (DSN lokal & SQL sumber diizinkan), `.gitignore`, docs DECISIONS (ADR13–16), DATABASE, ERD, README, infra/README, PROGRESS.
- Endpoint: `GET /health/live`, `GET /health/ready`, `GET /api/v1` (implemented). 102 operasi lain `x-status: planned` per milestone dan menghasilkan 404 JSON.
- Migration: failed_jobs, audit_logs (append-only trigger), outbox_events, idempotency_requests, system_settings.
- Environment/data: Neon project `rt05-takeda` (raspy-sky-23605923, aws-ap-southeast-1, PG17, gratis) dibuat dengan persetujuan pemilik; branch `production` (kosong) dan `dev` (br-crimson-art-b31mz59n). Data hanya sintetis/rollback; tidak ada data warga.
- Commands dan actual results:

  | Command | Hasil |
  | --- | --- |
  | CI `api.yml` (Postgres 17 service, role dari init SQL): `php artisan test` | 33 passed (370 assertions), 0 risky |
  | CI `rt05:db-smoke --connection=pgsql` / `pgsql_migrations` | rt05_app: tulis+baca ok, sisa 0, DDL denied; rt05_migrator: ok |
  | CI `npx @redocly/cli lint docs/openapi.yaml` | valid, 0 warning (operation-4xx-response off, ADR di redocly.yaml) |
  | Neon dev `composer migrate` (direct, rt05_owner) | 5 migration DONE |
  | Neon dev `rt05:db-grant-runtime-role rt05_app` | hak DML diterapkan |
  | Neon dev `rt05:db-smoke` (pooled, rt05_app) | server 17.11, endpoint pooled, sslmode verify-full, tulis+baca ok, sisa 0, DDL denied |
  | Neon dev `rt05:db-smoke --connection=pgsql_migrations` (direct) | ok, sisa 0 |
  | `APP_ENV=staging rt05:db-smoke` | exit 0 dengan verify-full; dengan URL `sslmode=require` exit 1 "sslmode harus verify-full" |
  | TLS negatif ke Neon | CA palsu: "certificate verify failed"; sslmode=disable: ditolak Neon "connection is insecure" |
  | `php artisan serve` → Neon dev | /health/live 200 ok; /health/ready 200 database ok; /api/v1 200 + request_id; /api/v1/auth/register 404 NOT_FOUND; Cache-Control no-store; body tanpa host/role |
  | Role check | rt05_app anggota neon_superuser: 0 |
  | `node infra/scripts/check-repo.mjs` | OK; uji negatif DSN Neon berpassword tertangkap |

- Evidence path: log CI PR #11 (API verification), tabel di atas.
- Acceptance gates passed: migration Postgres CI + Neon dev direct sukses; runtime pooled read/write sintetis terverifikasi; SSL verify-full terbukti (positif + negatif); client publik tidak menerima DSN (tes no-leak health/error); health/error schema diuji terhadap OpenAPI; role minimal (DDL denied, bukan superuser).
- Acceptance gates blocked/failed: tidak ada untuk gate ROADMAP. Catatan: Postgres lokal pada PC pemilik belum berjalan (Docker Desktop butuh WSL2 yang belum terpasang); tes Postgres dijalankan di CI.
- Security/finance/privacy implications: role runtime tanpa DDL; audit append-only; error tanpa stack trace/host; rute `storage/{path}` bawaan dimatikan; tes menolak database selain *_test lokal sehingga tidak menyentuh Neon.
- External mutation performed dan dasar otorisasi: merge PR #10 (instruksi "oke merge"); pembuatan project Neon rt05-takeda + branch dev + role rt05_app + migration fondasi di branch dev (persetujuan eksplisit di pertanyaan sesi M02); push branch + PR #11. Tidak ada perubahan Vercel/DNS/VPS. Branch production Neon tidak disentuh.
- Known limitations:
  - Tidak ada Postgres di PC pemilik sampai WSL2/Docker atau Postgres portabel disiapkan.
  - `sslrootcert=system` tidak didukung PHP Windows; dev memakai salinan CA bundle Git di `%USERPROFILE%\.rt05\ca-bundle.crt` (path tanpa spasi). Bundle salinan tidak otomatis ter-update.
  - `pg_prepared_statements` via pooler menampilkan statement milik pooler; tes nol-prepared hanya berarti pada koneksi direct.
  - Password Neon ada di `rest-api/.env` lokal (di-gitignore). Credential owner pernah tampil di keluaran tool agent saat diambil; pertimbangkan reset password rt05_owner branch dev setelah sesi.
  - FK actor_id ke users menunggu M06. Redis/queue/session menunggu M03.
- ADR/doc updates: ADR13–ADR16; DATABASE.md, ERD.md (implementasi M02), rest-api/README, infra/README, README root, openapi.yaml.
- Next milestone: M03 (Redis queue/cache, worker, scheduler, outbox dispatcher). Prasyarat lokal Redis juga butuh Docker/WSL atau Redis di CI.

### M01 — Monorepo foundation

- Status: passed (10 Oktober 2026). Gate lokal, CI GitHub, dan preview Vercel PR terbukti. PR belum di-merge; setelah merge, Root Directory Vercel diubah pemilik (docs/website/VERCEL_ROOT.md).
- Branch/commit/PR: `codex/m01-monorepo-foundation` dari `main` 34e9b212a3948a52e5f705a561691fac10103ae9. Commit: 30e3903 (rename murni 219 file, 100% identik), 8b36540 (adaptasi monorepo), 350390a (perbaikan script paket), 1ccc932 (progress), commit penutupan ini. PR: https://github.com/morrispes5/RT-05-TAKEDA/pull/10.
- Tanggal: 10 Oktober 2026.
- Scope dan FR/BR: FR01 (rute/layout baseline), BR01/BR02 dipertahankan; tidak ada fitur baru.
- Dependency verified: M00. `git status` bersih di `main`; tag `web-frontend-approved-2026-10-10` = HEAD; branch remote `claude/hopeful-ptolemy-acq64b` tidak memuat commit tambahan; tidak ada perubahan pengguna yang belum di-commit. Repo publik, `main` tanpa branch protection.
- Runtime: PHP 8.4.13, Composer 2.8.12, Node 22.16.0, npm 10.9.2, Git 2.54, gh 2.97, Docker 29.6.1 (daemon tidak berjalan saat inspeksi). Flutter/Dart tidak ditemukan. Android SDK (platforms 34–36.1, build-tools 36.x), emulator + AVD Pixel_7a/Pixel_8a, JBR Android Studio tersedia; `java` di PATH = 1.8, `JAVA_HOME` kosong.
- File berubah:
  - Dipindah: source/preview/scripts/tests/config/lockfile → `website/`; dokumen/bukti/screenshot frontend → `docs/website/`; `.github/workflows/frontend.yml` → `website.yml`.
  - Baru: `docs/*.md` (18 dokumen paket), `docs/website/VERCEL_ROOT.md`, `docs/evidence/m01/`, `.github/workflows/repo.yml`, `infra/scripts/check-repo.mjs`, `infra/README.md`, `mobile/README.md`, `rest-api/README.md`, `vercel.json` root (jembatan), `.gitignore` root.
  - Diubah: `README.md`/`AGENTS.md` root (gabungan paket + AGENTS frontend), `website/README.md`, `website/scripts/package-frontend.ps1`, `docs/FRONTEND.md` (koreksi commit/tree), `docs/SOURCES.md` (tautan tag), `docs/DECISIONS.md` (P13, P14, ADR11, ADR12), tautan internal `docs/website/*`.
  - Tetap di root: `.claude/`, `.editorconfig`, `.gitattributes`.
- Endpoint/migration/config baru: tidak ada endpoint/migration. Config: workflow `website.yml` (working-directory `website`, path filter `website/**`), `repo.yml`.
- Environment/data: lokal Windows; tanpa database; tanpa data warga.
- Commands dan actual results:

  | Command (cwd) | Hasil |
  | --- | --- |
  | Baseline di root, commit 34e9b21: `npm.cmd run build:preview`, `git status -- preview` | 19 rute diekspor; `preview/` tanpa diff |
  | Baseline: `php artisan test`, `php vendor/bin/pint --test` | 9 tes/155 assertions passed; Pint passed |
  | `node scripts/check-live.mjs https://rt05takeda.vercel.app` (sebelum migrasi) | 19/19 HTML exact, 26 aset cocok, 404 untuk rute tak dikenal, HP PASS, 0 error browser |
  | `curl -I` live | 200, `X-Robots-Tag: noindex`, `/pengurus/konten` 307 → `/pengurus` |
  | `website/`: `npm.cmd run build:preview` | 19 rute; 67 blob `website/preview/` identik dengan blob `preview/` baseline |
  | `website/`: `php artisan test`; Pint | 9/155 passed; passed |
  | `website/`: `php artisan route:list` | Rute tetap `/`, `/profil`, `/fasilitas`, `/dokumentasi/{slug}`, `/artikel/{slug}`, `/kontak`, `/pengurus/*`; tidak ada prefix `/website` |
  | `website/`: `npm.cmd run test:preview` (Playwright Chromium) | 95 kasus responsif, 41 Axe, 33 tautan internal, 0 error browser; interaksi, editor (artikel/album/persistensi/isolasi/recovery/text safety/reset, 0 network write), slideshow 3 detik semua PASS |
  | `website/`: `npm.cmd audit` | 0 vulnerabilities |
  | Clone bersih branch: `node infra/scripts/check-repo.mjs`, composer/npm install, build, test, Pint, `git diff --exit-code -- preview` | Semua lulus; preview clean |
  | Clone bersih: `scripts/package-frontend.ps1` | 201/201 file `website/` cocok blob Git; ZIP berawalan `web/`, tanpa `docs/`/`mobile/` |
  | Uji negatif `check-repo.mjs` (DSN berpassword, `.git` bersarang, tautan rusak) | Exit 1, ketiganya tertangkap; dibersihkan |
  | Parse YAML `.github/workflows/*.yml` (paket `yaml` di scratchpad) | Valid; `website.yml` 13 step working-directory `website`; `repo.yml` 3 step |
  | `git log --follow -- website/README.md` | History berlanjut ke commit sebelum migrasi |
  | `git push -u origin codex/m01-monorepo-foundation`; `gh pr create` | PR #10 dibuat, mergeable/clean |
  | GitHub Actions run 38045547706 (Repository checks / hygiene) | pass |
  | GitHub Actions run 38045547743 (Website verification / verify) | pass: 19 rute, 95 responsif, 41 Axe, 0 error browser |
  | Vercel deployment PR (dpl_7TmjL5n7RZkFdBiQF2uchf558nEQ), Root Directory belum diubah | Ready; dilayani lewat jembatan `vercel.json` root |
  | Fetch + SHA-256 seluruh 67 file preview PR dari Chrome pemilik (preview dilindungi SSO Vercel) | 66 exact; `/` cocok setelah membuang script toolbar Vercel Live yang disisipkan Vercel; 404 untuk rute tak dikenal; `/pengurus/konten` redirect; `X-Robots-Tag: noindex` |

- Evidence path: `docs/evidence/m01/local-qa.json`, `docs/evidence/m01/live-web-before-migration.json`, `docs/evidence/m01/pr10-ci-and-preview.json`.
- Acceptance gates passed: struktur normal tanpa `.git` bersarang; routes/desain unchanged; preview export sesuai source; test web relevan lulus; workflow valid (dijalankan di GitHub Actions); credential tidak ikut (check-repo); dokumen/prompt dipasang; PROGRESS memuat commit baseline; konfigurasi Vercel eksternal yang belum diubah dicatat terpisah (VERCEL_ROOT.md).
- Acceptance gates blocked/pending: tidak ada untuk M01. Tindak lanjut di luar gate: merge PR #10, ubah Root Directory Vercel, hapus jembatan `vercel.json` root.
- Security/finance/privacy implications: tidak ada data warga/credential. `.gitignore` root menolak `.env*`, kunci, signing, dump.
- External mutation performed dan dasar otorisasi: push branch `codex/m01-monorepo-foundation` dan pembuatan PR #10 atas instruksi langsung pemilik ("push aja terus bikin PR nya"). Tidak ada merge, perubahan setelan Vercel, DNS, atau VPS. Baca saja: GitHub API, Vercel API, HTTP GET preview, fetch preview PR melalui Chrome pemilik.
- Known limitations:
  - Proyek Vercel preview tidak terlihat dari connector agent pada team `morriz`; Root Directory aktual belum dibaca. Jembatan `vercel.json` root menjaga preview sampai pemilik mengubahnya (docs/website/VERCEL_ROOT.md).
  - Commit 30e3903 sendiri berisi workflow dengan path lama; branch dinilai sebagai satu PR.
  - Preview PR Vercel memakai Deployment Protection; `scripts/check-live.mjs` dari luar mendapat 302 SSO. Verifikasi preview PR memakai sesi Chrome pemilik. Alias production `rt05takeda.vercel.app` publik dan tetap dapat diperiksa dengan `check-live`.
  - Connector Vercel agent tidak berizin membaca deployment proyek `morrizshkki/rt05takeda` (403).
  - Flutter SDK belum terpasang (prasyarat M05).
- ADR/doc updates: ADR11, ADR12, P13, P14; koreksi FRONTEND.md (34e9b21 adalah commit, tree 37510826bfedb447ea572f0b72cd5577f7bd0270).
- Next milestone: M02 (Laravel API + Neon dev). Tutup M01 setelah push/PR: CI hijau dan preview Vercel PR cocok dengan `check-live`.

## Template laporan milestone

### MNN — Nama

- Status:
- Branch/commit/PR:
- Tanggal:
- Scope dan FR/BR:
- Dependency verified:
- File berubah:
- Endpoint/migration/config baru:
- Environment/data:
- Commands:
- Actual results:
- Evidence path:
- Acceptance gates passed:
- Acceptance gates blocked/failed:
- Security/finance/privacy implications:
- External mutation performed dan dasar otorisasi:
- Known limitations:
- ADR/doc updates:
- Next milestone:

### Sesi 3 (10 Oktober 2026) — layanan inti, mobile, artefak deploy

- Instruksi pemilik: kerjakan semampunya lintas milestone (izin VPS/Docker/PC), hemat SSD, desain mobile boleh kolaborasi Google Stitch. Dicatat sebagai P15.
- Branch `codex/m06-m12-layanan-inti`, PR https://github.com/morrispes5/RT-05-TAKEDA/pull/12 (belum merge).
- Logbook #1–#4, Tugas 03/04, ERD v1.1 (31 tabel) dibaca dari Google Drive. Tabel domain mengikuti nama ERD v1.1 (ADR17 rencana): akun, token_registrasi, permohonan_akun, rumah, pemilik_rumah, keluarga, warga, penghunian, kategori/pengaduan/foto/riwayat, aspirasi/riwayat, agenda, pengumuman, notifikasi, preferensi, tarif/tagihan/pembayaran/alokasi iuran, transaksi_kas/riwayat. Penyesuaian v1: rupiah bigint, outbox/idempotency/audit_logs.
- API: 82 route implemented (OpenAPI 1.0.0-m12, 33 planned: konten publik CMS M13/M14, media terpisah, device push, app-version).
- Bukti:
  - CI API (Postgres 17, role runtime): 71 tes lulus termasuk identitas, keuangan (snapshot tarif, multi-bulan, parsial ditolak, idempotency replay/409, alokasi ganda ditolak DB, pembalikan sekali, koreksi atomik, ledger immutable), layanan (anonimitas feed/detail/riwayat/notifikasi, edit pemilik, versi 409, foto re-encode & spoof 415, outbox dedup).
  - Smoke HTTP lokal → Neon dev: 24/24 lulus (login, keluarga, iuran Rp75.000, transparansi tanpa PII, saldo kas 530.000, anonim, 403 warga, akun menunggu, token no-store, AMOUNT_MISMATCH, replay, 409, pembalikan, inbox, Google Calendar).
  - CI Infra images: compose valid, image API + website build, smoke gateway (/health/live, /api/v1, /profil, 404 JSON) lulus.
  - Mobile: `flutter analyze` bersih, `flutter test` 13 lulus (lokal); CI build APK debug berjalan.
- Bug nyata ditemukan & diperbaiki: catch-up tagihan melewatkan bulan berjalan (konversi Asia/Jakarta→UTC); timestamptz(0) membulatkan ke atas sehingga outbox tertahan; FK self-reference sebelum PK; Sanctum provider tidak terdaftar karena --no-scripts.
- Lingkungan PC: Flutter di C:\Users\USER\dev\flutter (1,4 GB, Android saja). WSL/Docker lokal sengaja TIDAK dipasang (hemat SSD); tes Postgres/Redis/Docker di GitHub CI. Emulator Pixel_8a dinyalakan.
- Neon dev di-reset (migrate:fresh, branch dev sintetis) dan di-seed `rt05:demo-seed`; password akun demo hanya ada di `/tmp/demo-seed.txt` lokal (Git Bash), tidak di repo.
- VPS: Hostinger KVM 2 srv2030339.hstgr.cloud 187.77.113.136, Ubuntu 24.04 + Coolify. SSH dari agent ditolak pengaman otomatis; port 8000 (panel Coolify) tidak terjangkau dari luar. DNS morriz.tech: @ → 2.57.91.91, www CNAME; record `takeda` BELUM dibuat (sesi terhenti karena batas pemakaian).
- Blocked/tersisa: DNS takeda + takeda-staging; deploy Coolify (butuh URL/akses panel Coolify; credential production dimasukkan pemilik sendiri ke Coolify); branch Neon production role rt05_app; SMTP; FCM; signing key rilis; website CMS ke API (M13/M14); UAT.

### Sesi 4 (10 Oktober 2026) — emulator, DNS, siap deploy

- PR #12 merged (00cd078) setelah API, Infra images, Mobile, Repository checks hijau.
- Emulator Pixel_8a: APK CI terpasang; login warga demo → beranda (iuran Oktober Belum dibayar), iuran (Agustus/September Lunas), layanan (pengaduan anonim "Identitas disembunyikan"), akun/keluarga; login pengurus → ringkasan (saldo Rp530.000, 3 rumah, 1 permohonan) → catat iuran Oktober A-1 → saldo Rp605.000, rekap 1 lunas/2 belum. Bukti: docs/evidence/mobile/01–08.
- DNS: A takeda dan takeda-staging → 187.77.113.136 dibuat lewat hPanel (izin pemilik); resolusi 8.8.8.8 terverifikasi; HTTPS ke host menjawab 503 dari proxy Coolify (belum ada aplikasi).
- Neon: branch staging (dari dev, data demo) dibuat; role rt05_app di branch production dibuat via SQL (bukan neon_superuser). Env server ditulis ke infra/env/.env.staging dan .env.production (gitignored, tidak dicetak).
- Deploy: infra/compose.vps.yml + infra/scripts/deploy-vps.sh (ADR18). Menunggu pemilik menjalankan script di Konsol web Hostinger (agent tidak menjalankan perintah shell di server).
- Keterbatasan: staging memakai password rt05_app yang sama dengan dev (ALTER ROLE ditolak di branch turunan); push FCM, SMTP, CMS website→API (M13/M14), signing key rilis, UAT belum.

### Sesi 5 (11 Oktober 2026) — deploy staging lewat Konsol web

- Otorisasi langsung pemilik: jalankan instruksi deploy staging dari Claude melalui Chrome dan beri kabar setelah selesai. Target hanya `takeda-staging.morriz.tech` / project `rt05-staging`.
- Baseline source server/lokal: `53a8b10c9156fa6dcbd57d623863efb238f39d64`. Perbaikan lokal disimpan pada branch `codex/m04-staging-routing`; server menjalankan baseline tersebut dengan patch empat file yang sama. Patch belum berada pada `origin/main`.
- Build pertama lima image berhasil (~311 detik), deploy script exit 0. Migrasi: `Nothing to migrate`; hak DML role runtime diterapkan. Env server berada di `/root/.rt05/.env.staging`, mode 600; direktori mode 700.
- Dua masalah live ditemukan dan diperbaiki: label Traefik berbentuk mapping tidak menginterpolasi nama label sehingga HTTPS menjawab 503; chain proxy tidak mempertahankan HTTPS sehingga URL aset HTTP diblokir browser. Label kini memakai list `key=value`; gateway mempercayai proxy privat; website menerima `TRUSTED_PROXIES` dan APP_ENV staging.
- File implementasi: `infra/compose.vps.yml`, `infra/compose.production.yml`, `infra/docker/gateway.Caddyfile`, `website/bootstrap/app.php`. Hanya gateway/web staging dibuat ulang setelah perbaikan; tidak ada operasi mutasi pada container layanan lain, DNS, firewall, atau production.
- Verifikasi 07:31 WIB: HTTPS valid tanpa bypass; `/health/live`, `/health/ready`, `/`, `/profil`, `/api/v1` semuanya HTTP 200; ready mengembalikan database ok. Browser Chrome memuat CSS/JS/gambar HTTPS dan navigasi profil berhasil. API/web/Redis healthy; worker/scheduler Up; enam container staging restart count 0.
- Lokal: `php artisan test --compact` lulus 9 tes/155 assertion; pemeriksaan repo dan `git diff --check` lulus. Caddy validation, Compose config, build web ulang (~11 detik) berhasil.
- Insiden rahasia: setelah env ditempel dengan terminal echo off, pembacaan AX browser masih mencatat nilai textarea dalam keluaran alat. Tidak ada nilainya pada source atau evidence tersimpan; clipboard dibersihkan. Rotasi password role database dan APP_KEY staging diperlukan sebagai tindak lanjut; password runtime dev yang sama perlu ditangani bersama. Rotasi belum dilakukan.
- Handoff lengkap: [docs/evidence/m04/STAGING_DEPLOY_2026-10-11.md](evidence/m04/STAGING_DEPLOY_2026-10-11.md). Jangan menjalankan ulang script yang melakukan reset ke `origin/main` sebelum patch disatukan ke main, karena akan mengembalikan dua bug deploy.
- Batas hasil: deploy/smoke staging selesai; gate M04 menyeluruh (Neon write, job/heartbeat, restart persistence) dan M15/M16 belum dinilai ulang pada sesi ini.

### Sesi 6 (11 Oktober 2026) — verifikasi staging, APK staging, temuan rahasia

- Verifikasi dari PC: `/health/ready` 200 (database ok), `/health/live`, `/api/v1`, `/`, `/profil` 200; `POST /api/v1/auth/mobile-login` tanpa body → 422, `GET /api/v1/auth/me` tanpa token → 401. `https://takeda.morriz.tech` masih sertifikat default Traefik (production belum di-deploy).
- Patch routing sesi 5 dibawa ke main lewat PR #14 (branch `codex/m04-staging-routing`).
- APK debug dengan `API_BASE_URL=https://takeda-staging.morriz.tech` dibangun via workflow_dispatch (run 38099378306, sukses), dipasang ke emulator Pixel_8a; layar login tampil. Bukti: docs/evidence/mobile/09-login-staging.png. Login ke staging belum diuji agent (credential ke host non-lokal diketik pemilik sendiri). Catatan: kunci debug CI berbeda per run, jadi APK lama harus di-uninstall sebelum memasang yang baru.
- Temuan: password `rt05_owner` di `.env.staging` identik dengan `.env.production` (role disalin saat branch Neon dibuat), sehingga insiden sesi 5 juga mengenai credential owner production. Rotasi oleh agent ditolak pengaman (penulisan secret store); **blocked → pemilik**: reset password `rt05_owner` di branch production (dan staging/dev) serta buat APP_KEY production baru sebelum deploy production. Langkah ada di docs/DEPLOYMENT.md (Rotasi credential).

## Checkpoint sesi

### Sesi 7 (11 Oktober 2026) — koordinasi dan pemulihan M03 setelah listrik mati

- Instruksi terbaru: Codex mengelola Claude Code, Grok, dan Kimi dengan ownership
  file terpisah, satu milestone beserta gate sebelum maju; target sampai M05
  bertahap, semua perbedaan planning/Drive capstone dilaporkan. Pemilik menyediakan
  Chrome Hostinger dan mengizinkan terminal/emulator serta akses deployment.
  Otorisasi dicatat di DECISIONS dan COORDINATION; tidak meminta ulang akses.
- Run Orca `run_2d500f79d407` dipulihkan dan coordinator diikat ulang pada runtime
  setelah listrik mati. Sesi Claude yang sudah dipulihkan pemilik dipakai kembali;
  tidak mereset konteks, provisioning, database, atau pekerjaan pengguna.
- PR #14 diverifikasi lima cek SUCCESS, ditinjau, lalu merged dengan HEAD
  `8a723f9` ke merge commit `c644b84`. Branch `codex/m03-async-recovery`
  dibuat dari baseline itu. Server tidak berubah akibat merge lokal/GitHub.
- Pembagian: Claude `rest-api/` (dispatcher, job, failure parking, heartbeat dan
  tes); Grok `infra/` serta workflow API/infra (Redis terpisah, worker, recovery
  drill); Kimi laporan penerimaan independen; Codex Git, kontrak, dokumen dan
  verifikasi gabungan. Tidak ada agent lain mengubah Git pada tahap ini.
- Audit awal Kimi menemukan dispatcher sinkron tanpa Redis job, satu Redis
  untuk antrean/cache, serta bukti restart yang belum ada. Backend dan infra
  diserahkan dengan worker_done diterima. Laporan di `docs/evidence/m03/`.
- Kontrak: `ProcessOutboxEvent` membawa UUID, inbox/completion atomik di Postgres;
  lease/stale replay; event gagal diparkir lewat `failed_at` dan retry per UUID.
  Config queue `retry_after=180`, `after_commit=true`; connection queue/cache
  terpisah; probe internal `rt05:heartbeat-check`. ERD/ASYNC_JOBS diperbarui;
  tidak ada endpoint HTTP baru sehingga kontrak OpenAPI tidak berubah.
- Validasi yang sudah dijalankan: `node infra/scripts/check-repo.mjs` lulus
  (470 berkas pada checkpoint integrasi); `git diff --check` lulus;
  `website/: php artisan test --compact` lulus 9 tes/155 assertion.
  Website tidak diubah pada branch M03, sehingga tidak ada ekspor preview baru.
- Backend lokal: Pint passed; `RT05_SKIP_TEST_MIGRATIONS=1 php vendor/bin/phpunit
  tests/Feature/HeartbeatTest.php tests/Unit` passed 10 tes/50 assertion tanpa
  koneksi database. Tes Postgres/Redis penuh menunggu CI; angka ini bukan
  kelulusan gate recovery.
- Infra statis `check-m03-compose.mjs` dan `bash -n` lulus. Sesudah handoff,
  Codex memperbaiki kepemilikan PID worker/scheduler dengan exec PHP, memeriksa
  inbox kosong/outbox committed sebelum SIGKILL, menunggu dua delivery drain,
  mengikuti AOF everysec, dan menambah path filter CI skrip recovery.
- Dokumen capstone Tugas 03/04 dibaca melalui Google Drive; sumber dicatat di
  SOURCES; Logbook 1/2/3 dan brainstorm turut dibandingkan. Semua selisih yang
  teridentifikasi dicatat di CAPSTONE_ALIGNMENT.md, termasuk flow approval
  registrasi utama dan tipe nominal. Chrome Hostinger dapat diinspeksi melalui computer-use; emulator
  Pixel_8a dinyalakan, `adb shell getprop sys.boot_completed` mengembalikan 1.
  Belum ada login emulator atau redeploy staging pada checkpoint ini.
- CI aktual kode `0d678bb` pada PR #15: API run 38112554974 SUCCESS,
  88 tes/727 assertion; kill setelah commit, duplikat, AOF everysec restart,
  queue-loss replay (attempts=2/inbox=1), heartbeat worker/scheduler,
  runtime/migrator smoke rollback=0 dan OpenAPI. Infra run 38112554907
  SUCCESS: Compose, build API/web, gateway smoke. Hygiene/Vercel keduanya
  SUCCESS. Bukti rinci: `docs/evidence/m03/CI_RESULTS.md`.
- Review final Kimi memeriksa log CI langsung dan menutup B01–B05/N01–N04;
  seluruh gate M03 PASS. Docker/WSL lokal tetap tidak dipasang sesuai ADR19;
  CI sintetis ini bukan bukti deployment VPS baru. Tidak menjalankan tes Neon.
- Kimi aktif mengaudit meskipun composed startup Orca keliru menandai
  `agent_prompt_stalled`; laporan tersimpan. Coordinator memulihkan tracking
  tanpa menggandakan atau merestart agent yang masih bekerja. Review final
  dikirim sebagai prompt biasa dan task dipulihkan manual berdasarkan laporan
  tersimpan; tidak mengklaim worker_done diterima dari capability yang dicabut.
- Langkah berikutnya: merge penutupan M03, kemudian staging M04 karena gate
  M03 telah terbukti. Insiden credential
  sesi 5–6 tetap open; production/UAT/signing/SMTP/FCM bukan hasil tahap ini.

### Sesi 8 (11 Oktober 2026) — M04 setelah M03 lulus

- PR #15 merged `27a3e5b` sesudah lima cek SUCCESS pada `ea4179e`;
  kode/API/infra diuji ulang, review final Kimi PASS. Branch M04:
  `codex/m04-staging-recovery`, dari main yang sudah memuat M03.
- Claude menyerahkan probe CLI `rt05:staging-smoke`: APP_ENV staging dan
  flag konfirmasi wajib sebelum akses DB; satu event sintetis tanpa penerima,
  akun, inbox atau keuangan. Guard *_test CI lama tidak dilonggarkan.
- PR #16 pada `75dee91`: lima check SUCCESS. API run 38113599864 lulus
  93 tes/766 assertion (lima StagingSmokeTest); infra 38113599888 dan hygiene
  38113599875 SUCCESS. Bukti `docs/evidence/m04/CI_RESULTS.md`; ini belum
  mencakup skrip rollout infra berikutnya maupun operasi live VPS.
- Grok menyiapkan rollout SHA tetap dan checkout release terpisah; Kimi
  memeriksa batas tindakan, schema/privasi, credential dan bukti live.
- Preflight nyata Chrome Hostinger ada di `docs/evidence/m04/PREFLIGHT.md`.
  Server mempunyai empat patch routing lama belum di-commit; dipertahankan.
  Available RAM 5614 MiB dan disk 80G; Docker 29.8.2/Compose 5.6.0.
  Coolify/aplikasi lain berjalan; tidak disentuh.
- Dua password Redis staging belum ada. AUTH tetap wajib; provisioning
  khusus staging harus menjaga credential/APP_KEY/DSN yang sudah ada.
  Tidak merotasi credential production yang sebelumnya ditolak pengaman.
- Setelah handoff Grok, Codex menutup temuan integrasi: same-SHA enam service,
  snapshot volume/mount dan unrelated ID/StartedAt, compatibility rollback,
  timeout probe, serta marker pending yang harus selesai dengan attempts
  sama setelah Redis AOF restart. Helper Python hanya mengisi dua key Redis
  staging kosong/hilang secara atomik pada env existing privat; tanpa output
  nilai atau perubahan DSN/APP_KEY. `bash -n`, empat pure synthetic tests,
  check-m03-compose, check-repo dan diff-check lokal lulus; belum dijalankan
  terhadap file env/server nyata. Reviewer pengganti Claude memeriksa kode.
- Gate live M04 masih pending: rollout baru, Neon read/write sintetis,
  heartbeat empat komponen, restart/persistence dan pemeriksaan service lain.
  M05 belum dibuka untuk implementasi baru sebelum bukti remote M04.
- Audit baca-saja kesiapan M05 oleh Claude ada di
  `docs/evidence/m05/READINESS_REVIEW.md`; belum implementasi baru atau gate
  mobile. M05 dapat dibuktikan dengan health request nyata; login layanan
  M06 dan UAT fitur berikutnya bukan prasyarat fondasi M05.
- Kimi menyelesaikan checkpoint kode backend, tetapi dispatch review infra
  berikutnya berhenti karena provider 403 batas penggunaan 5 jam. Task
  `task_9689bdf377b5` ditandai blocked lewat pemulihan coordinator, tanpa
  memalsukan worker_done. Sesi tidak ditutup/diganti atau diulang saat limit.
  Review independen integrasi infra dialihkan kepada Claude.

### Penutupan operasi M04 (11 Oktober, sesudah sesi/kuota dipulihkan)

- Instruksi terbaru pemilik: bila Kimi limit, alihkan tugas ke Grok, pertahankan
  sesi Kimi. Review acceptance final M04 memakai Grok, Dispatch baru
  `ctx_457c94ed8b6a`; kepemilikan file review saja. Codex tetap pemilik Git/VPS.
- Provisioning hanya dua key Redis staging kosong melalui helper yang blob
  SHA256-nya cocok; file env root mode 600/direktori 700, credential lain
  dipertahankan, tidak ada nilai dicetak. Ini sudah dieksekusi, bukan pending.
- Rollout pertama62 menemukan kesalahan nyata opsi `compose run --no-build`;
  berhenti sebelum migrasi/API. Dipulihkan dari image62 tanpa edit worktree:
  migrasi failed_at applied, grant runtime DML, up stack hanya project sendiri.
  Perbaikan opsi `run --rm --no-deps --pull never` dan guard CI di12b4642.
- Lima cek PR #16 pada12b sukses: API38115505380 (93 tes/766 assertion),
  infra38115505350, hygiene38115505386, Vercel dan Preview Comments.
- Rollout otomatis final12b benar-benar selesai di Hostinger. Current-sha12b,
  previous62; enam service image SHA sama dan worker/scheduler ID sama API.
  Source legacy dengan empat patch routing belum commit tetap dipertahankan.
- Runtime smoke final12b PASS: Neon staging pooled rt05_app verify-full
  read/write rollback0 DDLdenied, direct owner rollback0; event published1
  tetap tersimpan sesudah Redis antrean AOF restart dan completed1/inbox0.
  Empat heartbeat sehat; tiga volume name/CreatedAt dan 13 unrelated ID/StartedAt
  sama. Diff baseline unrelated dari sebelum operasi62 ke12 comparison=0.
- GET luar VPS TLS valid: live/ready/API root dan empat rute website200;
  ready databaseok; tanpa token auth/me401; rute tidak ada404. Profil Agus,
  aset beranda/profil tidak memakai HTTP. API /profil belum M13 (404 expected).
- Neon connector mengonfirmasi staging terpisah dari dev/production; pencarian
  substring host yang diizinkan pada env cocok dengan compute staging, tanpa DSN.
  Bukti lengkap `docs/evidence/m04/LIVE_RESULTS.md`. Review Grok final menerima
  gate fungsional pada `docs/evidence/m04/GROK_ACCEPTANCE_REVIEW.md`.
- Gate fungsional M04 terpenuhi. Rotasi credential DB/APP_KEY insiden lama
  belum selesai karena pengaman otomatis sebelumnya menolak (secret store);
  tidak dicoba lewat jalur lain. Tidak mengklaim keamanan production/M16.
  M05 dibuka setelah review, pembukuan dan merge PR16; hanya fondasi mobile.

### Checkpoint M05 — implementasi dan review awal

- Branch `codex/m05-mobile-foundation`, rencana dipush pada `0309f3e` setelah PR16 merged. Scope hanya fondasi M05; layanan bisnis historis M06+ belum diterima oleh gate baru.
- Claude memiliki `mobile/**`: config, health, secure session, go_router, brand/font terbundel, lokalisasi dan tes skala teks. Overflow tata letak lama ditemukan lewat widget test dan sedang diperbaiki; tidak ada perubahan aturan bisnis.
- Grok menyerahkan workflow/guard CI awal dan laporan `docs/evidence/m05/GROK_CI_RESULT.md`. Review Codex menemukan perbedaan penerimaan slash akhir/port dengan Dart; follow-up Grok aktif untuk menyamakan kontrak. Belum ada run GitHub atau APK baru.
- Pemilik memperkirakan kuota Kimi tersedia lagi. Sesi sama mulai merespons audit, tetapi dua `worker-start` diberi `agent_prompt_stalled` oleh Orca; laporan completion konteks M04 lama ditolak `inactive_dispatch`. Ini bukan bukti gate baru. Sesi dipertahankan dan scope diarahkan ke audit rencana M05 saja; Grok tetap melanjutkan pekerjaan utamanya.
- `docs/MOBILE.md` menjelaskan struktur fondasi aktual, konfigurasi origin/HTTPS, health publik sebagai gate M05, dan pilihan kecil label navigasi maksimum skala1,3 (isi layar mengikuti skala sistem). Gate analyze/test baru, artifact CI, health emulator, offline/retry dan review final masih pending.
- `node infra/scripts/check-repo.mjs` (516 berkas pada pemeriksaan Codex) serta `git diff --check` lulus pada checkpoint dokumentasi; angka dapat bertambah saat worker menulis. Tidak menjalankan Gradle/APK lokal, tes/factory Neon, atau redeploy VPS.

Integrasi setelah handoff: Claude menyelesaikan75 tes, Grok menyerahkan guard
yang telah dikoreksi. Codex menambahkan validasi port/bentuk mentah URL dan dua
tes config, flag Android `allowBackup=false`, serta self-test guard CI Node22.
Hasil lokal Codex: `flutter analyze` No issues found; `flutter test`77/77;
format25 file0changed; self-test guard, `check-repo`520 file dan diff-check lulus.
Completion audit rencana Kimi akhirnya diterima pada ctx_820928d88366
(worker-show completed/succeeded); retry duplikat ditolak, tidak dihitung ulang.
Tiga butir rencana implisit dibuat eksplisit. CI/APK dan emulator tetap pending;
scope fitur/business/API/ERD tidak berubah, sehingga kontrak API/schema tidak
perlu diubah untuk fondasi ini.

### Remediasi credential12 Oktober — source dan staging isolasi PASS, rotasi OPEN

- Insiden historis DB/APP_KEY tetap open dan menghalangi data warga nyata/
  production. Pemisahan role DDL semestinya ditutup pada fondasi staging,
  tidak ditunda ke M16. Tidak mencoba ulang atau mengakali penolakan otomatis
  penulisan secret store; operator harus memberikan bukti rotasi nonsecret.
- Baseline ba3528d: reproduksi `docker compose --env-file` example eksplisit
  dan env sintetis, production+VPS `config --format json` menunjukkan key
  DATABASE_URL_UNPOOLED pada API, tiga worker, scheduler dan migrate.
  Nilai env/DSN asli tidak dibaca/dicetak dan tidak ada koneksi DB.
- Patch: key direct hanya `migrate.environment`; PHP tidak mendaftarkan
  pgsql_migrations bila URL direct tidak tersedia; TLS hanya melewati koneksi
  migrasi yang tidak tersedia, runtime tetap strict; smoke/grant menolak
  sebelum DB call. Direct smoke lewat job migrate sementara. Deploy/rollback
  memeriksa graph Compose efektif, release lama tanpa isolasi ditolak.
- Root .dockerignore menutup jalur COPY env/cache/storage privat pada semua
  stage. CI BuildKit menguji canary env/cache sintetis beserta kontrol sumber,
  logo dan contoh env yang harus tetap tersedia. Tidak mengganti website/mobile,
  aturan bisnis, schema atau kontrak HTTP/OpenAPI/ERD.
- File: infra/compose.production.yml; check_database_isolation.py;
  check-build-context.sh; deploy-vps.sh/deploy-staging-immutable.sh;
  staging-runtime-smoke.sh; .github/workflows/infra.yml; config/database.php,
  TlsPolicy, DatabaseSmoke/GrantRuntimeRole; DatabaseIsolationTest,
  TlsPolicyTest dan HealthTest; SECURITY/DATABASE/DEPLOYMENT/PROGRESS.
- Validasi lokal: Compose5.2.0 production+VPS resolved graph PASS setelah patch;
  guard --self-test PASS19 kasus bocor/misconfig dan dua kontrol valid; phpunit filter
  DatabaseIsolationTest|TlsPolicyTest PASS11 tes48 assertion. Bootstrap tetap
  menolak host nonlokal/database selain *_test; RT05_SKIP_TEST_MIGRATIONS=1
  hanya untuk focused tests tanpa DB. Tidak melakukan factory/tes Neon.
- PHP syntax empat file PASS, Bash syntax empat script PASS, Pint PASS,
  check-m03-compose PASS, check-repo538 berkas dan diff-check PASS.
  Docker CLI tersedia tetapi daemon lokal tidak jalan; tidak menghidupkan/
  memasang Docker/WSL atau mengklaim BuildKit lokal. Full API/BuildKit/image
  verifikasi menunggu CI. Investigator independen selesai; reviewer kandidat
  menemukan bypass query username/password terhadap parser Laravel. Root
  mengonfirmasi sumber dan menolak override credential, termasuk percent/
  array/NUL dan username JSON encoded; opsi TLS/channel_binding tetap valid.
  Tidak ada temuan konkret lain pada satu siklus review. Folder pengguna
  .agents/ tetap untracked.
- Pada checkpoint ini VPS masih deployment yang terakhir diverifikasi12b4642;
  source patch belum di-deploy. Status kode dan operasi live harus dipisahkan.
  Runbook rotasi kini menjaga env atomik, inventaris data terenkripsi/revokasi
  sesi, dan bukti penolakan credential lama; tidak menghapus env terlebih dulu.
- [PR18](https://github.com/morrispes5/RT-05-TAKEDA/pull/18), source2b8dbce:
  seluruh lima checks SUCCESS. [API38190853171](https://github.com/morrispes5/RT-05-TAKEDA/actions/runs/38190853171)
  PASS100 tes785 assertion, Pint, worker/Redis recovery, runtime DML/DDLdenied,
  direct migration smoke dan OpenAPI. [Infra38190853238](https://github.com/morrispes5/RT-05-TAKEDA/actions/runs/38190853238)
  PASS2m31s: BuildKit lima canary env/cache tidak ikut COPY, source/logo/examples
  tetap tersedia; kedua graph Compose resolved dan guard19 kasus PASS;
  kedua image terbangun dan HTTP image smoke PASS. Hygiene38190853200 dan
  Vercel/Preview Comments PASS. Runtime migration/grant/bootstrap sintetis
  tetap berfungsi; caller direct smoke pindah ke job, bukan container API.
  Ini bukti kode/build, belum bukti patch diterapkan atau credential dirotasi
  di VPS. Dokumen commit berikutnya tidak mengubah source yang diuji.
- Head penutupan6a8d4f8 identik source2b8 untuk kode/workflow. Semua lima
  checks terbaru SUCCESS: API38191265964 (100 tes), Infra38191265941,
  Hygiene38191265944, Vercel dan Preview Comments. PR18 merged
  `8e2d8a5393273d90ce0c2446c23e8ff9b97cb71e` pada01:19:33UTC.
- **Rollout staging aktual PASS**: hostname srv2030339 via konsol Hostinger
  yang diotorisasi. Helper blob6a8 SHA256
  e39ec37008ad630bfc46be5b3dbac94393ca94eb9e7857c04db026b3fd655f28 cocok
  dengan Git; umask077, job556073 mulai01:24:34UTC dan selesai exit0.
  Log privat `/root/rt05-security-6a8d4f8-deploy.log`. Footer menyatakan
  Selesai staging SHA6a8d4f8; graph isolasi PASS sebelum build/rollout.
  Lima runtime memakai image API yang sama; ID image
  b93025ee869d0d2eea010c597ffe57b93c2752caafcf38c5db34f1ea452deb76.
- Bukti runtime sesudah recreate: `docker exec ... printenv
  DATABASE_URL_UNPOOLED >/dev/null` pada api, worker-default, worker-media,
  worker-exports dan scheduler semuanya exit1 (key absent); tidak mencetak
  nilai. API direct smoke ditolak dengan pesan koneksi migrasi tidak tersedia,
  exit2, sebelum akses DB. DML smoke API: PG17.11/rt05_app, pooled,
  verify-full, tulis+baca ok, rollback0 dan DDLdenied.
- Kontrol operator: `compose run --rm --no-deps --pull never migrate php
  artisan rt05:db-smoke --connection=pgsql_migrations` pada tag6a8:
  rt05_owner/direct, verify-full, tulis+baca ok dan rollback0. DDL tidak
  diprobe pada job owner. Docker ps -aq filter project/service migrate kosong
  sesudah job --rm, tidak meninggalkan container DDL. Tidak menjalankan
  migration/factory/seed destruktif.
- Heartbeat aktual default1s/media2s/exports1s/scheduler59s. Pipeline
  assert_staging_images/volumes/unrelated lulus sebelum footer selesai.
  Redis-data CreatedAt2026-10-11T00:26:59Z, api-storage00:27:01Z,
  apk-downloads00:27:19Z dipertahankan. Pohon legacy empat patch tetap ada;
  service lain, DNS, port80/443 dan firewall tidak diubah.
- Probe HTTPS luar VPS: live/ready/APIv1/profil200; ready databaseok.
  Tidak merotasi DB/APP_KEY, tidak mengubah env privat existing, tidak deploy
  production atau memasukkan data warga. R08 selesai untuk source/staging;
  R07 tetap OPEN sampai bukti rotasi/revokasi nonsecret dari operator tersedia.
  Rollback ke12b yang masih membagikan migrator ditolak oleh guard; riwayat/
  image lama tidak dihapus dan tidak disebut target rollback aman.

### M05 — bukti source final dan emulator, gate PASS

- Source `13de24e230b44dd78b382260013108b5daf1d84a`, PR17. Seluruh delapan
  checks SUCCESS: Mobile38135976078 (format25 file0changed, analyzer bersih,
  77 tes dan APK dev/staging), Infra38135976074, Hygiene38135976098,
  Vercel dan Preview Comments. [CI_RESULTS.md](evidence/m05/CI_RESULTS.md).
- `gh run download`, `Get-FileHash`/scan ZIP Python, `aapt2 dump badging/xmltree`
  dan `adb install`: dua checksum cocok, aset SVG identik website, delapan
  TTF, nol pola DSN/credential yang diperiksa; allowBackup=false dan cleartext
  debug hanya host lokal. Instalasi staging Success setelah hanya paket uji
  lama kosong dihapus karena signing debug berbeda.
- Emulator Pixel_8a: startup staging health HTTPS nyata PASS, airplane/Wi-Fi/data
  offline menampilkan error; jaringan dipulihkan dan Coba lagi pada PID yang
  sama kembali health ok. UUID response tercatat di UI/logcat. Skala Android
  1,3/2,0 terbaca, scroll tombol tetap dapat dijangkau, ikon native resmi;
  tidak ada exception/overflow/crash. [LIVE_RESULTS.md](evidence/m05/LIVE_RESULTS.md)
  dan tujuh screenshot aman tanpa akun/data warga. Pengaturan emulator restored.
- Drill runtime memakai guest; preservasi token/race dibuktikan 14 session
  tests dalam77 CI, bukan autentikasi akun nyata. Tidak mengklaim korelasi
  UUID access log VPS, UAT, APK signed, SMTP/FCM atau gate M06+.
- Claude review CI PASS dan Grok review sumber mobile PASS. Kimi sesi sama
  membaca tujuh screenshot dan bukti aktual, verdict akhir PASS pada
  `task_84918b406e92` / `ctx_94fad7e0330f`. Worker_done `msg_7b7c8f755a8a`
  seq156 diterima, completed/succeeded; receipt awal agent_prompt_stalled
  tidak membuktikan limit. Sesi retained user_requested sebelum ack delivery.
  Tidak membuat retry duplikat. [KIMI_ACCEPTANCE_REVIEW.md](evidence/m05/KIMI_ACCEPTANCE_REVIEW.md).
- `check-repo`532 berkas dan `git diff --check` PASS pada checkpoint bukti.
  Perbedaan kosmetik banner STAGING dengan jam status bar dicatat di
  CAPSTONE_ALIGNMENT; source frozen, tidak ada perubahan schema/API atau deploy.
- Root menerima gate M05 berdasarkan bukti aktual dan review independen.
  Commit berikutnya hanya dokumentasi/screenshot aman; source mobile/workflow
  identik source13de24e. Sebelum merge PR17, check terbaru harus SUCCESS.
  Target sampai M05 terpenuhi secara fungsional; pekerjaan baru M06 tidak
  dibuka. Tahap berikutnya nanti: rekonsiliasi flow approval Drive/aturan bisnis
  dan gate identitas M06, dengan insiden credential historis tetap open.

10 Oktober 2026 (M01): checkout lokal pemilik berada di `Documents/RT X TAKEDA/RT-05-TAKEDA/`; folder induk `RT X TAKEDA/` berisi paket MD asli (tidak di-commit). PR #10 merged. Sesi M02: branch `codex/m02-api-neon-foundation`, PR #11. Neon dev sudah dimigrasi (5 migration fondasi). `rest-api/.env` lokal menunjuk Neon dev. php.ini lokal: pdo_pgsql/pgsql/intl/sodium diaktifkan. Tidak ada migration, image, atau job. Input belum tersedia: credential Neon, akses VPS/DNS (ditawarkan pemilik via Chrome untuk M04), SMTP, FCM, signing key, Flutter SDK.

Catat Git status, perubahan pengguna yang belum commit, migration yang sudah applied,
image/tag deploy, jobs pending, dan credential input missing tanpa menuliskan nilainya.

Jangan mengulangi provisioning/migration/deploy yang sudah berhasil hanya karena sesi baru.
Selesaikan perubahan pengguna dengan hati-hati; tidak reset/force-push.

## Bug/risiko

| ID | Severity | Domain | Repro/bukti | Milestone | Status |
| --- | --- | --- | --- | --- | --- |
| R01 | medium | website/release | `package-frontend.ps1` setelah dipindah ke `website/` menghasilkan ZIP kosong yang lolos verifikasi (filesVerified 0), lalu CRLF pada arsip subtree | M01 | fixed (350390a), diverifikasi 201/201 |
| R02 | low | docs | FRONTEND.md/SOURCES.md menyebut 34e9b21 sebagai Git tree; sebenarnya commit | M01 | fixed |
| R03 | medium | deploy preview | Root Directory Vercel belum diubah; tanpa jembatan root, merge akan memutus preview | M01 | mitigated (ADR12), menunggu pemilik |
| R04 | medium | mobile | Flutter SDK dahulu belum terpasang | M05 | resolved: Flutter3.47.7/Dart3.13.5; analyze77 tests/dua APK CI dan emulator staging lulus11 Oktober |
| R05 | high | database | pdo_pgsql named prepares + pooler Neon → transaksi gugur 25P02 pada tulis pertama | M02 | fixed (ADR14), terverifikasi di Neon pooled |
| R06 | medium | dev env | Docker Desktop tidak jalan: WSL2 belum terpasang (Windows Home) | M02/M03 | accepted limitation ADR19: build Docker/Postgres/Redis di CI, runtime staging di VPS; tidak perlu instalasi Docker/WSL lokal untuk gate ini |
| R07 | high | security | Credential DB dan APP_KEY historis terpapar; role/password diwariskan antar branch | M02/M04 | open: rotasi/revokasi semua lingkungan terdampak dan audit belum terbukti; data nyata/production blocked |
| R08 | high | security | DATABASE_URL_UNPOOLED diwariskan ke lima runtime; COPY dapat memasukkan env/cache sebelum penghapusan layer | M04 | fixed: PR18 merged, full CI100 tests/BuildKit PASS, staging6a8d4f8 runtime key absent/direct denied/DML preserved terverifikasi12 Oktober; R07 tetap open |

## Bukti historis frontend

Lihat docs/website/FRONTEND_HANDOFF.md setelah M01. Angka tes di handoff adalah bukti commit terdahulu, bukan otomatis hasil untuk HEAD yang baru.
