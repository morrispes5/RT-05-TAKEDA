<?php

namespace Tests\Feature;

use App\Domain\Operations\OutboxDispatcher;
use App\Domain\Services\Photo;
use App\Exceptions\DomainConflict;
use App\Jobs\ProcessOutboxEvent;
use App\Models\OutboxEvent;
use App\Support\Outbox;
use App\Support\SharedHosting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SharedHostingTest extends TestCase
{
    private array $events = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'rt05.deployment_profile' => 'shared',
            'queue.default' => 'database',
            'rt05.outbox.queue_connection' => 'database',
            'cache.default' => 'file',
            'session.driver' => 'file',
            'rt05.heartbeat.store' => 'file',
        ]);
        Cache::store('file')->forget('rt05:shared:awake');
        Cache::store('file')->forget('rt05:shared:last-sweep');
    }

    protected function tearDown(): void
    {
        // Synthetic *_test DB only (guarded by tests/bootstrap.php); no production access.
        DB::table('jobs')->delete();
        OutboxEvent::whereIn('id', $this->events)->delete();
        Cache::store('file')->forget('rt05:shared:awake');
        Cache::store('file')->forget('rt05:shared:last-sweep');
        parent::tearDown();
    }

    public function test_outbox_wakes_only_after_commit_and_rollback_leaves_no_hint(): void
    {
        DB::beginTransaction();
        $event = Outbox::record('rt05.smoke', 'smoke', null);
        $this->events[] = $event->id;
        $this->assertFalse(SharedHosting::awake());
        DB::rollBack();
        $this->assertFalse(SharedHosting::awake());
        $this->assertNull(OutboxEvent::find($event->id));

        DB::beginTransaction();
        $event = Outbox::record('rt05.smoke', 'smoke', null);
        $this->events[] = $event->id;
        $this->assertFalse(SharedHosting::awake());
        DB::commit();
        $this->assertTrue(SharedHosting::awake());
    }

    public function test_database_queue_after_commit_and_rollback(): void
    {
        DB::beginTransaction();
        Queue::connection('database')->push(new ProcessOutboxEvent('00000000-0000-0000-0000-000000000001'));
        $this->assertSame(0, DB::table('jobs')->count());
        DB::rollBack();
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertFalse(SharedHosting::awake());

        DB::transaction(fn () => Queue::connection('database')->push(new ProcessOutboxEvent('00000000-0000-0000-0000-000000000001')));
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertTrue(SharedHosting::awake());
    }

    public function test_persistent_queue_processes_outbox_after_hint_is_lost(): void
    {
        $event = DB::transaction(fn () => Outbox::record('rt05.smoke', 'smoke', null, ['penerima' => []]));
        $this->events[] = $event->id;
        Cache::store('file')->forget('rt05:shared:awake');
        $this->assertFalse(SharedHosting::awake());
        $result = OutboxDispatcher::dispatch();
        $this->assertSame(1, $result['enqueued']);
        $this->assertNull($event->fresh()->completed_at);
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'notifications', '--once' => true])->assertExitCode(0);
        $this->assertNotNull($event->fresh()->completed_at);
        $this->assertSame(0, Queue::connection('database')->size('notifications'));
    }

    public function test_idle_tick_does_not_query_database(): void
    {
        $this->travelTo(now()->setTime(12, 7, 0));
        Cache::store('file')->put('rt05:shared:last-sweep', time(), 3600);
        $queries = [];
        DB::listen(function ($event) use (&$queries): void {
            $queries[] = $event->sql;
        });
        $this->artisan('rt05:shared-tick')->assertExitCode(0);
        $this->assertSame([], $queries, 'An idle cron must not wake Neon.');
        $this->travelBack();
    }

    public function test_tick_refuses_vps_profile(): void
    {
        config(['rt05.deployment_profile' => 'vps']);
        $this->artisan('rt05:shared-tick')->assertExitCode(2);
    }

    public function test_shared_rejects_photo_above_byte_limit_before_decode(): void
    {
        $this->expectException(DomainConflict::class);
        Photo::stage(UploadedFile::fake()->create('oversized.jpg', 4097, 'image/jpeg'));
    }

    public function test_shared_photo_is_reencoded_and_kept_on_private_disk(): void
    {
        Storage::fake('local');
        $meta = Photo::stage(UploadedFile::fake()->image('photo.jpg', 2000, 1000));
        $result = Photo::transcode($meta['storage_key'], 'pengaduan/test.jpg');
        $this->assertSame('image/jpeg', $result['mime']);
        $this->assertSame(1600, $result['lebar']);
        Storage::disk('local')->assertExists('pengaduan/test.jpg');
    }
}
