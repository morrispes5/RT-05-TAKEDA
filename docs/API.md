# Kontrak REST API v1

Base path production: https://takeda.morriz.tech/api/v1.
Desain ini digunakan untuk membuat docs/openapi.yaml di M02. Implementasi endpoint dilakukan sesuai milestone; daftar ini bukan klaim endpoint sudah tersedia.

## Konvensi

JSON UTF-8, snake_case, UUID, timestamp ISO-8601 UTC, period YYYY-MM, amount_rupiah integer. List default 20, max 100, pagination dengan meta dan links. Filter/sort allowlist; jangan menerima SQL/field arbitrary.

```json
{
  "data": {"id": "uuid", "status": "submitted"},
  "meta": {"request_id": "uuid"}
}
```

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "Periksa isian.",
    "fields": {"periods": ["Pilih periode yang belum lunas."]},
    "request_id": "uuid"
  }
}
```

200 baca/update; 201 create; 202 job/permohonan async; 204 logout/arsip tanpa body; 401 tidak login; 403 akun/role ditolak; 404 resource tidak tersedia/di luar scope; 409 conflict/version/idempotency; 413 file terlalu besar; 415 tipe tidak didukung; 422 validasi; 429 limiter; 503 dependency sementara gagal. 419 browser CSRF diubah menjadi error JSON CSRF_EXPIRED.

403 bukan respons untuk membocorkan ID keluarga lain yang ada; policy private resource dapat menghasilkan 404. Jangan return stack trace.

## Autentikasi

| Method/path | Akses | Isi |
| --- | --- | --- |
| POST /auth/register | Public terbatas | email/password/password_confirmation/registration_token/house/family |
| POST /auth/additional-register | Public terbatas | email/password/token dan referensi rumah; membuat akun pending |
| POST /auth/mobile-login | Public terbatas | email/password/device_name; bearer token dan expires_at |
| POST /auth/web-login | Public terbatas+CSRF | Session API untuk admin CMS |
| GET /auth/me | Auth | role/status/current link; sanitized |
| POST /auth/logout | Auth+CSRF untuk cookie | Revoke current token atau session |
| POST /auth/logout-all | Auth | Password confirmation; revoke semua token/sesi |
| POST /auth/forgot-password | Public terbatas | Generic response; queued email |
| POST /auth/reset-password | Public terbatas | Reset token/email/password; revoke existing sessions/tokens |
| PATCH /auth/password | Auth | Current password + new; revoke token lain |
| GET /auth/additional-account-status | Pending/auth | Hanya permohonan sendiri |

GET /sanctum/csrf-cookie adalah route API gateway di luar /api/v1. CMS memanggilnya sebelum web-login/mutation dan mengirim X-XSRF-TOKEN. Native mobile hanya bearer; role/policy tetap diperiksa.

M06: CMS membaca cookie `rt05_api_xsrf` untuk header tersebut, terpisah dari
cookie `XSRF-TOKEN` shell website yang memakai kunci berbeda. Cookie session
`rt05_api_session` HttpOnly/Secure/SameSite=Lax, idle120menit. Semua mutation
cookie memerlukan token CSRF, termasuk request same-origin. `akun.versi_sesi`
memastikan pencabutan reset/logout-all/status/role berlaku terhadap browser.
Login web mengembalikan `data` akun tanpa bearer; endpoint/path mobile existing
dan nama field Indonesia ADR17 dipertahankan pada OpenAPI M06.

Email reset diantrekan after_commit. Tautan HTTPS `/reset-password` memakai
fragment token/email (dibaca JS, tidak masuk request/access log) dan menampilkan
kode yang bisa disalin ke Android. Respons forgot202 generik tidak membuktikan
email tiba; delivery nyata tetap gate. Reset berlaku60menit dan satu kali pakai.

Password baru minimal8 karakter Unicode dengan huruf dan angka, maksimal72byte
UTF-8 dan tanpa NUL, sesuai schema NewPassword dan PasswordPolicy sebelum bcrypt.
Konfirmasi password saat logout-all/ganti memakai kecocokan persis, termasuk
batas72byte dan larangan NUL; password lama tidak dipaksa memenuhi aturan
kekuatan password baru. Gangguan antrean/email tidak membatalkan transaksi akun
yang telah berhasil; forgot tetap memberi respons generik.
Penolakan tambahan mencabut token lama. Akun tetap jenis tambahan/status menunggu
dan permohonan ditolak; login ulang hanya membuka profil/status/alasan sendiri
serta logout. Akun nonaktif/diarsipkan tetap tidak dapat login.

Default limiter v1: login 5/menit per IP+normalized email; register 5/10 menit per IP; token failure 10/jam per IP; forgot 3/15 menit per IP+email; reset 10/15 menit per IP+email dengan penghitung terpisah dari forgot. Angka konfigurabel. Perhatikan NAT bersama; jangan membuka enumerasi email.

Kuota kegagalan token memesan slot sebelum verifikasi dan mengembalikannya
bila token benar atau kegagalan disebabkan hal lain. Penghitung dan expiry
diubah di bawah lock pada store limiter bersama; di staging Redis cache
menyimpan penghitung dan Redis queue noeviction menyimpan lock. Lock sibuk
ditolak429; release yang tidak memperoleh lock tetap memakai slot sampai
jendela berakhir. File cache lokal tidak menjadi koordinasi beberapa host.

## Rumah dan keluarga

| Endpoint | Perilaku |
| --- | --- |
| GET /household | Keluarga/rumah dari tautan akun aktif; `dapat_ubah` |
| PATCH /household | Akun utama; `versi` wajib; audit |
| POST/PATCH /household/members | Akun utama mengelola anggota sendiri; `versi` wajib |
| DELETE /household/members/{id} | Tutup keanggotaan + arsip warga, tidak hard-delete histori; `versi` wajib |
| GET/POST /admin/houses, GET/PATCH /admin/houses/{id} | Daftar/kelola rumah; koreksi alamat/tagihan dengan alasan, `rumah.versi`; kode_rumah tetap |
| POST /admin/houses/{id}/archive | Arsip dengan alasan; hunian aktif diakhiri, tagihan/kas tetap |
| GET/POST /admin/families, GET/PATCH /admin/families/{id} | Daftar/tambah/detail histori/ganti kepala (versi) |
| POST /admin/families/{id}/verify, /archive, /members, /primary-account | Verifikasi, arsip (tanpa hunian/akun aktif), tambah anggota, pindah akun utama |
| GET/PATCH /admin/residents/{id}, POST …/end, …/move | Administrasi anggota; pindah keluarga ikut memindah tautan akun sebagai tambahan |
| GET /admin/accounts, GET/PATCH /admin/accounts/{id} | Data/status akun; tidak menerima peran (pengganti rancangan `/admin/users`) |
| POST /admin/accounts/{id}/archive | Arsip final, tutup tautan, revoke bearer+sesi |
| POST /admin/occupancies, /{id}/end, /{id}/move | Mulai/akhiri/pindah hunian atomik; tagihan tetap di rumah |
| GET /admin/additional-account-requests | Review permohonan |
| POST /admin/additional-account-requests/{id}/approve atau /reject | Approve membuat keanggotaan+tautan bukan-utama (opsional `warga_id` existing); reject+alasan |
| GET /admin/registration-token | Admin-only, Cache-Control no-store |
| POST /admin/registration-token/rotate | Manual rotation dan revoke token lama |

Alamat/keluarga tidak searchable oleh anonymous/pending user. Semua update household menerapkan optimistic version.

M07 (kontrak lengkap: docs/evidence/m07/CONTRACT.md): akses keluarga dari `tautan_akun`
aktif. Akun utama ubah; akun tambahan disetujui baca saja (403 `PRIMARY_ACCOUNT_REQUIRED`);
menunggu/ditolak 403 untuk household dan feed; tanpa tautan 404 `HOUSEHOLD_NOT_LINKED`;
anggota keluarga lain 404; versi basi 409 `VERSION_CONFLICT`. Hak aktor diperiksa ulang di domain
setelah keluarga dikunci (bukan dari relasi yang dimuat controller): akun utama yang dicabut
bersamaan 403 `PRIMARY_ACCOUNT_REQUIRED`, akun yang dinonaktifkan/diarsipkan 403 `FORBIDDEN`,
tautan yang berpindah keluarga 409 `CONFLICT`; deadlock/serialization yang lolos 409 `CONFLICT`.
Kode 409 domain: `HOUSE_OCCUPIED`,
`FAMILY_ALREADY_OCCUPYING`, `OCCUPANCY_ENDED`, `HOUSE_ARCHIVED`, `FAMILY_ARCHIVED`, `FAMILY_OCCUPYING`,
`FAMILY_HAS_ACCOUNTS`, `HEAD_OF_FAMILY`, `RESIDENT_HAS_ACCOUNT`, `ACCOUNT_ARCHIVED`, `REQUEST_PENDING`,
`ALREADY_ARCHIVED`, `HOUSE_EXISTS`; 422: `DATE_OVERLAP`, `DATE_IN_FUTURE`, `SAME_HOUSE`, `SAME_FAMILY`,
`ALREADY_PRIMARY`, `ALREADY_HEAD`, `ACCOUNT_NOT_ACTIVE`, `ADMIN_ARCHIVE_OPERATOR_ONLY`, `MEMBER_PROTECTED`.

## Pengaduan dan aspirasi

| Endpoint | Perilaku |
| --- | --- |
| GET/POST /complaints | Feed tersanitasi / create milik actor |
| GET/PATCH /complaints/{id} | Baca role-aware / owner edit saat submitted |
| GET /complaints/{id}/history | Histori yang aman |
| GET/POST /aspirations | Feed / create terpisah |
| GET/PATCH /aspirations/{id} | Baca / owner edit saat submitted |
| GET /aspirations/{id}/history | Histori |
| POST /admin/complaints/{id}/status | status, note, expected_version |
| POST /admin/aspirations/{id}/status | status, note, expected_version |
| POST /admin/complaints/{id}/archive | Alasan+audit |
| POST /admin/aspirations/{id}/archive | Alasan+audit |

Create memakai category allowlist, title ≤150, body ≤5000, anonymous_to_residents boolean, media_ids maksimal 5. Media harus owned by actor, ready, dan purpose benar. submitted create boleh menunggu processing foto bila server membuat attachment placeholder, tetapi foto gagal tidak boleh menjadi URL public.

**Implementasi M08 (ADR21).** Pengaduan dibuat dengan multipart `kategori_id`, `judul`, `deskripsi`,
`sembunyikan_identitas`, dan `foto[]` maks. 5; tidak ada `media_ids` dari client sehingga referensi
media milik akun/parent lain tidak dapat dikirim. Server memeriksa ukuran (VPS >12 MB 422/413; shared >4 MiB 413),
magic bytes JPG/PNG/WebP yang harus sama dengan tipe header gambar (spoof/SVG/HTML/GIF 415) dan
VPS ≤50 MP / shared ≤6 MP (413). Profil shared juga menolak 429 `DAILY_QUOTA` bila akun sudah
membuat 5 pengaduan atau unggahan itu melewati 25 foto pada hari yang sama (WIB). Berkas mentah
privat bernama acak disimpan dengan status `diproses`. Setelah commit, `ProcessComplaintPhoto`
mentranskode ke JPEG ≤1600 px tanpa EXIF/GPS: pada VPS lewat antrean `media`, pada profil shared
di proses yang sama setelah response terkirim. Hasil `siap` atau `gagal`
(`UNREADABLE`, `PROCESSING_FAILED`, `PROCESSING_STALLED`).
Antrean gagal tidak membatalkan laporan; `rt05:media-recover` mengirim ulang. Respons `foto[]`
memuat `status` dan `url` hanya bila `siap`; warga lain hanya menerima foto siap. Unduhan
`/complaints/{id}/photos/{photoId}` menuntut foto milik laporan pada path dan status siap.

Kode domain: pemilik edit `versi` basi 409 `VERSION_CONFLICT`, bukan pemilik/bukan diajukan 403
`COMPLAINT_LOCKED`; admin transisi invalid 422 `INVALID_STATUS_TRANSITION`, buka ulang tanpa
alasan 422 `REOPEN_REASON_REQUIRED`, laporan arsip 409 `ALREADY_ARCHIVED` (status maupun arsip
ulang). Riwayat bagi warga lain menulis pelapor anonim sebagai "Warga", termasuk pelapor pengurus.

**Implementasi M09 (ADR22).** Aspirasi (kritik, saran, usulan) adalah domain terpisah: tabel `aspirasi` dan
`riwayat_aspirasi`, status `diajukan → ditinjau → selesai` (buka ulang `selesai → ditinjau` wajib
catatan). `POST /aspirations` menerima JSON `judul` (≤150), `isi` (≤5000), `sembunyikan_identitas`; tidak ada
kategori dan tidak ada foto. Aspirasi, riwayat awal, audit, dan outbox ditulis dalam satu transaksi, sehingga
201 hanya sesudah commit dan notifikasi pengurus tidak pernah mendahului data. Feed
`GET /aspirations` menerima `status` (`diajukan|ditinjau|selesai`; nilai lain 422), `milik_saya=1`, `page`,
`per_page` (1–100, bawaan 20) dan, hanya untuk pengurus, `arsip=1`; urutan terbaru dahulu. Warga lain
menerima `pengirim: null` untuk aspirasi anonim pada detail, feed, riwayat, dan notifikasi; riwayat
menulis pengirim anonim sebagai "Warga" bahkan bila pengirimnya pengurus. Respons selalu memuat
`archived_at` (terisi hanya untuk pengurus). Kode domain: pemilik edit `versi` basi 409 `VERSION_CONFLICT`,
bukan pemilik/bukan diajukan 403 `ASPIRATION_LOCKED`; admin transisi invalid 422
`INVALID_STATUS_TRANSITION`, buka ulang tanpa alasan 422 `REOPEN_REASON_REQUIRED`, arsip 409
`ALREADY_ARCHIVED`. Filter `status` pada `GET /complaints` kini juga ditolak 422 bila bukan
`diajukan|diproses|selesai`.

## Usulan kegiatan

Requirement lengkap: [USULAN_KEGIATAN.md](USULAN_KEGIATAN.md). Warga aktif mengajukan kegiatan (bukan aspirasi dan bukan agenda). Pengurus meninjau. **Persetujuan langsung menyalin usulan sekali menjadi satu baris `agenda`** dalam transaksi yang sama, dan outbox `agenda.dibuat` memberi tahu semua warga aktif. `GET /agendas` tetap satu-satunya jadwal resmi; usulan tidak ikut di daftar itu. Suntingan agenda kemudian tidak menulis ulang usulan.

| Endpoint | Perilaku |
| --- | --- |
| GET /activity-proposals | Warga: milik sendiri. Pengurus: semua. Filter `status` opsional (nilai lain 422) |
| POST /activity-proposals | Multipart `jenis`, `nama`, `deskripsi`, `lokasi`, `mulai_at`, `selesai_at`, `perkiraan_peserta` opsional, `kebutuhan` opsional, `pernyataan`, `dokumen[]` opsional |
| GET /activity-proposals/{id} | Pengusul atau pengurus; warga lain 404. Pengurus juga menerima `agenda_bentrok` |
| PATCH /activity-proposals/{id} | Pengusul, hanya `diajukan` atau `perlu_revisi`, wajib `versi`. Dari `perlu_revisi` kembali ke `diajukan` dan pengurus diberi tahu |
| GET /activity-proposals/{id}/history | Riwayat keputusan |
| GET /activity-proposals/{id}/documents/{documentId} | Unduhan privat (token) `attachment`, `nosniff`, `no-store` |
| POST /activity-proposals/{id}/documents/{documentId}/link | Tautan relatif bertanda tangan 5 menit untuk membuka dokumen di browser |
| GET /activity-proposals/documents/{documentId}/download | Unduhan lewat tautan bertanda tangan; salah/kedaluwarsa 403 `INVALID_SIGNATURE` |
| POST /admin/activity-proposals/{id}/status | `ditinjau`, `perlu_revisi`, `disetujui`, `ditolak` plus `expected_version`; `disetujui` membuat agenda |

Aturan form: `jenis` salah satu `olahraga|kerja_bakti|keagamaan|sosial|rapat|lainnya`; nama 5–160; deskripsi 20–5000; lokasi 3–200; mulai paling cepat 24 jam dan paling lambat satu tahun; selesai wajib, setelah mulai, maksimal 12 jam; peserta 1–1000; kebutuhan ≤1000; `pernyataan` harus diterima. Pelanggaran 422 dengan pesan per field.

Status: `diajukan` → `ditinjau` | `perlu_revisi` | `disetujui` | `ditolak`; dari `ditinjau` ke `perlu_revisi` | `disetujui` | `ditolak`. Catatan wajib untuk `perlu_revisi` dan `ditolak` (`DECISION_NOTE_REQUIRED` 422). Persetujuan untuk waktu yang sudah lewat 422 `PROPOSAL_DATE_PASSED`. Versi basi `VERSION_CONFLICT` 409. Usulan yang sudah menjadi agenda tidak dapat diubah statusnya (`PROPOSAL_SCHEDULED` 409). Bukan pengusul atau status terkunci `PROPOSAL_LOCKED` 403. Akun menunggu 403.

Dokumen maksimal 3, masing-masing 8 MB, menurut isi berkas: PDF (`%PDF-`), JPG/PNG/WebP (diubah menjadi JPEG ≤1600 px tanpa EXIF), atau TXT (ekstensi `.txt`, UTF-8 valid tanpa byte kontrol dan tanpa markup, maks. 1 MB). Lainnya 415 `UNSUPPORTED_MEDIA_TYPE`, terlalu banyak 422 `TOO_MANY_DOCUMENTS`, terlalu besar 413 `PAYLOAD_TOO_LARGE`. DOCX tidak diterima pada v1. Kunci penyimpanan `usulan/dokumen/{uuid}` pada disk privat; nama berkas asli dan identitas pengusul tidak masuk ke kunci atau URL. Dokumen tidak menempel pada agenda.

Perbaikan yang menambah berkas memakai `POST` dengan `_method=PATCH` karena PHP tidak mengisi unggahan pada metode PATCH. Tanpa berkas baru, klien mengirim JSON PATCH.

Notifikasi outbox: `usulan.diajukan` dan `usulan.diperbaiki` ke pengurus; `usulan.status_berubah` ke pengusul (persetujuan bertuliskan "disetujui dan masuk agenda"); `agenda.dibuat` ke semua warga aktif saat disetujui. Inbox tetap disimpan bila push gagal.

## Agenda, pengumuman, notifikasi

GET /agendas, /agendas/{id}, /agendas/{id}/calendar (link/ICS); GET /announcements dan detail; GET /notifications; PATCH /notifications/{id}/read; POST /notifications/read-all; PUT/DELETE /devices/{device_identifier}; PATCH /notification-preferences.

Admin: GET/POST/PATCH /admin/agendas dan /admin/announcements; POST {id}/publish atau /archive. Agenda published saat starts_at valid; batas state di OpenAPI.

## Iuran dan kas

| Endpoint | Perilaku |
| --- | --- |
| GET /dues/my | Tagihan/histori rumah akun |
| GET /dues/transparency | Kode rumah, period, amount/status; warga aktif |
| GET /cash/summary | Saldo/pemasukan/pengeluaran agregat dan daftar tersanitasi |
| GET/POST /admin/dues-rates | Tarif efektif; reason wajib |
| POST /admin/dues/generate | Generate/catch-up periode idempotent |
| GET /admin/dues-charges | Filter house/period/status |
| POST /admin/dues-payments | Pencatatan offline sinkron |
| GET /admin/dues-payments/{id} | Detail allocations dan audit yang diizinkan |
| POST /admin/dues-payments/{id}/reverse | Reason; buka charge; reversal ledger |
| POST /admin/dues-payments/{id}/correct | Reverse+replacement atomik |
| GET/POST /admin/cash-transactions | Manual nondues income/expense/opening |
| POST /admin/cash-transactions/{id}/reverse | Satu reversal dengan alasan |

Mutation finance wajib Idempotency-Key. Key UUID unik per niat transaksi. Key sama/payload sama replay respons asal; key sama/payload lain 409. Retry setelah timeout memakai key lama, bukan membuat key baru.

```json
{
  "house_id": "uuid",
  "periods": ["2026-10", "2026-11"],
  "amount_rupiah": 150000,
  "paid_on": "2026-10-10",
  "note": "Pembayaran diterima offline"
}
```

Server menentukan total dari charge snapshot; nominal contoh hanya benar jika kedua periode bertarif Rp75.000. Tidak menerima client-paid-status atau client-balance.

## Konten

Public GET /public/site-profile, /public/facilities, /public/articles, /public/articles/{slug}, /public/albums, /public/albums/{slug}. Hanya published snapshot.

Admin GET/POST/PATCH /admin/articles, /admin/albums, /admin/facilities; detail by UUID; POST {id}/publish dan /archive. PUT /admin/site-profile untuk fakta profil tervalidasi. CMS web memakai articles/albums; mobile admin dapat memakai seluruh domain konten.

Optimistic version pada save/publish; slug collision 409. Web SSR memakai public endpoints lewat internal network, bukan query DB.

## Media

POST /media dengan multipart file/purpose/parent_id opsional, auth wajib. JPG/PNG/WebP, ≤12 MB/file, ≤50 megapiksel; magic bytes diverifikasi. Return 202 asset ID/state. GET /media/{id}/status. GET /media/{id}/download dengan auth/policy. DELETE /media/{id} mengarsipkan asset yang boleh dilepas.

Jangan menerima SVG/HTML upload warga, remote URL fetch arbitrary, atau path disk dari client. Public URL hanya untuk published assets; signed private URL jika dipakai berumur pendek dan tetap tidak dibagikan di feed publik.

## Operasi

GET /admin/dashboard; GET /admin/audit-logs; POST /admin/exports; GET /admin/exports/{id}; GET /admin/exports/{id}/download; GET /app-version public dengan minimum_supported_version/download URL yang benar.

Liveness di /health/live tidak memanggil Neon. Readiness /health/ready memeriksa dependency dengan timeout dan data aman. Status detail worker/queue/outbox hanya internal/admin operasi.

## OpenAPI dan contract tests

M02 menghasilkan OpenAPI 3.1 dengan schemas, security schemes, examples, enums dan endpoint foundation. Endpoint milestone mendatang boleh ditandai planned, tidak didaftarkan seolah ready. CI memvalidasi spec serta respons nyata milestone aktif.

Semua endpoint yang ditambahkan/diganti harus mempunyai policy test, validasi, error shape, pagination, dan contoh. Mobile tidak boleh menebak field dari screenshot.
