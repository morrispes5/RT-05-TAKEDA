<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR43 butir 7: data pribadi arsip dianonimkan 12 bulan setelah diarsipkan (rt05:anonimkan-arsip). Penanda waktu ini juga
 * mencegah pemulihan arsip yang sudah dianonimkan. Aditif.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['warga', 'keluarga', 'akun'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->timestampTz('dianonimkan_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['warga', 'keluarga', 'akun'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('dianonimkan_at');
            });
        }
    }
};
