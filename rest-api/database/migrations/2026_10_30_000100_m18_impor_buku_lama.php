<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ADR49: pelunasan tagihan dari rekap Excel bendahara sebelum aplikasi ("buku lama").
 *
 * Bukan pembayaran: uangnya sudah ada di kas fisik dan masuk aplikasi sekali lewat saldo awal kas. Satu pelunasan aktif per
 * tagihan, append-only (hanya dibatalkan sekali dengan alasan), ikut terhitung di sisa tagihan dan validasi invarian M18.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE impor_keuangan (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                tahun smallint NOT NULL UNIQUE CHECK(tahun BETWEEN 2020 AND 2100),
                sampai_periode date NOT NULL CHECK(extract(day FROM sampai_periode)=1),
                sidik_jari char(64) NOT NULL UNIQUE,
                ringkasan jsonb NOT NULL DEFAULT '{}'::jsonb,
                diterapkan_oleh_id uuid NOT NULL REFERENCES akun(id),
                created_at timestamptz NOT NULL DEFAULT now()
            );
            CREATE TABLE pelunasan_buku_lama (
                id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
                tagihan_id uuid NOT NULL REFERENCES tagihan_iuran(id),
                impor_id uuid NOT NULL REFERENCES impor_keuangan(id),
                nominal bigint NOT NULL CHECK(nominal>0),
                dicatat_oleh_id uuid NOT NULL REFERENCES akun(id), created_at timestamptz NOT NULL DEFAULT now(),
                dibatalkan_at timestamptz, dibatalkan_oleh_id uuid REFERENCES akun(id), alasan_pembatalan text,
                CONSTRAINT pelunasan_buku_lama_pembatalan_lengkap CHECK (
                    (dibatalkan_at IS NULL AND dibatalkan_oleh_id IS NULL AND alasan_pembatalan IS NULL) OR
                    (dibatalkan_at IS NOT NULL AND dibatalkan_oleh_id IS NOT NULL AND length(trim(alasan_pembatalan)) BETWEEN 5 AND 300))
            );
            CREATE UNIQUE INDEX pelunasan_buku_lama_aktif ON pelunasan_buku_lama(tagihan_id) WHERE dibatalkan_at IS NULL;
            CREATE INDEX pelunasan_buku_lama_impor ON pelunasan_buku_lama(impor_id);

            CREATE TRIGGER impor_keuangan_immutable BEFORE UPDATE OR DELETE ON impor_keuangan
                FOR EACH ROW EXECUTE FUNCTION rt05_immutable_row();
            CREATE TRIGGER pelunasan_buku_lama_m18_immutable BEFORE UPDATE OR DELETE ON pelunasan_buku_lama
                FOR EACH ROW EXECUTE FUNCTION rt05_batal_saja();
            CREATE TRIGGER pelunasan_buku_lama_m18_lock BEFORE INSERT OR UPDATE OR DELETE ON pelunasan_buku_lama
                FOR EACH STATEMENT EXECUTE FUNCTION rt05_keuangan_lock();
            CREATE CONSTRAINT TRIGGER pelunasan_buku_lama_m18_check AFTER INSERT OR UPDATE OR DELETE ON pelunasan_buku_lama
                DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION rt05_finance_validate();

            -- Kolom baru hanya boleh ditambah di akhir (CREATE OR REPLACE VIEW); sisa ikut dikurangi buku lama.
            CREATE OR REPLACE VIEW rt05_tagihan_saldo AS
            SELECT t.*, t.nominal_tagihan + COALESCE(j.delta,0) AS nominal_efektif,
                COALESCE(a.uang,0) AS terbayar_uang, COALESCE(a.titipan,0) AS terbayar_titipan,
                COALESCE(k.nominal,0) AS keringanan,
                t.nominal_tagihan + COALESCE(j.delta,0)-COALESCE(a.uang,0)-COALESCE(a.titipan,0)-COALESCE(k.nominal,0)-COALESCE(b.nominal,0) AS sisa,
                COALESCE(b.nominal,0) AS terbayar_buku_lama, b.id AS buku_lama_id
            FROM tagihan_iuran t
            LEFT JOIN (SELECT tagihan_id, sum(nominal_alokasi) FILTER(WHERE pembayaran_id IS NOT NULL) AS uang,
                sum(nominal_alokasi) FILTER(WHERE penggunaan_titipan_id IS NOT NULL) AS titipan
                FROM alokasi_pembayaran WHERE dibatalkan_at IS NULL GROUP BY tagihan_id) a ON a.tagihan_id=t.id
            LEFT JOIN (SELECT tagihan_id,sum(nominal) AS nominal FROM keringanan_iuran WHERE dibatalkan_at IS NULL GROUP BY tagihan_id) k ON k.tagihan_id=t.id
            LEFT JOIN (SELECT tagihan_id,sum(delta) AS delta FROM penyesuaian_tagihan GROUP BY tagihan_id) j ON j.tagihan_id=t.id
            LEFT JOIN (SELECT tagihan_id,sum(nominal) AS nominal,(array_agg(id))[1] AS id FROM pelunasan_buku_lama WHERE dibatalkan_at IS NULL GROUP BY tagihan_id) b ON b.tagihan_id=t.id;
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Ledger buku lama tidak dihapus lewat rollback. Gunakan perbaikan maju atau runbook restore terverifikasi.');
    }
};
