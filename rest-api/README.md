# rest-api/ — Laravel REST API RT05 TAKEDA

**Status: fondasi M02.** Laravel 13.35 (sama dengan `website/`), PHP 8.4, PostgreSQL saja. `rest-api/` adalah pemilik tunggal database dan aturan bisnis; website dan mobile hanya memanggil `/api/v1`. Kontrak: [docs/openapi.yaml](../docs/openapi.yaml) (mesin) dan [docs/API.md](../docs/API.md) (desain).

## Yang sudah ada (M02)

| Bagian | Isi |
| --- | --- |
| Endpoint | `GET /health/live` (tanpa DB), `GET /health/ready` (DB + kebijakan TLS, 503 tanpa detail), `GET /api/v1` |
| Konvensi | `X-Request-Id` (UUID), error envelope tunggal `{"error":{code,message,fields?,request_id}}`, `ApiResource` dengan `meta.request_id`, semua error JSON tanpa stack trace |
| Database | Koneksi `pgsql` (runtime, pooled, role DML) dan `pgsql_migrations` (direct, pemilik schema); TLS `DB_SSLMODE`/`DB_SSLROOTCERT`; named prepares dimatikan untuk pooler Neon |
| Schema fondasi | `failed_jobs`, `audit_logs` (append-only via trigger), `outbox_events`, `idempotency_requests`, `system_settings` |
| Command | `rt05:db-smoke`, `rt05:db-grant-runtime-role` |
| Tes | Postgres nyata dengan role runtime; contract test OpenAPI; CI `.github/workflows/api.yml` |

Belum ada: users/auth (M06), Redis/queue/outbox dispatcher (M03), domain bisnis (M07+). Endpoint yang dirancang tercatat `x-status: planned` di OpenAPI dan menghasilkan 404 sampai milestone-nya.

## Struktur dan konvensi domain

```text
app/Http/Controllers/<Domain>/   tipis: authorize → FormRequest → Action → Resource
app/Http/Requests/<Domain>/      validasi + authorize() memanggil Policy
app/Http/Resources/<Domain>/     turunan ApiResource; field allowlist eksplisit
app/Policies/                    otorisasi per resource; resource privat di luar scope → 404
app/Actions/<Domain>/            transaksi DB + audit_logs + outbox_events dalam satu commit
app/Http/Responses/ApiError.php  satu-satunya pembuat error JSON
```

- Mutation penting: `DB::transaction` + row lock bila perlu + `audit_logs` + `outbox_events`. Side effect (email/push) lewat outbox, bukan di dalam transaksi.
- Mutation finance wajib `Idempotency-Key` (tabel `idempotency_requests`).
- Uang `bigint` rupiah; waktu `timestamptz` UTC; ID UUID.
- Setiap route baru wajib ada di `docs/openapi.yaml` dengan `x-status: implemented`; `OpenApiContractTest` gagal bila route dan kontrak berbeda.

## Menjalankan

```powershell
Set-Location rest-api
composer install
Copy-Item .env.example .env
php artisan key:generate
composer migrate          # php artisan migrate --database=pgsql_migrations --force
php artisan serve         # http://127.0.0.1:8000/health/live
php artisan rt05:db-smoke
```

### Database untuk tes

Tes **selalu** memakai PostgreSQL nyata pada database `*_test` di host lokal (`tests/bootstrap.php` menolak selain itu, jadi tes tidak pernah menyentuh Neon). Migration tes dijalankan sekali via `pgsql_migrations`, lalu tes berjalan sebagai role runtime `rt05_app`.

- Dengan Docker: `docker compose -f infra/compose.local.yml up -d` (Postgres 17 di `127.0.0.1:54329`, role dari [infra/docker/postgres/init](../infra/docker/postgres/init/01-rt05-roles.sql)), lalu `php artisan test`.
- Tanpa Docker: tes berjalan di GitHub Actions (`api.yml`) pada setiap PR.

### Neon (dev)

Project Neon `rt05-takeda` (aws-ap-southeast-1, Postgres 17): branch `production` (kosong, belum dipakai) dan `dev` (data sintetis). Role `rt05_owner` = pemilik schema/migration; role `rt05_app` = runtime DML, dibuat lewat SQL sehingga bukan anggota `neon_superuser`.

`.env` lokal untuk Neon dev:

```dotenv
DATABASE_URL=postgresql://rt05_app:***@<endpoint>-pooler.<region>.aws.neon.tech/rt05_takeda?sslmode=verify-full
DATABASE_URL_UNPOOLED=postgresql://rt05_owner:***@<endpoint>.<region>.aws.neon.tech/rt05_takeda?sslmode=verify-full
DB_SSLMODE=verify-full
DB_SSLROOTCERT=C:/Users/<anda>/.rt05/ca-bundle.crt
```

- URL dari Neon membawa `sslmode=require`; ganti ke `verify-full` karena parameter URL mengalahkan `DB_SSLMODE`.
- `sslrootcert=system` **tidak** berfungsi pada PHP Windows ("unregistered scheme"); pakai file CA bundle (mis. salinan `C:\Program Files\Git\mingw64\etc\ssl\certs\ca-bundle.crt`) pada path **tanpa spasi**, karena DSN Laravel tidak meng-quote nilai. Image Linux (M04) dapat memakai `system` atau bundle OS.
- Setelah migration baru: `php artisan rt05:db-grant-runtime-role rt05_app` (idempotent; default privileges juga sudah diset).
- Staging/production: `/health/ready` dan `rt05:db-smoke` menolak sslmode selain `verify-full` atau tanpa `sslrootcert`.

Credential tidak pernah di-commit, ditulis ke log, atau dikirim ke website/mobile.
