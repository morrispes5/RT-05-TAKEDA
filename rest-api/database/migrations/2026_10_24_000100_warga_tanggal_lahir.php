<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR42 (permintaan Pak RT 24 Okt 2026): tanggal lahir anggota keluarga, opsional, untuk statistik kelompok umur warga.
 * Umur tidak disimpan; dihitung dari tanggal lahir per hari (WIB). Aditif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warga', function (Blueprint $table) {
            $table->date('tanggal_lahir')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('warga', function (Blueprint $table) {
            $table->dropColumn('tanggal_lahir');
        });
    }
};
