# Vercel setelah migrasi monorepo (M01)

Status 10 Oktober 2026: **belum diubah di Vercel.** M01 tidak mengubah integrasi eksternal. Dokumen ini instruksi untuk pemilik proyek Vercel.

## Keadaan sekarang

- Preview live: https://rt05takeda.vercel.app (`Server: Vercel`, `X-Robots-Tag: noindex`, redirect `/pengurus/konten` → `/pengurus`).
- Sebelum M01, Vercel membaca `vercel.json` di root repo dan menyajikan `preview/` tanpa build (`installCommand`/`buildCommand` = `echo skip`).
- Sesudah M01, website berada di `website/`. Ada dua file:
  - `website/vercel.json` — konfigurasi kanonik, `outputDirectory: preview`.
  - `vercel.json` di root — jembatan transisi, isi sama kecuali `outputDirectory: website/preview`.
- `node infra/scripts/check-repo.mjs` gagal bila kedua file berbeda selain `outputDirectory`.

Dengan jembatan ini, merge M01 ke `main` tidak memutus preview walaupun Root Directory proyek Vercel belum diubah. Proyek Vercel tidak terlihat dari koneksi Vercel agent pada team `morriz`, sehingga pengaturan aktual proyek belum dapat dibaca langsung oleh agent; pemilik perlu memeriksanya di dashboard.

## Mengubah Root Directory (disarankan setelah PR M01 di-merge)

1. Buka dashboard Vercel → proyek yang menyajikan `rt05takeda.vercel.app` → **Settings → Build & Deployment**.
2. **Root Directory**: ubah menjadi `website`. Biarkan "Include files outside the root directory" sesuai default.
3. Framework Preset: `Other`. Build/Install/Output dibaca dari `website/vercel.json`; tidak perlu override di dashboard.
4. Simpan, lalu **Redeploy** deployment `main` terbaru.
5. Verifikasi dari folder `website/`:

   ```powershell
   node scripts/check-live.mjs https://rt05takeda.vercel.app
   ```

   Harapan: 19/19 HTML cocok, 26 aset cocok, rute tak dikenal 404, `browserErrors` kosong.
6. Setelah terbukti, hapus `vercel.json` root dalam PR kecil terpisah dan catat di `docs/PROGRESS.md`.

Opsional: **Ignored Build Step** `git diff --quiet HEAD^ HEAD -- .` (relatif Root Directory) agar perubahan `rest-api/` atau `mobile/` tidak memicu redeploy preview.

## Peran Vercel ke depan

Vercel tetap sebagai **preview statis** frontend selama pengembangan (keputusan pemilik 10 Oktober 2026). Production berjalan di VPS Hostinger pada subdomain `takeda.morriz.tech` (M16), staging `takeda-staging.morriz.tech` (M04). Vercel tidak menjalankan PHP, API, atau data warga.
