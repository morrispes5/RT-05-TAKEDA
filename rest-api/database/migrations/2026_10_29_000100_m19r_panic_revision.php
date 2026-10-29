<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE kejadian_darurat ALTER COLUMN jenis DROP NOT NULL');
        DB::statement('ALTER TABLE kejadian_darurat DROP CONSTRAINT darurat_jenis_check');
        DB::statement("ALTER TABLE kejadian_darurat ADD CONSTRAINT darurat_jenis_check CHECK (jenis IS NULL OR jenis IN ('kebakaran','kemalingan','penyerangan','lainnya'))");
        DB::statement('ALTER TABLE kejadian_darurat DROP CONSTRAINT darurat_status_check');
        DB::statement("ALTER TABLE kejadian_darurat ADD CONSTRAINT darurat_status_check CHECK (status IN ('aktif','selesai','palsu','dibatalkan_pelapor'))");
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION rt05_guard_darurat() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Riwayat darurat tidak boleh dihapus' USING ERRCODE = '23514';
                END IF;
                IF NEW.versi <> OLD.versi + 1
                    OR (NEW.id, NEW.pelapor_id, NEW.rumah_id, NEW.created_at)
                        IS DISTINCT FROM (OLD.id, OLD.pelapor_id, OLD.rumah_id, OLD.created_at) THEN
                    RAISE EXCEPTION 'Transisi riwayat darurat tidak valid' USING ERRCODE = '23514';
                END IF;
                IF OLD.status = 'aktif' AND NEW.status <> 'aktif'
                    AND NEW.jenis IS NOT DISTINCT FROM OLD.jenis THEN
                    RETURN NEW;
                END IF;
                IF OLD.status = 'aktif' AND NEW.status = 'aktif'
                    AND NEW.penyalahgunaan IS NOT DISTINCT FROM OLD.penyalahgunaan
                    AND NEW.ditangani_oleh_id IS NOT DISTINCT FROM OLD.ditangani_oleh_id
                    AND NEW.catatan_pengurus IS NOT DISTINCT FROM OLD.catatan_pengurus
                    AND NEW.selesai_at IS NOT DISTINCT FROM OLD.selesai_at
                    AND NEW.jenis IS NOT NULL THEN
                    RETURN NEW;
                END IF;
                RAISE EXCEPTION 'Transisi riwayat darurat tidak valid' USING ERRCODE = '23514';
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        if (DB::table('kejadian_darurat')->whereNull('jenis')->orWhere('status', 'dibatalkan_pelapor')->exists()) {
            throw new RuntimeException('Revisi panik M19R sudah memakai jenis kosong atau pembatalan pelapor; rollback ditolak.');
        }
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION rt05_guard_darurat() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'Riwayat darurat tidak boleh dihapus' USING ERRCODE = '23514';
                END IF;
                IF OLD.status <> 'aktif' OR NEW.status = 'aktif'
                    OR (NEW.id, NEW.pelapor_id, NEW.rumah_id, NEW.jenis, NEW.created_at)
                        IS DISTINCT FROM (OLD.id, OLD.pelapor_id, OLD.rumah_id, OLD.jenis, OLD.created_at)
                    OR NEW.versi <> OLD.versi + 1 THEN
                    RAISE EXCEPTION 'Transisi riwayat darurat tidak valid' USING ERRCODE = '23514';
                END IF;
                RETURN NEW;
            END;
            $$;
        SQL);
        DB::statement('ALTER TABLE kejadian_darurat DROP CONSTRAINT darurat_status_check');
        DB::statement("ALTER TABLE kejadian_darurat ADD CONSTRAINT darurat_status_check CHECK (status IN ('aktif','selesai','palsu'))");
        DB::statement('ALTER TABLE kejadian_darurat ALTER COLUMN jenis SET NOT NULL');
        DB::statement('ALTER TABLE kejadian_darurat DROP CONSTRAINT darurat_jenis_check');
        DB::statement("ALTER TABLE kejadian_darurat ADD CONSTRAINT darurat_jenis_check CHECK (jenis IN ('kebakaran','kemalingan','penyerangan','lainnya'))");
    }
};
