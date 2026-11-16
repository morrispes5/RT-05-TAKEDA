<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ADR61 (16 Nov 2026, permintaan pemilik): data inti tidak boleh hilang karena DELETE/TRUNCATE yang tidak sengaja
 * (bug, SQL manual, skrip salah sasaran). Aplikasi sudah memakai arsip yang bisa dipulihkan (`archived_at`, status
 * `diarsipkan`, tanggal selesai); trigger ini memaksakan aturan yang sama di tingkat database, seperti buku kas dan
 * audit. Aditif: tidak mengubah baris dan tidak mengubah jalur aplikasi, yang memang tidak pernah menghapus tabel ini.
 *
 * Pembersihan yang disengaja (tes pada database *_test, perbaikan operator yang disetujui pemilik) membuka izin hanya
 * untuk satu transaksi: `SET LOCAL rt05.izinkan_hapus = 'ya'`.
 */
return new class extends Migration
{
    /** Tabel yang barisnya diarsipkan/diakhiri, tidak pernah dihapus aplikasi. */
    public const TABLES = [
        'akun', 'warga', 'keluarga', 'rumah', 'penghunian', 'keanggotaan_keluarga', 'tautan_akun', 'permohonan_akun',
        'pengaduan', 'riwayat_pengaduan', 'aspirasi', 'riwayat_aspirasi', 'usulan_kegiatan', 'riwayat_usulan_kegiatan',
        'agenda', 'pengumuman', 'konten', 'konfirmasi_iuran', 'tagihan_iuran',
    ];

    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION rt05_larang_hapus() RETURNS trigger LANGUAGE plpgsql AS $fn$
            BEGIN
                IF current_setting('rt05.izinkan_hapus', true) = 'ya' THEN
                    RETURN CASE WHEN TG_LEVEL = 'ROW' THEN OLD ELSE NULL END;
                END IF;
                RAISE EXCEPTION 'Baris % tidak boleh dihapus permanen. Arsipkan agar dapat dipulihkan.', TG_TABLE_NAME
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $fn$;
        SQL);
        foreach (self::TABLES as $table) {
            // Idempotent: M07 yang dipasang ulang setelah rollback sudah memasang trigger untuk tabelnya sendiri.
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_larang_hapus ON $table");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_larang_kosongkan ON $table");
            DB::unprepared("CREATE TRIGGER {$table}_larang_hapus BEFORE DELETE ON $table FOR EACH ROW EXECUTE FUNCTION rt05_larang_hapus()");
            DB::unprepared("CREATE TRIGGER {$table}_larang_kosongkan BEFORE TRUNCATE ON $table FOR EACH STATEMENT EXECUTE FUNCTION rt05_larang_hapus()");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_larang_hapus ON $table");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_larang_kosongkan ON $table");
        }
        DB::unprepared('DROP FUNCTION IF EXISTS rt05_larang_hapus()');
    }
};
