# Fine-tuning desain oleh Kimi Code (9 Oktober 2026)

Penyempurnaan 10 Oktober mempertahankan fondasi ini. Hero lengkung dan garis kuning tetap digunakan, headline HP dibuat solid, marquee disingkirkan dari tampilan, dan badge berhenti berputar. Alur artikel/album serta referensi Google Stitch dicatat dalam [CONTENT_PREVIEW.md](CONTENT_PREVIEW.md) dan [STITCH_SCREENS.md](STITCH_SCREENS.md). Catatan shell pengurus di bawah merupakan kondisi historis sebelum editor konten.

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

---

# Putaran 2 — Redesign logo & hero (9 Oktober 2026, sesi Kimi Code)

## Latar
Pemilik menilai hero lama (teks tengah + kolase 3 foto) terlalu generik/"template AI" dan logo
(ikon rumah dalam kotak biru) tidak khas. Redesign ini berpedoman pada
`.claude/skills/frontend-design/SKILL.md` (anti-pola default AI) dan tetap mematuhi mandat
`docs/website/FRONTEND_REFACTOR.md`: biru muda wajib ada, font dan warna inti tidak berubah, hanya foto asli.

## Konsep: "Gapura"
Elemen pembeda digali dari subjek itu sendiri: gapura masuk RT 05 (pediment garis merah-putih
HUT RI ke-81, dua pilar, tulisan "Selamat datang di RT 05 RW 07 Takeda").

### Logo baru — public/images/logo/rt05-mark.svg & rt05-mark-terang.svg
- Siluet gapura asli: pediment segitiga bergaris merah-putih (clipPath + stripe manual),
  dua pilar dengan kapital, titik kuning di puncak, garis tanah kuning. Digambar tangan sebagai SVG.
- Varian `-terang` untuk footer navy: pilar/outline biru sangat muda, merah dicerahkan (#e0607e).
- Nama file tidak berubah, sehingga navbar, footer, dan favicon otomatis memakai logo baru.

### Hero baru — resources/views/home.blade.php + app.css
- Layout editorial dua kolom (`.hero-grid`): copy di kiri, visual di kanan. Bukan lagi teks-tengah generik.
- Headline: baris kedua bergaya outline/hollow (`-webkit-text-stroke`, kelas `.hollow`,
  fallback `@supports` mengembalikan warna solid). Bukan aksen satu kata.
- Foto gapura dibingkai mask lengkung gerbang (`.hero-arch`, border-radius 999px atas) dengan
  bingkai putih inset (outline) — elemen visual utama yang tidak ada di template manapun.
- `.hero-tuck`: foto taman bermain kecil menumpuk di tepi arch dengan border putih.
- `.hero-badge`: badge teks melingkar SVG (textPath) berputar 26 detik, berisi tulisan gapura asli,
  logo mini di tengah. `aria-hidden`.
- `.hero-marquee`: strip navy berjalan 36 detik berisi teks sambutan gapura; dua span identik
  untuk loop mulus (`translateX(-50%)`).
- Latar hero: gradasi biru muda + pola garis vertikal tipis (repeating-linear-gradient) —
  mengingatkan pilar-pilar gapura, menjaga biru muda tetap dominan.
- Animasi pembuka tunggal yang terorkestrasi: `.hero-copy` anak-anaknya naik berurutan
  (`hero-rise`, delay 0/.1/.22/.34s), arch muncul `gate-rise` (naik + scale), badge `badge-pop`.
  Tidak ada animasi tersebar di section lain. Semua animasi otomatis mati via aturan
  `prefers-reduced-motion` global yang sudah ada (fill-mode `both` memastikan status akhir tampil).

### Selector lama yang DIHAPUS (jangan dipakai lagi)
`.hero-intro`, `.hero-collage`, `.hero-side`, `.hero-garden`, `.hero-post`, `.hero-main`,
`.hero-foot`, `.desktop-break` — beserta seluruh aturan media query terkait.

### Responsif hero baru
- ≥1500px: padding hero lebih lega, arch 620px.
- ≤1100px: gap dipersempit, arch 470px, badge/tuck diperkecil.
- ≤768px: hero jadi satu kolom (copy dulu, visual di bawah), arch 420px, tuck pindah ke kanan bawah.
- ≤600px: arch 340px dengan radius arch 170px, badge 82px, marquee lebih kecil.

## Verifikasi putaran 2
- `npm run build` + `scripts/build-preview.sh`: 21 rute + 404 sukses.
- `npm run test:preview`: 105/105 responsive PASS, 42 axe PASS, 30 tautan PASS, tanpa browser error.
- Catatan: screenshot QA (`artifacts/screenshots/`) menangkap hero di tengah animasi pembuka
  sehingga tampak pudar; status akhir diverifikasi manual lewat screenshot terpisah
  (`artifacts/hero-final-desktop.png`, `artifacts/hero-final-mobile.png`) setelah 2,5 detik.

---

# Putaran 3 — Hero premium interaktif & pembersihan AI slop (9 Oktober 2026, sesi Kimi Code)

## Latar
Pemilik menilai eyebrow "RT 05 RW 07 · Taman Kedaung, Ciputat" + dot kuning sebagai pola AI generik,
meminta lebih banyak animasi, gambar hero yang interaktif, background yang tidak kosong, lebih banyak
SVG/garis, dan referensi desain kelas Awwwards. Riset referensi: pola SOTD 2025–2026 (kinetic type,
pointer parallax, drag interaction, mesh gradient + grain) dan skill `.claude/skills/frontend-design/SKILL.md`.

## Perubahan

### Dibuang (pola AI slop)
- `.place-line` (dot kuning + teks lokasi) dihapus total dari markup dan semua media query.
  Identitas lokasi tetap hidup di marquee, badge melingkar, dan footer.
- Separator "·" di marquee diganti ikon mini-gapura SVG (`.mq-gapura`, inline di home.blade.php).

### Hero interaktif (resources/js/app.js)
- Parallax pointer 3 lapis: `[data-parallax-scene]` dengan `[data-parallax]` = 14/30/46 px untuk
  arch/tuck/badge; lerp rAF 0.08; hanya `pointer:fine`; transform translate3d only.
- Drag-to-pan: `[data-drag-pan]` pada `.hero-arch`; geser memindahkan foto via properti CSS
  `translate` pada img (dibatasi ±70/±45px; img `scale:1.12` agar tak ada celah); kursor grab/grabbing.
- Listener `animationend` (once) membersihkan animasi pembuka agar fill-mode `both` tidak
  menahan transform inline parallax.
- Semua interaksi non-aktif saat `prefers-reduced-motion`.

### Background premium (app.css)
- `.home-hero`: 4 lapis mesh gradient (biru muda atas-kanan, kuning lembut kiri-bawah, wash biru
  kiri-atas, linear ke putih) + pola garis pilar 0.028 opacity + grain `feTurbulence` inline
  data-URI via `::after` (tanpa aset baru).
- `.hero-watermark`: dua pasang outline arch raksasa kiri-kanan (stroke navy 0.07) — motif gapura.
- `.hero-marquee`: gradasi navy 3 titik; `.facility-section`: gradasi hijau-putih lembut.

### Tipografi kinetik & SVG
- Headline per-baris: `.line` overflow-hidden + `line-up` (translateY 112%→0, stagger 0/.14s).
- `.hero-arc-line`: garis lengkung kuning di atas headline, digambar via `stroke-dashoffset`
  (arc-draw 1.1s, delay .55s) — melengkapi siluet gapura.
- `.intro-art`: ilustrasi line-art original (rumah, dua warga berpegangan tangan, pohon, matahari
  kuning) di section Tentang Takeda. Digambar tangan, stroke navy 2.4.
- `.squiggle`: garis gelombang kuning di bawah h2 section fasilitas & jurnal.

### Motion tambahan
- Scroll reveal: `[data-reveal]` + IntersectionObserver (reveal-init → reveal-in, sekali,
  threshold .12) pada intro/fasilitas/jurnal/mobile-note. Tanpa JS/reduced-motion konten tampil normal.
- Sheen sweep pada `.primary` saat hover (`::after` gradient translate).

## Verifikasi putaran 3
- `php artisan test`: 8/167 PASS · Pint PASS.
- `npm run build` + `build-preview.sh`: 21 rute + 404.
- `npm run test:preview`: 105/105 responsive, 42 axe WCAG, 30 tautan, interaksi PASS, tanpa error.
- Screenshot manual pasca-animasi: `artifacts/hero-v3-desktop.png` (parallax terverifikasi visual
  via `hero-v3-desktop-parallax.png`), `artifacts/hero-v3-mobile.png`.

## Foto dokumentasi Drive
Pencarian folder "DOKUMENTASI RT 05"/Kelompok 8/foto 17 Agustusan via rclone (My Drive +
shared-with-me, kedalaman 3–4) TIDAK menemukan folder tersebut — kemungkinan akses Codex dulu
memakai konektor Drive dengan izin berbeda. Tidak ada foto baru yang ditambahkan; menunggu pemilik
membagikan/menunjuk lokasi folder. Jangan menambah foto tanpa sumber asli.
