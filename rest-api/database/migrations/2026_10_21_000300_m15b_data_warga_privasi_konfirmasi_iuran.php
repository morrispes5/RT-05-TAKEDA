<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Permintaan Pak RT (20 Okt 2026, ADR35). Semua additive:
| - warga.nik dan keluarga.nomor_kk: opsional, terenkripsi (cast `encrypted`, APP_KEY), tidak pernah dikirim utuh lewat API.
| - akun.privasi_versi / privasi_disetujui_at: persetujuan Kebijakan Privasi per versi.
| - konfirmasi_iuran: foto bukti transfer dari warga untuk diperiksa pengurus. Hanya konfirmasi; pembayaran tetap
|   dicatat pengurus lewat jalur pembayaran offline (BUSINESS_RULES), tidak ada status lunas otomatis.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warga', function (Blueprint $table) {
            $table->text('nik')->nullable();
        });
        Schema::table('keluarga', function (Blueprint $table) {
            $table->text('nomor_kk')->nullable();
        });
        Schema::table('akun', function (Blueprint $table) {
            $table->string('privasi_versi', 20)->nullable();
            $table->timestampTz('privasi_disetujui_at')->nullable();
        });

        Schema::create('konfirmasi_iuran', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('akun_id')->constrained('akun');
            $table->foreignUuid('rumah_id')->constrained('rumah');
            $table->jsonb('periode');
            $table->unsignedInteger('nominal')->nullable();
            $table->string('catatan', 300)->nullable();
            $table->string('storage_key', 200);
            $table->string('mime', 40);
            $table->unsignedInteger('bytes');
            $table->unsignedInteger('lebar');
            $table->unsignedInteger('tinggi');
            $table->string('status', 20)->default('diajukan');
            $table->string('catatan_pengurus', 300)->nullable();
            $table->foreignUuid('diproses_oleh_id')->nullable()->constrained('akun');
            $table->timestampTz('diproses_at')->nullable();
            $table->timestampsTz();
            $table->index(['status', 'created_at']);
            $table->index(['akun_id', 'created_at']);
        });
        DB::statement("ALTER TABLE konfirmasi_iuran ADD CONSTRAINT konfirmasi_iuran_status_check CHECK (status IN ('diajukan', 'selesai', 'ditolak'))");
        DB::statement("ALTER TABLE konfirmasi_iuran ADD CONSTRAINT konfirmasi_iuran_proses_check CHECK ((status = 'diajukan') = (diproses_at IS NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('konfirmasi_iuran');
        Schema::table('akun', function (Blueprint $table) {
            $table->dropColumn(['privasi_versi', 'privasi_disetujui_at']);
        });
        Schema::table('keluarga', function (Blueprint $table) {
            $table->dropColumn('nomor_kk');
        });
        Schema::table('warga', function (Blueprint $table) {
            $table->dropColumn('nik');
        });
    }
};
