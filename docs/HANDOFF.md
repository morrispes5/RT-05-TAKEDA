# Serah terima sesi — RT05 TAKEDA

Dokumen hidup untuk agent AI dan manusia yang melanjutkan pekerjaan. Isinya status saat ini, sisa pekerjaan, bug terbuka,
pertanyaan untuk pemilik, dan cara kerja operasional yang tidak terlihat dari kode. **Perbarui bagian 2–6 setiap kali
menutup sesi.** Sumber kebenaran status tetap [PROGRESS.md](PROGRESS.md); keputusan ada di [DECISIONS.md](DECISIONS.md).

Terakhir diperbarui: **27 Oktober 2026** (ADR45: M19 sebelum M16, rencana tombol panik dirinci; kerja lokal tanpa akses origin otomatis. Rilis terakhir tetap APK 1021).

## 1. Cara mulai sesi baru

1. Ikuti skill init (`.cursor/skills/init/SKILL.md`): `git status -sb`, baca AGENTS → README → PRD →
   BUSINESS_RULES → ARCHITECTURE → ROADMAP → PROGRESS → COORDINATION → **dokumen ini**.
2. **Instruksi pemilik 27 Okt (ADR45): jangan fetch/pull/push atau mengakses remote `origin` otomatis.**
   Lanjut dari dokumen dan Git lokal. Catat bahwa status remote tidak dicek ulang; jangan memicu permintaan kata sandi.
   Checkout bisa dipakai bersama (lihat bagian 9); jangan menimpa perubahan sesi lain.
3. Pemilik berkomunikasi dalam Bahasa Indonesia santai. Jawab dalam Bahasa Indonesia, ringkas, tanpa jargon berlebihan.
4. Kerjakan satu milestone yang dibuka pemilik. **M17 passed (25 Okt, gate emulator diterima pemilik)**; **M18 passed staging sintetis/emulator, APK 1021 perbaikan QA**; berikutnya **M19 tombol panik sebelum M16** (ADR45). Baca [M19_PLAN.md](M19_PLAN.md). Catatan permintaan pemilik juga ada di `../CATATAN_PERMINTAAN_PAK_RT.md` (folder induk, di luar repo).

## 2. Snapshot status (27 Okt 2026)

Rilis terbaru: [RELEASE_1021.md](evidence/m18/RELEASE_1021.md). PR #90 merged `b6e1c4b` dari source `ab108b6`,
CI 9/9; staging memakai SHA merge dan APK 1021 sudah di Drive ID tetap serta unduh resmi. Backend 413/413,
Flutter 326/326, website 40/40, kontrak/lint/hygiene lulus. Upgrade 1020 tanpa uninstall, cicilan dan dialog diuji
di emulator; saldo uji pulih, rekonsiliasi nol. Uji baca 20 pengguna: 895/895 expected, P95 710 ms.
Emulator/PG lokal berhenti. Bukti kode: [QA_FIXES.md](evidence/m18/QA_FIXES.md).
Prioritas terbaru: **M19 perencanaan**, belum kode atau bukti alarm; rencana [M19_PLAN.md](M19_PLAN.md), keputusan ADR45.

Audit sebelum patch pada `codex/m18-qa-audit`: [QA_REVIEW.md](evidence/m18/QA_REVIEW.md).
Backend 406, website 40, Flutter 308 PASS; tujuh PoC mereproduksi lima bug. Dua security medium, satu security low,
dua fungsional P2; tangani sebelum production. Tidak ada implementasi/merge/deploy/APK baru pada audit.
Cakupan manual parsial, 141 file unik dibaca penuh; bukan klaim semua 1.069 file dibaca per baris.

| Hal | Nilai |
| --- | --- |
| Milestone | M00–M15/M15b passed; **M17 passed** setelah verifikasi ulang dan emulator; **M18 passed (staging sintetis + emulator, APK 1021)**; **M19 in_progress (perencanaan), didahulukan sebelum M16**. Bukti Fase A M16 tetap tersimpan |
| Kode implementasi M18 terbaru | `b6e1c4b` merge PR #90 perbaikan QA, CI source 9/9. Baseline M18 sebelumnya `afe4ebb` PR #88 |
| API M18 merged | `ee18f55` PR #84, CI 5/5 PASS pada head `672f447`. Termasuk staging/APK 1020 |
| Mobile pengurus merged | `c68a7a1` PR #85, CI 7/7 PASS; rilis M18 terpadu 1020 sudah diuji/distribusikan |
| `main` setelah penutupan M17 | `0f3b251` (PR #81 merged, pemeriksaan 3/3 sukses); baseline aplikasi `cd4a21f`, tree identik `c91192f` |
| Staging | Immutable `b6e1c4b`, v2 aktif hanya staging; runtime-smoke, heartbeat/pooled/direct/AOF lulus, rekonsiliasi nol. Patch tidak mengubah skema. Jangan rollback kode M17/flag off setelah transaksi v2 |
| Website Vercel | Preview statis dari `website/preview/`; `/aplikasi` menaut ke halaman unduh staging. Tidak diuji ulang sesi ini |
| APK uji tim | **1021 / 1.0.0**, kode `b6e1c4b`, Senin 26 Okt build 19.48.25; Selasa 27 Okt Drive 00.07.29, `/unduh` 00.12.04 WIB. Kedua APK/metadata HTTP dan binary Drive cocok hash artifact; ID Drive tetap |
| Link APK | https://drive.google.com/file/d/1G3YAstTNabAD_Eo44QinoRdM9ZYfiixr/view (ID tetap). https://takeda-staging.morriz.tech/aplikasi |
| Bukti M17 | [VERIFICATION_1018.md](evidence/m17/VERIFICATION_1018.md): backend 358, website 40, Flutter 293, staging dan emulator lulus. Bukan bukti HP nyata |
| PR lain | #26 `cursor/cloud-agent-notes-0d43` masih terbuka pada pemeriksaan sesi ini; tidak disentuh |
| Gate M16 | Lihat PROGRESS M16 dan bagian 4. Penutupan M17 tidak menutup UAT mitra/perangkat/production |

Penerimaan awal M18: [STAGING_ACCEPTANCE.md](evidence/m18/STAGING_ACCEPTANCE.md). Backend **406/406 (4.316 assertions)**,
Flutter **308/308**, analyze bersih, format **96/0**, Pint PASS. Skenario uang, kebocoran, konkurensi dan
JSON/CSV/XLSX lulus di staging; upgrade 1018 → 1020 mempertahankan sesi/avatar. Pengurus/warga dan penyimpanan Excel
Android diuji. Semua transaksi sintetis dibalik, 14 rumah/akun uji diarsipkan, kas kembali baseline, rekonsiliasi akhir nol.
Backup pasca-v2/restore terisolasi **19 tabel/view cocok** dan salinan terenkripsi offsite. Emulator dan PostgreSQL lokal berhenti.
UAT HP nyata/mitra dan production M16 tetap terbuka. Skrip/state/kredensial uji hanya di direktori privat operator
`.rt05/m18-stage-check` dan `/root/.rt05/m18` staging; tidak di Git.
Perbaikan QA memakai `.rt05/m18-qa-release` dan `/root/.rt05/qa1021`, juga privat. Backup pra-patch/restore
19 tabel/view cocok dan encrypted offsite; satu transaksi QA dibalik, satu rumah/dua akun uji diarsipkan.

## 2a. Riwayat rilis APK uji tim (link Drive tetap sama, tiap build = versi baru file yang sama)

M18 selesai bertahap melalui PR #82 rencana, #83 DB/domain, #84 API, #85 mobile pengurus, #86 transparansi/privasi,
#87 laporan/Excel dan #88 konsistensi kas. Rincian tes tiap tahap di PROGRESS serta bukti `evidence/m18/`.
APK 1019 dibatalkan/tidak disebar; hanya build 1020 yang disebar untuk M18. Flag aktif di staging dan tetap default false
pada kode/konfigurasi contoh; production tidak diubah. Jangan rollback kode M17/flag off sesudah transaksi v2.

Pemilik minta tanggal dan jam rilis selalu dicatat agar mudah diingat. Waktu dalam WIB.

| Build | versionCode | Commit | Build selesai | Diunggah ke Drive | Isi utama | Status uji tim |
| --- | --- | --- | --- | --- | --- | --- |
| 1006 | 3006 | `adee683` | 19 Okt ±21.37 | 19 Okt 21.40 | UAT putaran 1 (ADR32): edit foto pengaduan, batas unggah, crash pemilih jam usulan | Diuji tim 19 Okt 21.40–20 Okt 00.45 (19.59–21.40 masih build 1005) |
| 1007 | 3007 | `71dc523` | 20 Okt ±10.03 | 20 Okt 10.11 | UAT putaran 2 (ADR33): ekspor CSV, tagihan langsung, pemilih bulan, Blok wajib, revisi permohonan, PDF ≤1 MB | **Tidak sempat diuji** (digantikan 1008) |
| 1008 | 3008 | `4376b44` | 20 Okt 14.38 | 20 Okt 14.42 | 1007 + draf pengaduan tidak hilang (#48) + outbox tetap dipantau (#46, server) | Diuji sebagian oleh tim (checklist putaran 2 belum lengkap); temuan putaran 3 membuatnya digantikan 1009 |
| 1009 | 3009 (arm64), 2009 (armeabi-v7a) | `9d9cf07` | **20 Okt 20.03** | **20 Okt ±20.07 ke `/unduh` staging** (Drive: belum, menunggu pemilik) | UAT putaran 3 (ADR34): pulihkan arsip (konten, rumah, akun, keluarga), tombol Kembali, jam selesai usulan manual, akun tambahan wajib nama kepala keluarga (alamat opsional) | Menunggu uji tim |
| 1010 | 3010 / 2010 | `2f781d7` | 21 Okt 20.40 | 21 Okt 20.45 ke `/unduh` | M15b (ADR35): Profil RT05, bukti transfer, WhatsApp, NIK/KK, Kebijakan Privasi | Diuji agent di emulator; 6 temuan → 1011 |
| 1011 | 3011 / 2011 | `f392612` | **22 Okt 06.08** | **22 Okt 06.09 ke `/unduh`** (Drive: menunggu pemilik) | 1010 + perbaikan temuan uji emulator | Diuji ulang agent di emulator (lulus); menunggu uji tim |
| 1012 (1.0.0) | 3012 / 2012 | `42fadc9` | **22 Okt 09.51** | **22 Okt 09.54 ke `/unduh` + `versi.json`** | M16 Fase A: halaman unduh resmi, kartu Tentang aplikasi, pemberitahuan versi baru | Menunggu uji pemilik |
| 1013 (1.0.0) | 3013 / 2013 | `b1d080d` | 22 Okt 10.57 | 22 Okt 10.58 ke `/unduh` + `versi.json` | Foto pengaduan/bukti tidak tertinggal di cache HP; server: kunci tagihan/pembayaran, CSP CMS, performa | Menunggu uji pemilik |
| **1014** (1.0.0) | 3014 / 2014 | `e74f2e0` | **24 Okt 13.25** | **24 Okt 13.27 ke `/unduh` + `versi.json`** (Drive: menunggu pemilik) | ADR41: menu Air Bersih KSM berlogo di Lainnya pengurus (warga tidak melihat). Catatan rilis `versi.json` diisi manual (dispatch tanpa input `catatan`); APK, ukuran, SHA-256 tidak berubah, sertifikat = kunci rilis tetap | Menunggu uji pemilik |
| **1015** (1.0.0) | 3015 / 2015 | `9f0fdf9` | **24 Okt 15.54** | **24 Okt 15.55 `/unduh` + `versi.json`; Drive 15.56** | ADR42: tanggal lahir anggota + Statistik usia pengurus; privasi 2026-10-24 (setuju ulang) | Diuji agent di emulator (lulus); menunggu uji pemilik |
| 1016 | — | `9507c4d` | 25 Okt ±14.55 | **Tidak disebar** | M17 tanpa avatar; dibatalkan karena permintaan foto profil keluarga datang sebelum rilis | — |
| 1017 | 3017 / 2017 | `d601c43` | 25 Okt ±15.38 | **Tidak disebar** (hanya emulator) | M17 + avatar; uji emulator menemukan 2 perbaikan kecil → 1018 | Diuji agent di emulator |
| **1018** (1.0.0) | 3018 / 2018 | `8f8a1f1` | **25 Okt 16.09** | **25 Okt 16.10 `/unduh` + `versi.json`; Drive 16.11** | M17 (ADR43): foto profil keluarga 15 avatar, nomor WhatsApp Ketua RT, super admin ubah peran, chip jalan Mawar 8–14, umur di daftar keluarga, privasi 2026-10-25 (setuju ulang). Catatan `versi.json` dipecah manual (lihat perbaikan skrip) | Verifikasi ulang Codex + emulator 25 Okt lulus; **M17 passed**, pemilik menerima gate emulator |
| 1019 | — | `6b44763` | **Dibatalkan** | **Tidak disebar** | Kandidat awal M18, dihentikan untuk menyamakan ringkasan kas pengurus dengan laporan | Bukan rilis tim |
| **1020** (1.0.0) | **3020 / 2020** | `afe4ebb` | **Senin 26 Okt 14.11.44** | **26 Okt 14.28.29 `/unduh` + metadata terakhir; Drive 14.28 WIB, ID tetap** | M18: tarif rumah, parsial/keringanan, sumbangan/titipan manual, bukti terkoreksi, pembalikan, kas warga, laporan Excel 14 sheet, privasi 26 Okt | **Lulus staging + emulator**, upgrade in-place 1018, pengurus/warga/Excel teruji. HP nyata/UAT mitra di M16 |
| **1021** (1.0.0) | **3021 / 2021** | `b6e1c4b` | **Senin 26 Okt 19.48.25** | **Selasa 27 Okt Drive 00.07.29; `/unduh` + metadata terakhir 00.12.04 WIB, ID tetap** | Perbaikan lima temuan QA: auth, dokumen/sesi, push/consent, nominal sisa cicilan; copy pembalikan | **Lulus staging + emulator**, upgrade 1020 tanpa uninstall/sesi hilang; saran25000, hash unduh/Drive cocok; fixture dibalik/arsip, rekonsiliasi nol. HP nyata/UAT mitra di M16 |

Perbaikan website (foto sampul artikel, PR #55) tidak butuh APK; sudah aktif di staging sejak deploy 20 Okt 14.32 WIB. Pulihkan arsip di CMS web juga sudah aktif di staging sejak deploy ±19.58 WIB (tanpa APK).

APK 32-bit (`armeabi-v7a`) sudah dibangun sejak 1006 tetapi sebelum 20 Okt malam hanya arm64 yang dibagikan; HP 32-bit milik anggota tim gagal memasang arm64 ("Aplikasi tidak terinstal"). Varian 32-bit 1008 dan 1009 disajikan di `/unduh`; hasil pasang di HP 32-bit baru dilaporkan berhasil mengunduh, bukan hasil uji fitur.

## 3. Yang sudah selesai di M15 (ringkas; rincian di PROGRESS dan [evidence/m15/REPORT.md](evidence/m15/REPORT.md))

- **APK rilis kunci tetap** (ADR31): workflow `Mobile release APK` (dispatch), split per ABI (arm64 ±22 MB), sertifikat
  diverifikasi `apksigner` di CI. Kunci hanya di GitHub environment `release` + Drive pribadi pemilik (tidak ada salinan lokal).
- **Draf pengaduan pulih** saat Android mematikan aplikasi ketika kamera terbuka (dari M08).
- **Keamanan**: audit dependency 0, secret scanning 0, riwayat Git bersih, `SecurityHeaders` website (HSTS, anti-framing).
- **Performa**: 20 pengguna P95 781 ms, 0 error. **Kuota Neon**: IdleGuard + mutex pendek → compute staging bisa tidur.
- **Notifikasi** 6–50 dtk. **axe** WCAG 2.1 A/AA staging 0 pelanggaran.
- **UAT tim putaran 1** (19 Okt malam, 4 penguji) → PR #51 (ADR32): tambah/hapus foto pengaduan selama *diajukan*, batas
  unggah per akun (5 pengaduan + 25 foto/hari, 5 MB/foto), crash pemilih jam usulan, CORS `/unduh/*`.
- **UAT tim putaran 2** (20 Okt pagi; poin B, C, D, F lulus) → PR #52 (ADR33): ekspor CSV Pembayaran/Kas/Rekap di aplikasi,
  tagihan langsung untuk rumah/warga baru, *Mulai tagih* pakai pemilih bulan, Blok wajib, *Minta perbaikan* permohonan akun
  tambahan (`perlu_revisi`), PDF/TXT usulan ≤1 MB, CMS *Lihat arsip* + cache konten 15 dtk.
- **UAT tim putaran 3** (20 Okt malam, APK 1008) → PR #58 (ADR34): arsip tidak lagi satu arah (konten→draf, rumah, akun→nonaktif, keluarga), tombol Kembali (tab lain → dashboard, dashboard → konfirmasi keluar), jam selesai usulan tidak terisi otomatis, akun tambahan wajib nama/hubungan/nama kepala keluarga dengan alamat opsional (rumah dicocokkan otomatis dari kepala yang unik). Tes lokal: rest-api 332/332, Flutter 243, website 16/16; CI PR #58 10/10.
- **Uji web 20 Okt** (bukti [evidence/m15/web-20okt/](evidence/m15/web-20okt/)):
  - Vercel: `node scripts/check-preview.mjs https://rt05takeda.vercel.app` → 21 rute × 5 lebar, 45 cek a11y, 37 tautan,
    editor demo, hero 3 dtk: semua PASS, 0 error browser, 0 request tulis.
  - Staging API CMS: `infra/scripts/m14-stage-check.mjs` 21/21 PASS.
  - Staging UI CMS: `website/scripts/cms-ui-live.mjs` 16/16 PASS (edit artikel terbit → pesan "tekan Terbitkan" → website
    tetap versi lama → Terbitkan → versi baru ±15 dtk → Arsipkan → hilang dari website → muncul di *Lihat arsip*). Setelah PR #58 skrip
    diperluas (C16–C20: Pulihkan → draf, tidak langsung tampil di website, arsipkan lagi) dan dijalankan ke staging `9d9cf07`: **21/21 PASS**.

## 4. Sisa gate M15 — dipindah ke M16 (ADR36, 22 Okt 2026)

Gate ROADMAP M15: semua alur kritis lulus, tidak ada blocker high/critical, target UAT tercatat, provider nyata bekerja,
APK bertanda tangan terpasang. **Jangan menandai passed tanpa bukti nyata. Jangan mengarang hasil UAT/survei.**

| # | Item | Pemilik kerja | Status / langkah |
| --- | --- | --- | --- |
| 1 | Uji tim dengan APK **1009**: putaran 2 (PDF >1 MB ditolak, tagihan rumah baru, pemilih bulan, Ekspor CSV Pembayaran/Kas/Rekap, *Minta perbaikan*, Blok wajib) + putaran 3 (Pulihkan rumah/keluarga/akun/konten di mobile dan CMS web, tombol Kembali, jam selesai usulan manual, daftar akun tambahan tanpa alamat dan persetujuan oleh pengurus, subkeluarga tampil dengan rumah yang sama dengan kepala) | Pemilik + tim | Menunggu. Hasil uji 1008 belum diterima agent kecuali temuan putaran 3 |
| 2 | Catat hasil UAT putaran 1 dan 2 sebagai bukti | Agent (data dari pemilik) | Belum. Format di [UAT_M15.md](UAT_M15.md): CSV tanpa nama di `docs/evidence/m15/uat/` + `UAT_RESULTS.md`. Sejauh ini hanya ringkasan di PROGRESS |
| 3 | PR #50 (issue #46, #48) | Agent | **Selesai 20 Okt**: main digabung ke branch (merge, bukan rebase), lokal rest-api 321/321, Flutter 230, CI hijau, merge `4376b44`, deploy staging, APK 1008. Notifikasi pasca-deploy 55,3 dtk |
| 4 | Issue #47: klasifikasi/gate bukti performa | Agent | **Bukti teknis selesai 26 Okt:** tes 7/7; staging 20 pengguna: 899/899 expected, p95 656 ms, 0 unexpected. 43 respons 404 diharapkan eksplisit untuk admin tanpa rumah. Issue GitHub masih terbuka |
| 5 | `rt05:media-recover` dan `rt05:push-recover` juga menunggu sweep per jam saat idle | Agent | Tindak lanjut dari PR #50 (di luar #46) |
| 6 | Uji HP nyata: kamera ditutup paksa, koneksi lambat, push foreground/background/terminated dengan APK rilis | Pemilik (agent memandu) | Sebagian tersirat di UAT tim; belum ada bukti terstruktur |
| 7 | Verifikasi developer Android (agar sideload tanpa peringatan) | Pemilik (identitas pribadi) | Belum |
| 8 | Privacy notice final ([PRIVACY_NOTICE_DRAFT.md](PRIVACY_NOTICE_DRAFT.md)) | Pemilik/mitra (kontak, masa simpan) | Belum; jangan masukkan data warga asli sebelum final |
| 9 | UAT mitra (peserta nyata, target ≥80% nilai 4–5) | Pemilik | Uji tim pemilik bukan UAT mitra; jumlah/hasil diisi dari sesi nyata |
| 10 | Aksesibilitas manual (TalkBack/pembaca layar) | Agent + HP | Belum |
| 11 | Uji pada profil shared hosting (UAT dan beban) | Agent | ROADMAP M15; belum dijalankan di hosting shared sungguhan |
| 12 | Latihan pindah/kembali ke PostgreSQL cadangan (ADR29, Supabase) | Agent | Belum diimplementasi; sebagian bisa digeser ke M16 dengan persetujuan pemilik |

### 4b. M15b — permintaan Pak RT (ADR35)

PR #60 merge `2f781d7` 21 Okt 20.32 WIB, staging di-deploy, APK 1010 dirilis 20.40 WIB dan diuji agent di emulator (lulus, 6 temuan
kecil diperbaiki di `codex/m15b-perbaikan-uji` untuk APK 1011; rincian di PROGRESS). Isi: Profil RT05 + cerita Taman Kedaung editable (CMS web
`/pengurus/profil` dan aplikasi), transparansi iuran khusus pengurus, bukti transfer + WhatsApp bendahara (nomor diatur
pengurus, nomor uji pemilik diisi lewat aplikasi di staging, tidak di Git), tombol WhatsApp per warga, NIK/KK opsional
terenkripsi, Kebijakan Privasi wajib disetujui setelah masuk, kartu Lengkapi data. Tes lokal: rest-api 340/340, Flutter 262,
website 21/21. Sisa: PR + CI, merge/deploy/APK (butuh persetujuan pemilik), isi nomor bendahara uji di staging, uji HP.

Status 6 item tunda (jawaban pemilik 21 Okt): verifikasi developer Android belum; privacy notice "kayaknya sudah bisa" (teks
ADR35 sudah ada, kontak/masa simpan belum); latihan DB cadangan, uji shared hosting, TalkBack, dan sweep media/push belum bisa.
Pemilik minta dituntun untuk keenamnya setelah M15b selesai. Hosting production: Biznet ±Rp399 rb/tahun RAM 1 GB (jenis paket
belum dikonfirmasi; jalankan preflight `SHARED_HOSTING.md` sebelum membeli).

## 5. Bug dan temuan terbuka

M18: seluruh gate uang staging/emulator lulus, tidak ada blocker keuangan yang ditemukan pada cakupan uji.
Audit QA 26 Okt menemukan lima bug baru; laporan QA dan tabel berikut melengkapi hasil penerimaan sebelumnya.
**Status terbaru:** QA-S1–S3/QA-F1–F3 sudah fixed dan dirilis di staging/APK 1021, bukti RELEASE_1021.
Tabel berikut mempertahankan temuan audit asal beserta penutupannya. PostgreSQL tes sudah dihentikan.
M17: checklist 1018 lulus; ANR System UI saat boot emulator pulih tanpa wipe.
PostgreSQL tes sudah berhenti; folder pgdata sementara belum dihapus karena alat menolak pembersihan.
Tabel di bawah memuat temuan historis; status yang lebih baru di PROGRESS M16 mengungguli catatan lama.

| Tingkat | Temuan | Status |
| --- | --- | --- |
| Medium, QA-S1 | Rotasi email melewati budget recovery | Fixed/dirilis staging; request 21 setelah 20 email berbeda ditolak 429/Retry-After |
| Medium, QA-S2 | Dokumen A diluncurkan setelah logout/login B | Fixed/APK 1021; mounted/token/akun/epoch, lima skenario widget dan kontrol normal lulus |
| Low, QA-S3 | Hash-work akun berbeda | Fixed/dirilis staging; kerja pembanding sesuai hasher, bukan klaim timing jaringan identik |
| P2, QA-F1 | Nominal bukti cicilan memakai tagihan penuh | Fixed/APK 1021; emulator membuktikan 75000→25000 sesuai sisa, input manual tetap lulus widget |
| P2, QA-F2 | Push melewati consent | Fixed/APK 1021; antre/recheck sesi, pending tetap menerima push persetujuan pada tes terkontrol |
| Low, QA-F3 | Copy pembatalan M12 tidak akurat untuk cicilan | Fixed/APK 1021; emulator membuktikan saldo tagihan dihitung ulang |
| Selesai teknis, tracking terbuka | #47 klasifikasi/gate performa | Audit 26 Okt: tes 7/7, staging 899/899 expected, 0 unexpected, p95 656 ms. 404 admin tanpa rumah dijelaskan; issue belum ditutup |
| Info | Aturan minimal 10 karakter (ringkasan, pembuka, isi bagian artikel; deskripsi album) | Validasi M13, **tidak diubah** di PR #58. Pemilik menyebutnya saat melaporkan arsip; belum jelas apakah ingin dilonggarkan |
| Selesai, perlu uji tim | UAT E: "gambar artikel tidak bisa diganti, tetap SVG" | Penyebab dikonfirmasi dari data staging: artikel tim (terbit 19 Okt 22.43 WIB) punya foto sampul asli, tetapi website selalu menggambar SVG. Diperbaiki PR #55 (ADR33 butir 7): sampul asli tampil di kartu, artikel pilihan, halaman baca; tanpa sampul tetap SVG. Live staging `cms-ui-live.mjs` 17/17 termasuk C05b |
| Operasional | Staging tidak terjangkau lewat IPv4 dari sebagian ISP (MyRepublic rumah pemilik) | Solusi sementara: WARP + record AAAA. Proxy Cloudflare perlu izin pemilik (M16). Bukti: `docs/evidence/m14/NETWORK_MYREPUBLIC.md` (dari sesi paralel, belum di-commit) |
| Operasional | Kuota Neon Free 100 CU-jam/bulan per project (staging + production + dev satu project) | Diperbaiki IdleGuard; pantau `active_time_seconds` lewat Neon MCP `list_branches` |
| Low | Vercel hanya memuat konten hasil ekspor terakhir; artikel yang diterbitkan lewat CMS staging tidak otomatis muncul di Vercel | By design (preview statis). Opsi ekspor berkala → tanya pemilik (M16) |
| Low | Dependabot alerts nonaktif di repo publik | Rekomendasi ke pemilik |
| Info | APK lama masih tersaji di `https://takeda-staging.morriz.tech/unduh/` (volume `rt05-staging_apk-downloads`) | Boleh dibersihkan setelah tim memasang 1007 (tanya dulu) |
| Info | Insiden credential historis (rotasi) | Ditutup di M16 |

Temuan yang **bukan bug** (jangan dikerjakan ulang): kas admin layar putih = APK M10 lama memanggil `/cash/summary`
(dipindah ADR26). Aspirasi tetap teks saja tanpa foto (keputusan pemilik 19 Okt). Login pengurus "gagal" saat UAT
ternyata pulih sendiri (throttle/salah ketik).

## 6. Pertanyaan terbuka untuk pemilik

**Keputusan 25 Okt sudah dijawab:** emulator cukup untuk gate M17; titipan M18 digunakan atas pilihan pengurus
(tidak otomatis, tidak menambah kas kedua). [M18_PLAN.md](M18_PLAN.md) + ADR44 beserta default bagian 9
disetujui pemilik 25 Okt dan sudah diimplementasikan/diuji. Tidak ada pertanyaan bisnis M18 yang harus dijawab ulang.
Daftar berikut adalah histori gate M15/M16, bukan permintaan mengulang jawaban M17.
Audit QA 26 Okt tidak menambah pertanyaan bisnis; perbaikan teknis berikutnya ada di laporan QA.
Pemilik sudah mengotorisasi perbaikan; tidak perlu meminta ulang persetujuan patch yang sama.

1. ~~Temuan E~~ — dijawab 20 Okt: foto asli tidak menggantikan SVG; sudah diperbaiki (PR #55).
2. Hasil uji tim dengan APK **1009** (dan sisa 1008): poin mana berhasil/gagal? (Untuk dicatat sebagai bukti, bukan dikarang.)
3. Privacy notice: nomor/email kontak dan masa simpan data yang dipakai?
4. Kapan verifikasi developer Android dengan identitas pemilik?
5. UAT mitra: siapa/berapa peserta dan kapan? (Atau pemilik memutuskan uji tim cukup — catat sebagai keputusan.)
6. ~~Merge PR #50 + APK 1008~~ — disetujui 20 Okt ("asalkan kasih tau tanggal dan waktu perilisan"); selesai, lihat bagian 2a.
7. Boleh mengaktifkan proxy Cloudflare untuk `takeda-staging` (atasi IPv4 ISP)? (Menyentuh DNS → perlu izin.)
8. Vercel perlu ekspor konten berkala dari API agar artikel baru ikut tampil saat VPS mati?
9. Boleh menghapus APK lama di `/unduh` staging? Aktifkan Dependabot?
10. Latihan PostgreSQL cadangan (ADR29) tetap di M15 atau digeser ke M16?
11. **Selesai:** APK terbaru 1021 sudah di Drive ID tetap dan `/unduh`; rclone terkena kuota shared-client, connector binary-update sukses. Jangan tanyakan ulang distribusi lama.
12. Aturan minimal 10 karakter pada isi artikel/album: dipertahankan atau dilonggarkan (angka berapa)?
14. Kebijakan Privasi: kontak resmi (nomor/email), masa simpan data setelah warga pindah, batas waktu tanggapan?
15. Impor NIK/KK dari Excel RT: perlu? (Belum dibuat; butuh keputusan dan dasar persetujuan warga.)
16. Setuju penempatan M15b (fitur baru) sebelum M16? Tombol WhatsApp memakai ikon buatan, perlu logo resmi?
13. Apakah ada kasus dua keluarga dalam satu rumah? Sistem hanya mengenal satu keluarga utama aktif per rumah; subkeluarga/akun tambahan memakai rumah keluarga kepala yang sama.

## 7. Arsitektur lingkungan (urutan pemilik: lokal → Vercel → VPS)

| Lapisan | Isi | Bila VPS mati |
| --- | --- | --- |
| Lokal (PC pemilik) | tes, build, preview | tidak terpengaruh |
| Vercel `rt05takeda.vercel.app` | ekspor statis `website/preview/` (cabang `main`), CMS **mode demo** (simpan di browser saja, `data-cms-mode="demo"`), `noindex` | **tetap jalan** untuk website publik |
| VPS staging `takeda-staging.morriz.tech` | gateway Caddy + web Laravel (CMS mode server) + rest-api + worker + scheduler + Redis; database Neon branch staging | login CMS, terbit artikel, dan aplikasi mobile berhenti sampai VPS hidup |
| Target jangka panjang | shared hosting (`RT05_DEPLOYMENT_PROFILE=shared`), VPS hanya opsi terakhir | — |

Setiap fitur wajib jalan di profil `shared` (antrean database, cron `rt05:shared-tick`, tanpa Redis/daemon) dan profil VPS.

## 8. Runbook operasional

**Akses staging dari PC pemilik.** IPv4 rumah ke Hostinger putus; pakai Cloudflare WARP (`warp-cli --accept-tos connect`) + IPv6
VPS `2a02:4780:59:7d0b::1`. SSH: alias `rt05-vps-v6` di `~/.ssh/config`. Skrip Node live: `node --require
"C:/Users/USER/.rt05/v6-lookup.cjs" <skrip>`. Chromium Playwright: `RT05_RESOLVE_V6=2a02:4780:59:7d0b::1`. Git Bash: pakai
`MSYS_NO_PATHCONV=1` bila argumen diawali `/`. Hanya sentuh project Docker `rt05-staging`; ada project lain pemilik di VPS
(mis. `morriz-commerce-*`) — jangan disentuh. Jangan polling SSH lebih sering dari 60 dtk.

**Deploy staging (immutable).** `git show <SHA>:infra/scripts/deploy-staging-immutable.sh > <scratch>` → `scp` ke VPS (bukan
pipa SSH; pipa menghasilkan file kosong) → cocokkan SHA-256 (`aa50ca1a629ba5a7…`) → `(setsid nohup bash <skrip> <SHA> >
/root/rt05-<label>.log &)` → pantau `^>>> Selesai|GAGAL`. Bila outbox macet setelah deploy: `php artisan schedule:clear-cache`.

**Rilis APK.** `gh workflow run mobile-release.yml` (environment `release`, dispatch dari `main`). versionCode = `1000 + run`
(+1000 untuk armeabi-v7a, +2000 untuk arm64). Unduh artifact, `sha256sum -c`, cek sertifikat = `RT05_RELEASE_CERT_SHA256`.
APK rilis tidak bisa menimpa APK debug (uninstall sekali). Kunci rilis: **jangan buat baru, jangan simpan lokal** — cadangan
di Drive pribadi pemilik.

**Distribusi APK ke tim lewat link Drive yang sama.** Salin APK ke volume staging (`docker volume inspect -f
'{{.Mountpoint}}' rt05-staging_apk-downloads`, `install -m 0644`) sehingga tersaji di `/unduh/<nama>.apk` (CORS terbuka).
Folder APK Drive `13Or_D8v5TrIPP0XUM_GjDn2ACYFHkkpO`, file ID tetap `1G3YAstTNabAD_Eo44QinoRdM9ZYfiixr`.
Rclone dapat `copyto` ke nama lama lalu `moveto` nama baru agar ID tetap. Pada 27 Okt rclone ditolak kuota proyek
shared-client; fallback connector Drive **`update_file(file_uri=<path APK lokal>, name=...)` sudah mendukung
penggantian binary pada ID yang sama**. Baca metadata dulu, jangan membuat file APK baru. Verifikasi ID/parent/ukuran,
lalu `fetch(download_raw_file=true, include_base64=false)` untuk readback SHA-256; jangan mencetak URL unduhan bertoken.
Connector `upload_file` dipakai untuk berkas baru seperti backup terenkripsi, hanya ke folder backup **privat**.
Jangan mengubah sharing, klien OAuth, kuota cloud atau konfigurasi akun untuk mengatasi rate limit dalam tugas rilis.

**Tes lokal.**
- rest-api: `php artisan test` (phpunit.xml → Postgres lokal 127.0.0.1:54329, DB `rt05_test`; Postgres 17 portabel dijalankan
  di background). **Jangan pernah `--env=testing`**: tidak ada `.env.testing`, sehingga Laravel memakai `rest-api/.env` =
  Neon branch dev (insiden 20 Okt).
- mobile: Flutter di `C:\Users\USER\dev\flutter\bin\flutter.bat` (tidak di PATH). Dialog `BusyButton` di widget test perlu
  `pump` bertahap, bukan `pumpAndSettle`. PC RAM terbatas: utamakan tes widget + build APK di CI; emulator hanya bila diminta.
- website (dari `website/`): `php artisan test`, `npm.cmd run build:preview`, `npm.cmd run test:preview`; live:
  `node scripts/check-live.mjs <url>`, `node scripts/check-preview.mjs <url>`, `node scripts/a11y-live.mjs`,
  `scripts/cms-ui-live.mjs`.
- Semua perubahan: `node infra/scripts/check-repo.mjs` dan `git diff --check`.

**Cek live staging** (akun sintetis dari fixture `~/.rt05/`, di luar Git): `infra/scripts/m14-stage-check.mjs` (CMS API),
`m15-load-check.mjs`, `m15-notify-latency.mjs`, `usulan-stage-acceptance.mjs`, `m13-stage-check.mjs`.

## 9. Aturan kerja pemilik dan jebakan

- **27 Okt, ADR45:** jangan fetch/pull/push atau mengakses remote `origin` otomatis. Kerja dari dokumentasi lokal;
  jangan mengubah credential helper/URL remote atau meminta kata sandi. Akses remote menunggu instruksi baru pemilik.

- Commit sebagai `morrispes5 <morrispes5@gmail.com>`, **tanpa trailer Co-Authored-By dan tanpa footer PR** (instruksi pemilik).
  Branch `codex/mNN-topik`; jangan force-push; repo tidak memakai auto-merge.
- Folder induk `RT X TAKEDA` juga repo Git milik pemilik (RT-05-TAKEDA sebagai gitlink). Jangan commit di induk.
- Checkout dapat dipakai bersama sesi paralel. Per 20 Okt ada perubahan sesi lain yang **belum di-commit**: tambahan
  bagian "Diagnosis jaringan MyRepublic" di `docs/PROGRESS.md`, file `docs/evidence/m14/NETWORK_MYREPUBLIC.md`, dan folder
  `.agents/`. Jangan menimpa atau ikut men-stage; untuk PROGRESS, stage versi `HEAD` + perubahan sendiri
  (`git hash-object -w` + `git update-index --cacheinfo`).
- Jangan menebak kredensial/kontak; jangan mengetik kata sandi asli pemilik di situs mana pun. Kredensial uji ada di
  `~/.rt05/` (privat).
- Jangan mengubah DNS, firewall, port, atau production tanpa izin eksplisit untuk tindakan itu.
- Pemilik tidak ingin bergantung pada VPS; tawarkan dulu sebelum deploy ke VPS di luar staging rutin.
- WebFetch eksternal sering ditolak situs Hostinger; jangan dijadikan bukti host mati (pakai check-host.net).
- Screenshot Chrome pemilik bisa macet bila jendela tertutup/minimize; uji fungsional pakai Playwright lokal. Untuk klik
  menu Drive tanpa screenshot, pakai `find` (ref elemen) lalu klik ref.
- Worktree untuk tes: jangan junction/symlink `vendor` dari checkout lain (autoload memuat kelas checkout lain → hasil tes
  palsu). Di worktree: `composer install`, `cp .env.example .env && php artisan key:generate` (sama seperti CI).
- Branch yang sedang dipakai worktree lain (mis. `C:/Users/USER/Documents/RT05-worktrees/m15-qa-fixes` milik sesi Codex) jangan
  di-checkout ulang; buat branch lokal lain lalu push ke remote branch yang sama (fast-forward, tanpa force).
- Aktivitas uji tim dibaca dari `audit_logs` Neon branch staging (`br-holy-bread-b300uftg`) lewat Neon MCP `run_sql` (SELECT saja).
- Pemilik ingin **tanggal dan jam rilis APK** selalu disampaikan dan dicatat (bagian 2a).

## 10. Langkah berikutnya yang disarankan

**Baca ADR45 (27 Okt) dan ADR43 butir 8.** Pemilik memprioritaskan tombol panik:
**M17 → M18 → M19 → M16 → M20**. Rencana M19 ada di [M19_PLAN.md](M19_PLAN.md), belum implementasi/uji alarm.
Jadwal ADR43: pindah VPS staging 2 Nov (`RUNBOOK_PRODUCTION.md` bagian 7), target beta 8 Nov dan rilis penuh
paling lambat awal 2027 tetap rencana yang perlu dinilai terhadap hasil M19. Data asli hanya di production;
perubahan urutan tidak menghapus gate M16. Perbaikan APK kecil dikumpulkan per rilis (jangan rilis APK tiap bug).

**M17 passed (25 Okt)** dengan penerimaan emulator oleh pemilik. **M18 passed (26 Okt)** untuk staging sintetis
+ emulator; bukti lengkap di [STAGING_ACCEPTANCE.md](evidence/m18/STAGING_ACCEPTANCE.md), APK 1020 sudah di
Drive ID tetap dan unduh resmi (14.28 WIB). Titipan manual, keringanan bukan uang masuk, koreksi append-only;
flag v2 hanya staging. Jangan mengulang rencana/migrasi M18 atau rollback ke kode M17.

QA-S1–S3/QA-F1–F3 fixed dan dirilis sebagai APK 1021; staging dan emulator lulus, fixture dibalik/diarsipkan.
Berikutnya inspeksi dan rincian teknis **M19**, mengikuti ketentuan Claude yang sudah ditulis, matriks uji dan batas di plan.
Alarm layar kunci/aplikasi ditutup wajib dibuktikan di HP nyata. Jangan menganggap hasil emulator sebagai HP nyata
atau izin deploy production. UAT mitra dan daftar gate M16 di PROGRESS tetap berlaku sesudah M19.

## 11. Air Bersih KSM — aplikasi terpisah, menu khusus pengurus (ADR41, 24 Okt 2026; menggantikan ADR40)

- **Keputusan terbaru (ADR41):** tidak digabung, tidak ada API/login bersama. RT05 hanya punya menu "Air Bersih KSM" berlogo
  KSM Banyu Bening (tanpa panah) untuk **pengurus saja** (web: tampil setelah sesi pengurus terbukti; mobile: Lainnya pengurus).
  Pengurus login lagi di aplikasi air bersih; akunnya hanya untuk Ketua RT. Butir di bawah yang menyebut "subfitur/penggabungan"
  sudah tidak berlaku.

### Catatan lama (ADR40, 23 Okt 2026)

- **Apa:** aplikasi tagihan air KSM Banyu Bening (catat meter, tagihan air, pembayaran) untuk pengurus. Repo terpisah
  `morrispes5/air-bersih-ksm-claude` (lokal `C:UsersUSERDocumentsAIR BERSIH KSM CLAUDE`), SvelteKit + WoWSQL, live di
  https://airbersih-ksm-claude.vercel.app. Dikerjakan sesi Claude Code lain milik pemilik; dokumen kanonik `docs/PRD.md` di
  repo itu dan catatan lokal `catatan-lokal/HANDOFF.md` (tidak di Git).
- **Kedudukan:** pemilik menetapkan Air Bersih KSM sebagai **subfitur RT05 di bagian pengurus**, bukan aplikasi terpisah
  selamanya. Penggabungan kode menunggu diskusi pemilik dengan tim capstone; jangan memulainya tanpa milestone/ADR baru.
- **Sekarang:** hanya tautan (web sidebar `/pengurus` dan mobile Lainnya). Jangan menambah tabel/endpoint air bersih di
  `rest-api` dan jangan menyambung ke database WoWSQL-nya.
- **Koordinasi antar sesi:** perubahan di RT05 yang menyangkut air bersih dicatat di ADR40 + bagian ini; sesi Air Bersih KSM
  mencatat sisi lainnya di PRD Fase 6 repo itu.
