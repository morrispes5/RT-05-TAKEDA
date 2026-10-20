<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| UAT M15 (20 Okt 2026): pengurus dapat meminta pemohon akun tambahan memperbaiki data
| (status perlu_revisi), bukan hanya setuju/tolak. Additive: status lama tetap valid.
*/
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE permohonan_akun DROP CONSTRAINT IF EXISTS permohonan_status_check');
        DB::statement("ALTER TABLE permohonan_akun ADD CONSTRAINT permohonan_status_check CHECK (status IN ('diajukan', 'perlu_revisi', 'disetujui', 'ditolak'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE permohonan_akun SET status = 'diajukan' WHERE status = 'perlu_revisi'");
        DB::statement('ALTER TABLE permohonan_akun DROP CONSTRAINT IF EXISTS permohonan_status_check');
        DB::statement("ALTER TABLE permohonan_akun ADD CONSTRAINT permohonan_status_check CHECK (status IN ('diajukan', 'disetujui', 'ditolak'))");
    }
};
