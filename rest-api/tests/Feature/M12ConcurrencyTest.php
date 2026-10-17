<?php

namespace Tests\Feature;

use App\Domain\Finance\Dues;
use App\Domain\Finance\Reconciliation;
use App\Models\PembayaranIuran;
use App\Models\TransaksiKas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CommittedData;
use Tests\Support\Concurrent;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * Gate M12 "independent concurrency" dan "timeout replay" (TESTING.md FIN-9/10/13): proses PHP
 * terpisah dengan koneksi Postgres masing-masing, dilepas bersamaan oleh barrier advisory lock.
 */
class M12ConcurrencyTest extends TestCase
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

    private function http(string $uri, string $bearer, array $body, int $i, ?string $key = null): array
    {
        return ['kind' => 'http', 'method' => 'POST', 'uri' => $uri, 'bearer' => $bearer, 'body' => $body, 'ip' => "10.12.0.$i", 'label' => "m12-$i"]
            + ['headers' => ['Idempotency-Key' => $key ?? (string) Str::uuid()]];
    }

    private function house(string $n)
    {
        $rumah = $this->warga($n)->rumah();
        DB::table('rumah')->where('id', $rumah->id)->update(['mulai_tagih' => '2026-10-01']);

        return $rumah;
    }

    /** @param list<array{status:int, code:?string}> $results */
    private function tally(array $results): array
    {
        $t = [];
        foreach ($results as $r) {
            $k = $r['status'].($r['code'] ? ':'.$r['code'] : '');
            $t[$k] = ($t[$k] ?? 0) + 1;
        }
        ksort($t);

        return $t;
    }

    private function body(string $rumahId): array
    {
        return ['rumah_id' => $rumahId, 'periode' => ['2026-10'], 'nominal' => 75000, 'tanggal_bayar' => now('Asia/Jakarta')->toDateString()];
    }

    public function test_two_admins_paying_the_same_charge_with_different_keys_post_once(): void
    {
        $rumah = $this->house('m12a');
        Dues::generate(Dues::period('2026-10'));
        $t1 = $this->pengurus('m12-bendahara@contoh.test')->createToken('race')->plainTextToken;
        $t2 = $this->pengurus('m12-ketua@contoh.test')->createToken('race')->plainTextToken;

        $results = $this->runConcurrently([
            $this->http('/api/v1/admin/dues-payments', $t1, $this->body($rumah->id), 1),
            $this->http('/api/v1/admin/dues-payments', $t2, $this->body($rumah->id), 2),
            $this->http('/api/v1/admin/dues-payments', $t1, $this->body($rumah->id), 3),
        ]);

        $this->assertSame(['201' => 1, '409:CHARGE_ALREADY_PAID' => 2], $this->tally($results));
        $this->assertSame(1, PembayaranIuran::where('rumah_id', $rumah->id)->count());
        $this->assertSame(1, TransaksiKas::where('sumber', 'iuran')->whereIn('pembayaran_id', PembayaranIuran::where('rumah_id', $rumah->id)->pluck('id'))->count());
        $this->assertSame(0, Reconciliation::run()['total_masalah']);
    }

    public function test_timeout_retries_with_the_same_key_in_flight_post_once_and_replay(): void
    {
        $rumah = $this->house('m12b');
        Dues::generate(Dues::period('2026-10'));
        $token = $this->pengurus('m12-retry@contoh.test')->createToken('race')->plainTextToken;
        $key = (string) Str::uuid();

        $results = $this->runConcurrently(array_map(fn ($i) => $this->http('/api/v1/admin/dues-payments', $token, $this->body($rumah->id), $i, $key), [1, 2, 3]));

        $ok = array_filter($results, fn ($r) => $r['status'] === 201);
        $this->assertSame(3, count($ok) + count(array_filter($results, fn ($r) => $r['code'] === 'IDEMPOTENCY_IN_PROGRESS')), json_encode($this->tally($results)));
        $this->assertGreaterThanOrEqual(1, count($ok));
        $this->assertCount(1, array_unique(array_map(fn ($r) => $r['data']['nomor_bukti'], $ok)), 'semua respons sukses adalah posting yang sama');
        $this->assertSame(1, PembayaranIuran::where('rumah_id', $rumah->id)->count());
        $this->assertSame(1, DB::table('idempotency_requests')->where('key', strtolower($key))->count());
    }

    public function test_concurrent_reversals_of_one_payment_reverse_once(): void
    {
        $rumah = $this->house('m12c');
        Dues::generate(Dues::period('2026-10'));
        $admin = $this->pengurus('m12-balik@contoh.test');
        $token = $admin->createToken('race')->plainTextToken;
        $payment = Dues::recordPayment($admin, $rumah->id, ['2026-10'], 75000, now('Asia/Jakarta')->toDateString(), null);

        $results = $this->runConcurrently(array_map(fn ($i) => $this->http("/api/v1/admin/dues-payments/{$payment->id}/reverse", $token, ['alasan' => "Salah catat $i"], $i), [1, 2, 3]));

        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['status'] >= 200 && $r['status'] < 300)), json_encode($this->tally($results)));
        $this->assertSame(2, count(array_filter($results, fn ($r) => $r['code'] === 'PAYMENT_ALREADY_REVERSED')));
        $this->assertSame(1, TransaksiKas::where('jenis', 'pembalikan')->where('sumber', 'iuran')
            ->whereIn('membalik_id', TransaksiKas::where('pembayaran_id', $payment->id)->pluck('id'))->count());
        $this->assertSame('belum_bayar', DB::table('tagihan_iuran')->where('rumah_id', $rumah->id)->value('status'));
        $this->assertSame(0, Reconciliation::run()['total_masalah']);
    }
}
