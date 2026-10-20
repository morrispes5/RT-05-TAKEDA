# Serah terima sesi — RT05 TAKEDA

Dokumen hidup untuk agent AI dan manusia yang melanjutkan pekerjaan. Isinya status saat ini, sisa pekerjaan, bug terbuka,
pertanyaan untuk pemilik, dan cara kerja operasional yang tidak terlihat dari kode. **Perbarui bagian 2–6 setiap kali
menutup sesi.** Sumber kebenaran status tetap [PROGRESS.md](PROGRESS.md); keputusan ada di [DECISIONS.md](DECISIONS.md).

Terakhir diperbarui: **20 Oktober 2026 siang WIB** oleh Claude (sesi M15 panjang: rilis APK, UAT putaran 1–2, uji web).

## 1. Cara mulai sesi baru

1. Ikuti skill init (`.cursor/skills/init/SKILL.md`): `git status -sb`, `git fetch`, baca AGENTS → README → PRD →
   BUSINESS_RULES → ARCHITECTURE → ROADMAP → PROGRESS → COORDINATION → **dokumen ini**.
2. Bandingkan branch lokal dengan `origin/main` sebelum kerja. Pemilik juga memakai agent lain (Codex, Cursor Cloud); branch
   lokal bisa basi dan checkout ini kadang dipakai bersama (lihat bagian 9).
3. Pemilik berkomunikasi dalam Bahasa Indonesia santai. Jawab dalam Bahasa Indonesia, ringkas, tanpa jargon berlebihan.
4. Kerjakan satu milestone yang dibuka pemilik. Saat ini **M15 masih `in_progress`**; M16 belum dibuka.

## 2. Snapshot status (20 Okt 2026)

| Hal | Nilai |
| --- | --- |
| Milestone | M00–M14 **passed**; **M15 in_progress**; M16 planned |
| `main` | `71dc523` (PR #52, UAT putaran 2) |
| Staging (VPS Hostinger, project Docker `rt05-staging`) | `71dc523`, migrasi `2026_10_20_000100_permohonan_akun_perlu_revisi` sudah jalan, 0 GAGAL |
| Website Vercel (`https://rt05takeda.vercel.app`) | sama persis dengan `website/preview/` di `main` (cek 20 Okt: 21/21 halaman, 32 aset) |
| APK uji tim | build **1007** (`rt05-takeda-staging-1007-arm64-v8a.apk`, versionCode 3007, run [39361652243](https://github.com/morrispes5/RT-05-TAKEDA/actions/runs/39361652243)), SHA-256 `8186ac54e471c2ccb190f1286de15ed1b75b068ca72b18fc3df95c822d70db4d` |
| Link APK untuk tim (Drive pemilik, "siapa saja yang punya link") | https://drive.google.com/file/d/1G3YAstTNabAD_Eo44QinoRdM9ZYfiixr/view — **link tetap**; build baru diunggah sebagai *versi baru* file yang sama |
| PR terbuka | **#50** (perbaikan QA #46/#48, sebagian #47) — belum di-merge, lihat bagian 4 |
| Issue terbuka | #46, #47, #48 (QA M15, medium) |

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
- **Uji web 20 Okt** (bukti [evidence/m15/web-20okt/](evidence/m15/web-20okt/)):
  - Vercel: `node scripts/check-preview.mjs https://rt05takeda.vercel.app` → 21 rute × 5 lebar, 45 cek a11y, 37 tautan,
    editor demo, hero 3 dtk: semua PASS, 0 error browser, 0 request tulis.
  - Staging API CMS: `infra/scripts/m14-stage-check.mjs` 21/21 PASS.
  - Staging UI CMS: `website/scripts/cms-ui-live.mjs` 16/16 PASS (edit artikel terbit → pesan "tekan Terbitkan" → website
    tetap versi lama → Terbitkan → versi baru ±15 dtk → Arsipkan → hilang dari website → muncul di *Lihat arsip* hanya baca).

## 4. Sisa M15 (gate belum terpenuhi)

Gate ROADMAP M15: semua alur kritis lulus, tidak ada blocker high/critical, target UAT tercatat, provider nyata bekerja,
APK bertanda tangan terpasang. **Jangan menandai passed tanpa bukti nyata. Jangan mengarang hasil UAT/survei.**

| # | Item | Pemilik kerja | Status / langkah |
| --- | --- | --- | --- |
| 1 | Uji ulang tim dengan APK **1007** (poin putaran 2 + temuan E artikel) | Pemilik + tim | Menunggu hasil. Checklist uji ada di pesan sesi 20 Okt; ringkasannya: PDF >1 MB ditolak, tagihan rumah baru muncul, pemilih bulan, Ekspor CSV 3 tab, alur *Minta perbaikan*, Blok wajib, CMS edit→Terbitkan + Lihat arsip |
| 2 | Catat hasil UAT putaran 1 dan 2 sebagai bukti | Agent (data dari pemilik) | Belum. Format di [UAT_M15.md](UAT_M15.md): CSV tanpa nama di `docs/evidence/m15/uat/` + `UAT_RESULTS.md`. Sejauh ini hanya ringkasan di PROGRESS |
| 3 | PR #50 (issue #46 IdleGuard menunda retry outbox, #48 draf pengaduan bisa hilang) | Agent | Rebase ke `main` terbaru, jalankan tes, CI, merge, deploy staging, rilis APK baru (draf #48 ada di mobile) |
| 4 | Issue #47: bukti performa menghitung 4xx sebagai sukses; ada 404 yang **belum dijelaskan** | Agent | Selidiki 404 di `m15-load-check.mjs`, perbaiki `EXPECT`/fixture, ulang uji beban 20 pengguna |
| 5 | `rt05:media-recover` dan `rt05:push-recover` juga menunggu sweep per jam saat idle | Agent | Tindak lanjut dari PR #50 (di luar #46) |
| 6 | Uji HP nyata: kamera ditutup paksa, koneksi lambat, push foreground/background/terminated dengan APK rilis | Pemilik (agent memandu) | Sebagian tersirat di UAT tim; belum ada bukti terstruktur |
| 7 | Verifikasi developer Android (agar sideload tanpa peringatan) | Pemilik (identitas pribadi) | Belum |
| 8 | Privacy notice final ([PRIVACY_NOTICE_DRAFT.md](PRIVACY_NOTICE_DRAFT.md)) | Pemilik/mitra (kontak, masa simpan) | Belum; jangan masukkan data warga asli sebelum final |
| 9 | UAT mitra (peserta nyata, target ≥80% nilai 4–5) | Pemilik | Uji tim pemilik bukan UAT mitra; jumlah/hasil diisi dari sesi nyata |
| 10 | Aksesibilitas manual (TalkBack/pembaca layar) | Agent + HP | Belum |
| 11 | Uji pada profil shared hosting (UAT dan beban) | Agent | ROADMAP M15; belum dijalankan di hosting shared sungguhan |
| 12 | Latihan pindah/kembali ke PostgreSQL cadangan (ADR29, Supabase) | Agent | Belum diimplementasi; sebagian bisa digeser ke M16 dengan persetujuan pemilik |

## 5. Bug dan temuan terbuka

| Tingkat | Temuan | Status |
| --- | --- | --- |
| Medium | #46 IdleGuard dapat menunda retry outbox hingga sweep per jam | Fix di PR #50, belum merge/deploy |
| Medium | #48 draf pengaduan bisa terhapus bila halaman ditutup saat pemulihan async | Fix di PR #50, belum merge/APK |
| Medium | #47 skrip bukti performa: 4xx dihitung sukses, 404 tak dijelaskan | Sebagian di PR #50 |
| Perlu konfirmasi | UAT E: "artikel tidak bisa diganti dan tidak ada arsip" | Diperbaiki ADR33 butir 6 dan lulus uji UI 16/16. Belum dipastikan apakah maksud pemilik "website tetap versi lama" (sudah ditangani) atau tombol Simpan/Edit error (minta screenshot) |
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

1. Temuan E "artikel tidak bisa diganti": maksudnya website tetap versi lama setelah diedit, atau Simpan/Edit error? (Kalau error, minta screenshot.)
2. Hasil uji ulang tim dengan APK 1007: poin mana berhasil/gagal? (Untuk dicatat sebagai bukti, bukan dikarang.)
3. Privacy notice: nomor/email kontak dan masa simpan data yang dipakai?
4. Kapan verifikasi developer Android dengan identitas pemilik?
5. UAT mitra: siapa/berapa peserta dan kapan? (Atau pemilik memutuskan uji tim cukup — catat sebagai keputusan.)
6. Boleh merge PR #50 lalu rilis APK 1008?
7. Boleh mengaktifkan proxy Cloudflare untuk `takeda-staging` (atasi IPv4 ISP)? (Menyentuh DNS → perlu izin.)
8. Vercel perlu ekspor konten berkala dari API agar artikel baru ikut tampil saat VPS mati?
9. Boleh menghapus APK lama di `/unduh` staging? Aktifkan Dependabot?
10. Latihan PostgreSQL cadangan (ADR29) tetap di M15 atau digeser ke M16?

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
- Screenshot Chrome pemilik bisa macet bila jendela tertutup/minimize; uji fungsional pakai Playwright lokal.

## 10. Langkah berikutnya yang disarankan

1. Tanyakan pertanyaan bagian 6 (minimal 1, 2, 6).
2. Rebase + merge PR #50, deploy staging, rilis APK 1008, unggah sebagai versi baru di link Drive yang sama.
3. Selidiki 404 issue #47, ulang uji beban.
4. Rekam hasil UAT tim (putaran 1 dan 2) dalam format UAT_M15.md bila pemilik memberi data.
5. Setelah item 6–9 bagian 4 dari pemilik masuk, tutup gate M15 di PROGRESS, lalu tunggu pemilik membuka M16.
