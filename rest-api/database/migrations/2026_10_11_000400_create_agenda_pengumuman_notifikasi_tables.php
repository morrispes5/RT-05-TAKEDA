<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ERD v1.1 domain 06 (agenda, pengumuman) dan 07 (notifikasi, preferensi_notifikasi).
| Waktu disimpan UTC (timestamptz), ditampilkan Asia/Jakarta.
| notifikasi: tepat satu kolom konteks terisi kecuali jenis 'sistem'; event_id mencegah duplikat.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('dibuat_oleh_id')->constrained('akun')->restrictOnDelete();
            $table->string('nama', 160);
            $table->timestampTz('mulai_at');
            $table->timestampTz('selesai_at')->nullable();
            $table->string('lokasi', 200);
            $table->text('deskripsi');
            $table->timestampTz('pengingat_terkirim_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('archived_at')->nullable();

            $table->index('mulai_at');
        });
        DB::statement('ALTER TABLE agenda ADD CONSTRAINT agenda_rentang_valid CHECK (selesai_at IS NULL OR selesai_at >= mulai_at)');

        Schema::create('pengumuman', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('dibuat_oleh_id')->constrained('akun')->restrictOnDelete();
            $table->string('judul', 160);
            $table->text('isi');
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('archived_at')->nullable();

            $table->index('published_at');
        });

        Schema::create('preferensi_notifikasi', function (Blueprint $table) {
            $table->foreignUuid('akun_id')->primary()->constrained('akun')->restrictOnDelete();
            $table->boolean('pengumuman')->default(true);
            $table->boolean('agenda')->default(true);
            $table->boolean('pengaduan')->default(true);
            $table->boolean('aspirasi')->default(true);
            $table->boolean('iuran')->default(true);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
        });

        Schema::create('notifikasi', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('event_id');
            $table->foreignUuid('penerima_id')->constrained('akun')->restrictOnDelete();
            $table->foreignUuid('pengumuman_id')->nullable()->constrained('pengumuman')->restrictOnDelete();
            $table->foreignUuid('agenda_id')->nullable()->constrained('agenda')->restrictOnDelete();
            $table->foreignUuid('pengaduan_id')->nullable()->constrained('pengaduan')->restrictOnDelete();
            $table->foreignUuid('aspirasi_id')->nullable()->constrained('aspirasi')->restrictOnDelete();
            $table->uuid('tagihan_id')->nullable();
            $table->string('jenis', 20);
            $table->string('judul', 160);
            $table->text('isi');
            $table->timestampTz('dibaca_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['event_id', 'penerima_id']);
            $table->index(['penerima_id', 'created_at']);
        });
        DB::statement("ALTER TABLE notifikasi ADD CONSTRAINT notifikasi_jenis_check CHECK (jenis IN ('pengumuman', 'agenda', 'pengaduan', 'aspirasi', 'iuran', 'sistem'))");
        DB::statement(<<<'SQL'
            ALTER TABLE notifikasi ADD CONSTRAINT notifikasi_satu_konteks CHECK (
                (jenis = 'sistem' AND num_nonnulls(pengumuman_id, agenda_id, pengaduan_id, aspirasi_id, tagihan_id) = 0)
                OR (jenis = 'pengumuman' AND pengumuman_id IS NOT NULL AND num_nonnulls(agenda_id, pengaduan_id, aspirasi_id, tagihan_id) = 0)
                OR (jenis = 'agenda' AND agenda_id IS NOT NULL AND num_nonnulls(pengumuman_id, pengaduan_id, aspirasi_id, tagihan_id) = 0)
                OR (jenis = 'pengaduan' AND pengaduan_id IS NOT NULL AND num_nonnulls(pengumuman_id, agenda_id, aspirasi_id, tagihan_id) = 0)
                OR (jenis = 'aspirasi' AND aspirasi_id IS NOT NULL AND num_nonnulls(pengumuman_id, agenda_id, pengaduan_id, tagihan_id) = 0)
                OR (jenis = 'iuran' AND tagihan_id IS NOT NULL AND num_nonnulls(pengumuman_id, agenda_id, pengaduan_id, aspirasi_id) = 0)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
        Schema::dropIfExists('preferensi_notifikasi');
        Schema::dropIfExists('pengumuman');
        Schema::dropIfExists('agenda');
    }
};
