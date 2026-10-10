<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Transactional outbox (docs/ASYNC_JOBS.md). Event ditulis dalam transaksi domain yang sama;
| dispatcher/lease/replay dibangun pada M03. safe_payload hanya ID/type/teks aman, bukan PII.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('gen_random_uuid()'));
            $table->string('type', 100);
            $table->string('aggregate_type', 100);
            $table->uuid('aggregate_id')->nullable();
            $table->jsonb('safe_payload')->default(DB::raw("'{}'::jsonb"));
            $table->timestampTz('available_at')->useCurrent();
            $table->timestampTz('dispatch_lease_until')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('last_error_code', 100)->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });

        DB::statement('ALTER TABLE outbox_events ADD CONSTRAINT outbox_events_attempts_nonnegative CHECK (attempts >= 0)');
        DB::statement('ALTER TABLE outbox_events ADD CONSTRAINT outbox_events_completed_after_created CHECK (completed_at IS NULL OR completed_at >= created_at)');
        DB::statement('CREATE INDEX outbox_events_pending_idx ON outbox_events (available_at) WHERE completed_at IS NULL');
        DB::statement('CREATE INDEX outbox_events_aggregate_idx ON outbox_events (aggregate_type, aggregate_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
