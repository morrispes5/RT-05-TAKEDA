# Konteks untuk agent RT 05 Takeda

Desain website telah diterima pemilik pada **10 Oktober 2026**. Repo ini adalah modul website untuk calon monorepo `web`, `mobile`, dan `rest-api`; pembangunan mobile/API belum dimulai dalam repo ini.

Baca terlebih dahulu `docs/FRONTEND_HANDOFF.md`, kemudian `docs/CONTENT_PREVIEW.md` dan `docs/QA_STITCH_PREVIEW.md`. Dokumen refactor awal dan catatan Kimi bersifat historis; lingkup dan perilaku terbaru ada dalam handoff serta sumber kode.

- Pertahankan desain yang diterima ketika mengintegrasikan modul: navy/kuning/sky, font, logo, foto asli, layout responsif, dan hero lengkung. Perubahan selanjutnya mengikuti arahan baru pemilik.
- Hero otomatis setiap 3 detik, tanpa tombol Jeda/Putar. Empat indikator tetap bekerja. Reduced motion serta tab/hero tidak terlihat tetap dihormati.
- Ketua RT: Agus Ferdiansyah. Sekretaris dan bendahara anonim. Album Perayaan 17 Agustus tanpa tahun. Jangan mengarang kontak atau data warga.
- Website publik tanpa akun. Pengurus web hanya Artikel dan Dokumentasi. Pendataan, pengaduan, aspirasi, agenda, iuran, keuangan, dan token registrasi adalah lingkup mobile dengan backend/API yang akan dibangun.
- Editor saat ini menyimpan draf/foto lokal dalam IndexedDB. Tidak ada autentikasi operasional, upload server, publikasi online, REST API bisnis, atau sinkronisasi mobile. Skeleton Laravel bukan bukti backend operasional.
- Sumber UI: `resources/views`, `resources/css`, `resources/js`, `resources/data`, serta `public/images`. `app/Support/SiteContent.php`, controller, dan routes diperlukan untuk render/ekspor Blade.
- Jangan mengedit `preview/` secara manual. Setelah perubahan sumber, jalankan `npm.cmd run build:preview` dan commit hasil ekspornya. Vercel saat ini hanya menyajikan `preview/`, tidak menjalankan PHP.
- Windows memakai `npm.cmd`/`npx.cmd`. Petunjuk instalasi ada di README. Jangan menjalankan `composer setup` untuk frontend karena script tersebut menjalankan migrasi. Tidak perlu migrasi untuk preview.
- Pilih pemeriksaan sesuai perubahan. Workflow repo menjalankan build, Laravel, Pint, konsistensi ekspor, dan browser QA. Perubahan dokumentasi/paket tidak perlu mengubah desain atau dependensi.
- Gunakan branch `codex/` untuk PR lokal. Jangan masukkan `.env`, credential, dependency terpasang, database, foto asli Drive, atau draf browser ke Git/arsip.

Rencana monorepo serta contoh impor `web/` ada di handoff. Framework mobile, kontrak API, model database, login, dan deployment backend masih perlu diputuskan bersama pemilik/agent berikutnya.
