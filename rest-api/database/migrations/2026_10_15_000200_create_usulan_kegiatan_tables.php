<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Usulan kegiatan warga. Bukan agenda kedua: baris agenda tetap satu-satunya
| jadwal resmi. agenda_id diisi hanya saat pengurus memasukkan usulan yang
| sudah disetujui. Dokumen privat, nama berkas acak, tanpa identitas pengusul.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usulan_kegiatan', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('pengusul_id')->constrained('akun')->restrictOnDelete();
            $table->foreignUuid('rumah_id')->nullable()->constrained('rumah')->restrictOnDelete();
            $table->foreignUuid('agenda_id')->nullable()->unique()->constrained('agenda')->restrictOnDelete();
            $table->string('nama', 160);
            $table->text('deskripsi');
            $table->string('lokasi', 200);
            $table->timestampTz('mulai_at');
            $table->timestampTz('selesai_at')->nullable();
            $table->string('status', 20)->default('diajukan');
            $table->unsignedInteger('versi')->default(1);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            $table->index(['pengusul_id', 'created_at']);
            $table->index('status');
        });
        DB::statement("ALTER TABLE usulan_kegiatan ADD CONSTRAINT usulan_kegiatan_status_check CHECK (status IN ('diajukan', 'ditinjau', 'perlu_revisi', 'disetujui', 'ditolak'))");
        DB::statement('ALTER TABLE usulan_kegiatan ADD CONSTRAINT usulan_kegiatan_rentang_valid CHECK (selesai_at IS NULL OR selesai_at >= mulai_at)');

        Schema::create('riwayat_usulan_kegiatan', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('usulan_kegiatan_id')->constrained('usulan_kegiatan')->restrictOnDelete();
            $table->foreignUuid('aktor_id')->constrained('akun')->restrictOnDelete();
            $table->string('status_dari', 20)->nullable();
            $table->string('status_ke', 20);
            $table->text('catatan')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['usulan_kegiatan_id', 'created_at']);
        });

        Schema::create('dokumen_usulan', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('usulan_kegiatan_id')->constrained('usulan_kegiatan')->restrictOnDelete();
            $table->string('storage_key', 200)->unique();
            $table->string('mime', 40);
            $table->unsignedInteger('bytes');
            $table->unsignedSmallInteger('urutan');
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['usulan_kegiatan_id', 'urutan']);
        });

        Schema::table('notifikasi', function (Blueprint $table) {
            $table->foreignUuid('usulan_kegiatan_id')->nullable()->constrained('usulan_kegiatan')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE notifikasi DROP CONSTRAINT IF EXISTS notifikasi_jenis_check');
        DB::statement('ALTER TABLE notifikasi DROP CONSTRAINT IF EXISTS notifikasi_satu_konteks');
        DB::statement("ALTER TABLE notifikasi ADD CONSTRAINT notifikasi_jenis_check CHECK (jenis IN ('pengumuman', 'agenda', 'pengaduan', 'aspirasi', 'iuran', 'sistem', 'usulan'))");
        DB::statement(<<<'SQL'
            ALTER TABLE notifikasi ADD CONSTRAINT notifikasi_satu_konteks CHECK (
                (jenis = 'sistem' AND num_nonnulls(pengumuman_id, agenda_id, pengaduan_id, aspirasi_id, tagihan_id, usulan_kegiatan_id) = 0)
                OR (jenis = 'pengumuman' AND pengumuman_id IS NOT NULL AND num_nonnulls(agenda_id, pengaduan_id, aspirasi_id, tagihan_id, usulan_kegiatan_id) = 0)
                OR (jenis = 'agenda' AND agenda_id IS NOT NULL AND num_nonnulls(pengumuman_id, pengaduan_id, aspirasi_id, tagihan_id, usulan_kegiatan_id) = 0)
                OR (jenis = 'pengaduan' AND pengaduan_id IS NOT NULL AND num_nonnulls(pengumuman_id, agenda_id, aspirasi_id, tagihan_id, usulan_kegiatan_id) = 0)
                OR (jenis = 'aspirasi' AND aspirasi_id IS NOT NULL AND num_nonnulls(pengumuman_id, agenda_id, pengaduan_id, tagihan_id, usulan_kegiatan_id) = 0)
                OR (jenis = 'iuran' AND tagihan_id IS NOT NULL AND num_nonnulls(pengumuman_id, agenda_id, pengaduan_id, aspirasi_id, usulan_kegiatan_id) = 0)
                OR (jenis = 'usulan' AND usulan_kegiatan_id IS NOT NULL AND num_nonnulls(pengumuman_id, agenda_id, pengaduan_id, aspirasi_id, tagihan_id) = 0)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE notifikasi DROP CONSTRAINT IF EXISTS notifikasi_jenis_check');
        DB::statement('ALTER TABLE notifikasi DROP CONSTRAINT IF EXISTS notifikasi_satu_konteks');
        Schema::table('notifikasi', function (Blueprint $table) {
            $table->dropConstrainedForeignId('usulan_kegiatan_id');
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
        Schema::dropIfExists('dokumen_usulan');
        Schema::dropIfExists('riwayat_usulan_kegiatan');
        Schema::dropIfExists('usulan_kegiatan');
    }
};
