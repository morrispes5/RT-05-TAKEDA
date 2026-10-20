# Uji website 20 Oktober 2026 (setelah UAT putaran 2, `main` = staging = `71dc523`)

Diminta pemilik: uji semua fungsi web di lapisan Vercel (cadangan saat VPS mati) dan staging. Screenshot lewat Chrome
pemilik macet (jendela tidak terlihat), sehingga uji memakai Chromium Playwright di PC pemilik. Akun sintetis saja;
konten uji diarsipkan di akhir.

| Lapisan | Perintah (dari `website/` kecuali disebut) | Hasil | Berkas |
| --- | --- | --- | --- |
| Vercel `rt05takeda.vercel.app` | `node scripts/check-live.mjs https://rt05takeda.vercel.app` | 21/21 HTML sama persis dengan `preview/`, 32 aset cocok, rute asing 404, 0 error | — |
| Vercel | `node scripts/check-preview.mjs https://rt05takeda.vercel.app` | 21 rute × 5 lebar (360–1440), 45 cek axe, 37 tautan internal, menu/filter/lightbox/pencarian, zoom 200%, editor demo (artikel, album, simpan, pemulihan gagal simpan, reset), 0 request tulis jaringan, hero 3 dtk (tanpa tombol jeda, reduced motion, tab tersembunyi, tanpa JS): **PASS** | [vercel-full-qa.json](vercel-full-qa.json) |
| Staging API CMS | `node --require <v6-lookup> infra/scripts/m14-stage-check.mjs` (dari root) | **21/21 PASS** | [staging-cms-api-21.json](staging-cms-api-21.json) |
| Staging UI CMS | `RT05_RESOLVE_V6=… node --require <v6-lookup> scripts/cms-ui-live.mjs` | **16/16 PASS** (dua kali; percobaan pertama 2 FAIL karena skrip membaca judul di notifikasi dan tidak menunggu cache 15 dtk — skrip diperbaiki, bukan aplikasi) | [staging-cms-ui-16.json](staging-cms-ui-16.json), [perubahan tersimpan](cms-perubahan-tersimpan.png), [arsip](cms-arsip.png) |

Catatan: Vercel memakai CMS mode demo (penyimpanan browser) dan konten hasil ekspor terakhir; artikel yang diterbitkan
lewat CMS staging tidak otomatis muncul di Vercel. Ini bukan uji pembaca layar manual dan bukan UAT mitra.
