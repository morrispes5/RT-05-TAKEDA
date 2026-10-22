<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit M16: kas sudah immutable (rt05_immutable_row); tagihan, pembayaran, dan alokasi belum dikunci di database. Pengunci ini
 * berlaku juga untuk role runtime, jadi kredensial aplikasi yang bocor tidak bisa mengubah nominal/rumah/nomor bukti diam-diam.
 * Perubahan sah (Dues): tagihan hanya `status`; pembayaran hanya tercatat → dibatalkan (pembatal, waktu, alasan) sekali;
 * alokasi hanya `dibatalkan_at` dari kosong. Pembayaran dan alokasi tidak pernah dihapus. Tagihan tanpa alokasi boleh dihapus
 * (FK alokasi sudah menolak tagihan yang pernah dibayar).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION rt05_tagihan_terkunci() RETURNS trigger LANGUAGE plpgsql AS $fn$
            BEGIN
                IF (NEW.id, NEW.tarif_id, NEW.rumah_id, NEW.periode, NEW.nominal_tagihan, NEW.jatuh_tempo, NEW.created_at)
                    IS DISTINCT FROM (OLD.id, OLD.tarif_id, OLD.rumah_id, OLD.periode, OLD.nominal_tagihan, OLD.jatuh_tempo, OLD.created_at) THEN
                    RAISE EXCEPTION 'tagihan_iuran hanya boleh berubah status' USING ERRCODE = 'insufficient_privilege';
                END IF;
                RETURN NEW;
            END $fn$;

            CREATE OR REPLACE FUNCTION rt05_pembayaran_terkunci() RETURNS trigger LANGUAGE plpgsql AS $fn$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'pembayaran_iuran tidak boleh dihapus; gunakan pembalikan' USING ERRCODE = 'insufficient_privilege';
                END IF;
                IF (NEW.id, NEW.rumah_id, NEW.diterima_oleh_id, NEW.nomor_bukti, NEW.tanggal_bayar, NEW.total_bayar, NEW.catatan,
                        NEW.menggantikan_id, NEW.created_at)
                    IS DISTINCT FROM (OLD.id, OLD.rumah_id, OLD.diterima_oleh_id, OLD.nomor_bukti, OLD.tanggal_bayar, OLD.total_bayar,
                        OLD.catatan, OLD.menggantikan_id, OLD.created_at)
                    OR NOT (OLD.status = 'tercatat' AND NEW.status = 'dibatalkan' AND OLD.dibatalkan_at IS NULL
                        AND NEW.dibatalkan_at IS NOT NULL AND NEW.dibatalkan_oleh_id IS NOT NULL) THEN
                    RAISE EXCEPTION 'pembayaran_iuran hanya boleh dibatalkan sekali; gunakan pembalikan' USING ERRCODE = 'insufficient_privilege';
                END IF;
                RETURN NEW;
            END $fn$;

            CREATE OR REPLACE FUNCTION rt05_alokasi_terkunci() RETURNS trigger LANGUAGE plpgsql AS $fn$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'alokasi_pembayaran tidak boleh dihapus' USING ERRCODE = 'insufficient_privilege';
                END IF;
                IF (NEW.id, NEW.tagihan_id, NEW.pembayaran_id, NEW.nominal_alokasi, NEW.created_at)
                    IS DISTINCT FROM (OLD.id, OLD.tagihan_id, OLD.pembayaran_id, OLD.nominal_alokasi, OLD.created_at)
                    OR OLD.dibatalkan_at IS NOT NULL OR NEW.dibatalkan_at IS NULL THEN
                    RAISE EXCEPTION 'alokasi_pembayaran hanya boleh dibatalkan sekali' USING ERRCODE = 'insufficient_privilege';
                END IF;
                RETURN NEW;
            END $fn$;

            CREATE TRIGGER tagihan_iuran_terkunci BEFORE UPDATE ON tagihan_iuran
                FOR EACH ROW EXECUTE FUNCTION rt05_tagihan_terkunci();
            CREATE TRIGGER pembayaran_iuran_terkunci BEFORE UPDATE OR DELETE ON pembayaran_iuran
                FOR EACH ROW EXECUTE FUNCTION rt05_pembayaran_terkunci();
            CREATE TRIGGER alokasi_pembayaran_terkunci BEFORE UPDATE OR DELETE ON alokasi_pembayaran
                FOR EACH ROW EXECUTE FUNCTION rt05_alokasi_terkunci();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS tagihan_iuran_terkunci ON tagihan_iuran;
            DROP TRIGGER IF EXISTS pembayaran_iuran_terkunci ON pembayaran_iuran;
            DROP TRIGGER IF EXISTS alokasi_pembayaran_terkunci ON alokasi_pembayaran;
            DROP FUNCTION IF EXISTS rt05_tagihan_terkunci();
            DROP FUNCTION IF EXISTS rt05_pembayaran_terkunci();
            DROP FUNCTION IF EXISTS rt05_alokasi_terkunci();
            SQL);
    }
};
