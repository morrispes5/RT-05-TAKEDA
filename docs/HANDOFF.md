# Serah terima sesi — RT05 TAKEDA

Dokumen hidup untuk agent AI dan manusia yang melanjutkan pekerjaan. Isinya status saat ini, sisa pekerjaan, bug terbuka,
pertanyaan untuk pemilik, dan cara kerja operasional yang tidak terlihat dari kode. **Perbarui bagian 2–6 setiap kali
menutup sesi.** Sumber kebenaran status tetap [PROGRESS.md](PROGRESS.md); keputusan ada di [DECISIONS.md](DECISIONS.md).

Terakhir diperbarui: **22 Oktober 2026 ±06.30 WIB** oleh Claude (M15b: PR #60 dan #61 merge, APK 1011 dirilis dan diuji di emulator).

## 1. Cara mulai sesi baru

1. Ikuti skill init (`.cursor/skills/init/SKILL.md`): `git status -sb`, `git fetch`, baca AGENTS → README → PRD →
   BUSINESS_RULES → ARCHITECTURE → ROADMAP → PROGRESS → COORDINATION → **dokumen ini**.
2. Bandingkan branch lokal dengan `origin/main` sebelum kerja. Pemilik juga memakai agent lain (Codex, Cursor Cloud); branch
   lokal bisa basi dan checkout ini kadang dipakai bersama (lihat bagian 9).
3. Pemilik berkomunikasi dalam Bahasa Indonesia santai. Jawab dalam Bahasa Indonesia, ringkas, tanpa jargon berlebihan.
4. Kerjakan satu milestone yang dibuka pemilik. Saat ini **M15 masih `in_progress`** (menunggu uji 1009 dan catatan UAT), **M15b**
   (permintaan Pak RT 20 Okt, ADR35) sedang dikerjakan; M16 belum dibuka. Catatan permintaan pemilik juga ada di
   `../CATATAN_PERMINTAAN_PAK_RT.md` (folder induk, di luar repo).

## 2. Snapshot status (20 Okt 2026)

| Hal | Nilai |
| --- | --- |
| Milestone | M00–M14 **passed**; **M15 in_progress**; **M15b in_progress** (ADR35); M16 planned |
| `main` | `f392612` (PR #61 perbaikan uji emulator; PR #60 M15b; PR #58/#59 putaran 3) |
| Staging (VPS Hostinger, project Docker `rt05-staging`) | `f392612`, deploy immutable 22 Okt ±06.02–06.06 WIB, 0 GAGAL. Editor Profil RT05 CMS web `cms-profile-live.mjs` 9/9 PASS (22 Okt) |
| Website Vercel (`https://rt05takeda.vercel.app`) | mengikuti `website/preview/` di `main`; ekspor dibangun ulang di PR #58 (tombol Pulihkan hanya di CMS mode server, bukan mode demo). Verifikasi `check-preview.mjs` terhadap Vercel setelah PR #58 **belum dijalankan** |
| APK uji tim | build **1011** (commit `f392612`, run [39690611853](https://github.com/morrispes5/RT-05-TAKEDA/actions/runs/39690611853)). arm64-v8a versionCode 3011, SHA-256 `af171e007670ec4bb782c9e2…` (lengkap di berkas `.sha256` artifact); armeabi-v7a 2011, SHA-256 `0301005d77053118ea2e4a06…`. Unduh: `https://takeda-staging.morriz.tech/unduh/rt05-takeda-staging-1011-arm64-v8a.apk` dan `...-armeabi-v7a.apk`. Riwayat: bagian 2a |
| Link APK untuk tim (Drive pemilik, "siapa saja yang punya link") | https://drive.google.com/file/d/1G3YAstTNabAD_Eo44QinoRdM9ZYfiixr/view — **link tetap**; build baru diunggah sebagai *versi baru* file yang sama. **Isinya masih 1008** sampai pemilik mengunggah 1009 (Chrome sesi agent login sebagai akun lain tanpa akses folder Drive; agent tidak meminta akses). Sementara tim memakai tautan `/unduh` di atas |
| PR terbuka | tidak ada (PR #50, #53, #55, #56, #58 sudah merge 20 Okt) |
| Issue terbuka | **#54** (pelacak sisa gate M15, checklist), #47 (bukti performa, medium). #46 dan #48 ditutup oleh PR #50 |

## 2a. Riwayat rilis APK uji tim (link Drive tetap sama, tiap build = versi baru file yang sama)

Pemilik minta tanggal dan jam rilis selalu dicatat agar mudah diingat. Waktu dalam WIB.

| Build | versionCode | Commit | Build selesai | Diunggah ke Drive | Isi utama | Status uji tim |
| --- | --- | --- | --- | --- | --- | --- |
| 1006 | 3006 | `adee683` | 19 Okt ±21.37 | 19 Okt 21.40 | UAT putaran 1 (ADR32): edit foto pengaduan, batas unggah, crash pemilih jam usulan | Diuji tim 19 Okt 21.40–20 Okt 00.45 (19.59–21.40 masih build 1005) |
| 1007 | 3007 | `71dc523` | 20 Okt ±10.03 | 20 Okt 10.11 | UAT putaran 2 (ADR33): ekspor CSV, tagihan langsung, pemilih bulan, Blok wajib, revisi permohonan, PDF ≤1 MB | **Tidak sempat diuji** (digantikan 1008) |
| 1008 | 3008 | `4376b44` | 20 Okt 14.38 | 20 Okt 14.42 | 1007 + draf pengaduan tidak hilang (#48) + outbox tetap dipantau (#46, server) | Diuji sebagian oleh tim (checklist putaran 2 belum lengkap); temuan putaran 3 membuatnya digantikan 1009 |
| 1009 | 3009 (arm64), 2009 (armeabi-v7a) | `9d9cf07` | **20 Okt 20.03** | **20 Okt ±20.07 ke `/unduh` staging** (Drive: belum, menunggu pemilik) | UAT putaran 3 (ADR34): pulihkan arsip (konten, rumah, akun, keluarga), tombol Kembali, jam selesai usulan manual, akun tambahan wajib nama kepala keluarga (alamat opsional) | Menunggu uji tim |
| 1010 | 3010 / 2010 | `2f781d7` | 21 Okt 20.40 | 21 Okt 20.45 ke `/unduh` | M15b (ADR35): Profil RT05, bukti transfer, WhatsApp, NIK/KK, Kebijakan Privasi | Diuji agent di emulator; 6 temuan → 1011 |
| **1011** | 3011 / 2011 | `f392612` | **22 Okt 06.08** | **22 Okt 06.09 ke `/unduh`** (Drive: menunggu pemilik) | 1010 + perbaikan temuan uji emulator | Diuji ulang agent di emulator (lulus); menunggu uji tim |

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

## 4. Sisa M15 (gate belum terpenuhi)

Gate ROADMAP M15: semua alur kritis lulus, tidak ada blocker high/critical, target UAT tercatat, provider nyata bekerja,
APK bertanda tangan terpasang. **Jangan menandai passed tanpa bukti nyata. Jangan mengarang hasil UAT/survei.**

| # | Item | Pemilik kerja | Status / langkah |
| --- | --- | --- | --- |
| 1 | Uji tim dengan APK **1009**: putaran 2 (PDF >1 MB ditolak, tagihan rumah baru, pemilih bulan, Ekspor CSV Pembayaran/Kas/Rekap, *Minta perbaikan*, Blok wajib) + putaran 3 (Pulihkan rumah/keluarga/akun/konten di mobile dan CMS web, tombol Kembali, jam selesai usulan manual, daftar akun tambahan tanpa alamat dan persetujuan oleh pengurus, subkeluarga tampil dengan rumah yang sama dengan kepala) | Pemilik + tim | Menunggu. Hasil uji 1008 belum diterima agent kecuali temuan putaran 3 |
| 2 | Catat hasil UAT putaran 1 dan 2 sebagai bukti | Agent (data dari pemilik) | Belum. Format di [UAT_M15.md](UAT_M15.md): CSV tanpa nama di `docs/evidence/m15/uat/` + `UAT_RESULTS.md`. Sejauh ini hanya ringkasan di PROGRESS |
| 3 | PR #50 (issue #46, #48) | Agent | **Selesai 20 Okt**: main digabung ke branch (merge, bukan rebase), lokal rest-api 321/321, Flutter 230, CI hijau, merge `4376b44`, deploy staging, APK 1008. Notifikasi pasca-deploy 55,3 dtk |
| 4 | Issue #47: bukti performa menghitung 4xx sebagai sukses; ada 404 yang **belum dijelaskan** | Agent | Selidiki 404 di `m15-load-check.mjs`, perbaiki `EXPECT`/fixture, ulang uji beban 20 pengguna |
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

| Tingkat | Temuan | Status |
| --- | --- | --- |
| Medium | #47 skrip bukti performa: 4xx dihitung sukses, 404 tak dijelaskan | Skrip sudah menegakkan gate (PR #50); 404 belum diselidiki, uji beban belum diulang |
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
11. Unggah APK **1009** ke Drive sebagai versi baru file yang sama (agent tidak punya akses akun Drive pemilik)? Atau tim cukup memakai tautan `/unduh`?
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
Di Chrome pemilik (Drive folder `13Or_D8v5TrIPP0XUM_GjDn2ACYFHkkpO`): `fetch` APK dari `/unduh` menjadi `File`, ganti sementara
`HTMLInputElement.prototype.click` agar input file diisi lewat `DataTransfer`, lalu klik kanan file → Informasi file →
Kelola versi → Upload versi baru. Pulihkan prototipe setelahnya. Cek ukuran lewat connector Drive `get_file_metadata`, ganti
judul dengan `update_file`. Connector Drive tidak bisa mengunggah isi file atau menyunting Google Doc.

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

1. Minta hasil uji tim dengan APK 1009 (bagian 6 no. 2); catat sesuai UAT_M15.md. Jalankan `infra/scripts/staging-runtime-smoke.sh` dan `check-preview.mjs` ke Vercel yang belum dijalankan setelah PR #58.
2. Selidiki 404 issue #47, ulang uji beban.
3. Tanyakan pertanyaan bagian 6 yang tersisa (3, 4, 5, 7–10).
4. Rekam hasil UAT tim (putaran 1 dan 2) dalam format UAT_M15.md bila pemilik memberi data.
5. Setelah item 6–9 bagian 4 dari pemilik masuk, tutup gate M15 di PROGRESS, lalu tunggu pemilik membuka M16.
