<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| M13 konten publik (ADR28). Satu tabel `konten` untuk artikel, album, fasilitas, dan profil situs:
| draf (jsonb) dapat diedit dengan versi optimistik; `terbit` adalah snapshot terpisah yang hanya
| berubah saat diterbitkan, jadi pengunjung tidak pernah melihat draf. Slug unik per jenis; slug
| lama yang pernah terbit disimpan untuk redirect. Media: unggahan pengurus (disk privat, EXIF
| dibuang) atau foto statis website yang sudah diterima (path di bawah /images/).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('konten_media', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('sumber', 10);
            $table->string('path_statis', 200)->nullable()->unique();
            $table->jsonb('ukuran_statis')->nullable();
            $table->string('storage_key', 200)->nullable()->unique();
            $table->string('mime', 40);
            $table->integer('bytes')->nullable();
            $table->integer('lebar');
            $table->integer('tinggi');
            $table->string('status', 10)->default('siap');
            $table->foreignUuid('dibuat_oleh_id')->nullable()->constrained('akun')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE konten_media ADD CONSTRAINT konten_media_sumber_check CHECK (
            (sumber = 'statis' AND path_statis IS NOT NULL AND storage_key IS NULL AND path_statis LIKE '/images/%')
            OR (sumber = 'unggah' AND storage_key IS NOT NULL AND path_statis IS NULL))");
        DB::statement("ALTER TABLE konten_media ADD CONSTRAINT konten_media_status_check CHECK (status IN ('siap', 'gagal'))");

        Schema::create('konten', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('jenis', 12);
            $table->string('slug', 120);
            $table->integer('versi')->default(1);
            $table->string('status', 10)->default('draf');
            $table->jsonb('draf');
            $table->jsonb('terbit')->nullable();
            $table->string('slug_terbit', 120)->nullable();
            $table->integer('versi_terbit')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->string('sumber', 10)->default('pengurus');
            // Urutan tampil (fasilitas/profil mengikuti urutan katalog yang diterima); 0 = urut waktu terbit.
            $table->smallInteger('urutan')->default(0);
            $table->foreignUuid('dibuat_oleh_id')->nullable()->constrained('akun')->restrictOnDelete();
            $table->foreignUuid('diubah_oleh_id')->nullable()->constrained('akun')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('archived_at')->nullable();

            $table->unique(['jenis', 'slug']);
            $table->index(['jenis', 'status', 'published_at']);
        });
        DB::statement("ALTER TABLE konten ADD CONSTRAINT konten_jenis_check CHECK (jenis IN ('artikel', 'album', 'fasilitas', 'profil'))");
        DB::statement("ALTER TABLE konten ADD CONSTRAINT konten_status_check CHECK (status IN ('draf', 'terbit', 'arsip'))");
        DB::statement("ALTER TABLE konten ADD CONSTRAINT konten_slug_format CHECK (slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$')");
        DB::statement("ALTER TABLE konten ADD CONSTRAINT konten_terbit_lengkap CHECK (status <> 'terbit' OR (terbit IS NOT NULL AND published_at IS NOT NULL AND slug_terbit IS NOT NULL))");
        DB::statement("ALTER TABLE konten ADD CONSTRAINT konten_sumber_check CHECK (sumber IN ('pengurus', 'impor'))");
        // Profil situs hanya satu baris.
        DB::statement("CREATE UNIQUE INDEX konten_profil_tunggal ON konten (jenis) WHERE jenis = 'profil'");

        Schema::create('konten_slug_lama', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('konten_id')->constrained('konten')->restrictOnDelete();
            $table->string('jenis', 12);
            $table->string('slug', 120);
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['jenis', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konten_slug_lama');
        Schema::dropIfExists('konten');
        Schema::dropIfExists('konten_media');
    }
};
