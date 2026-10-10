# infra/ — dukungan operasi

**Status: sebagian (M02).** Aktif: `scripts/check-repo.mjs`, `compose.local.yml` (Postgres 17), `sql/grant-runtime-role.sql`, `docker/postgres/init/`. Docker image dan Nginx dibuat pada **M03/M04** sesuai [docs/DEPLOYMENT.md](../docs/DEPLOYMENT.md) dan [docs/ASYNC_JOBS.md](../docs/ASYNC_JOBS.md).

| Lokasi | Isi | Milestone |
| --- | --- | --- |
| `scripts/check-repo.mjs` | File terlarang, pola secret, `.git` bersarang, tautan relatif Markdown, paritas `vercel.json` | M01 (aktif) |
| `docker/` | Dockerfile API/web/worker | M03–M04 |
| `nginx/` | web-nginx, api-nginx, routing `/api/v1` | M04 |
| `compose.local.yml` | Postgres 17 di 127.0.0.1:54329 (M02); Redis queue/cache, SMTP capture (M03) | M02 aktif |
| `sql/grant-runtime-role.sql` | Hak DML role runtime; sumber tunggal untuk lokal, CI, dan Neon | M02 aktif |
| `docker/postgres/init/` | Role rt05_migrator/rt05_app dan database rt05_dev/rt05_test lokal | M02 aktif |
| `compose.staging.yml` | `takeda-staging.morriz.tech` | M04 |
| `compose.production.yml` | `takeda.morriz.tech` | M16 |

```powershell
node infra/scripts/check-repo.mjs
```

Tidak ada IP server, credential, atau record DNS nyata di folder ini. Perubahan VPS/DNS hanya dalam scope yang diotorisasi pemilik.
