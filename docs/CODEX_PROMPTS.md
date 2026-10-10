# Prompt eksekusi Codex — RT05 TAKEDA

Salin prompt yang sesuai ke Codex pada root checkout repo. Paket ini tidak memerintahkan perubahan GitHub/server dari chat perancangan; instruksi berikut berlaku saat pengguna menjalankannya di workspace Codex dengan scope yang ditetapkan.

## Prompt pertama — M01

```text
Kamu senior software engineer untuk RT05 TAKEDA. Kerjakan M01 saja pada repository lokal ini.

Baca AGENTS.md yang sudah ada, README.md, docs/PRD.md, docs/BUSINESS_RULES.md,
docs/ARCHITECTURE.md, docs/MONOREPO.md, docs/FRONTEND.md,
docs/ROADMAP.md, docs/DECISIONS.md, dan docs/PROGRESS.md.
Baca juga handoff frontend existing sebelum memindahkannya.

Produk mempunyai website publik yang sudah diterima, mobile Flutter warga/pengurus,
Laravel REST API, database PostgreSQL Neon, Redis, workers/scheduler, dan target
VPS Hostinger. Domain yang benar morriz.tech; host rancangan takeda.morriz.tech.
Iuran Rp75.000 per rumah per bulan, dibayar offline dan dicatat pengurus.
Tidak ada payment gateway. Jumlah developer tidak menentukan pembagian modul.

Inspeksi Git status/branch/remote/HEAD, sumber frontend, workflow, config Vercel,
runtime dan perubahan pengguna. Buat branch codex/m01-monorepo-foundation.
Pertahankan repository dan Git history yang sama. Pindahkan frontend lengkap
ke website/ tanpa .git bersarang dan tanpa membuang shell Laravel.
Letakkan konteks lintas modul di docs/ dan dokumentasi frontend di docs/website/.
Gabungkan README/AGENTS existing; jangan menimpa catatan terbaru.
Adaptasi workflow pada .github root dan paths build/test/artifacts.
Buat placeholder mobile/, rest-api/, infra/ untuk tahap berikutnya.
Rute publik tetap /profil dan seterusnya, bukan /website/profil.
Pertahankan desain accepted, foto, font, logo, dan perilaku frontend.

Jalankan validasi baseline web yang relevan dari website/, periksa konsistensi
hasil ekspor, relative links, dan tidak ada secrets. Jangan menjalankan migration
production atau mengubah DNS/VPS/integrasi Vercel external pada M01.
Siapkan instruksi perubahan Vercel root bila diperlukan.

Selesaikan scope lokal M01, perbarui PROGRESS.md dengan hasil nyata, command,
gate yang lulus/blocker, dan file berubah. Akhiri dengan ringkasan serta status
kesiapan M02. Jangan membangun seluruh roadmap dalam satu turn.
```

## Instruksi umum untuk semua prompt berikut

Baca AGENTS/PROGRESS/ROADMAP dan dokumen domain terkait. Verifikasi dependency aktual, jangan menganggap passed dari narasi. Implementasikan scope sampai gate teruji. Update OpenAPI/ERD/progress bila berubah. Hindari perubahan di luar milestone. Jika akses/credential kurang, selesaikan config/local tests dahulu dan catat gate external blocked tanpa mengarang hasil.

## M02 — Laravel API / Neon

```text
Kerjakan M02 RT05 TAKEDA. Buat foundation Laravel REST API di rest-api/
dengan Postgres/Eloquent, resource/error/policy conventions, health, env mapping,
dan OpenAPI v1. Ikuti DATABASE.md: pooled runtime, direct migrations, TLS,
dev/staging/prod terisolasi. Gunakan credential/project Neon yang sudah
disediakan; jangan membuat layanan berbayar atau project kedua diam-diam.
Buat hanya schema foundation yang diperlukan. Uji local Postgres dan Neon dev
actual jika credential tersedia. Tidak memakai SQLite/Drizzle/Prisma untuk bisnis.
```

## M03 — Redis / worker / outbox

```text
Kerjakan M03. Implementasikan Redis queue/cache terpisah, worker default/media/
exports, scheduler, durable outbox dengan lease/completion/replay, idempotent
consumer, heartbeat dan failed-job handling. Ikuti ASYNC_JOBS.md.
Uji duplicate event, kill/restart, stale dispatched, Redis restart/queue-loss,
dan rollback DB. Gunakan synthetic CLI smoke, bukan public mutation endpoint.
Keuangan tidak diposting melalui queue.
```

## M04 — Hostinger staging

```text
Kerjakan M04. Siapkan dan validasi Docker images/Compose staging, routing
website/API, isolated Neon/Redis/storage dan health. Dengan akses yang sudah
diotorisasi, inspeksi VPS Hostinger dan reuse reverse proxy/panel existing.
Jangan mengganggu service lain atau membuka Redis. Host staging rancangan
takeda-staging.morriz.tech. Terapkan perubahan server/DNS hanya jika scope
tersebut sudah diotorisasi. Buktikan HTTPS, worker job nyata, persistence,
Neon staging dan public routes. Jika akses kurang, kirim build/config/runbook
siap pakai dan status gate live blocked.
```

## M05 — Flutter foundation

```text
Kerjakan M05. Scaffold satu Flutter Android application di mobile/, pin
toolchain/plugins kompatibel, theme brand, Riverpod/router, DTO/HTTP/errors,
secure token storage dan flavors. Hubungkan health API actual. Fake adapters
hanya test/dev eksplisit, tidak fallback production. Buat APK debug dan uji
install serta request di emulator/HP. Ikuti MOBILE.md.
```

## M06 — Auth / registrasi / token

```text
Kerjakan M06 API+Flutter. Implementasikan register akun utama, schema household
minimum untuk transaksi registrasi, Sanctum mobile token, CMS session/CSRF,
login/logout/reset password, status enforcement, rate limit, invite 6 digit
dan manual/mingguan rotation. Token registrasi bukan login bearer atau hak admin.
Bootstrap admin lewat command audited tanpa credential default di Git.
Uji collision/leading-zero/revoked token/role tampering/CSRF/reset/revocation.
```

## M07 — Pendataan dan akun tambahan

```text
Kerjakan M07. Lengkapi houses/families/residents/memberships/occupancies/
account links, admin CRUD/verify/archive, own household edit akun utama,
registrasi tambahan pending dan review approve/reject. Implementasikan
layar Flutter terkait. Uji primary concurrency, IDOR, pending restrictions,
perpindahan/kontrakan/histori. Tidak membuat tagihan per akun atau meminta NIK wajib.
```

## M08 — Pengaduan / media

```text
Kerjakan M08 end-to-end. Pengaduan dan status history, ownership/version,
private upload processing server, camera/gallery mobile, anonymized resident
serializer, admin status action, outbox/inbox. Uji identity leaks di semua
resource/media/notifikasi, spoof/oversized file, concurrent stale status,
owner edit boundary dan worker retry. Anonimitas tidak menyembunyikan pelapor
dari admin.
```

## M09 — Aspirasi

```text
Kerjakan M09. Bangun aspirasi sebagai domain terpisah dari pengaduan, state/
histori/privasi/outbox dan Flutter warga/admin. Reuse komponen yang tepat,
jangan menyatukan semantik atau mengarang voting/forum. Uji confirmation
after commit, status transitions, identity masking, policy dan notification dedup.
```

## M10 — Komunitas / notifikasi

```text
Kerjakan M10. Agenda/kalender/pengumuman, admin forms, add-to-Google-calendar
via link/ICS, inbox/preferences/device tokens, FCM Android dan reminder
dedup. Uji timezone, changed agenda/downtime, HP foreground/background/
terminated dan permission denied. Provider nyata pada staging wajib dibuktikan;
tanpa credential tandai integrasi external blocked. Push bukan sumber data final.
```

## M11 — Tarif / tagihan

```text
Kerjakan M11. Rate efektif per bulan, snapshot charge house-period unique,
billing start, idempotent/catch-up generator, tunggakan, own/transparency/
admin recap dan Flutter. Default Rp75.000/rumah/bulan. Uji tarif berubah
tidak mengubah tagihan lama, rerun/concurrent generator, banyak akun satu rumah,
histori 12 bulan dan minimisasi PII. Belum membuat payment gateway.
```

## M12 — Payment offline / kas

```text
Kerjakan M12 dan semua gate finance di TESTING.md. Catat pembayaran offline
multi-bulan sinkron dalam Postgres transaction, Idempotency-Key, row locks,
allocations/cash/audit/outbox. Tambahkan kas nondues/opening, atomic reverse/
correct immutable, CSV exports private dan Flutter admin/warga.
Uji dua admin concurrent, timeout replay, failure sebelum/after commit,
queue/provider down, partial/overpay reject, reversal once, saldo reconciliation.
Tidak menambahkan checkout, QRIS/gateway, dompet atau saldo diedit manual.
```

## M13 — Konten backend

```text
Kerjakan M13. API artikel/album/fasilitas/profil dengan draft/published snapshot,
version, public-only serializers, ready media, publish/archive/redirect slug,
cache invalidation. Import katalog JSON/foto existing idempotent dan pertahankan
facts accepted. Buat mobile admin content management. Uji draft isolation,
import ulang, concurrent edit dan publication. Jangan mengarang kontak/tahun.
```

## M14 — Website real CMS

```text
Kerjakan M14. Integrasikan approved website dengan API: ContentRepository HTTP,
CMS cookie auth/CSRF, server drafts/media/publish/archive, SiteContent internal
public API dan last-good cache. Demo IndexedDB terpisah; draf lokal tidak
auto-publish. CMS web hanya Artikel/Dokumentasi. Buktikan publish dari browser A
terlihat di browser B, draft privat dan baseline desain/navigation tetap lulus.
```

## M15 — Kandidat rilis / UAT

```text
Kerjakan M15. Full E2E, finance concurrency, Android actual devices, real reset/
push staging, UAT mitra tercatat, security/anonim/media/CSV/secrets, performance
warm/cold, accessibility manual, signed APK candidate dan release report.
Jangan mengarang peserta/hasil PASS. Temuan high/critical data/finance harus
selesai sebelum production. Catat perubahan desain yang memerlukan arahan pemilik.
```

## M16 — Production / operasi

```text
Kerjakan M16 setelah M15 passed. Siapkan production Hostinger, takeda.morriz.tech,
Neon roles/direct migration/pooled runtime, Redis/worker/scheduler, media,
backup offsite terenkripsi, restore/rollback drill, monitoring dan APK release.
Terapkan server/DNS/production hanya dalam otorisasi yang sudah tersedia;
kerjakan build/runbook/local/staging terlebih dahulu.
Tidak menghapus volume atau migrate:fresh production. Validasi dari jaringan luar,
safe smoke, reconciliation, heartbeat, backup/restore, version checksum dan
handover pengurus. Akhiri dengan URL/artifact nyata dan limitations yang terukur.
```

## Melanjutkan sesi

```text
Lanjutkan RT05 TAKEDA dari keadaan repository sekarang. Baca AGENTS.md,
docs/PROGRESS.md, docs/ROADMAP.md dan ADR terbaru, lalu inspeksi Git status.
Kerjakan milestone terakhir yang in_progress atau milestone yang saya sebutkan.
Jangan mengulangi migration/provisioning/deploy yang sudah berhasil tanpa alasan.
Selesaikan task/gate pending, verifikasi actual behavior, dan perbarui progress.
```

## Review milestone

```text
Review milestone MNN yang baru selesai. Cocokkan kode, API/OpenAPI, database,
mobile/web, BR/FR, tests dan bukti progress dengan gate ROADMAP.
Cari mismatch aktual; jangan menilai completed hanya dari checklist.
Perbaiki masalah dalam scope yang reversibel, jalankan validasi relevan,
dan laporkan passed/blocked dengan bukti serta risiko tersisa.
```
