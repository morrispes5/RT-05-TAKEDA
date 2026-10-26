<?php

namespace Tests\Feature;

use App\Domain\Finance\Dues;
use App\Domain\Finance\FlexibleDues;
use App\Domain\Finance\Reconciliation;
use App\Domain\Operations\OutboxDispatcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\CommittedData;
use Tests\Support\Fixtures;
use Tests\TestCase;

class M18DurabilityTest extends TestCase
{
    use CommittedData, Fixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markCommittedData();
    }

    protected function tearDown(): void
    {
        $this->purgeCommittedData();
        parent::tearDown();
    }

    public function test_unavailable_cache_and_queue_cannot_undo_committed_money_or_duplicate_retry(): void
    {
        config(['finance.v2' => true, 'rt05.deployment_profile' => 'vps']);
        $house = $this->warga('durable')->rumah()->id;
        $actor = $this->pengurus();
        $period = now('Asia/Jakarta')->format('Y-m');
        Dues::generate(Dues::period($period));
        $body = ['versi_keuangan' => 2, 'rumah_id' => $house, 'periode' => [$period], 'nominal' => 100000,
            'tanggal_bayar' => now('Asia/Jakarta')->toDateString(), 'perlakuan_lebih' => 'titipan', 'fingerprint' => FlexibleDues::preview($house, [$period])['fingerprint']];
        $headers = ['Idempotency-Key' => (string) Str::uuid()];
        Cache::shouldReceive('put')->andThrow(new \RuntimeException('Synthetic cache outage'));
        $first = $this->as($actor)->postJson('/api/v1/admin/dues-payments', $body, $headers)->assertCreated();
        $this->assertSame(1, DB::connection('pgsql_migrations')->table('pembayaran_iuran')->where('id', $first->json('data.id'))->count());
        Queue::shouldReceive('connection')->andThrow(new \RuntimeException('Synthetic queue outage'));
        $dispatched = OutboxDispatcher::dispatch();
        $this->assertGreaterThan(0, $dispatched['failed']);
        $this->assertSame(100000, Dues::balance());
        $this->postJson('/api/v1/admin/dues-payments', $body, $headers)->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        $this->assertSame(1, DB::table('pembayaran_iuran')->count());
        $this->assertSame(0, Reconciliation::run()['total_masalah']);
    }
}
