# Struktur repo dan pengembangan

## Struktur setelah M01

```text
RT-05-TAKEDA/
  AGENTS.md
  README.md
  .github/workflows/
  website/
    app/ bootstrap/ config/ resources/ public/ routes/
    scripts/ tests/ storage/
    artisan composer.json composer.lock
    package.json package-lock.json vite.config.js
    preview/ vercel.json .env.example
  mobile/
    lib/ test/ integration_test/
    android/ pubspec.yaml pubspec.lock
  rest-api/
    app/ bootstrap/ config/ database/ routes/ tests/
    artisan composer.json composer.lock .env.example
  docs/
    website/
    [dokumen paket ini]
  infra/
    docker/ nginx/ scripts/
    compose.local.yml
    compose.staging.yml
    compose.production.yml
```

Empat komponen produk utama tetap website/mobile/rest-api/docs. infra/ dan .github/ adalah dukungan operasi. Folder mobile/API awalnya boleh README placeholder di M01; aplikasi sesungguhnya dibuat M02/M05.

## Migrasi frontend

Gunakan repo yang sama dan Git history yang sama. Clone ke folder baru sebagai root monorepo; jangan git clone lagi di website/. Buat branch codex/m01-monorepo-foundation.

1. Rekam commit baseline, status Git, dan seluruh source/export/assets/docs.
2. Pindahkan berkas frontend dengan git mv. Jangan membuang shell Laravel atau lockfile.
3. Pindahkan dokumentasi frontend ke docs/website/; perbarui relative link README/AGENTS/script.
4. Simpan workflow pada .github/workflows/ root. Ubah working-directory=website serta path cache/artifacts/diff ekspor.
5. website/preview tetap output build, bukan source edit.
6. Konfigurasi Vercel root directory=website dan output=preview relatif root; perubahan integrasi dilakukan hanya saat diotorisasi. Migration repo tidak otomatis mengubah root Vercel.
7. Jalankan build/test baseline dari website dan periksa URL tetap /profil, bukan /website/profil.
8. Perbarui README dan AGENTS yang ada; jangan menghapus riwayat desain.

Jika repo berbeda diperlukan nanti, gunakan keputusan terpisah; tidak ada sinkronisasi otomatis dua repository pada v1.

## Runtime lokal

- PHP 8.4 untuk image API/web v1, setelah compatibility baseline diperiksa.
- Laravel 13 dari lockfile baseline; jangan upgrade dependency tanpa kebutuhan milestone.
- Node 22.12+ untuk frontend; pin versi yang lulus CI.
- Flutter stable yang kompatibel SDK/plugins, versi tepat dicatat saat M05; pubspec.lock di-commit.
- Postgres lokal dalam compose.local untuk unit/integration; Neon dev/staging untuk compatibility nyata.
- Redis queue/cache, SMTP capture lokal, storage lokal.

Perintah frontend mengikuti README baseline: composer install, npm ci, npm run build:preview. Windows gunakan npm.cmd/npx.cmd bila PowerShell membutuhkannya. Jangan composer setup hanya untuk render frontend karena dapat menjalankan migration.

Command local/deploy yang dibuat harus berasal dari source dan tersedia di infra/scripts; jangan mengandalkan alias terminal personal.

## Pembagian pekerjaan berapa pun jumlah developer

Setiap issue mencantumkan milestone, domain, FR/BR, output, kontrak endpoint, dependency, dan bukti penerimaan. Ownership per issue sementara, bukan role permanen di PRD.

| Area | Boleh paralel setelah |
| --- | --- |
| Frontend public migration | M00 |
| API foundation dan mobile shell | M01; menyepakati API shape |
| UI fitur dan endpoint fitur | Kontrak domain disepakati |
| Finance core dan finance mobile | M11 kontrak; jangan menulis ulang formula di client |
| Deployment/staging | M02–M03 foundation tersedia |

Satu issue tidak mengubah migration/kontrak milik issue lain tanpa koordinasi. Gunakan branch feature/ atau codex/; merge lewat PR setelah CI.

## Aturan kontrak

- docs/API.md adalah desain human-readable; docs/openapi.yaml dibuat di M02 dan menjadi kontrak machine-readable.
- Perubahan breaking tidak digabung sebelum web/mobile diperbarui atau adapter kompatibel tersedia.
- Gunakan field/data/status yang sama dalam contoh JSON, Resource API, fixture Flutter, dan OpenAPI.
- Migration baru immutable setelah dipakai lingkungan bersama; koreksi dengan migration berikutnya.
- Tidak membuat dua database warga atau dua sumber saldo.

## CI

Job dipicu path yang relevan, dengan integrasi penuh sebelum release. Web: test Laravel/Pint/build/export/browser. API: lint/tests Postgres+Redis/contract. Mobile: format/analyze/unit/widget/integration terpilih. Infra: compose config/build/health/smoke.

Credentials production tidak tersedia untuk job pull request. Seed hanya data sintetis. Uji deploy staging mengikuti branch/tag yang ditetapkan, bukan setiap PR dengan akses production.

## Handoff tiap milestone

Isi PROGRESS.md, tautkan commit/PR bila tersedia, catat command dan output, screenshot hanya bila membantu. Sesi baru membaca dokumen dan status aktual, bukan mengasumsikan semua milestone sebelumnya selesai.
