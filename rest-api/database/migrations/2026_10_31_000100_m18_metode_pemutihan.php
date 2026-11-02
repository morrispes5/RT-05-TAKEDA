<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ADR53: metode bayar (tunai/transfer) dan jenis keringanan (sukarela, pemutihan).
 *
 * Metode boleh kosong untuk catatan lama yang tidak tahu caranya; tidak ditebak. Keringanan "sukarela" = bayar di bawah tarif
 * tanpa tunggakan, "pemutihan" = bulan buku lama yang tidak dibayar dan dihapuskan RT. Kolom view baru hanya ditambah di akhir.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE pembayaran_iuran ADD COLUMN metode varchar(10) CONSTRAINT pembayaran_metode_check CHECK (metode IN ('tunai','transfer'));
            ALTER TABLE pelunasan_buku_lama ADD COLUMN metode varchar(10) CONSTRAINT pelunasan_buku_lama_metode_check CHECK (metode IN ('tunai','transfer'));
            ALTER TABLE keringanan_iuran ADD COLUMN jenis varchar(12) NOT NULL DEFAULT 'keringanan'
                CONSTRAINT keringanan_jenis_check CHECK (jenis IN ('keringanan','sukarela','pemutihan'));

            CREATE OR REPLACE VIEW rt05_tagihan_saldo AS
            SELECT t.*, t.nominal_tagihan + COALESCE(j.delta,0) AS nominal_efektif,
                COALESCE(a.uang,0) AS terbayar_uang, COALESCE(a.titipan,0) AS terbayar_titipan,
                COALESCE(k.nominal,0) AS keringanan,
                t.nominal_tagihan + COALESCE(j.delta,0)-COALESCE(a.uang,0)-COALESCE(a.titipan,0)-COALESCE(k.nominal,0)-COALESCE(b.nominal,0) AS sisa,
                COALESCE(b.nominal,0) AS terbayar_buku_lama, b.id AS buku_lama_id,
                COALESCE(k.sukarela,0) AS keringanan_sukarela, COALESCE(k.pemutihan,0) AS keringanan_pemutihan, b.metode AS buku_lama_metode
            FROM tagihan_iuran t
            LEFT JOIN (SELECT tagihan_id, sum(nominal_alokasi) FILTER(WHERE pembayaran_id IS NOT NULL) AS uang,
                sum(nominal_alokasi) FILTER(WHERE penggunaan_titipan_id IS NOT NULL) AS titipan
                FROM alokasi_pembayaran WHERE dibatalkan_at IS NULL GROUP BY tagihan_id) a ON a.tagihan_id=t.id
            LEFT JOIN (SELECT tagihan_id,sum(nominal) AS nominal, sum(nominal) FILTER(WHERE jenis='sukarela') AS sukarela,
                sum(nominal) FILTER(WHERE jenis='pemutihan') AS pemutihan
                FROM keringanan_iuran WHERE dibatalkan_at IS NULL GROUP BY tagihan_id) k ON k.tagihan_id=t.id
            LEFT JOIN (SELECT tagihan_id,sum(delta) AS delta FROM penyesuaian_tagihan GROUP BY tagihan_id) j ON j.tagihan_id=t.id
            LEFT JOIN (SELECT tagihan_id,sum(nominal) AS nominal,(array_agg(id))[1] AS id,(array_agg(metode))[1] AS metode
                FROM pelunasan_buku_lama WHERE dibatalkan_at IS NULL GROUP BY tagihan_id) b ON b.tagihan_id=t.id;
            SQL);
    }

    public function down(): void
    {
        throw new RuntimeException('Ledger keuangan tidak dihapus lewat rollback. Gunakan perbaikan maju atau runbook restore terverifikasi.');
    }
};
