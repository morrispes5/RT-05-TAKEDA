<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Pengaturan operasional terstruktur dengan optimistic version. Tidak menyimpan credential;
| tarif iuran memakai tabel dues_rates sendiri pada M11, bukan baris di sini.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->jsonb('value');
            $table->unsignedInteger('version')->default(1);
            $table->uuid('updated_by')->nullable();
            $table->timestampTz('updated_at')->useCurrent();
        });

        DB::statement('ALTER TABLE system_settings ADD CONSTRAINT system_settings_version_positive CHECK (version >= 1)');
        DB::statement("ALTER TABLE system_settings ADD CONSTRAINT system_settings_key_format CHECK (key ~ '^[a-z0-9_.:-]+$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
