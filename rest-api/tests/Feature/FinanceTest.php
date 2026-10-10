<?php

namespace Tests\Feature;

use App\Domain\Finance\Dues;
use App\Exceptions\DomainConflict;
use App\Http\Controllers\Admin\OperationsController;
use App\Models\TagihanIuran;
use App\Models\TarifIuran;
use App\Models\TransaksiKas;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Fixtures;
use Tests\TestCase;

/** Skenario keuangan kritis TESTING.md (FIN). Pembayaran offline; tanpa payment gateway. */
class FinanceTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    private function setupHouse(string $nomor = '1'): array
    {
        $warga = $this->warga($nomor);
        $rumah = $warga->rumah();
        DB::table('rumah')->where('id', $rumah->id)->update(['mulai_tagih' => '2026-10-01']);

        return [$warga, $rumah];
    }

    private function pay(array $body, ?string $key = null)
    {
        return $this->postJson('/api/v1/admin/dues-payments', $body + ['tanggal_bayar' => now('Asia/Jakarta')->toDateString()], ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    public function test_one_house_many_accounts_one_charge_per_month_and_rerun_safe(): void
    {
        [, $rumah] = $this->setupHouse();
        $p = Dues::period('2026-10');

        $this->assertSame(1, Dues::generate($p));
        $this->assertSame(0, Dues::generate($p));
        $this->assertSame(1, TagihanIuran::where('rumah_id', $rumah->id)->count());
        $this->assertSame(75000, TagihanIuran::where('rumah_id', $rumah->id)->value('nominal_tagihan'));
    }

    public function test_rate_change_does_not_alter_existing_charges(): void
    {
        [, $rumah] = $this->setupHouse();
        Dues::generate(Dues::period('2026-10'));
        TarifIuran::create(['nominal' => 80000, 'mulai_periode' => '2026-11-01', 'alasan' => 'tes']);
        Dues::generate(Dues::period('2026-11'));

        $this->assertSame(75000, TagihanIuran::where('rumah_id', $rumah->id)->where('periode', '2026-10-01')->value('nominal_tagihan'));
        $this->assertSame(80000, TagihanIuran::where('rumah_id', $rumah->id)->where('periode', '2026-11-01')->value('nominal_tagihan'));
    }

    public function test_no_charge_before_billing_start(): void
    {
        [, $rumah] = $this->setupHouse();
        DB::table('rumah')->where('id', $rumah->id)->update(['mulai_tagih' => '2026-12-01']);

        $this->assertSame(0, Dues::generate(Dues::period('2026-10')));
    }

    public function test_multi_month_payment_uses_snapshots_and_posts_everything_once(): void
    {
        [, $rumah] = $this->setupHouse();
        Dues::generate(Dues::period('2026-10'));
        TarifIuran::create(['nominal' => 80000, 'mulai_periode' => '2026-11-01', 'alasan' => 'tes']);
        Dues::generate(Dues::period('2026-11'));
        $this->as($this->pengurus());

        $res = $this->pay(['rumah_id' => $rumah->id, 'periode' => ['2026-10', '2026-11'], 'nominal' => 155000])->assertCreated();

        $this->assertSame(['2026-10', '2026-11'], $res->json('data.periode'));
        $this->assertSame(0, TagihanIuran::where('rumah_id', $rumah->id)->where('status', 'belum_bayar')->count());
        $this->assertSame(1, TransaksiKas::where('pembayaran_id', $res->json('data.id'))->count());
        $this->assertSame(155000, Dues::balance());
        $this->assertTrue(DB::table('audit_logs')->where('action', 'pembayaran_iuran.dicatat')->exists());
        $this->assertTrue(DB::table('outbox_events')->where('type', 'iuran.pembayaran_dicatat')->exists());
    }

    public function test_partial_or_over_payment_rejected_without_partial_write(): void
    {
        [, $rumah] = $this->setupHouse();
        Dues::generate(Dues::period('2026-10'));
        $this->as($this->pengurus());

        $this->pay(['rumah_id' => $rumah->id, 'periode' => ['2026-10'], 'nominal' => 50000])->assertStatus(422)->assertJsonPath('error.code', 'AMOUNT_MISMATCH');
        $this->pay(['rumah_id' => $rumah->id, 'periode' => ['2026-10'], 'nominal' => 100000])->assertStatus(422);
        $this->assertSame(0, DB::table('pembayaran_iuran')->count());
        $this->assertSame(0, Dues::balance());
    }

    public function test_idempotency_replay_and_conflict(): void
    {
        [, $rumah] = $this->setupHouse();
        Dues::generate(Dues::period('2026-10'));
        $this->as($this->pengurus());
        $key = (string) Str::uuid();
        $body = ['rumah_id' => $rumah->id, 'periode' => ['2026-10'], 'nominal' => 75000];

        $first = $this->pay($body, $key)->assertCreated();
        $replay = $this->pay($body, $key)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame($first->json('data.id'), $replay->json('data.id'));
        $this->assertSame(1, DB::table('pembayaran_iuran')->count());

        $this->pay($body + ['catatan' => 'beda'], $key)->assertStatus(409)->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_REUSED');
        $this->pay($body)->assertStatus(409)->assertJsonPath('error.code', 'CHARGE_ALREADY_PAID');
        $this->postJson('/api/v1/admin/dues-payments', $body + ['tanggal_bayar' => '2026-10-01'])->assertStatus(422);
    }

    public function test_database_blocks_second_active_allocation_even_if_app_check_bypassed(): void
    {
        [, $rumah] = $this->setupHouse();
        Dues::generate(Dues::period('2026-10'));
        $payment = Dues::recordPayment($this->pengurus(), $rumah->id, ['2026-10'], 75000, '2026-10-10', null);
        $charge = TagihanIuran::where('rumah_id', $rumah->id)->first();

        try {
            DB::transaction(fn () => DB::table('alokasi_pembayaran')->insert([
                'id' => (string) Str::uuid(), 'tagihan_id' => $charge->id, 'pembayaran_id' => $payment->id, 'nominal_alokasi' => 75000,
            ]));
            $this->fail('Alokasi kedua harus ditolak');
        } catch (QueryException $e) {
            $this->assertSame('23505', (string) $e->getCode());
        }
    }

    public function test_reversal_once_reopens_charge_and_balances_ledger(): void
    {
        [, $rumah] = $this->setupHouse();
        Dues::generate(Dues::period('2026-10'));
        $pengurus = $this->pengurus();
        $payment = Dues::recordPayment($pengurus, $rumah->id, ['2026-10'], 75000, '2026-10-10', null);
        $this->as($pengurus);

        $this->postJson("/api/v1/admin/dues-payments/{$payment->id}/reverse", ['alasan' => 'salah rumah'], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertOk()->assertJsonPath('data.status', 'dibatalkan');
        $this->assertSame('belum_bayar', TagihanIuran::where('rumah_id', $rumah->id)->value('status'));
        $this->assertSame(0, Dues::balance());
        $this->assertSame(2, TransaksiKas::count(), 'asal tetap ada + baris pembalikan');

        $this->postJson("/api/v1/admin/dues-payments/{$payment->id}/reverse", ['alasan' => 'lagi'], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(409)->assertJsonPath('error.code', 'PAYMENT_ALREADY_REVERSED');
    }

    public function test_correction_keeps_original_history_and_correct_balance(): void
    {
        [, $rumah] = $this->setupHouse();
        Dues::generate(Dues::period('2026-10'));
        Dues::generate(Dues::period('2026-11'));
        $pengurus = $this->pengurus();
        $payment = Dues::recordPayment($pengurus, $rumah->id, ['2026-10'], 75000, '2026-10-10', null);

        $replacement = Dues::correctPayment($pengurus, $payment->id, 'seharusnya dua bulan', ['2026-10', '2026-11'], 150000, '2026-10-10', null);

        $this->assertSame($payment->id, $replacement->menggantikan_id);
        $this->assertSame('dibatalkan', $payment->refresh()->status);
        $this->assertSame(150000, Dues::balance());
        $this->assertSame(0, TagihanIuran::where('rumah_id', $rumah->id)->where('status', 'belum_bayar')->count());
    }

    public function test_correction_failure_rolls_back_reversal(): void
    {
        [, $rumah] = $this->setupHouse();
        Dues::generate(Dues::period('2026-10'));
        $pengurus = $this->pengurus();
        $payment = Dues::recordPayment($pengurus, $rumah->id, ['2026-10'], 75000, '2026-10-10', null);

        try {
            Dues::correctPayment($pengurus, $payment->id, 'nominal salah', ['2026-10'], 1, '2026-10-10', null);
            $this->fail('Koreksi dengan nominal salah harus gagal');
        } catch (DomainConflict) {
        }
        $this->assertSame('tercatat', $payment->refresh()->status);
        $this->assertSame(75000, Dues::balance());
    }

    public function test_cash_ledger_is_immutable_and_manual_dues_income_impossible(): void
    {
        $pengurus = $this->pengurus();
        $entry = Dues::recordCash($pengurus, 'pengeluaran', 20000, '2026-10-10', 'Kebersihan', 'tes');
        $this->assertSame(-20000, $entry->nominal);

        try {
            DB::transaction(fn () => DB::table('transaksi_kas')->where('id', $entry->id)->update(['nominal' => -1]));
            $this->fail('Ledger harus immutable');
        } catch (QueryException $e) {
            $this->assertSame('42501', (string) $e->getCode());
        }
        try {
            DB::transaction(fn () => DB::table('transaksi_kas')->insert([
                'id' => (string) Str::uuid(), 'jenis' => 'pemasukan', 'sumber' => 'iuran', 'nominal' => 75000, 'tanggal' => '2026-10-10',
                'keterangan' => 'iuran palsu', 'dicatat_oleh_id' => $pengurus->id,
            ]));
            $this->fail('Iuran tanpa pembayaran harus ditolak');
        } catch (QueryException $e) {
            $this->assertSame('23514', (string) $e->getCode());
        }
    }

    public function test_opening_balance_only_once_and_cash_reversal_once(): void
    {
        $pengurus = $this->pengurus();
        Dues::recordCash($pengurus, 'saldo_awal', 100000, '2026-10-01', 'Saldo awal', 'tes');
        $this->expectException(DomainConflict::class);
        Dues::recordCash($pengurus, 'saldo_awal', 100000, '2026-10-01', 'Saldo awal', 'lagi');
    }

    public function test_cash_reversal_once(): void
    {
        $pengurus = $this->pengurus();
        $entry = Dues::recordCash($pengurus, 'pemasukan', 50000, '2026-10-01', 'Donasi', 'tes');
        Dues::reverseCash($pengurus, $entry->id, 'salah');
        $this->assertSame(0, Dues::balance());
        $this->expectException(DomainConflict::class);
        Dues::reverseCash($pengurus, $entry->id, 'lagi');
    }

    public function test_transparency_has_no_personal_data_and_resident_cannot_record(): void
    {
        [$warga] = $this->setupHouse();
        Dues::generate(Dues::period('2026-10'));

        $res = $this->as($warga)->getJson('/api/v1/dues/transparency?periode=2026-10')->assertOk();
        $this->assertStringNotContainsString('Uji Kepala', $res->getContent());
        $this->assertStringNotContainsString('@contoh.test', $res->getContent());
        $this->as($warga)->postJson('/api/v1/admin/dues-payments', [], ['Idempotency-Key' => (string) Str::uuid()])->assertForbidden();
    }

    public function test_my_dues_separates_arrears_and_explains_offline(): void
    {
        [$warga] = $this->setupHouse();
        DB::table('rumah')->update(['mulai_tagih' => now('Asia/Jakarta')->subMonths(2)->startOfMonth()->toDateString()]);
        DB::table('tarif_iuran')->update(['mulai_periode' => now('Asia/Jakarta')->subMonths(2)->startOfMonth()->toDateString()]);
        Dues::catchUp();

        $res = $this->as($warga)->getJson('/api/v1/dues/my')->assertOk();
        $this->assertSame(2, $res->json('data.tunggakan.jumlah_bulan'));
        $this->assertSame(150000, $res->json('data.tunggakan.total'));
        $this->assertNotNull($res->json('data.bulan_berjalan'), 'bulan berjalan menurut Asia/Jakarta harus dibuat catch-up');
        $this->assertStringContainsString('pengurus', $res->json('data.catatan'));
    }

    public function test_csv_export_sanitizes_formula_injection(): void
    {
        $this->assertSame("'=HYPERLINK(1)", OperationsController::safeCell('=HYPERLINK(1)'));
        $this->assertSame('-20000', OperationsController::safeCell(-20000));
        $this->assertSame("'@cmd", OperationsController::safeCell('@cmd'));
    }
}
