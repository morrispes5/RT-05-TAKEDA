<?php

namespace Tests\Feature;

use App\Domain\Finance\Dues;
use App\Domain\Finance\FlexibleDues as Finance;
use App\Domain\Finance\Reconciliation;
use App\Exceptions\DomainConflict;
use App\Support\Idempotency;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Fixtures;
use Tests\TestCase;

class M18FinanceDomainTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    private function house(string $number = '1'): string
    {
        $this->travelTo(now()->setDate(2026, 10, 25));
        config(['finance.v2' => true]);

        return $this->warga($number)->rumah()->id;
    }

    private function pay($actor, string $house, int $amount, ?string $shortfall = null, ?string $excess = null, array $periods = ['2026-10'])
    {
        return Finance::record($actor, $house, $periods, $amount, '2026-10-25', $shortfall, $excess,
            'Keputusan pengurus uji', Finance::preview($house, $periods)['fingerprint']);
    }

    private function invariant(): void
    {
        $this->assertSame(0, DB::table('rt05_finance_violations')->count());
        $this->assertSame(0, Reconciliation::run()['total_masalah']);
        // DatabaseTransactions rolls back; force deferred triggers to actually execute first.
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
    }

    public function test_meeting_eight_full_two_short_receipts_cash_is_700000(): void
    {
        $actor = $this->pengurus();
        for ($i = 1; $i <= 10; $i++) {
            $house = $this->house((string) $i);
            $this->pay($actor, $house, $i <= 8 ? 75000 : 50000, $i === 9 ? 'keringanan' : 'kurang_bayar');
        }
        $this->assertSame(700000, Dues::balance());
        $this->assertSame(25000, (int) DB::table('rt05_tagihan_saldo')->sum('keringanan'));
        $this->assertSame(25000, (int) DB::table('rt05_tagihan_saldo')->sum('sisa'));
        $this->invariant();
    }

    public function test_partial_payments_and_reversal_keep_remaining_allocations(): void
    {
        $house = $this->house();
        $actor = $this->pengurus();
        $first = $this->pay($actor, $house, 50000, 'kurang_bayar');
        $this->pay($actor, $house, 25000);
        $this->assertSame(0, Finance::preview($house, ['2026-10'])['sisa']);
        Finance::reverse($actor, $first->id, 'Salah pencatatan');
        $this->assertSame(50000, Finance::preview($house, ['2026-10'])['sisa']);
        $this->assertSame(25000, Dues::balance());
        $this->invariant();
    }

    public function test_second_receipt_on_paid_month_can_be_donation_or_deposit(): void
    {
        $actor = $this->pengurus();
        foreach (['sumbangan', 'titipan'] as $i => $choice) {
            $house = $this->house((string) ($i + 1));
            $this->pay($actor, $house, 75000);
            $payment = $this->pay($actor, $house, 25000, null, $choice);
            $this->assertSame(25000, (int) $payment->{'nominal_'.$choice});
        }
        $this->assertSame(200000, Dues::balance());
        $this->assertSame(25000, (int) DB::table('rt05_titipan_saldo')->sum('saldo'));
        $this->invariant();
    }

    public function test_deposit_use_and_reversal_do_not_duplicate_cash(): void
    {
        $house = $this->house();
        $actor = $this->pengurus();
        $payment = $this->pay($actor, $house, 100000, null, 'titipan');
        Dues::generate(Dues::period('2026-11'));
        $usage = Finance::useDeposit($actor, $house, ['2026-11'], 25000, '2026-10-25', Finance::preview($house, ['2026-11'])['fingerprint']);
        $this->assertSame(100000, Dues::balance());
        $this->assertSame(50000, Finance::preview($house, ['2026-11'])['sisa']);
        $this->invariant();
        try {
            Finance::reverse($actor, $payment->id, 'Salah pencatatan');
            $this->fail('Deposit already used must block source reversal');
        } catch (DomainConflict $e) {
            $this->assertStringContainsString('titipan', $e->getMessage());
        }
        Finance::reverseDeposit($actor, $usage, 'Salah periode pemakaian');
        Finance::reverse($actor, $payment->id, 'Salah pencatatan');
        $this->assertSame(0, Dues::balance());
        $this->assertSame(0, (int) DB::table('rt05_titipan_saldo')->sum('saldo'));
        $this->invariant();
    }

    public function test_relief_reverses_with_payment_and_never_counts_as_cash(): void
    {
        $house = $this->house();
        $actor = $this->pengurus();
        $payment = $this->pay($actor, $house, 50000, 'keringanan');
        $this->assertSame(50000, Dues::balance());
        $this->assertSame(0, Finance::preview($house, ['2026-10'])['sisa']);
        $this->invariant();
        Finance::reverse($actor, $payment->id, 'Salah input penerimaan');
        $this->assertSame(75000, Finance::preview($house, ['2026-10'])['sisa']);
        $this->invariant();
    }

    public function test_stale_preview_rejects_second_payment_without_reinterpreting_as_excess(): void
    {
        $house = $this->house();
        $actor = $this->pengurus();
        $fingerprint = Finance::preview($house, ['2026-10'])['fingerprint'];
        $this->pay($actor, $house, 75000);
        try {
            Finance::record($actor, $house, ['2026-10'], 75000, '2026-10-25', null, 'titipan', null, $fingerprint);
            $this->fail('Stale preview must fail');
        } catch (DomainConflict $e) {
            $this->assertStringContainsString('berubah', $e->getMessage());
        }
        $this->assertSame(75000, Dues::balance());
        $this->invariant();
    }

    public function test_special_rate_starts_future_month_and_old_snapshot_never_changes(): void
    {
        $house = $this->house();
        $actor = $this->pengurus();
        Finance::setHouseRate($actor, $house, '2026-11', 50000, 'Keputusan rapat uji');
        Dues::generate(Dues::period('2026-11'));
        $this->assertSame(75000, Finance::preview($house, ['2026-10'])['sisa']);
        $this->assertSame(50000, Finance::preview($house, ['2026-11'])['sisa']);
        Finance::setHouseRate($actor, $house, '2026-12', null, 'Kembali tarif umum');
        Dues::generate(Dues::period('2026-12'));
        $this->assertSame(75000, Finance::preview($house, ['2026-12'])['sisa']);
        $this->invariant();
    }

    public function test_failed_correction_rolls_back_reversal(): void
    {
        $house = $this->house();
        $actor = $this->pengurus();
        $payment = $this->pay($actor, $house, 75000);
        try {
            Finance::correct($actor, $payment->id, 'Koreksi nominal salah', ['2026-10'], 50000, '2026-10-25', null, null, Finance::preview($house, ['2026-10'])['fingerprint']);
            $this->fail('Missing shortfall choice must roll back the whole correction');
        } catch (DomainConflict) {
            $this->assertSame('tercatat', $payment->refresh()->status);
        }
        $this->assertSame(75000, Dues::balance());
        $this->invariant();
    }

    private function rejectSql(callable $write): void
    {
        try {
            DB::transaction(function () use ($write) {
                $write();
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail('Database must reject invalid ledger state');
        } catch (QueryException $e) {
            $this->assertContains($e->errorInfo[0], ['23514', '42501']);
        }
        $this->invariant();
    }

    public function test_runtime_sql_cannot_modify_payment_amount_or_remove_history(): void
    {
        $house = $this->house();
        $payment = $this->pay($this->pengurus(), $house, 75000);
        $this->rejectSql(fn () => DB::table('pembayaran_iuran')->where('id', $payment->id)->update(['total_bayar' => 70000]));
        $this->rejectSql(fn () => DB::table('pembayaran_iuran')->where('id', $payment->id)->delete());
    }

    public function test_runtime_sql_cannot_fake_paid_status_or_cross_house_relief(): void
    {
        $house = $this->house();
        $other = $this->house('2');
        $actor = $this->pengurus();
        $payment = $this->pay($actor, $house, 75000);
        $charge = DB::table('tagihan_iuran')->where('rumah_id', $other)->value('id');
        $this->rejectSql(fn () => DB::table('tagihan_iuran')->where('id', $charge)->update(['status' => 'lunas']));
        $this->rejectSql(function () use ($charge, $payment, $actor) {
            DB::table('keringanan_iuran')->insert(['tagihan_id' => $charge, 'pembayaran_id' => $payment->id, 'nominal' => 75000,
                'alasan' => 'Keringanan lintas rumah', 'dicatat_oleh_id' => $actor->id]);
            DB::table('tagihan_iuran')->where('id', $charge)->update(['status' => 'lunas']);
        });
    }

    public function test_runtime_sql_cannot_create_cashless_receipt_or_negative_deposit(): void
    {
        $house = $this->house();
        $actor = $this->pengurus();
        $this->rejectSql(fn () => DB::table('pembayaran_iuran')->insert(['rumah_id' => $house, 'diterima_oleh_id' => $actor->id,
            'nomor_bukti' => 'UJI-TANPA-KAS', 'tanggal_bayar' => '2026-10-25', 'total_bayar' => 75000,
            'nominal_sumbangan' => 75000, 'versi_keuangan' => 2, 'status' => 'tercatat']));
        $payment = $this->pay($actor, $house, 100000, null, 'titipan');
        $lot = DB::table('mutasi_titipan')->where('pembayaran_id', $payment->id)->first();
        $this->rejectSql(function () use ($house, $actor, $payment, $lot) {
            $usage = (string) Str::uuid();
            DB::table('penggunaan_titipan')->insert(['id' => $usage, 'rumah_id' => $house, 'nominal' => 30000, 'tanggal' => '2026-10-25', 'dicatat_oleh_id' => $actor->id]);
            DB::table('mutasi_titipan')->insert(['rumah_id' => $house, 'pembayaran_id' => $payment->id, 'lot_id' => $lot->id,
                'penggunaan_titipan_id' => $usage, 'jenis' => 'pakai', 'nominal' => 30000, 'arah' => -1, 'dicatat_oleh_id' => $actor->id]);
        });
    }

    public function test_charge_adjustment_preserves_snapshot_and_has_no_cash_effect(): void
    {
        $house = $this->house();
        $actor = $this->pengurus();
        $charge = DB::table('tagihan_iuran')->where('rumah_id', $house)->value('id');
        Finance::adjustCharge($actor, $charge, -25000, 'Koreksi hasil rapat');
        $this->assertSame(75000, DB::table('tagihan_iuran')->where('id', $charge)->value('nominal_tagihan'));
        $this->assertSame(50000, Finance::preview($house, ['2026-10'])['sisa']);
        $this->assertSame(0, Dues::balance());
        $this->pay($actor, $house, 50000);
        $this->invariant();
    }

    public function test_full_relief_without_receipt_and_reversal_have_zero_cash(): void
    {
        $house = $this->house();
        $actor = $this->pengurus();
        $charge = DB::table('tagihan_iuran')->where('rumah_id', $house)->value('id');
        $id = Finance::relieve($actor, $charge, 75000, 'Pembebasan khusus rapat');
        $this->assertSame(0, Finance::preview($house, ['2026-10'])['sisa']);
        $this->assertSame(0, Dues::balance());
        $this->invariant();
        Finance::reverseRelief($actor, $id, 'Koreksi keputusan rapat');
        $this->assertSame(75000, Finance::preview($house, ['2026-10'])['sisa']);
        $this->assertSame(0, DB::table('pembayaran_iuran')->count());
        $this->invariant();
    }

    public function test_adjustment_reversal_is_recorded_without_mutating_snapshot(): void
    {
        $house = $this->house();
        $actor = $this->pengurus();
        $charge = DB::table('tagihan_iuran')->where('rumah_id', $house)->value('id');
        $id = Finance::adjustCharge($actor, $charge, -25000, 'Koreksi pertama uji');
        Finance::reverseAdjustment($actor, $id, 'Pembalikan koreksi uji');
        $this->assertSame(75000, Finance::preview($house, ['2026-10'])['sisa']);
        $this->assertSame(2, DB::table('penyesuaian_tagihan')->count());
        $this->assertSame(0, Dues::balance());
        $this->invariant();
    }

    public function test_idempotency_normalizes_nested_object_keys_but_preserves_values(): void
    {
        $actor = $this->pengurus();
        $request = Request::create('/test', 'POST');
        $request->headers->set('Idempotency-Key', (string) Str::uuid());
        $calls = 0;
        $mutation = function () use (&$calls) {
            $calls++;

            return response()->json(['receipt' => 'synthetic'], 201);
        };
        Idempotency::run($request, $actor, 'nested-test', ['choice' => ['amount' => 50000, 'kind' => 'titipan']], $mutation);
        $replay = Idempotency::run($request, $actor, 'nested-test', ['choice' => ['kind' => 'titipan', 'amount' => 50000]], $mutation);
        $this->assertSame(1, $calls);
        $this->assertSame('true', $replay->headers->get('Idempotent-Replayed'));
        try {
            Idempotency::run($request, $actor, 'nested-test', ['choice' => ['kind' => 'titipan', 'amount' => 25000]], $mutation);
            $this->fail('A changed amount must conflict');
        } catch (DomainConflict $e) {
            $this->assertSame('IDEMPOTENCY_KEY_REUSED', $e->errorCode);
        }
    }
}
