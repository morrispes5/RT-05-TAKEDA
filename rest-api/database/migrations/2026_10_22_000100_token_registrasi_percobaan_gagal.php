<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit M16: penghitung salah tebak per token registrasi, lintas IP. Batas per IP mudah diakali dengan banyak alamat
 * IPv6; setelah RegistrationToken::MAX_FAILURES token diganti otomatis. Aditif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('token_registrasi', function (Blueprint $table) {
            $table->unsignedInteger('percobaan_gagal')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('token_registrasi', function (Blueprint $table) {
            $table->dropColumn('percobaan_gagal');
        });
    }
};
