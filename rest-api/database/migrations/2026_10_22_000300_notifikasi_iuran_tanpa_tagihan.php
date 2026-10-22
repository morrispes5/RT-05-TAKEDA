<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Temuan uji pemilik 22 Okt 2026: notifikasi bukti transfer (ADR35, `iuran.konfirmasi_dikirim` ke pengurus dan
 * `iuran.konfirmasi_diproses` ke warga) berjenis `iuran` tanpa tagihan tertentu, tetapi constraint mewajibkan tagihan_id.
 * Inbox gagal dibuat (23514) sejak 21 Okt. Jenis `iuran` kini boleh tanpa tagihan; konteks lain tetap dilarang.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->replace("(jenis = 'iuran' AND num_nonnulls(pengumuman_id, agenda_id, pengaduan_id, aspirasi_id, usulan_kegiatan_id) = 0)");
    }

    public function down(): void
    {
        // Baris iuran tanpa tagihan yang sudah tercipta ditolak constraint lama; rollback hanya untuk database tanpa baris itu.
        $this->replace("(jenis = 'iuran' AND tagihan_id IS NOT NULL AND num_nonnulls(pengumuman_id, agenda_id, pengaduan_id, aspirasi_id, usulan_kegiatan_id) = 0)");
    }

    private function replace(string $iuran): void
    {
        DB::statement('ALTER TABLE notifikasi DROP CONSTRAINT IF EXISTS notifikasi_satu_konteks');
        DB::statement(<<<SQL
            ALTER TABLE notifikasi ADD CONSTRAINT notifikasi_satu_konteks CHECK (
                (jenis = 'sistem' AND num_nonnulls(pengumuman_id, agenda_id, pengaduan_id, aspirasi_id, tagihan_id, usulan_kegiatan_id) = 0)
                OR (jenis = 'pengumuman' AND pengumuman_id IS NOT NULL AND num_nonnulls(agenda_id, pengaduan_id, aspirasi_id, tagihan_id, usulan_kegiatan_id) = 0)
                OR (jenis = 'agenda' AND agenda_id IS NOT NULL AND num_nonnulls(pengumuman_id, pengaduan_id, aspirasi_id, tagihan_id, usulan_kegiatan_id) = 0)
                OR (jenis = 'pengaduan' AND pengaduan_id IS NOT NULL AND num_nonnulls(pengumuman_id, agenda_id, aspirasi_id, tagihan_id, usulan_kegiatan_id) = 0)
                OR (jenis = 'aspirasi' AND aspirasi_id IS NOT NULL AND num_nonnulls(pengumuman_id, agenda_id, pengaduan_id, tagihan_id, usulan_kegiatan_id) = 0)
                OR $iuran
                OR (jenis = 'usulan' AND usulan_kegiatan_id IS NOT NULL AND num_nonnulls(pengumuman_id, agenda_id, pengaduan_id, aspirasi_id, tagihan_id) = 0)
            )
            SQL);
    }
};
