<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ERD v1.1 domain 04. Pengaduan: diajukan → diproses → selesai. Aspirasi: diajukan → ditinjau → selesai.
| sembunyikan_identitas hanya menyembunyikan pelapor dari warga lain; pengurus tetap melihat.
| versi = optimistic lock untuk perubahan status (409 bila basi).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori_pengaduan', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('nama', 80)->unique();
            $table->boolean('aktif')->default(true);
            $table->timestampTz('created_at')->useCurrent();
        });

        Schema::create('pengaduan', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('pelapor_id')->constrained('akun')->restrictOnDelete();
            $table->foreignUuid('kategori_id')->constrained('kategori_pengaduan')->restrictOnDelete();
            $table->foreignUuid('rumah_id')->nullable()->constrained('rumah')->restrictOnDelete();
            $table->string('judul', 160);
            $table->text('deskripsi');
            $table->boolean('sembunyikan_identitas')->default(false);
            $table->string('status', 20)->default('diajukan');
            $table->unsignedInteger('versi')->default(1);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('archived_at')->nullable();

            $table->index(['status', 'created_at']);
        });
        DB::statement("ALTER TABLE pengaduan ADD CONSTRAINT pengaduan_status_check CHECK (status IN ('diajukan', 'diproses', 'selesai'))");

        Schema::create('foto_pengaduan', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('pengaduan_id')->constrained('pengaduan')->restrictOnDelete();
            // Kunci acak; tidak memuat nama/email pelapor.
            $table->string('storage_key', 200)->unique();
            $table->string('mime', 40);
            $table->unsignedInteger('bytes');
            $table->unsignedSmallInteger('lebar');
            $table->unsignedSmallInteger('tinggi');
            $table->unsignedSmallInteger('urutan');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['pengaduan_id', 'urutan']);
        });

        Schema::create('riwayat_pengaduan', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('pengaduan_id')->constrained('pengaduan')->restrictOnDelete();
            $table->foreignUuid('aktor_id')->constrained('akun')->restrictOnDelete();
            $table->string('status_dari', 20)->nullable();
            $table->string('status_ke', 20);
            $table->text('catatan')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['pengaduan_id', 'created_at']);
        });

        Schema::create('aspirasi', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('pengirim_id')->constrained('akun')->restrictOnDelete();
            $table->string('judul', 160);
            $table->text('isi');
            $table->boolean('sembunyikan_identitas')->default(false);
            $table->string('status', 20)->default('diajukan');
            $table->unsignedInteger('versi')->default(1);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('archived_at')->nullable();

            $table->index(['status', 'created_at']);
        });
        DB::statement("ALTER TABLE aspirasi ADD CONSTRAINT aspirasi_status_check CHECK (status IN ('diajukan', 'ditinjau', 'selesai'))");

        Schema::create('riwayat_aspirasi', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('aspirasi_id')->constrained('aspirasi')->restrictOnDelete();
            $table->foreignUuid('aktor_id')->constrained('akun')->restrictOnDelete();
            $table->string('status_dari', 20)->nullable();
            $table->string('status_ke', 20);
            $table->text('catatan')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['aspirasi_id', 'created_at']);
        });

        DB::table('kategori_pengaduan')->insert(array_map(fn ($nama) => ['nama' => $nama], [
            'Kebersihan', 'Keamanan', 'Infrastruktur', 'Fasilitas umum', 'Ketertiban', 'Lainnya',
        ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_aspirasi');
        Schema::dropIfExists('aspirasi');
        Schema::dropIfExists('riwayat_pengaduan');
        Schema::dropIfExists('foto_pengaduan');
        Schema::dropIfExists('pengaduan');
        Schema::dropIfExists('kategori_pengaduan');
    }
};
