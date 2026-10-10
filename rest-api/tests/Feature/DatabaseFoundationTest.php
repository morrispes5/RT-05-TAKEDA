<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Constraint, role, dan tipe kolom fondasi pada PostgreSQL nyata (bukan SQLite). */
class DatabaseFoundationTest extends TestCase
{
    use DatabaseTransactions;

    protected array $connectionsToTransact = ['pgsql'];

    private function assertSqlState(string $state, Closure $statement): void
    {
        try {
            // Nested transaction = savepoint, sehingga transaksi tes tetap dapat dipakai.
            DB::transaction($statement);
            $this->fail("Diharapkan SQLSTATE $state");
        } catch (QueryException $e) {
            $this->assertSame($state, (string) $e->getCode(), $e->getMessage());
        }
    }

    private function idempotency(array $overrides = []): array
    {
        return array_merge([
            'actor_id' => (string) Str::uuid(),
            'route_scope' => 'dues-payments.store',
            'key' => (string) Str::uuid(),
            'request_hash' => hash('sha256', 'payload'),
        ], $overrides);
    }

    public function test_connections_are_postgres_with_separate_roles(): void
    {
        $runtime = DB::connection('pgsql')->selectOne('select current_user as role, version() as version');
        $migrations = DB::connection('pgsql_migrations')->selectOne('select current_user as role');

        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertStringStartsWith('PostgreSQL', $runtime->version);
        $this->assertNotSame($runtime->role, $migrations->role);
        $this->assertFalse((bool) DB::selectOne('select rolsuper from pg_roles where rolname = current_user')->rolsuper);
    }

    public function test_runtime_role_cannot_run_ddl(): void
    {
        $this->assertSqlState('42501', fn () => DB::statement('create table rt05_ddl_probe (id int)'));
        $this->assertSqlState('42501', fn () => DB::statement('drop table system_settings'));
    }

    public function test_runtime_role_has_dml_on_foundation_tables(): void
    {
        DB::table('system_settings')->insert(['key' => 'test.dml', 'value' => json_encode(['a' => 1])]);
        DB::table('system_settings')->where('key', 'test.dml')->update(['version' => 2]);
        $this->assertSame(2, DB::table('system_settings')->where('key', 'test.dml')->value('version'));
        DB::table('system_settings')->where('key', 'test.dml')->delete();
        $this->assertFalse(DB::table('system_settings')->where('key', 'test.dml')->exists());
    }

    public function test_all_timestamps_are_timestamptz(): void
    {
        $columns = DB::select(<<<'SQL'
            select table_name, column_name, data_type from information_schema.columns
            where table_schema = 'public' and data_type like 'timestamp%' and table_name <> 'migrations'
        SQL);

        $this->assertNotEmpty($columns);
        foreach ($columns as $column) {
            $this->assertSame('timestamp with time zone', $column->data_type, "$column->table_name.$column->column_name");
        }
    }

    public function test_audit_logs_are_append_only(): void
    {
        $id = DB::table('audit_logs')->insertGetId([
            'action' => 'test.created',
            'entity_type' => 'test',
            'safe_diff' => json_encode(['field' => 'x']),
        ]);

        $this->assertTrue(Str::isUuid($id));
        $this->assertSqlState('42501', fn () => DB::table('audit_logs')->where('id', $id)->update(['action' => 'diubah']));
        $this->assertSqlState('42501', fn () => DB::table('audit_logs')->where('id', $id)->delete());
        $this->assertSame('test.created', DB::table('audit_logs')->where('id', $id)->value('action'));
    }

    public function test_idempotency_key_is_unique_per_actor_and_scope(): void
    {
        $row = $this->idempotency();
        DB::table('idempotency_requests')->insert($row);

        $this->assertSqlState('23505', fn () => DB::table('idempotency_requests')->insert(
            array_merge($row, ['request_hash' => hash('sha256', 'payload lain')])
        ));

        // Key sama untuk scope lain atau actor lain diperbolehkan.
        DB::table('idempotency_requests')->insert(array_merge($row, ['route_scope' => 'cash.store']));
        DB::table('idempotency_requests')->insert(array_merge($row, ['actor_id' => (string) Str::uuid()]));
        $this->assertSame(3, DB::table('idempotency_requests')->where('key', $row['key'])->count());
    }

    public function test_idempotency_checks_reject_invalid_state(): void
    {
        $this->assertSqlState('23514', fn () => DB::table('idempotency_requests')->insert($this->idempotency(['state' => 'done'])));
        $this->assertSqlState('23514', fn () => DB::table('idempotency_requests')->insert($this->idempotency(['request_hash' => str_repeat('Z', 64)])));
        $this->assertSqlState('23514', fn () => DB::table('idempotency_requests')->insert($this->idempotency(['state' => 'completed'])));
    }

    public function test_outbox_and_settings_checks(): void
    {
        $this->assertSqlState('23514', fn () => DB::table('outbox_events')->insert([
            'type' => 'test.event', 'aggregate_type' => 'test', 'attempts' => -1,
        ]));
        $this->assertSqlState('23514', fn () => DB::table('system_settings')->insert(['key' => 'Bukan Format', 'value' => '{}']));
        $this->assertSqlState('23514', fn () => DB::table('system_settings')->insert(['key' => 'ok.key', 'value' => '{}', 'version' => 0]));

        $id = DB::table('outbox_events')->insertGetId(['type' => 'test.event', 'aggregate_type' => 'test']);
        $event = DB::table('outbox_events')->find($id);
        $this->assertSame(0, $event->attempts);
        $this->assertNull($event->completed_at);
    }

    public function test_db_smoke_command_passes_on_runtime_connection(): void
    {
        $exit = Artisan::call('rt05:db-smoke', ['--connection' => 'pgsql']);

        $this->assertSame(0, $exit, Artisan::output());
        $this->assertStringContainsString('denied', Artisan::output());
    }
}
