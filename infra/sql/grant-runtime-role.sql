-- Hak minimal role runtime API/worker pada satu database (docs/DATABASE.md).
-- Dijalankan oleh role pemilik schema (role migration) dengan psql:
--   psql "$DATABASE_URL_UNPOOLED" -v owner_role=<role_migration> -v app_role=<role_runtime> -f infra/sql/grant-runtime-role.sql
-- Role runtime: DML saja, tanpa CREATE/ALTER/DROP. Idempotent; aman dijalankan ulang setelah migration.
-- Tidak memuat password. Role runtime dibuat terlebih dahulu (Neon console/API atau init lokal).

\set ON_ERROR_STOP on

REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO :"app_role";
REVOKE CREATE ON SCHEMA public FROM :"app_role";

GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO :"app_role";
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO :"app_role";

ALTER DEFAULT PRIVILEGES FOR ROLE :"owner_role" IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO :"app_role";
ALTER DEFAULT PRIVILEGES FOR ROLE :"owner_role" IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO :"app_role";
