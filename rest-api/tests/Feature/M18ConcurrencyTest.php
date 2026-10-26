<?php

namespace Tests\Feature;

use App\Domain\Finance\Dues;
use App\Domain\Finance\FlexibleDues;
use App\Domain\Finance\Reconciliation;
use App\Models\KonfirmasiIuran;
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

    public function test_two_independent_deposit_requests_cannot_overdraw(): void
    {
        $house = $this->warga('m18deposit')->rumah()->id;
        $actor = $this->pengurus();
        Dues::generate(Dues::period('2026-10'));
        Dues::generate(Dues::period('2026-11'));
        FlexibleDues::record($actor, $house, ['2026-10'], 100000, '2026-10-25', null, 'titipan', null,
            FlexibleDues::preview($house, ['2026-10'])['fingerprint']);
        $token = $actor->createToken('synthetic-race')->plainTextToken;
        $body = ['versi_keuangan' => 2, 'rumah_id' => $house, 'periode' => ['2026-11'], 'nominal' => 25000,
            'tanggal' => '2026-10-25', 'fingerprint' => FlexibleDues::preview($house, ['2026-11'])['fingerprint']];
        $results = $this->runConcurrently(array_map(fn ($i) => ['kind' => 'http', 'method' => 'POST', 'uri' => '/api/v1/admin/deposit-usages',
            'bearer' => $token, 'body' => $body, 'headers' => ['Idempotency-Key' => (string) Str::uuid()],
            'label' => 'm18-use-'.$i, 'env' => ['RT05_FINANCE_V2' => 'true']], [1, 2]));
        $this->assertCount(1, array_filter($results, fn ($r) => $r['status'] === 201));
        $this->assertCount(1, array_filter($results, fn ($r) => $r['status'] === 409));
        $this->assertSame(0, (int) DB::table('rt05_titipan_saldo')->sum('saldo'));
        $this->assertSame(100000, Dues::balance());
        $this->assertSame(0, Reconciliation::run()['total_masalah']);
    }

    public function test_two_admins_approving_same_confirmation_post_one_receipt(): void
    {
        $resident = $this->warga('m18approve');
        $house = $resident->rumah()->id;
        Dues::generate(Dues::period('2026-10'));
        $confirmation = KonfirmasiIuran::create(['akun_id' => $resident->id, 'rumah_id' => $house, 'periode' => ['2026-10'],
            'nominal' => 75000, 'storage_key' => 'iuran/synthetic.jpg', 'mime' => 'image/jpeg', 'bytes' => 100,
            'lebar' => 10, 'tinggi' => 10, 'status' => 'diajukan']);
        $tokens = [$this->pengurus()->createToken('synthetic-race')->plainTextToken,
            $this->pengurus('pengurus2@contoh.test')->createToken('synthetic-race')->plainTextToken];
        $body = ['versi_keuangan' => 2, 'periode' => ['2026-10'], 'nominal' => 50000, 'tanggal_bayar' => '2026-10-25',
            'perlakuan_kurang' => 'keringanan', 'alasan' => 'Nominal bukti terkoreksi', 'fingerprint' => FlexibleDues::preview($house, ['2026-10'])['fingerprint']];
        $results = $this->runConcurrently(array_map(fn ($token, $i) => ['kind' => 'http', 'method' => 'POST',
            'uri' => '/api/v1/admin/dues-confirmations/'.$confirmation->id.'/approve', 'bearer' => $token, 'body' => $body,
            'headers' => ['Idempotency-Key' => (string) Str::uuid()], 'label' => 'm18-approve-'.$i, 'env' => ['RT05_FINANCE_V2' => 'true']], $tokens, [1, 2]));
        $this->assertCount(1, array_filter($results, fn ($r) => $r['status'] === 201));
        $this->assertCount(1, array_filter($results, fn ($r) => $r['code'] === 'ALREADY_PROCESSED'));
        $this->assertSame(50000, Dues::balance());
        $this->assertSame(1, DB::table('pembayaran_iuran')->count());
        $this->assertSame(0, Reconciliation::run()['total_masalah']);
    }
}
