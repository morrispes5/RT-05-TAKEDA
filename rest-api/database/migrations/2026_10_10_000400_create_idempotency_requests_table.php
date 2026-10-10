<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Idempotency-Key untuk mutation finance (docs/DATABASE.md). Satu baris per actor/route/key;
| request_hash (SHA-256 hex payload ternormalisasi) mengikat payload sehingga key sama dengan
| payload berbeda menjadi 409. actor_id mendapat FK ke users pada M06.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_requests', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->uuid('actor_id');
            $table->string('route_scope', 100);
            $table->uuid('key');
            $table->char('request_hash', 64);
            $table->string('state', 20)->default('processing');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->jsonb('response_body')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('completed_at')->nullable();

            $table->unique(['actor_id', 'route_scope', 'key']);
        });

        DB::statement("ALTER TABLE idempotency_requests ADD CONSTRAINT idempotency_requests_state_check CHECK (state IN ('processing', 'completed'))");
        DB::statement("ALTER TABLE idempotency_requests ADD CONSTRAINT idempotency_requests_hash_hex CHECK (request_hash ~ '^[0-9a-f]{64}$')");
        DB::statement("ALTER TABLE idempotency_requests ADD CONSTRAINT idempotency_requests_completed_has_response CHECK (state <> 'completed' OR (response_status IS NOT NULL AND completed_at IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_requests');
    }
};
