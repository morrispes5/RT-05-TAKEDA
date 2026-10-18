# M14 — Deploy staging, cek live CMS web, dan drill API mati (19 Oktober 2026)

Hanya project Docker `rt05-staging`. Akses dari PC pemilik lewat Cloudflare WARP + IPv6 VPS (alias SSH
`rt05-vps-v6`, host key diverifikasi). Tanpa perubahan DNS, firewall, port, production, atau service lain.
Akun yang dipakai sintetis (fixture privat di `%USERPROFILE%/.rt05`, di luar Git); foto uji dibuat GD (kotak warna).

## Deploy

| Langkah | Hasil |
| --- | --- |
| PR #41 (`c21d523`) CI | 6/6 lulus; merge `08f2a1e` |
| Skrip deploy dari `08f2a1e` → `/root/.rt05/deploy-staging-immutable-m14.sh` | SHA-256 diawali `aa50ca1a629ba5a7` (sama dengan repo); `bash -n` OK. Upload pertama lewat pipa SSH menghasilkan file kosong (hash `e3b0c442…`) sehingga tidak ada yang berjalan; diulang lewat `scp` lalu diverifikasi sebelum dijalankan |
| Deploy `08f2a1e8928856e31b616ac2940c1b3cee88e528` | `Selesai`, 0 GAGAL; 9 container `rt05-staging` sehat; `/health/ready` ready |
| Env web | `RT05_CONTENT_API=http://api` (default compose; env staging tidak diubah) |

## Cek live CMS web

Skrip [`infra/scripts/m14-stage-check.mjs`](../../../infra/scripts/m14-stage-check.mjs) meniru browser: cookie jar,
Origin/Referer staging, `X-XSRF-TOKEN` dari `rt05_api_xsrf`. Ringkasan: [m14-live-summary-08f2a1e.json](m14-live-summary-08f2a1e.json).

**21/21 PASS**: `/artikel` website dirender dari konten impor; `/pengurus/*` mode server tanpa salinan konten di
halaman; warga tidak mendapat sesi (422 generik); web-login tanpa CSRF 419; pengurus masuk lewat cookie tanpa token;
mutation tanpa CSRF 419; origin asing 401; draf artikel dibuat; unggah foto lewat cookie (multipart + CSRF) siap;
pratinjau foto draf privat 200 dan publik 404; draf album dengan foto; draf tidak tampil di website (404); terbitkan
artikel + album; artikel tampil di website dari browser lain tanpa akun setelah 45 dtk (cache fresh 60 dtk); album
tampil dengan foto unggahan; foto publik setelah terbit; versi basi 409 `VERSION_CONFLICT`; arsip; arsip hilang dari
website ≤60 dtk; keluar → `auth/me` 401. Konten uji diarsipkan di akhir.

Browser bawaan Claude (PC pemilik, WARP): `https://takeda-staging.morriz.tech/pengurus/artikel` → dialihkan ke
`/pengurus/masuk?kembali=%2Fpengurus%2Fartikel`, `data-cms-mode="server"`, form email/kata sandi tampil. Login UI
dengan kata sandi tidak diketik agent di host non-lokal; alur UI lengkap (login, simpan, unggah, terbit, arsip, 409,
keluar, API mati) sudah diperagakan di browser pada stack lokal satu origin (lihat PROGRESS M14).

## Drill API mati (fallback website)

`docker stop rt05-staging-api-1` pukul 17.47.22 UTC selama ±70 dtk (trap memastikan container dinyalakan lagi):

| Path | Hasil saat API mati |
| --- | --- |
| `/artikel` | 200, 0,041 dtk, konten tampil |
| `/artikel/cara-mencuci-tangan` | 200, 0,039 dtk |
| `/dokumentasi/perayaan-17-agustus` | 200, 0,034 dtk |
| `/` | 200, 0,033 dtk |
| `/pengurus/masuk` | 200 (CMS menampilkan pesan server bermasalah bila dipakai) |
| `/api/v1/public/articles` lewat gateway | 502 (diharapkan) |

Log web hanya 2 peringatan `site_content.api_unavailable` (penanda gagal 30 dtk mencegah timeout per request).
API kembali `healthy` pukul 17.49.03 UTC; `/health/ready` ready.

## Keterbatasan

- Bukan production dan bukan UAT warga/pengurus nyata (M15). Shared hosting belum diuji (sketsa `.htaccess`).
- Perubahan terbit muncul di website paling lambat ±60 dtk (ADR30).
- Screenshot browser staging tidak diambil (jendela app tertutup jendela lain); bukti berupa teks DOM di atas.
