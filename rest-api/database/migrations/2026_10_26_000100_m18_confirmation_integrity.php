<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION rt05_konfirmasi_terkunci() RETURNS trigger LANGUAGE plpgsql AS $fn$
            BEGIN
                IF (to_jsonb(NEW)-ARRAY['status','catatan_pengurus','diproses_oleh_id','diproses_at','pembayaran_id','updated_at'])
                    IS DISTINCT FROM (to_jsonb(OLD)-ARRAY['status','catatan_pengurus','diproses_oleh_id','diproses_at','pembayaran_id','updated_at'])
                    OR OLD.status<>'diajukan' OR NEW.status NOT IN ('selesai','ditolak')
                    OR NEW.diproses_at IS NULL OR NEW.diproses_oleh_id IS NULL THEN
                    RAISE EXCEPTION 'Pengajuan asli terkunci; hanya boleh diproses satu kali' USING ERRCODE='42501';
                END IF;
                RETURN NEW;
            END $fn$;
            CREATE TRIGGER konfirmasi_iuran_m18_immutable BEFORE UPDATE ON konfirmasi_iuran
                FOR EACH ROW EXECUTE FUNCTION rt05_konfirmasi_terkunci();
            CREATE OR REPLACE VIEW rt05_konfirmasi_violations AS
            SELECT k.id FROM konfirmasi_iuran k JOIN pembayaran_iuran p ON p.id=k.pembayaran_id
                WHERE k.rumah_id<>p.rumah_id OR k.status<>'selesai' OR k.diproses_oleh_id IS NULL
                    OR k.diproses_at IS NULL OR (k.nominal IS DISTINCT FROM p.total_bayar AND length(trim(COALESCE(k.catatan_pengurus,'')))<5);
            CREATE OR REPLACE FUNCTION rt05_konfirmasi_validate() RETURNS trigger LANGUAGE plpgsql AS $fn$
            BEGIN
                IF EXISTS(SELECT 1 FROM rt05_konfirmasi_violations) THEN
                    RAISE EXCEPTION 'Konfirmasi dan pembayaran tidak konsisten' USING ERRCODE='23514';
                END IF;
                RETURN NULL;
            END $fn$;
            CREATE CONSTRAINT TRIGGER konfirmasi_iuran_m18_check AFTER INSERT OR UPDATE ON konfirmasi_iuran
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION rt05_konfirmasi_validate();
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('M18 ledger memakai perbaikan maju atau restore terverifikasi.');
    }
};
