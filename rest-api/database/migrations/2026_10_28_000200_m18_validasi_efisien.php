<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit M18 (28 Okt): validasi ledger tetap penuh, tetapi tidak diulang untuk setiap baris.
 *
 * Sebelumnya setiap baris yang berubah memicu pemindaian seluruh rt05_finance_violations saat commit, sehingga biaya
 * tumbuh kuadratik (300 rumah × 12 bulan ≈ 16 detik). Sekarang trigger statement rt05_keuangan_lock mereset penanda
 * transaksi setiap ada statement baru di tabel keuangan, dan trigger validasi tertunda hanya memindai sekali setelah
 * statement terakhir. Bila ada perubahan lagi sesudah validasi (mis. SET CONSTRAINTS IMMEDIATE di tengah transaksi),
 * penanda direset dan validasi berikutnya memindai ulang. Ditambah indeks kolom penghubung yang dipakai view.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE INDEX IF NOT EXISTS alokasi_pembayaran_pembayaran ON alokasi_pembayaran(pembayaran_id) WHERE pembayaran_id IS NOT NULL;
            CREATE INDEX IF NOT EXISTS alokasi_pembayaran_penggunaan ON alokasi_pembayaran(penggunaan_titipan_id) WHERE penggunaan_titipan_id IS NOT NULL;
            CREATE INDEX IF NOT EXISTS alokasi_pembayaran_tagihan ON alokasi_pembayaran(tagihan_id);
            CREATE INDEX IF NOT EXISTS keringanan_iuran_pembayaran ON keringanan_iuran(pembayaran_id) WHERE pembayaran_id IS NOT NULL;
            CREATE INDEX IF NOT EXISTS mutasi_titipan_pembayaran ON mutasi_titipan(pembayaran_id);
            CREATE INDEX IF NOT EXISTS mutasi_titipan_penggunaan ON mutasi_titipan(penggunaan_titipan_id) WHERE penggunaan_titipan_id IS NOT NULL;
            CREATE INDEX IF NOT EXISTS mutasi_titipan_membalik ON mutasi_titipan(membalik_id) WHERE membalik_id IS NOT NULL;
            CREATE INDEX IF NOT EXISTS transaksi_kas_membalik ON transaksi_kas(membalik_id) WHERE membalik_id IS NOT NULL;

            CREATE OR REPLACE FUNCTION rt05_keuangan_lock() RETURNS trigger LANGUAGE plpgsql AS $fn$
            BEGIN
                PERFORM pg_advisory_xact_lock(hashtext('rt05:keuangan'));
                -- Ada perubahan baru: hasil validasi sebelumnya dalam transaksi ini tidak berlaku lagi.
                PERFORM set_config('rt05.keuangan_tervalidasi', '', true);
                RETURN NULL;
            END $fn$;

            CREATE OR REPLACE FUNCTION rt05_finance_validate() RETURNS trigger LANGUAGE plpgsql AS $fn$
            BEGIN
                PERFORM pg_advisory_xact_lock(hashtext('rt05:keuangan'));
                IF current_setting('rt05.keuangan_tervalidasi', true) = '1' THEN
                    RETURN NULL;
                END IF;
                IF EXISTS(SELECT 1 FROM rt05_finance_violations) THEN
                    RAISE EXCEPTION 'Invarian keuangan tidak terpenuhi' USING ERRCODE='23514';
                END IF;
                PERFORM set_config('rt05.keuangan_tervalidasi', '1', true);
                RETURN NULL;
            END $fn$;
            SQL);
    }

    public function down(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION rt05_keuangan_lock() RETURNS trigger LANGUAGE plpgsql AS $fn$
            BEGIN
                PERFORM pg_advisory_xact_lock(hashtext('rt05:keuangan'));
                RETURN NULL;
            END $fn$;
            CREATE OR REPLACE FUNCTION rt05_finance_validate() RETURNS trigger LANGUAGE plpgsql AS $fn$
            BEGIN
                PERFORM pg_advisory_xact_lock(hashtext('rt05:keuangan'));
                IF EXISTS(SELECT 1 FROM rt05_finance_violations) THEN
                    RAISE EXCEPTION 'Invarian keuangan tidak terpenuhi' USING ERRCODE='23514';
                END IF;
                RETURN NULL;
            END $fn$;
            DROP INDEX IF EXISTS alokasi_pembayaran_pembayaran, alokasi_pembayaran_penggunaan, alokasi_pembayaran_tagihan,
                keringanan_iuran_pembayaran, mutasi_titipan_pembayaran, mutasi_titipan_penggunaan, mutasi_titipan_membalik,
                transaksi_kas_membalik;
            SQL);
    }
};
