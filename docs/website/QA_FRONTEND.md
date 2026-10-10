# QA frontend RT 05 Takeda
Catatan historis 9 Oktober. Hasil dan batas terbaru ada di [QA_STITCH_PREVIEW.md](QA_STITCH_PREVIEW.md).

Tanggal pemeriksaan lokal: 9 Oktober 2026 (Asia/Jakarta).
Diperbarui 9 Oktober 2026 oleh sesi Kimi Code setelah fine-tuning desain (lihat DESIGN_FINETUNE_KIMI.md):
seluruh pemeriksaan di bawah dijalankan ulang setelah perubahan CSS dan hasilnya tetap PASS.

## Hasil lokal
| Pemeriksaan | Hasil |
| --- | --- |
| PHP 8.4.13 / Laravel test | PASS: 8 tes, 167 assertions |
| Laravel Pint | PASS |
| npm build + ekspor Laravel | PASS: 21 rute + 404, tanpa database |
| Chromium responsive | PASS: 105 kasus (21 rute pada 360, 390, 768, 1024, 1440 px) |
| Axe WCAG 2 A/AA + 2.1 AA | PASS: 42 halaman/viewport (390 dan 1440), tanpa pelanggaran terdeteksi |
| Tautan internal dan fragment | PASS: 30 target |
| Gambar, alt, console/page errors | PASS, tidak ada aset gagal dalam browser QA |
| Menu publik/pengurus + Escape + fokus | PASS |
| Filter foto/artikel, lightbox next/Escape | PASS |
| Reduced motion | PASS |
| Unknown route dan /admin | 404 sesuai rancangan |
| Mutasi POST pada modul pengurus | Ditolak 405 |
| npm audit | 0 temuan setelah patch tooling concurrently/shell-quote |
| git diff --check | PASS |

## Bukti
- artifacts/qa-report.json: keluaran tes browser; disimpan lokal, tidak masuk Git.
- artifacts/screenshots/: 35 screenshot penuh untuk 7 halaman pada 5 viewport.
- Pemeriksaan visual: beranda desktop/mobile, detail artikel desktop, ringkasan pengurus desktop, iuran mobile.
- CI mengunggah folder artifacts sebagai frontend-evidence.
- Browser otomatis menggunakan Chromium. Safari, Firefox, perangkat fisik, dan screen reader belum diuji.

## Temuan yang diperbaiki
- Overflow mobile akibat intrinsic sizing SVG di grid artikel: dibatasi pada kotak dengan aspect ratio.
- Ekspor lama hanya halaman utama: kini semua rute dicatat SiteContent dan dirender dari kernel Laravel.
- Sumber scan Tailwind dibatasi ke views dan JS agar HTML preview/compiled views tidak mengubah hasil build.
- Layout tanpa JS tetap memperlihatkan menu navigasi; interaksi galeri/filter membutuhkan JS.
- Foto papan peraturan menggunakan varian maksimal 960 px dari manifest, bukan URL 1600 yang tidak ada.
- .env.example sudah memakai session/cache file; konfigurasi ini dipertahankan agar website lokal tidak meminta tabel database.

## Batas dan rilis
Shell pengurus bukan bukti autentikasi/otorisasi. Tidak ada backend CRUD, data warga, token, transaksi,
saldo, atau pengiriman notifikasi. Kontak/jam pelayanan belum diumumkan. Sekretaris/bendahara anonim.
Vercel melayani hasil statis, bukan proses PHP. Pemeriksaan otomatis tidak menyatakan aksesibilitas
sempurna, persetujuan seluruh konten mitra, atau kesiapan layanan operasional.

PR, hasil CI, dan deployment dapat dilihat pada tautan GitHub/Vercel yang dilaporkan setelah push.

## Tautan pemeriksaan jarak jauh
- PR: https://github.com/morrispes5/RT-05-TAKEDA/pull/4
- Workflow: https://github.com/morrispes5/RT-05-TAKEDA/actions/workflows/frontend.yml
- Preview branch: https://rt05takeda-git-codex-frontend-refactor-morrizshkki.vercel.app
- Alias utama: https://rt05takeda.vercel.app

Vercel melaporkan preview branch Ready melalui check GitHub. Pemeriksaan isi preview branch
terhambat Vercel Authentication: konektor Vercel mengembalikan 403 untuk team/proyek ini.
Proteksi tidak diubah. Alias utama dapat dibaca tanpa login dan akan diperiksa setelah merge.
Hasil smoke live setelah merge disimpan lokal di artifacts/live-report.json.

## Smoke live pasca-merge (9 Oktober 2026, sesi Kimi Code)
PR #4 di-merge squash ke `main` (b694bc3), branch kerja dihapus. Redeploy alias utama berjalan
otomatis. `node scripts/check-live.mjs` terhadap https://rt05takeda.vercel.app: 21/21 rute
menyajikan HTML yang identik dengan preview/ lokal, 25 aset cocok, rute tak dikenal 404,
smoke browser mobile PASS, tanpa browser error. Laporan lengkap: artifacts/live-report.json
(lokal, tidak masuk Git).
