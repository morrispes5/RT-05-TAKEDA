# Roadmap eksekusi — M00 sampai M20

## Prioritas aktif — 27 Oktober 2026 (ADR45)

Pemilik mendahulukan **M19 tombol panik sebelum M16**: M17 → M18 → **M19** → M16 → M20.
M18 passed staging sintetis/emulator; M19 saat ini perencanaan, belum implementasi atau uji alarm.
Rencana rinci dari ketentuan Claude: [M19_PLAN.md](M19_PLAN.md). Gate production M16 tetap berlaku.

## Penyesuaian target deployment — 15 Oktober 2026

Nomor M00–M16 dan kelulusan VPS tetap. Tambahan M03/M04 `in_progress`: profil shared hosting; live `blocked`. Selesaikan gate kompatibilitas berikut sebelum integrasi M10, bukan mengulang fitur dari M01. Rincian implementasi dan acceptance: [SHARED_HOSTING.md](SHARED_HOSTING.md).

| Milestone | Penyesuaian profil shared |
| --- | --- |
| M00 | Desain tetap |
| M01 | Konteks, ADR23, contoh config dan runbook |
| M02 | Neon TLS verify-full, pooled runtime DML/direct migration terpisah |
| M03 | Queue database, kerja setelah response, cron jaring 15 menit, file lock/cache; AOF hanya VPS |
| M04 | PoC staging shared: PHP, outbound, private storage, routing, cron ≤15 menit, response selesai duluan |
| M05 | Base URL per flavor; HTTPS perangkat |
| M06 | Reset Resend, token/sesi/CSRF, rate limit file diuji ulang |
| M07 | Regresi izin/transaksi; aturan pendataan tetap |
| M08 | Batas foto lebih ringan, transkode setelah response, kuota harian, retry; alert disk sebelum live |
| M09 | Aspirasi tetap; regresi inbox melalui queue baru; PR24 independen |
| M10 | FCM nyata, fanout terbatas, reminder catch-up/dedup; inbox ≤60 detik diuji |
| M11 | Generator idempotent dan catch-up; snapshot tarif tetap |
| M12 | Keuangan atomik; ekspor kecil boleh stream, ekspor besar tetap job privat FR19 |
| M13 | Media privat/published terpisah; cache invalidation |
| M14 | Dua aplikasi pada satu origin, reset routes, cookie/CSRF, public fallback |
| M15 | UAT dan beban pada shared; cold start/Neon CU-hours, Android nyata; latihan pindah/kembali ke PostgreSQL cadangan (ADR29) |
| M16 | Domain RT, rotasi credential, offsite backup/restore, rollback dan handover; PostgreSQL cadangan aktif-pasif + pemantau kuota Neon (ADR29) |


M00 adalah frontend yang sudah diterima. Ada 16 milestone lanjutan menuju rilis penuh. Milestone adalah unit hasil yang dapat diperiksa, bukan perkiraan durasi atau batas jumlah developer.

Setiap milestone: baca sumber →implementasi →tes →bukti →PROGRESS. Jangan menjalankan seluruh roadmap dalam satu prompt. Pekerjaan independent dapat paralel setelah dependency/kontrak tersedia.

## Peta

| ID | Hasil | Dependency |
| --- | --- | --- |
| M00 | Baseline frontend diterima | Sudah ada |
| M01 | Monorepo dan konteks siap | M00 |
| M02 | Laravel API, kontrak dan Neon dev siap | M01 |
| M03 | Redis, worker, scheduler, outbox pulih | M02 |
| M04 | Staging fondasi di Hostinger | M03 |
| M05 | Flutter foundation terhubung API | M02; validasi remote M04 |
| M06 | Autentikasi dan registrasi utama | M03, M05 |
| M07 | Administrasi rumah/keluarga/akun lengkap | M06 |
| M08 | Pengaduan end-to-end dan media | M07 |
| M09 | Aspirasi end-to-end | M08 |
| M10 | Agenda, pengumuman, inbox dan push | M07, M03; laporan M08/M09 |
| M11 | Tarif, tagihan, tunggakan | M07 |
| M12 | Pembayaran offline, kas, koreksi, ekspor | M11, M03 |
| M13 | Backend konten publik dan pengelolaan mobile | M08 media, M06 auth |
| M14 | Website CMS/publikasi memakai API | M13 |
| M15 | UAT, keamanan, integrasi, APK kandidat | Semua fitur M06–M14 |
| M16 | Production, domain, backup, monitoring, handover | M15, M04 |

Status awal seluruh M01–M16 planned. Akses server/credential dapat memblokir bagian deployment, tetapi bukan alasan mengabaikan kode/config/test lokal yang sudah dapat dikerjakan.

## M00 — Baseline frontend

**Status:** desain diterima berdasarkan handoff repository, bukan hasil pembangunan paket ini.

**Input:** repo RT-05-TAKEDA, handoff 10 Oktober 2026, preview live.

**Yang dijaga:** source Blade/CSS/JS/data/media, lockfiles, preview output, rute, font/logo/foto asli, desain accepted dan dokumentasi QA historis.

**Bukti:** FRONTEND.md mencatat commit desain/release/tree yang terinspeksi. Agent M01 memeriksa HEAD aktual dan build ulang; jangan mengganti angka test historis menjadi klaim tes baru.

## M01 — Monorepo foundation

**Baca:** AGENTS, MONOREPO, FRONTEND, PRD.

**Scope:**

- Pindah frontend ke website/ dalam repo yang sama.
- docs lintas modul pada docs/; dokumen frontend pada docs/website/.
- Root README/AGENTS/.github; placeholder mobile/rest-api/infra.
- Adaptasi commands/CI paths dan rencana root Vercel.
- Gabungkan dokumen, jangan overwrite perubahan baru.

**Output:** branch migrasi, struktur normal tanpa nested .git, build web tetap valid, dokumen/prompt dipasang.

**Gate:** routes/desain unchanged; preview export sesuai source; test web relevan lulus; workflow valid; credential tidak ikut; PROGRESS memuat commit baseline. Konfigurasi external Vercel yang belum diubah dicatat terpisah.

**Tidak termasuk:** membuat mobile/API lengkap, migration production, redesign.

## M02 — API dan Neon foundation

**Baca:** ARCHITECTURE, DATABASE, ERD, API, SECURITY.

**Scope:**

- Scaffold Laravel 13 rest-api dengan runtime PHP kompatibel.
- FormRequest/Resource/error/request-ID/policy conventions.
- Health endpoints, API v1 route group, config env.
- Postgres lokal/CI dan koneksi Neon dev yang disediakan.
- Mapping pooled runtime/direct migrations/SSL dan minimal roles.
- docs/openapi.yaml foundation dan contracts planned per domain.
- Schema foundation audit/outbox/idempotency/failed jobs/settings sesuai kebutuhan.

**Output:** API runnable; test Postgres; koneksi Neon nyata bila credential tersedia; contoh API dan error konsisten.

**Gate:** migration local serta Neon dev direct sukses; runtime pooled read/write synthetic terverifikasi; SSL; public client tidak menerima DSN; health/error schemas diuji. Jika credential belum tersedia, local foundation selesai tetapi gate Neon tetap blocked, bukan passed.

**Tidak termasuk:** seluruh tabel domain langsung dibuat, mengganti ORM/stack.

## M03 — Redis, queue, scheduler, outbox

**Baca:** ASYNC_JOBS, ARCHITECTURE, TESTING.

**Scope:**

- Redis queue/cache connections dan prefix environment.
- Worker default/media/exports image/commands; scheduler.
- Outbox dispatcher dengan leases/completion/replay dan consumer idempotent.
- Notification inbox infrastructure generic; provider adapters fake eksplisit local.
- Heartbeats/failed jobs/log aman; config after_commit/timeouts/retry.
- Compose lokal dan CLI smoke event sintetis.

**Output:** side effect nyata diproses worker, dapat recover dari restart.

**Gate:** duplicate event satu inbox; kill/restart; stale dispatched replay; AOF restart; queue-loss recovery; DB rollback tidak meninggalkan event; no public Redis. Domain cron belum dijalankan sebelum schema domain tersedia.

**Tidak termasuk:** saldo ditulis queue, provider credential ditebak.

## M04 — Staging awal di Hostinger

**Baca:** DEPLOYMENT, SECURITY, FRONTEND.

**Scope:**

- Inspeksi server/proxy/services/kapasitas dengan akses yang diotorisasi.
- Build API/web/worker images immutable, Compose staging, env/volumes/health.
- Neon staging terisolasi, Redis terisolasi.
- Host staging/DNS/TLS bila otorisasi dan input tersedia.
- Deploy baseline web dan foundation API; synthetic smoke.
- Dokumentasi actual runtime, ports, versions, headroom, dan rollback.

**Output:** fondasi bekerja pada server target, atau seluruh config/build siap dengan blocker akses eksplisit.

**Gate live:** HTTPS, API routing, web routes, Neon read/write, worker/scheduler heartbeat, restart persistence, no collateral service disruption, smoke dari luar VPS.

**Tidak termasuk:** memasukkan data warga nyata, mengalihkan domain production.

## M05 — Flutter foundation

**Baca:** MOBILE, API, SECURITY.

**Scope:**

- Scaffold/pin Flutter stable, Android setup, package identity.
- Theme brand, router, Riverpod, HTTP/DTO/error, secure storage.
- Dev/staging/prod flavor; health smoke API.
- Reusable forms/loading/empty/error; fake fixtures test-only.
- CI analyze/unit/widget dan debug APK.

**Output:** aplikasi Android installable dan terhubung API staging/local.

**Gate:** HP/emulator request nyata; tidak ada DSN/secrets; fake mode tidak ada production; HTTPS validation; responsive/text scaling dasar; analyze/test lulus.

**Tidak termasuk:** menyatakan login/layanan selesai sebelum implementasi.

## M06 — Auth, token, registrasi utama

**Baca:** BUSINESS_RULES identitas, API, DATABASE, SECURITY, MOBILE.

**Scope:**

- Users/Sanctum/password reset/invites dan schema rumah-keluarga minimum yang diperlukan registration transaction.
- Registrasi akun utama dengan normalized address/collision handling.
- Token 6 digit leading-zero, view/copy admin, manual/mingguan rotation.
- Mobile login/register/logout/profile/security storage.
- Session+CSRF web CMS auth API; operator bootstrap admin audited.
- Forgot/reset via queue+SMTP adapter dan revoke.

**Output:** warga utama dapat register/login pada API+Flutter; admin account initial tersedia lewat command aman.

**Gate:** token/duplication/role/status/rate-limit/CSRF tests; data keluarga atomik; mobile session expiry; reset path dengan provider staging nyata jika tersedia. Provider missing →reset external test blocked, bukan pura-pura terkirim.

**Tidak termasuk:** auto-admin dari token, default admin password Git, entire household dashboard.

## M07 — Rumah, keluarga, anggota, akun tambahan

**Baca:** BUSINESS_RULES rumah, ERD, API, MOBILE.

**Scope:**

- Complete occupancies/memberships/account links/time ranges.
- Data keluarga own read/edit akun utama; warga biasa tidak melihat keluarga lain.
- Admin CRUD/verify/archive/inactive rumah/keluarga/warga.
- Registrasi tambahan pending, review approve/reject, akses terbatas.
- Rumah kontrakan/perpindahan/primary transaction.
- Mobile warga dan admin screens.

**Output:** pendataan terpusat dan link identity valid.

**Gate:** dua primary concurrent ditolak; satu house banyak akun tidak menggandakan unit tagihan; move/archive history preserved; IDOR denied; pending cannot read resident feed.

**Tidak termasuk:** NIK/KTP wajib, hard-delete finansial, auto-waiver rumah kosong.

## M08 — Pengaduan dan media

**Baca:** PRD FR07/08/21, API, SECURITY, ASYNC_JOBS.

**Scope:**

- Complaint/history/actions/policies/versions.
- Upload private, server validation/transcoding/metadata removal/media states.
- Flutter feed/detail/create/edit submitted/foto/anonim/status admin.
- Outbox status event dan inbox notification.
- Safe feed serializer tanpa identitas anonim.

**Output:** create warga→foto diproses→admin update→histori/inbox terlihat.

**Gate:** kamera/galeri permission; spoof/oversized denied; anonymous no leak; owner boundary; stale status 409; archive preserved; worker retry safe.

**Tidak termasuk:** moderasi AI, public internet complaint feed.

## M09 — Aspirasi

**Baca:** PRD FR09, BUSINESS_RULES, API, MOBILE.

**Scope:** modul aspirasi terpisah; submitted/reviewed/completed/reopen, privacy, histori, notifikasi, mobile warga/admin. Reuse komponen/media/service nyata tanpa menyatukan tabel/semantik pengaduan.

**Output:** kritik/saran/usulan dapat dikirim dan ditindaklanjuti.

**Gate:** confirmation nyata setelah commit, valid transisi, identity masking, own edit, notification dedup, filtering/pagination.

**Tidak termasuk:** voting/forum/comment baru.

## M10 — Agenda, pengumuman, push

**Baca:** PRD FR10/11/20, ASYNC_JOBS, MOBILE, SECURITY.

**Scope:**

- Agenda/pengumuman published/archive dan admin forms.
- Kalender mobile/date/timezone/add-to-Google-calendar link/ICS.
- Notifications inbox/read/preferences/device tokens.
- FCM Android adapter dan SMTP integration nyata pada staging.
- Fanout chunk, reminder H-1, policy/update-app notification type.
- Usulan kegiatan warga (BR23): pengajuan, keputusan pengurus, dan salinan sekali ke agenda. Bukan syarat penutupan M10; FCM tetap terpisah.

**Output:** komunitas dan notifikasi berjalan tanpa menjadikan push sumber data final.

**Gate:** HP foreground/background/terminated; permission denial; stale/unauthorized deep links safe; timezone; reminder dedup/changed agenda/downtime; provider gagal tidak menghapus event.

**Tidak termasuk:** Google OAuth full calendar sync, WhatsApp otomatis.

## M11 — Tarif, charge, tunggakan

**Baca:** BUSINESS_RULES iuran, DATABASE, API, TESTING.

**Scope:**

- Rates/effective periods, house billing start/enabled.
- Generate/catch-up charges dengan snapshot.
- API own/transparency/admin monthly recap.
- Mobile status/histori dan tarif admin.
- Schedule monthly generation dan reminder-slot keys.

**Output:** daftar iuran per rumah/periode benar, unpaid sampai dicatat.

**Gate:** generator rerun/concurrent unique; tarif lama stable; tidak menagih sebelum start; 12 bulan history; kode rumah transparency tanpa PII; warna+label.

**Tidak termasuk:** mencatat uang nyata sebelum M12, mengubah charge lama dari tarif baru.

## M12 — Pembayaran offline, kas, reversal, export

**Baca:** BUSINESS_RULES keuangan, DATABASE, API, TESTING, SECURITY.

**Scope:**

- Sync multi-period payment transaction dan Idempotency-Key.
- Allocations, cash entries, nondues income/expense/opening balance.
- Atomic reverse/correct + immutable audit.
- Mobile admin record/confirm/history/reverse; warga own history/transparency/cash.
- CSV export worker private/expired; iuran reminder tanggal 5/20.
- Reconciliation command charge-payment-allocation-ledger.

**Output:** pembayaran offline dicatat tepat satu kali dan kas konsisten.

**Gate:** seluruh FIN critical tests, independent concurrency, timeout replay, partial/overpay denied, queue/provider failure after commit, reversal once, CSV safe/access boundary.

**Tidak termasuk:** gateway/checkout/QRIS/webhook/dompet, balance edits tanpa ledger.

## M13 — Konten publik backend dan mobile admin

**Baca:** FRONTEND, ERD, API, SECURITY.

**Scope:**

- Artikel/album/fasilitas/profil API, draft/published snapshots, media references.
- Publish/archive/version/slug redirect; public resource published-only.
- Import katalog JSON+foto baseline idempotent tanpa mengarang facts.
- Mobile admin mengelola konten publik dan fasilitas.
- Public cache invalidation outbox.

**Output:** backend dapat menyediakan konten publik actual dari database.

**Gate:** duplicate import safe; draft tidak bocor; publish ready media; concurrent edit 409; snapshot stable; slug redirects; facts accepted retained.

**Tidak termasuk:** redesign website atau otomatis publish draf browser.

## M14 — Website runtime dan CMS real

**Baca:** FRONTEND, ARCHITECTURE, API, DEPLOYMENT.

**Scope:**

- HTTP ContentRepository untuk editor; demo IndexedDB terpisah jelas.
- Login CMS, session/CSRF, server drafts/media/publish/archive.
- SiteContent read-only internal API/published snapshots/cache fallback.
- Same-origin routing pada staging; public pages tetap tanpa akun.
- Browser-to-browser publication dan initial content import.

**Output:** pengurus publish tanpa commit source; pengunjung lain melihat perubahan.

**Gate:** auth/CSRF/role deny; draft isolation; live server upload/publish; API outage fallback safe; seluruh baseline navigation/design tests tetap lulus.

**Tidak termasuk:** memperluas CMS web ke iuran/pendataan atau mengganti approved UI.

## M15 — Integrasi, UAT, keamanan, kandidat rilis

**Baca:** TESTING, SECURITY, DEPLOYMENT, PRD.

**Scope:**

- Full E2E warga/admin/public web dan finance concurrency.
- Android HP nyata, push/camera/permissions/slow network.
- UAT mitra dengan jumlah/hasil nyata.
- Security/anonim/media/CSV/secrets/dependency review.
- Performance target warm/cold, headroom VPS, query pagination.
- APK signed candidate; privacy notice, facts/data import plan.
- Accessibility manual termasuk slideshow; perubahan desain dicatat bila perlu.

**Output:** release candidate dan acceptance report; bug resolved/prioritized.

**Gate:** semua alur kritis lulus, tidak ada high/critical blocker, target UAT tercatat, provider nyata bekerja, signed APK terpasang. Tidak mengarang PASS atau survey.

**Tidak termasuk:** auto production rollout sebelum gate operasi.

## M16 — Production dan handover

**Baca:** [M16_PLAN.md](M16_PLAN.md) (rencana rilis ke warga, 22 Okt 2026), DEPLOYMENT, SECURITY, TESTING, PROGRESS.

M16 adalah milestone terakhir (keputusan pemilik 22 Okt 2026); setelah passed, perubahan berikutnya dicatat sebagai revisi.
Tambahan scope: website ditemukan di Google (sitemap, data terstruktur, Search Console) dan kepercayaan APK (halaman unduh
resmi, versi 1.0.0, verifikasi developer Android).

**Scope:**

- Production env/Neon roles/Redis/volumes/images dan budget alert.
- Domain takeda.morriz.tech, HTTPS, routing, trusted proxy.
- Backup terenkripsi offsite dan restore drill; rollback drill.
- Seed/import data real hanya yang tervalidasi; bootstrap admin aman.
- Deploy migration additive→API/web→workers/scheduler.
- APK final/download/version metadata/checksum/release notes.
- Monitoring/alerts, SOP pengurus, operasi dan perawatan.

**Output:** sistem live, APK warga, runbook, backup yang terbukti, handover.

**Dipindah dari M15 (ADR36, 22 Okt 2026):** verifikasi developer Android, kontak dan masa simpan Kebijakan Privasi, latihan
PostgreSQL cadangan (ADR29), uji hosting production Biznet (profil shared, preflight SHARED_HOSTING), TalkBack manual,
pemulihan otomatis foto/push, issue #47.

**Gate:** DNS/TLS dari luar, real env service health, auth/resource policy, safe smoke, worker/scheduler/outbox, reconciliation, restore target, rollback, privacy/support contact nyata, credentials tidak di source.

**Otorisasi:** perubahan server/DNS/production harus berada dalam instruksi pemilik yang berlaku. Tanpa akses, seluruh artifact/runbook/build tetap dibuat dan status live blocked; jangan mengklaim deploy.

## M17 — Penyesuaian hasil rapat RT 24 Oktober 2026 (ADR43)

**Scope:** kontak resmi Ketua RT di website/Kebijakan Privasi; bendahara Mas Wirawan di profil pengurus + nomor WhatsApp bendahara
di pengaturan; **super admin** `takedatamankedaung@gmail.com` dapat mengangkat/menurunkan pengurus dari aplikasi (diaudit,
pengurus terakhir dilindungi); pilihan jalan Mawar 8–14 + blok/nomor baku saat daftar dan input pengurus; masa simpan data
12 bulan di Kebijakan Privasi (versi baru); verifikasi alur kepala keluarga meninggal dan akun anggota keluarga otomatis.
Kumpulan perbaikan kecil APK (umur di daftar keluarga warga).

**Gate:** tes backend/mobile/web lulus; super admin tidak bisa dipakai pengurus biasa; tidak ada data/kontak dikarang.

## M18 — Keuangan fleksibel (kritis, ADR43 butir 6)

**Scope:** tarif khusus per rumah; nominal bayar bebas dengan pilihan saat persetujuan (lunas dengan keringanan / kurang bayar,
sumbangan / titipan); bukti transfer bernominal → persetujuan bendahara dengan nominal terkoreksi → pembayaran + kas; kas =
uang yang benar-benar diterima; rekonsiliasi diperluas; saldo kas + ringkasan bulanan untuk warga; laporan tahunan; Excel
`.xlsx` rapi (per bulan, rekap tahun, rumah × bulan).

**Gate:** skenario rapat lulus tes otomatis (contoh: 8 rumah × 75 rb + 2 rumah × 50 rb = kas +700 rb, bukan 750 rb; dua rumah
75 rb + 25 rb; bayar lebih); rekonsiliasi 0 masalah; tidak ada edit diam-diam (koreksi = pembalikan); uji bendahara di staging.

## M19 — Tombol panik (uji coba, ADR43 butir 8)

**Prioritas:** berikutnya setelah M18, sebelum M16 (ADR45). **Rencana:** [M19_PLAN.md](M19_PLAN.md).

**Scope:** shortcut Android; aktivasi 3 langkah; kirim ke semua warga + pengurus dengan identitas pelapor, rumah, jenis
darurat; notifikasi bergaya alarm (Android); pengurus menandai selesai/palsu; peringatan + nonaktif setelah 3 penyalahgunaan;
fitur dapat dimatikan pengurus.

**Gate:** uji HP nyata (bunyi di layar terkunci, aplikasi tertutup); batas laju; audit; teks peringatan bukan pengganti 112.

## M20 — Dukungan iPhone lewat PWA (ADR43 butir 9)

**Scope:** versi web aplikasi warga/pengurus yang dapat dipasang di layar utama iPhone (Safari); login, iuran, layanan,
notifikasi web (iOS 16.4+ setelah dipasang); tanpa Apple Developer berbayar.

**Gate:** diuji di iPhone nyata (milik tim/warga); keterbatasan (alarm panik, kamera) didokumentasikan.

## Menutup milestone

Status hanya: planned, in_progress, passed, blocked. Partial completion ditulis per task; jangan menandai milestone passed jika gate utama blocked. Setiap hasil akhir menyebutkan milestone berikutnya, bukan otomatis melanjutkan semua milestone tanpa permintaan.

Jika requirement berubah, revisi FR/BR/ADR/kontrak sebelum mengubah domain lain. Tidak perlu membagi nama developer pada PRD; assign issue sesuai tim aktual.
