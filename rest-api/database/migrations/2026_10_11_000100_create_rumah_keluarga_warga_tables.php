<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ERD v1.1 domain 02 — rumah, pemilik_rumah, keluarga, warga, penghunian.
| Penghunian menghubungkan rumah–keluarga dengan rentang waktu; riwayat tidak dihapus.
| Tagihan melekat pada rumah (tagihan_aktif + mulai_tagih ditetapkan pengurus).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pemilik_rumah', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('nama', 120);
            $table->string('whatsapp', 25)->nullable();
            $table->text('catatan')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('archived_at')->nullable();
        });

        Schema::create('rumah', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('pemilik_id')->nullable()->constrained('pemilik_rumah')->restrictOnDelete();
            $table->string('kode_rumah', 30)->unique();
            $table->string('blok', 30)->nullable();
            $table->string('jalan', 150);
            $table->string('nomor', 20);
            // Alamat ternormalisasi untuk mencegah rumah ganda karena beda penulisan.
            $table->string('alamat_key', 220)->unique();
            $table->boolean('tagihan_aktif')->default(true);
            $table->date('mulai_tagih')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('archived_at')->nullable();
        });
        DB::statement('ALTER TABLE rumah ADD CONSTRAINT rumah_mulai_tagih_awal_bulan CHECK (mulai_tagih IS NULL OR extract(day from mulai_tagih) = 1)');

        Schema::create('keluarga', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('status', 20)->default('belum_verifikasi');
            $table->unsignedInteger('versi')->default(1);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('archived_at')->nullable();
        });
        DB::statement("ALTER TABLE keluarga ADD CONSTRAINT keluarga_status_check CHECK (status IN ('belum_verifikasi', 'terverifikasi'))");

        Schema::create('warga', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('keluarga_id')->constrained('keluarga')->restrictOnDelete();
            $table->string('nama', 120);
            $table->string('hubungan_keluarga', 40);
            $table->boolean('is_kepala')->default(false);
            $table->string('whatsapp', 25)->nullable();
            $table->string('status', 20)->default('aktif');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('archived_at')->nullable();

            $table->index('keluarga_id');
        });
        DB::statement("ALTER TABLE warga ADD CONSTRAINT warga_status_check CHECK (status IN ('aktif', 'pindah', 'meninggal'))");
        DB::statement('CREATE UNIQUE INDEX warga_satu_kepala_aktif ON warga (keluarga_id) WHERE is_kepala AND archived_at IS NULL');

        Schema::create('penghunian', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('rumah_id')->constrained('rumah')->restrictOnDelete();
            $table->foreignUuid('keluarga_id')->constrained('keluarga')->restrictOnDelete();
            $table->string('jenis_hunian', 20)->default('pemilik');
            $table->date('mulai_tanggal');
            $table->date('selesai_tanggal')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE penghunian ADD CONSTRAINT penghunian_jenis_check CHECK (jenis_hunian IN ('pemilik', 'kontrak'))");
        DB::statement('ALTER TABLE penghunian ADD CONSTRAINT penghunian_rentang_valid CHECK (selesai_tanggal IS NULL OR selesai_tanggal >= mulai_tanggal)');
        DB::statement('CREATE UNIQUE INDEX penghunian_satu_aktif_per_rumah ON penghunian (rumah_id) WHERE selesai_tanggal IS NULL');
        DB::statement('CREATE UNIQUE INDEX penghunian_satu_aktif_per_keluarga ON penghunian (keluarga_id) WHERE selesai_tanggal IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('penghunian');
        Schema::dropIfExists('warga');
        Schema::dropIfExists('keluarga');
        Schema::dropIfExists('rumah');
        Schema::dropIfExists('pemilik_rumah');
    }
};
