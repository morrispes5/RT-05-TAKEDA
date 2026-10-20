---
name: init
description: Memuat konteks operasional monorepo RT05 TAKEDA (RT 05 Taman Kedaung, Ciputat) sebelum mengubah kode atau melanjutkan fitur. Use when the user invokes /init, starts a session in this repository, or asks to continue the website, mobile app, REST API, iuran, pengaduan, aspirasi, agenda, kas, CMS, or deployment.
---

# Init — RT05 TAKEDA

Jalankan skill ini sebelum mengubah kode. Ringkasan di bawah ini bukan pengganti dokumen. Jika skill dan `docs/PROGRESS.md` berbeda, ikuti `docs/PROGRESS.md`. Instruksi pemilik terbaru mengalahkan keduanya; catat keputusan baru di `docs/DECISIONS.md`.

## Lokasi

Git root adalah folder `RT-05-TAKEDA/`, bukan folder induk `RT X TAKEDA`. Semua path di skill ini relatif terhadap Git root. Perintah dijalankan dari sana, kecuali perintah website yang dijalankan dari `website/`.

## Langkah

1. `git status -sb`, `git branch --show-current`, dan `git rev-parse --show-toplevel`. Jangan reset, force-push, atau commit kecuali diminta.
2. Baca berurutan: `AGENTS.md`, `README.md`, `docs/PRD.md`, `docs/BUSINESS_RULES.md`, `docs/ARCHITECTURE.md`, `docs/ROADMAP.md`, `docs/PROGRESS.md`, `docs/COORDINATION.md`, `docs/HANDOFF.md`.
3. Baca dokumen domain milestone yang sedang dikerjakan (tabel di bawah). Jangan membuka milestone berikutnya.
4. Jika percakapan ini sudah membaca berkas itu dan `docs/PROGRESS.md` tidak berubah, baca ulang hanya `docs/PROGRESS.md`, `docs/COORDINATION.md`, `docs/HANDOFF.md`, dan dokumen milestone aktif.
5. Laporkan briefing di akhir skill, lalu kerjakan satu milestone yang pemilik minta. `/init` sendiri berhenti setelah briefing.

## Yang tidak berubah

- Produk: RT05 TAKEDA, singkatan Taman Kedaung. Ketua: Agus Ferdiansyah. Sekretaris dan bendahara belum dipublikasikan. Jangan mengarang nama, kontak, tahun kegiatan, jumlah warga, atau fakta RT.
- Website publik tanpa akun. Login web hanya CMS Artikel dan Dokumentasi. Desain diterima: navy/kuning/sky, logo gapura, font, foto asli, layout. Jangan ganti dengan desain generik.
- Warga, rumah, keluarga, pengaduan, aspirasi, agenda, iuran, kas, token, dan notifikasi dilayani mobile.
- Iuran Rp75.000 per rumah per bulan, dibayar offline, dicatat pengurus. Tidak ada tombol bayar, dompet, QRIS, checkout, atau webhook pembayaran. Nominal tagihan adalah snapshot periode; tarif baru tidak mengubah tagihan lama.
- Admin yang ditetapkan punya hak bisnis setara. Token registrasi bukan hak admin. Akun tambahan menunggu persetujuan.
- Anonimitas laporan berlaku terhadap warga lain. Pengurus melihat pelapor. Jangan menaruh identitas anonim di payload warga, log, nama berkas, notifikasi publik, atau URL.
- Arsip untuk data penting. Koreksi keuangan memakai pembalikan dan rekaman pengganti, bukan edit saldo di tempat.
- Database dan aturan bisnis hanya di `rest-api/`. Website dan mobile tidak menyambung Neon atau Redis.
- Jangan commit `.env`, credential, token, data warga asli, dump, kunci APK, atau draf browser. Jangan `migrate:fresh`, wipe, atau flush pada production. Jangan pakai Neon production untuk seed atau tes.

## Status tersimpan 20 Oktober 2026

Angka ini hanya titik mulai. Baca `docs/PROGRESS.md` untuk status hidup dan `docs/HANDOFF.md` untuk serah terima sesi terakhir.

M00–M14 **passed** untuk staging sintetis. **M15 in_progress**: staging dan APK uji tim 1007 pada `71dc523`; UAT tim putaran 1–2 sudah diperbaiki (ADR32, ADR33). Sisa gate M15 (hasil UAT tercatat, PR #50 untuk issue #46–#48, uji HP nyata, verifikasi developer Android, privacy notice final, UAT mitra) dan pertanyaan untuk pemilik ada di `docs/HANDOFF.md`. M16 (production) **belum dibuka**.

Keterbatasan yang tetap terbuka: aspirasi teks saja, tanpa foto (ADR22, ditegaskan pemilik 19 Okt); foto pengaduan bisa ditambah/dihapus hanya selama status *diajukan* (ADR32); Vercel hanya preview statis dengan CMS demo.

## Fitur lanjutan

Kerjakan hanya milestone yang pemilik buka, plus prasyarat yang sudah passed. Jangan menjalankan M10–M16 dalam satu giliran.

| ID | Hasil | Baca |
| --- | --- | --- |
| M10 | Agenda, pengumuman, inbox, push | `docs/ASYNC_JOBS.md`, `docs/MOBILE.md`, `docs/SECURITY.md`, PRD FR10/FR11/FR20 |
| M11 | Tarif, tagihan, tunggakan | `docs/BUSINESS_RULES.md` bagian iuran, `docs/DATABASE.md`, `docs/TESTING.md` |
| M12 | Pembayaran offline, kas, pembalikan, ekspor | Aturan keuangan, `docs/TESTING.md` skenario 1–18 |
| M13 | Konten publik di API dan mobile admin | `docs/FRONTEND.md`, `docs/ERD.md`, `docs/API.md` |
| M14 | CMS website memakai API | `docs/FRONTEND.md`, `docs/ARCHITECTURE.md`, `docs/website/FRONTEND_HANDOFF.md` |
| M15 | UAT, keamanan, APK kandidat | `docs/TESTING.md`, `docs/SECURITY.md`, `docs/UAT_M15.md`, `docs/HANDOFF.md` |
| M16 | Production `takeda.morriz.tech`, backup, handover | `docs/DEPLOYMENT.md` |

M10 bergantung pada M07, M03, serta laporan M08/M09. M11 bergantung pada M07. M12 bergantung pada M11 dan M03.

## Peta modul

| Folder | Peran |
| --- | --- |
| `website/` | Laravel 13, Blade, Tailwind 4. Preview statis Vercel. Bukan pemilik data warga |
| `mobile/` | Satu aplikasi Flutter Android, tampilan warga dan pengurus |
| `rest-api/` | Laravel 13, Sanctum, pemilik Neon dan aturan bisnis. Kontrak `docs/openapi.yaml` |
| `infra/` | Docker, Caddy, Compose, `infra/scripts/check-repo.mjs` |
| `docs/` | Sumber kebenaran. Frontend historis di `docs/website/` |

Host rancangan: production `takeda.morriz.tech`, staging `takeda-staging.morriz.tech`, API di `/api/v1`. Preview web: `https://rt05takeda.vercel.app`. Vercel hanya preview statis.

## Saat mengubah kode

- Branch `codex/mNN-topik`. Implementasi paling sederhana yang lulus penerimaan. Transaksi lewat action/service domain.
- Mutasi penting: transaksi Postgres, audit, dan outbox. Uang dan status tagihan tidak hanya lewat antrean. Kegagalan email atau push tidak membatalkan transaksi yang sudah commit.
- Perbarui OpenAPI dan `docs/ERD.md` bila kontrak atau skema berubah. Perbarui `docs/PROGRESS.md` dengan perintah, hasil, bukti, dan blocker. Checkbox tidak selesai hanya karena kode ada.
- Jalankan `node infra/scripts/check-repo.mjs` untuk setiap perubahan. Uji perilaku di `docs/TESTING.md`. Jangan mengklaim tes, Neon, atau deploy lulus tanpa menjalankannya.
- Website: dari `website/`, Windows memakai `npm.cmd`. Jangan `composer setup` (skrip itu migrasi). Jangan edit `website/preview/` manual; setelah ubah sumber, `npm.cmd run build:preview` lalu commit hasil ekspor. `website/vercel.json` kanonik; `vercel.json` root adalah jembatan — ubah keduanya bersama. Rute tetap `/profil`, `/artikel`, bukan prefix `website/`.
- Hero berputar tiap 3 detik tanpa tombol jeda. Empat indikator tetap. Album 17 Agustus tanpa tahun.

## Briefing

Setelah langkah baca, jawab singkat:

- Git root, branch, dan apakah ada perubahan pengguna yang tidak boleh ditimpa
- Milestone terakhir yang passed, dan milestone yang masih terkunci
- Dokumen domain yang akan dipakai bila ada tugas
- Batas yang tetap dijaga pada tugas itu (desain web, iuran offline, anonimitas, data sintetis)

Lalu sebut milestone berikutnya tanpa mulai mengerjakannya.
