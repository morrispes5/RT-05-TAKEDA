# Instruksi agent — RT05 TAKEDA

## Tujuan dan urutan baca

Bangun sistem informasi dan layanan RT 05 Taman Kedaung dari frontend yang telah diterima menjadi monorepo operasional. Baca README.md, docs/PRD.md, docs/BUSINESS_RULES.md, docs/ARCHITECTURE.md, docs/ROADMAP.md, dan docs/PROGRESS.md sebelum implementasi. Lalu baca dokumen domain milestone aktif.

Dokumen frontend dari repository asal tetap menjadi bukti baseline. Sejak M01 dokumentasi frontend berada di docs/website/ (mulai dari docs/website/FRONTEND_HANDOFF.md, lalu CONTENT_PREVIEW.md dan QA_STITCH_PREVIEW.md). Dokumen refactor awal dan catatan Kimi bersifat historis.

## Aturan yang harus dipertahankan

- Nama produk RT05 TAKEDA; singkatan Taman Kedaung.
- Halaman website publik tidak memerlukan akun. Login operasional terbatas pada pengelolaan Artikel dan Dokumentasi.
- Data warga, rumah, keluarga, pengaduan, aspirasi, agenda, iuran, kas, token, dan notifikasi dilayani mobile.
- Iuran Rp75.000 per rumah per bulan; pembayaran offline; pengurus mencatat; tanpa payment gateway.
- Tidak ada tombol bayar online, dompet, QRIS pembayaran otomatis, checkout, atau webhook pembayaran.
- Nominal tagihan disimpan per periode dan tidak berubah akibat perubahan tarif berikutnya.
- Semua pengurus yang ditetapkan sebagai admin memiliki hak bisnis setara.
- Akun tambahan memerlukan persetujuan pengurus. Token registrasi tidak memberi hak admin.
- Anonimitas laporan berlaku terhadap warga lain; pengurus dapat melihat pelapor.
- Data penting diarsipkan; koreksi keuangan menggunakan pembalikan dan rekaman pengganti.
- Database dan aturan bisnis dimiliki rest-api/. Website/mobile tidak terhubung langsung ke Neon atau Redis.
- Pertahankan desain website yang telah diterima: navy/kuning/sky, logo gapura, font, foto asli, dan layout. Jangan mengganti dengan desain generik.
- Jangan mengarang nama, kontak, tahun kegiatan, jumlah warga, atau fakta RT. Ketua: Agus Ferdiansyah; sekretaris/bendahara belum dipublikasikan.

## Modul website (`website/`)

Catatan dari AGENTS repo frontend sebelum M01, dengan path yang diperbarui:

- Hero otomatis setiap 3 detik, tanpa tombol Jeda/Putar. Empat indikator tetap bekerja. Reduced motion serta tab/hero tidak terlihat tetap dihormati.
- Album Perayaan 17 Agustus tanpa tahun. Sekretaris dan bendahara anonim.
- Editor saat ini menyimpan draf/foto lokal dalam IndexedDB. Tidak ada autentikasi operasional, upload server, publikasi online, REST API bisnis, atau sinkronisasi mobile. Shell Laravel di website/ bukan bukti backend operasional dan tidak boleh dibuang.
- Sumber UI: `website/resources/views`, `resources/css`, `resources/js`, `resources/data`, serta `public/images`. `app/Support/SiteContent.php`, controller, dan routes diperlukan untuk render/ekspor Blade.
- Jangan mengedit `website/preview/` secara manual. Setelah perubahan sumber, jalankan `npm.cmd run build:preview` dari `website/` dan commit hasil ekspornya. Vercel hanya menyajikan preview statis, tidak menjalankan PHP.
- Semua perintah website dijalankan dari `website/`. Windows memakai `npm.cmd`/`npx.cmd`. Jangan menjalankan `composer setup` untuk frontend karena script tersebut menjalankan migrasi.
- `website/` bukan prefix URL. Rute publik tetap `/profil`, `/artikel`, dan seterusnya.
- Konfigurasi Vercel kanonik `website/vercel.json`; `vercel.json` root adalah jembatan transisi sampai Root Directory Vercel diubah (lihat docs/website/VERCEL_ROOT.md). Ubah keduanya bersamaan.
- Jangan masukkan foto asli Drive atau draf browser ke Git/arsip.

## Workflow

1. Inspeksi branch, status Git, struktur, versi runtime, dan perubahan pengguna.
2. Kerjakan satu milestone aktif beserta prasyarat yang sudah selesai.
3. Gunakan branch codex/mNN-topik; jangan force-push, reset perubahan pengguna, atau membuat repo Git bersarang.
4. Tulis implementasi paling sederhana yang memenuhi penerimaan. Domain action/service untuk transaksi; hindari microservices, Kubernetes, CQRS menyeluruh, dan framework ganda.
5. Uji perilaku penting sesuai docs/TESTING.md. Jangan mengklaim tes/deploy berhasil tanpa menjalankannya. `node infra/scripts/check-repo.mjs` dijalankan untuk setiap perubahan.
6. Perbarui docs/PROGRESS.md dengan file, perintah, hasil, bukti, dan blocker. Tidak ada checkbox selesai hanya karena kode telah dibuat.
7. Perbarui kontrak API/OpenAPI dan ERD bila implementasi berubah.
8. Berikan ringkasan akhir: hasil, validasi, keterbatasan, dan milestone berikutnya.

## Batas keselamatan perubahan

- Tidak commit .env, credential, token, data warga asli, dump database, APK signing key, atau draf browser.
- Jangan menjalankan migrate:fresh, db:wipe, purge queue, flushall Redis, atau hapus volume pada production.
- Jangan mereset Neon production atau memakai production untuk seed/factory/tes.
- Jangan mengubah DNS, service lain di VPS, port 80/443, firewall, atau deployment production tanpa ruang lingkup yang telah diotorisasi.
- Kerjakan konfigurasi, build, pengujian lokal/staging, dan runbook terlebih dahulu. Jika akses atau tindakan production belum diotorisasi, tandai langkah itu blocked sambil menyelesaikan pekerjaan lain.
- Jangan mengganti Laravel/Flutter/PostgreSQL diam-diam atau menambah layanan berbayar.
- Kegagalan email/push tidak boleh menghapus transaksi bisnis yang sudah berhasil.
- Jangan menyimpan identitas anonim pada payload warga, log, filename media, notifikasi publik, atau URL.

## Cara menghadapi ketidakjelasan

Keputusan bisnis yang sudah ada di BUSINESS_RULES.md bersifat mengikat. Keputusan teknis rancangan v1 ada di DECISIONS.md. Gunakan default terbatas yang tertulis; jangan menambah fitur sendiri. Credential, endpoint infra, signing key, dan nomor kontak nyata tidak boleh ditebak.

Instruksi langsung terbaru pemilik mendahului paket ini. Catat perubahan agar agent berikutnya membaca keputusan yang sama.

## Definition of done universal

Kode dan kontrak konsisten, tes relevan lulus, akses ditolak dengan benar, tidak ada secrets, dokumentasi/progress diperbarui, dan perilaku milestone dapat diperagakan. Mock adapter atau build lokal tidak membuktikan integrasi Neon maupun deployment live.
