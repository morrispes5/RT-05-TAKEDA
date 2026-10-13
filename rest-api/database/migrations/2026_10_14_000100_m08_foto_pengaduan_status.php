<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| M08: foto pengaduan diproses worker-media (antrean media), bukan di request.
| diproses → siap | gagal. Baris lama sudah ditranskode sinkron sehingga default 'siap'.
| storage_key menunjuk berkas mentah privat selama diproses, lalu JPEG hasil transkode.
| Additive: kolom baru default/nullable; aplikasi lama tetap berfungsi.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foto_pengaduan', function (Blueprint $table) {
            $table->string('status', 20)->default('siap');
            $table->unsignedSmallInteger('percobaan')->default(0);
            $table->string('kode_gagal', 40)->nullable();
            $table->timestampTz('updated_at')->nullable();

            $table->index(['status', 'updated_at']);
        });
        DB::statement("ALTER TABLE foto_pengaduan ADD CONSTRAINT foto_pengaduan_status_check CHECK (status IN ('diproses', 'siap', 'gagal'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE foto_pengaduan DROP CONSTRAINT IF EXISTS foto_pengaduan_status_check');
        Schema::table('foto_pengaduan', function (Blueprint $table) {
            $table->dropIndex(['status', 'updated_at']);
            $table->dropColumn(['status', 'percobaan', 'kode_gagal', 'updated_at']);
        });
    }
};
