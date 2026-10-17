<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| M10 push Android (ERD device_tokens → perangkat_push). Satu baris per instalasi aplikasi.
| Token FCM terenkripsi (APP_KEY); token_hash SHA-256 menjaga satu token hanya milik satu akun.
| Baris terikat sesi Sanctum: logout/revoke menghapus token akses → perangkat ikut terhapus,
| sehingga push tidak pernah sampai ke HP yang sesinya sudah berakhir.
| notifikasi.push_status: observabilitas pengiriman; inbox tetap sumber data final (FR20).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perangkat_push', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->foreignUuid('akun_id')->constrained('akun')->cascadeOnDelete();
            $table->foreignId('personal_access_token_id')->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->string('device_identifier', 200)->unique();
            $table->char('token_hash', 64)->unique();
            $table->text('fcm_token');
            $table->string('platform', 10);
            $table->timestampTz('last_seen_at')->useCurrent();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->nullable();

            $table->index('akun_id');
        });
        DB::statement("ALTER TABLE perangkat_push ADD CONSTRAINT perangkat_push_platform_check CHECK (platform IN ('android'))");

        Schema::table('notifikasi', function (Blueprint $table) {
            $table->string('push_status', 12)->nullable();
            $table->timestampTz('push_at')->nullable();
        });
        DB::statement("ALTER TABLE notifikasi ADD CONSTRAINT notifikasi_push_status_check CHECK (push_status IS NULL OR push_status IN ('terkirim', 'dilewati', 'gagal'))");
        DB::statement('CREATE INDEX notifikasi_push_pending_idx ON notifikasi (event_id) WHERE push_status IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS notifikasi_push_pending_idx');
        DB::statement('ALTER TABLE notifikasi DROP CONSTRAINT IF EXISTS notifikasi_push_status_check');
        Schema::table('notifikasi', function (Blueprint $table) {
            $table->dropColumn(['push_status', 'push_at']);
        });
        Schema::dropIfExists('perangkat_push');
    }
};
