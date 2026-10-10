# Roadmap eksekusi — M00 sampai M16

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

**Baca:** DEPLOYMENT, SECURITY, TESTING, PROGRESS.

**Scope:**

- Production env/Neon roles/Redis/volumes/images dan budget alert.
- Domain takeda.morriz.tech, HTTPS, routing, trusted proxy.
- Backup terenkripsi offsite dan restore drill; rollback drill.
- Seed/import data real hanya yang tervalidasi; bootstrap admin aman.
- Deploy migration additive→API/web→workers/scheduler.
- APK final/download/version metadata/checksum/release notes.
- Monitoring/alerts, SOP pengurus, operasi dan perawatan.

**Output:** sistem live, APK warga, runbook, backup yang terbukti, handover.

**Gate:** DNS/TLS dari luar, real env service health, auth/resource policy, safe smoke, worker/scheduler/outbox, reconciliation, restore target, rollback, privacy/support contact nyata, credentials tidak di source.

**Otorisasi:** perubahan server/DNS/production harus berada dalam instruksi pemilik yang berlaku. Tanpa akses, seluruh artifact/runbook/build tetap dibuat dan status live blocked; jangan mengklaim deploy.

## Menutup milestone

Status hanya: planned, in_progress, passed, blocked. Partial completion ditulis per task; jangan menandai milestone passed jika gate utama blocked. Setiap hasil akhir menyebutkan milestone berikutnya, bukan otomatis melanjutkan semua milestone tanpa permintaan.

Jika requirement berubah, revisi FR/BR/ADR/kontrak sebelum mengubah domain lain. Tidak perlu membagi nama developer pada PRD; assign issue sesuai tim aktual.
