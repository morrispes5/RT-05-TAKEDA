<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| UAT M15 (20 Okt 2026, ADR34): permohonan akun tambahan divalidasi dari nama pemohon, hubungan, dan nama
| kepala keluarga; alamat menjadi opsional karena rumah dicocokkan dari kepala keluarga. Additive: kolom
| nullable, permohonan lama tidak berubah.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permohonan_akun', function (Blueprint $table) {
            $table->string('nama_kepala_diajukan', 120)->nullable()->after('hubungan_keluarga');
        });
    }

    public function down(): void
    {
        Schema::table('permohonan_akun', function (Blueprint $table) {
            $table->dropColumn('nama_kepala_diajukan');
        });
    }
};
