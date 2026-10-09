# Fine-tuning desain oleh Kimi Code (9 Oktober 2026)

Dokumen ini mencatat semua perubahan yang dilakukan sesi **Kimi Code** di atas hasil kerja sesi Codex,
agar sesi Codex berikutnya langsung memahami apa yang berubah dan mengapa.

## Konteks dan permintaan
- Pemilik merasa tampilan hasil refactor masih terasa datar dan kurang menarik.
- Syarat tetap: biru muda (`--sky`) wajib ada; arah desain "Halaman Bersama", font Bricolage Grotesque
  + Public Sans, logo gapura, hero tengah dengan kolase foto asli, dan kuning `#f2cd5a` secukupnya
  **tidak diubah**.
- Pendekatan yang dipilih: fine-tune dalam bahasa desain yang sama (bukan re-branding).

## Lingkup perubahan
Hanya `resources/css/app.css` (satu file). Tidak ada perubahan pada Blade, route, controller, data,
konten teks, atau dependency. Tidak ada gambar baru. `preview/` diregenerasi ulang dari sumber.

## Token baru di `:root`
| Token | Nilai | Fungsi |
| --- | --- | --- |
| `--sky-deep` | `#d9eaf6` | Ujung gradasi hero (biru muda lebih dalam) |
| `--shadow-sm` | `0 1px 2px rgba(18,62,101,.05), 0 3px 10px rgba(18,62,101,.06)` | Bayangan lembut komponen |
| `--shadow-md` | `0 6px 20px rgba(18,62,101,.1), 0 2px 6px rgba(18,62,101,.05)` | Bayangan media foto |
| `--shadow-lg` | `0 22px 50px -14px rgba(18,62,101,.25)` | Bayangan saat hover / foto utama |
| `--ring` | `0 0 0 1px rgba(18,62,101,.07)` | (Disediakan, belum dipakai selector manapun) |
| `--ease` | `cubic-bezier(.4,0,.2,1)` | Kurva transisi seragam |

Semua bayangan memakai tinta navy `rgba(18,62,101,…)` agar konsisten dengan identitas biru,
bukan hitam netral.

## Daftar perubahan selector (app.css)

### Fondasi
- `body`: font-size 15px → 16px.
- `h2`: clamp(30px,3.4vw,46px) → clamp(31px,3.6vw,50px).
- `.section-label`: tambah `letter-spacing:.04em`.
- `.text-link`: transisi warna; ikon panah bergeser 4px saat hover.
- `.button`: min-height 50→52px, padding lebih lega, radius 8→10px, transisi seragam.
- `.primary`: bayangan navy halus; hover mengangkat 1px + bayangan lebih dalam.

### Navigasi & header
- `.site-header`: latar `rgba(255,255,255,.85)` + `backdrop-filter: blur(12px)` (sticky tetap).
- `.main-nav a`: transisi warna hover.
- `.main-nav .nav-contact`: radius 7→10px + shadow-sm; hover memperdalam border dan bayangan.

### Hero beranda
- `.home-hero`: latar flat `--sky` → `radial-gradient` biru muda memusat di tengah atas
  (`#d6e9f6 → var(--sky) → #fff`), sehingga biru muda tetap dominan namun hidup.
- `.hero-collage img`: shadow-md + hover angkat 4px dengan shadow-lg.
- `.hero-main img`: radius 10→12px + shadow-lg (foto utama paling dalam).
- `.hero-main figcaption` (chip kuning): radius 5→9px + bayangan agar terbaca mengambang di atas foto.
- Radius kolase samping diseragamkan (8→10px pada sudut tajam).

### Section & kartu
- `.facility-lead > img`: radius 10→12px + shadow-md; hover angkat 3px + shadow-lg; ikon panah bergeser saat hover.
- `.facility-list > a`: padding-inline 10px dengan margin-inline -10px + radius 10px; hover memberi
  latar putih + shadow-sm; thumbnail membesar 1.04x saat hover. Item pertama tetap padding-top 0.
- `.article-art`: radius 9→12px + shadow-sm; kartu hover: art terangkat 4px + ilustrasi zoom 1.04x.
- `.article-card h3 a`: transisi warna hover.
- `.mobile-note`: radius 12→16px, border `#d7e6f1`, shadow-sm.
- `.filter-bar button`: radius 6→9px + transisi; hover memperdalam border + shadow-sm;
  state aktif (`aria-pressed`) diberi bayangan navy.
- `.photo-trigger`: radius 10→12px + shadow-sm; hover terangkat 4px + shadow-lg + zoom foto 1.045x;
  badge "perbesar" diberi shadow-sm.
- `.featured-article`: radius 14→18px + shadow-sm; hover shadow-md.
- `.profile-panorama img`: radius 11→14px + shadow-md; chip caption putih diberi radius+shadow.
- `.facility-story > img`: radius 10→14px + shadow-md; hover angkat 3px + shadow-lg.
- `.contact-layout figure img`: radius 11→14px + shadow-md.
- `.source-note`: radius 8→10px + border `#dce9f3`.

### Shell pengurus
- `.overview-panel`: radius 9→12px + shadow-sm; hover terangkat 2px + shadow-md.
- `.management-panel`: radius 9→12px + shadow-sm.
- `.module-directory > a`: padding-inline 10px dengan margin-inline -10px + radius 9px;
  hover latar `#f4f8fb`.
- `.management-nav a`: radius 7→8px + transisi.
- `.module-table tbody tr`: hover latar `#f7fafc`.
- `.preview-notice`: radius 8→10px + shadow-sm.

### Responsif
Tidak ada nilai breakpoint yang diubah; semua penyesuaian memakai radius/shadow/padding-inline
yang aman di semua viewport. Diverifikasi ulang oleh QA browser (lihat di bawah).

## Verifikasi (9 Oktober 2026, sesi Kimi Code)
- `php artisan test`: 8 passed / 167 assertions.
- `php vendor/bin/pint --test`: PASS (30 files).
- `npm run build`: sukses.
- `scripts/build-preview.sh`: 21 rute + 404 diekspor ulang ke `preview/`.
- `npm run test:preview`: 105/105 kasus responsif PASS, 42 pemeriksaan axe WCAG tanpa pelanggaran,
  30 tautan internal PASS, tanpa browser error, interaksi PASS.
- Screenshot sebelum fine-tune disimpan di `artifacts/screenshots-before-finetune/`
  (folder `artifacts/` diabaikan Git); screenshot sesudah ada di `artifacts/screenshots/`.

## Yang sengaja TIDAK diubah
- Token warna inti: `--navy #123e65`, `--ink #183b50`, `--sky #eef5fa`, `--yellow #f2cd5a`, `--muted #536a79`.
- Font, logo, struktur halaman, dan seluruh copy teks.
- Area pengurus tetap shell pratinjau tanpa data/CRUD/autentikasi.

## Catatan untuk sesi berikutnya
- Kalau ingin menambah kedalaman pada komponen baru, gunakan `--shadow-sm/md/lg` dan `--ease`;
  jangan buat nilai bayangan atau kurva transisi ad-hoc.
- Hover lift memakai pola `translateY(-2px s.d. -4px)` + penguatan bayangan; ikuti pola ini.
- `@media (prefers-reduced-motion:reduce)` di akhir file mematikan semua transisi/animasi baru
  secara otomatis — tidak perlu penanganan khusus.
