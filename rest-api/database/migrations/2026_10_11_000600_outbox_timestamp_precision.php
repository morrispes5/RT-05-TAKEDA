<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
| timestamptz(0) membulatkan ke detik terdekat (bisa ke atas), sehingga available_at yang
| diisi CURRENT_TIMESTAMP dapat berada di masa depan relatif now() dan event tertahan.
| Kolom waktu outbox memakai presisi mikrodetik.
*/
return new class extends Migration
{
    private const COLUMNS = ['available_at', 'dispatch_lease_until', 'published_at', 'completed_at', 'created_at'];

    public function up(): void
    {
        DB::statement('ALTER TABLE outbox_events DROP CONSTRAINT outbox_events_completed_after_created');
        foreach (self::COLUMNS as $column) {
            DB::statement("ALTER TABLE outbox_events ALTER COLUMN $column TYPE timestamptz(6)");
        }
        DB::statement('ALTER TABLE outbox_events ADD CONSTRAINT outbox_events_completed_after_created CHECK (completed_at IS NULL OR completed_at >= created_at)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE outbox_events DROP CONSTRAINT outbox_events_completed_after_created');
        foreach (self::COLUMNS as $column) {
            DB::statement("ALTER TABLE outbox_events ALTER COLUMN $column TYPE timestamptz(0)");
        }
        DB::statement('ALTER TABLE outbox_events ADD CONSTRAINT outbox_events_completed_after_created CHECK (completed_at IS NULL OR completed_at >= created_at)');
    }
};
