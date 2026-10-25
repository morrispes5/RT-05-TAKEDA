<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** M17: avatar keluarga dari 15 pilihan bawaan (App\Domain\Residents\FamilyAvatars). Aditif, boleh kosong. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keluarga', function (Blueprint $t) {
            $t->string('avatar', 30)->nullable();
        });
        DB::statement("ALTER TABLE keluarga ADD CONSTRAINT keluarga_avatar_check CHECK (avatar IS NULL OR avatar IN ('ayah', 'ibu', 'ayah_peci', 'ibu_hijab', 'kakek', 'nenek', 'nenek_hijab', 'kakak_laki', 'kakak_perempuan', 'remaja_hijab', 'adik_laki', 'adik_perempuan', 'bayi', 'pasangan', 'keluarga'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE keluarga DROP CONSTRAINT IF EXISTS keluarga_avatar_check');
        Schema::table('keluarga', function (Blueprint $t) {
            $t->dropColumn('avatar');
        });
    }
};
