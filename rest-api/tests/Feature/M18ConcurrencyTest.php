<?php

namespace Tests\Feature;

use App\Domain\Finance\Dues;
use App\Domain\Finance\FlexibleDues;
use App\Domain\Finance\Reconciliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CommittedData;
use Tests\Support\Concurrent;
use Tests\Support\Fixtures;
use Tests\TestCase;

class M18ConcurrencyTest extends TestCase
{
    use CommittedData, Concurrent, Fixtures;

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

    private function race(bool $sameKey): void
    {
        $house = $this->warga('m18race')->rumah()->id;
        Dues::generate(Dues::period('2026-10'));
        $actor = $this->pengurus();
        $body = ['house' => $house, 'periods' => ['2026-10'], 'amount' => 100000,
            'fingerprint' => FlexibleDues::preview($house, ['2026-10'])['fingerprint']];
        $key = (string) Str::uuid();
        $results = $this->runConcurrently(array_map(fn ($i) => ['kind' => 'm18-payment', 'actor' => $actor->id,
            'key' => $sameKey ? $key : (string) Str::uuid(), 'body' => $body, 'label' => 'm18-'.$i], [1, 2, 3]));
        $success = array_values(array_filter($results, fn ($r) => $r['status'] === 201));
        $this->assertCount($sameKey ? 3 : 1, $success);
        $this->assertCount(1, array_unique(array_column(array_column($success, 'data'), 'id')));
        $this->assertSame(1, DB::table('pembayaran_iuran')->count());
        $this->assertSame(100000, Dues::balance());
        $this->assertSame(25000, (int) DB::table('rt05_titipan_saldo')->sum('saldo'));
        $this->assertSame(0, Reconciliation::run()['total_masalah']);
    }

    public function test_three_retries_same_key_create_one_receipt_and_one_deposit(): void
    {
        $this->race(true);
    }

    public function test_different_keys_with_stale_preview_cannot_duplicate_receipt(): void
    {
        $this->race(false);
    }
}
