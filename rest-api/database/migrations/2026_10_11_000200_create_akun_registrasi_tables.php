<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| ERD v1.1 domain 03 — akun, token_registrasi, permohonan_akun, ditambah tabel framework
| personal_access_tokens (Sanctum, UUID) dan password_reset_tokens.
| peran: warga | pengurus (semua pengurus setara). jenis: utama | tambahan.
| status: menunggu (akun tambahan belum disetujui) | aktif | nonaktif | diarsipkan.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akun', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('warga_id')->nullable()->unique()->constrained('warga')->restrictOnDelete();
            $table->string('nama', 120);
            $table->string('email', 254)->unique();
            $table->string('password_hash', 255);
            $table->string('peran', 20)->default('warga');
            $table->string('jenis', 20)->default('utama');
            $table->string('status', 20)->default('aktif');
            $table->timestampTz('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
        });
        DB::statement("ALTER TABLE akun ADD CONSTRAINT akun_peran_check CHECK (peran IN ('warga', 'pengurus'))");
        DB::statement("ALTER TABLE akun ADD CONSTRAINT akun_jenis_check CHECK (jenis IN ('utama', 'tambahan'))");
        DB::statement("ALTER TABLE akun ADD CONSTRAINT akun_status_check CHECK (status IN ('menunggu', 'aktif', 'nonaktif', 'diarsipkan'))");
        DB::statement('ALTER TABLE akun ADD CONSTRAINT akun_email_lowercase CHECK (email = lower(email))');

        Schema::create('token_registrasi', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('dibuat_oleh_id')->nullable()->constrained('akun')->restrictOnDelete();
            // Nilai terenkripsi APP_KEY untuk lihat/salin pengurus; digest HMAC untuk verifikasi.
            $table->text('kode_terenkripsi');
            $table->char('kode_digest', 64);
            $table->boolean('aktif')->default(true);
            $table->timestampTz('berlaku_sampai')->nullable();
            $table->timestampTz('dicabut_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement('CREATE UNIQUE INDEX token_registrasi_satu_aktif ON token_registrasi ((true)) WHERE aktif');

        Schema::create('permohonan_akun', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('akun_id')->unique()->constrained('akun')->restrictOnDelete();
            $table->foreignUuid('token_id')->nullable()->constrained('token_registrasi')->restrictOnDelete();
            $table->string('jenis', 20);
            $table->string('status', 20);
            // Akun tambahan: rumah dicocokkan diam-diam dari alamat; pemohon tidak melihat data rumah.
            $table->foreignUuid('rumah_id')->nullable()->constrained('rumah')->restrictOnDelete();
            $table->string('nama_pemohon', 120)->nullable();
            $table->string('hubungan_keluarga', 40)->nullable();
            $table->string('alamat_diajukan', 220)->nullable();
            $table->foreignUuid('diproses_oleh_id')->nullable()->constrained('akun')->restrictOnDelete();
            $table->timestampTz('diproses_at')->nullable();
            $table->text('catatan')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
        DB::statement("ALTER TABLE permohonan_akun ADD CONSTRAINT permohonan_jenis_check CHECK (jenis IN ('utama', 'tambahan'))");
        DB::statement("ALTER TABLE permohonan_akun ADD CONSTRAINT permohonan_status_check CHECK (status IN ('diajukan', 'disetujui', 'ditolak'))");

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestampTz('last_used_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampsTz();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
        });

        // FK yang ditunda sejak M02 (ADR16).
        DB::statement('ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_actor_fk FOREIGN KEY (actor_id) REFERENCES akun (id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE idempotency_requests ADD CONSTRAINT idempotency_actor_fk FOREIGN KEY (actor_id) REFERENCES akun (id) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE idempotency_requests DROP CONSTRAINT IF EXISTS idempotency_actor_fk');
        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_actor_fk');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('permohonan_akun');
        Schema::dropIfExists('token_registrasi');
        Schema::dropIfExists('akun');
    }
};
