# Progress — RT05 TAKEDA

Updated: 10 Oktober 2026.

## Status

Paket konteks diimpor ke repository pada M01 (10 Oktober 2026). Belum ada kode API/mobile, server, DNS, Neon, atau Redis yang diubah.

| Milestone | Status | Bukti/ketergantungan |
| --- | --- | --- |
| M00 | accepted_baseline | Handoff repo menyatakan desain accepted; M01 rerun QA baseline pada 34e9b21 dan dari website/ (lihat M01) |
| M01 | passed | PR #10: CI hygiene + verify hijau; preview Vercel PR 67/67 file cocok build lokal; lihat M01 |
| M02 | planned | API/Neon integration belum dilakukan |
| M03 | planned | Redis/worker/outbox belum dilakukan |
| M04 | planned | Staging Hostinger belum diinspeksi/deploy |
| M05 | planned | Flutter belum dibuat |
| M06 | planned | Auth/registrasi belum operasional |
| M07 | planned | Pendataan/akun tambahan belum operasional |
| M08 | planned | Pengaduan/media belum operasional |
| M09 | planned | Aspirasi belum operasional |
| M10 | planned | Agenda/pengumuman/push belum operasional |
| M11 | planned | Tagihan/tarif belum operasional |
| M12 | planned | Payment offline/kas/export belum operasional |
| M13 | planned | CMS API belum operasional |
| M14 | planned | Website real publication belum terintegrasi |
| M15 | planned | UAT/full QA belum dilakukan |
| M16 | planned | Production baru belum dirilis |

M00 memakai label accepted_baseline khusus, bukan status passed hasil tes baru. M01–M16 menggunakan planned/in_progress/passed/blocked.

## Milestone aktif

Belum ada. M01 passed (PR #10 menunggu merge pemilik). Berikutnya M02.

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

## Checkpoint sesi

10 Oktober 2026 (M01): checkout lokal pemilik berada di `Documents/RT X TAKEDA/RT-05-TAKEDA/`; folder induk `RT X TAKEDA/` berisi paket MD asli (tidak di-commit). Branch `codex/m01-monorepo-foundation` di-push; PR #10 terbuka, CI hijau, belum di-merge. Tidak ada migration, image, atau job. Input belum tersedia: credential Neon, akses VPS/DNS (ditawarkan pemilik via Chrome untuk M04), SMTP, FCM, signing key, Flutter SDK.

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
| R04 | medium | mobile | Flutter SDK tidak terpasang | M05 | open |

## Bukti historis frontend

Lihat docs/website/FRONTEND_HANDOFF.md setelah M01. Angka tes di handoff adalah bukti commit terdahulu, bukan otomatis hasil untuk HEAD yang baru.
