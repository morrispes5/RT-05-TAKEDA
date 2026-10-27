<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit M19 (27 Okt): penanda penyalahgunaan yang keliru dapat dibatalkan pengurus tanpa mengubah riwayat darurat
 * (baris kejadian tetap terkunci). Satu koreksi per laporan, wajib alasan, append-only. Aditif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('koreksi_penyalahgunaan_darurat', function (Blueprint $t) {
            $t->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $t->foreignUuid('kejadian_darurat_id')->unique()->constrained('kejadian_darurat')->restrictOnDelete();
            $t->foreignUuid('dicatat_oleh_id')->constrained('akun')->restrictOnDelete();
            $t->text('alasan');
            $t->timestampTz('created_at')->useCurrent();
        });
        DB::statement('ALTER TABLE koreksi_penyalahgunaan_darurat ADD CONSTRAINT koreksi_darurat_alasan CHECK (length(trim(alasan)) BETWEEN 5 AND 300)');
        DB::unprepared('CREATE TRIGGER koreksi_penyalahgunaan_darurat_immutable BEFORE UPDATE OR DELETE ON koreksi_penyalahgunaan_darurat
            FOR EACH ROW EXECUTE FUNCTION rt05_immutable_row()');
    }

    public function down(): void
    {
        if (DB::table('koreksi_penyalahgunaan_darurat')->exists()) {
            throw new RuntimeException('Koreksi penyalahgunaan sudah ada; rollback yang menghapus riwayat ditolak.');
        }
        Schema::dropIfExists('koreksi_penyalahgunaan_darurat');
    }
};
