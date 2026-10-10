-- Postgres LOKAL/CI saja. Password di bawah adalah nilai pengembangan publik, bukan credential.
-- Meniru pemisahan Neon: rt05_migrator = pemilik schema (migration), rt05_app = runtime DML.

\set ON_ERROR_STOP on

CREATE ROLE rt05_migrator LOGIN PASSWORD 'local-dev-only';
CREATE ROLE rt05_app LOGIN PASSWORD 'local-dev-only';

CREATE DATABASE rt05_dev OWNER rt05_migrator;
CREATE DATABASE rt05_test OWNER rt05_migrator;

\connect rt05_dev
ALTER SCHEMA public OWNER TO rt05_migrator;
\set owner_role rt05_migrator
\set app_role rt05_app
\ir ../../../sql/grant-runtime-role.sql

\connect rt05_test
ALTER SCHEMA public OWNER TO rt05_migrator;
\set owner_role rt05_migrator
\set app_role rt05_app
\ir ../../../sql/grant-runtime-role.sql
