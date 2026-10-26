<?php

namespace Tests\Feature;

use App\Domain\Finance\Dues;
use App\Domain\Finance\FlexibleDues;
use App\Domain\Finance\Reconciliation;
use App\Models\KonfirmasiIuran;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Fixtures;
use Tests\Support\OpenApi;
use Tests\TestCase;

class M18FinanceApiTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    private function setupFinance(): array
    {
        config(['finance.v2' => true]);
        $this->travelTo(now()->setDate(2026, 10, 26));
        $resident = $this->warga();
        $admin = $this->pengurus();
        $house = $resident->rumah()->id;
        $this->as($admin);

        return [$resident, $admin, $house];
    }

    private function payload(string $house, int $amount): array
    {
        $data = ['versi_keuangan' => 2, 'rumah_id' => $house, 'periode' => ['2026-10'], 'nominal' => $amount];
        $preview = $this->postJson('/api/v1/admin/dues-payments/preview', $data)->assertOk()->json('data');

        return $data + ['fingerprint' => $preview['fingerprint'], 'tanggal_bayar' => '2026-10-26'];
    }

    public function test_partial_receipt_replay_and_own_balance_are_consistent(): void
    {
        [$resident, , $house] = $this->setupFinance();
        $data = $this->payload($house, 50000) + ['perlakuan_kurang' => 'kurang_bayar'];
        $this->assertSame([], OpenApi::errors($data, 'M18PaymentInput'));
        $headers = ['Idempotency-Key' => (string) Str::uuid()];
        $first = $this->postJson('/api/v1/admin/dues-payments', $data, $headers)->assertCreated();
        $this->postJson('/api/v1/admin/dues-payments', $data, $headers)->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
        $own = $this->as($resident)->getJson('/api/v1/dues/my')->assertOk();
        $own->assertJsonPath('data.bulan_berjalan.sisa', 25000)->assertJsonPath('data.bulan_berjalan.status_pelunasan', 'kurang_bayar');
        $this->assertSame(50000, Dues::balance());
        $this->assertSame(0, Reconciliation::run()['total_masalah']);
        $this->as($this->pengurus('rekap@contoh.test'));
        $recap = $this->getJson('/api/v1/admin/dues-recap?periode=2026-10')->assertOk();
        $this->assertSame([], OpenApi::errors($recap->json(), 'DuesRecapEnvelope'));
        $recap->assertJsonPath('data.sisa', 25000)->assertJsonPath('data.terbayar', 50000);
    }

    public function test_old_client_and_disabled_flag_cannot_write_v2(): void
    {
        [, , $house] = $this->setupFinance();
        $this->postJson('/api/v1/admin/dues-payments', ['rumah_id' => $house])->assertStatus(422)->assertJsonPath('error.code', 'FINANCE_CLIENT_UPDATE_REQUIRED');
        config(['finance.v2' => false]);
        $this->postJson('/api/v1/admin/dues-payments/preview', ['versi_keuangan' => 2])->assertNotFound();
        $this->assertSame(0, DB::table('pembayaran_iuran')->count());
    }

    public function test_resident_cannot_access_admin_preview_rates_or_deposits(): void
    {
        [$resident, , $house] = $this->setupFinance();
        $this->as($resident)->postJson('/api/v1/admin/dues-payments/preview', ['versi_keuangan' => 2])->assertForbidden();
        $this->getJson("/api/v1/admin/houses/$house/deposits")->assertForbidden();
        $this->getJson("/api/v1/admin/houses/$house/dues-rates")->assertForbidden();
        $this->assertSame(0, Dues::balance());
    }

    public function test_confirmation_corrected_amount_requires_reason_and_posts_once(): void
    {
        [$resident, , $house] = $this->setupFinance();
        $confirmation = KonfirmasiIuran::create(['akun_id' => $resident->id, 'rumah_id' => $house, 'periode' => ['2026-10'],
            'nominal' => 75000, 'storage_key' => 'iuran/synthetic.jpg', 'mime' => 'image/jpeg', 'bytes' => 100,
            'lebar' => 10, 'tinggi' => 10, 'status' => 'diajukan']);
        $data = $this->payload($house, 50000) + ['perlakuan_kurang' => 'keringanan'];
        $url = '/api/v1/admin/dues-confirmations/'.$confirmation->id.'/approve';
        $headers = ['Idempotency-Key' => (string) Str::uuid()];
        $this->postJson($url, $data, $headers)->assertStatus(422)->assertJsonPath('error.code', 'REASON_REQUIRED');
        $this->assertSame(0, Dues::balance());
        $data['alasan'] = 'Nominal diverifikasi pengurus';
        $this->postJson($url, $data, $headers)->assertCreated();
        $this->postJson($url, $data, $headers)->assertCreated();
        $this->postJson($url, $data, ['Idempotency-Key' => (string) Str::uuid()])->assertConflict();
        $this->assertSame(75000, $confirmation->refresh()->nominal);
        $this->assertNotNull($confirmation->pembayaran_id);
        try {
            DB::transaction(fn () => DB::table('konfirmasi_iuran')->where('id', $confirmation->id)->update(['nominal' => 50000]));
            $this->fail('Original proof amount must remain immutable');
        } catch (QueryException $e) {
            $this->assertSame('42501', $e->errorInfo[0]);
        }
        $this->assertSame(50000, Dues::balance());
        $this->assertSame(0, Reconciliation::run()['total_masalah']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_preview_is_read_only_and_excess_requires_explicit_choice(): void
    {
        [, , $house] = $this->setupFinance();
        $data = $this->payload($house, 100000);
        $this->assertSame(0, DB::table('pembayaran_iuran')->count());
        $this->postJson('/api/v1/admin/dues-payments', $data, ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(422)->assertJsonPath('error.code', 'EXCESS_CHOICE_REQUIRED');
        $this->assertSame(0, Dues::balance());
    }

    public function test_database_rejects_confirmation_linked_to_other_house_payment(): void
    {
        [$resident, $actor, $house] = $this->setupFinance();
        $other = $this->warga('other')->rumah()->id;
        $payment = FlexibleDues::record($actor, $other, ['2026-10'], 75000, '2026-10-26', null, null, null,
            FlexibleDues::preview($other, ['2026-10'])['fingerprint']);
        $confirmation = KonfirmasiIuran::create(['akun_id' => $resident->id, 'rumah_id' => $house, 'periode' => ['2026-10'],
            'nominal' => 75000, 'storage_key' => 'iuran/synthetic.jpg', 'mime' => 'image/jpeg', 'bytes' => 100,
            'lebar' => 10, 'tinggi' => 10, 'status' => 'diajukan']);
        try {
            DB::transaction(function () use ($confirmation, $payment, $actor) {
                $confirmation->forceFill(['status' => 'selesai', 'pembayaran_id' => $payment->id, 'diproses_oleh_id' => $actor->id, 'diproses_at' => now()])->save();
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
            });
            $this->fail('Payment for another house must not satisfy this confirmation');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);
        }
        $this->assertSame('diajukan', $confirmation->refresh()->status);
        $this->assertSame(0, Reconciliation::run()['total_masalah']);
    }

    public function test_deposit_use_and_correct_keep_cash_unchanged(): void
    {
        [, , $house] = $this->setupFinance();
        $data = $this->payload($house, 100000) + ['perlakuan_lebih' => 'titipan'];
        $this->postJson('/api/v1/admin/dues-payments', $data, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        Dues::generate(Dues::period('2026-11'));
        $fingerprint = FlexibleDues::preview($house, ['2026-11'])['fingerprint'];
        $use = ['versi_keuangan' => 2, 'rumah_id' => $house, 'periode' => ['2026-11'], 'nominal' => 25000,
            'tanggal' => '2026-10-26', 'fingerprint' => $fingerprint];
        $id = $this->postJson('/api/v1/admin/deposit-usages', $use, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.id');
        $use['nominal'] = 10000;
        $use['alasan'] = 'Koreksi jumlah pemakaian';
        $use['fingerprint'] = FlexibleDues::preview($house, ['2026-11'])['fingerprint'];
        $this->postJson("/api/v1/admin/deposit-usages/$id/correct", $use, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $this->getJson("/api/v1/admin/houses/$house/deposits")->assertOk()->assertJsonPath('data.saldo', 15000);
        $this->assertSame(100000, Dues::balance());
        $this->assertSame(0, Reconciliation::run()['total_masalah']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_relief_and_adjustment_corrections_are_atomic(): void
    {
        [, , $house] = $this->setupFinance();
        $charge = DB::table('tagihan_iuran')->where('rumah_id', $house)->value('id');
        $d = ['versi_keuangan' => 2, 'nominal' => 25000, 'alasan' => 'Keringanan keputusan uji'];
        $id = $this->postJson("/api/v1/admin/dues-charges/$charge/reliefs", $d, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.id');
        $d['nominal'] = 100000;
        $this->postJson("/api/v1/admin/dues-reliefs/$id/correct", $d, ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
        $this->assertNull(DB::table('keringanan_iuran')->where('id', $id)->value('dibatalkan_at'));
        $d['nominal'] = 10000;
        $this->postJson("/api/v1/admin/dues-reliefs/$id/correct", $d, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $d['delta'] = -15000;
        $adjustment = $this->postJson("/api/v1/admin/dues-charges/$charge/adjustments", $d, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.id');
        $d['delta'] = -5000;
        $this->postJson("/api/v1/admin/dues-adjustments/$adjustment/correct", $d, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $this->assertSame(60000, FlexibleDues::preview($house, ['2026-10'])['sisa']);
        $this->assertSame(0, Dues::balance());
        $this->assertSame(0, Reconciliation::run()['total_masalah']);
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }

    public function test_common_and_house_rate_corrections_keep_versions(): void
    {
        [, , $house] = $this->setupFinance();
        $d = ['versi_keuangan' => 2, 'mulai_periode' => '2026-11', 'nominal' => 80000, 'alasan' => 'Tarif berdasarkan rapat'];
        $id = $this->postJson('/api/v1/admin/dues-rates', $d, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.id');
        $d['nominal'] = 85000;
        $this->postJson("/api/v1/admin/dues-rates/$id/correct", $d, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $d['nominal'] = 55000;
        $special = $this->postJson("/api/v1/admin/houses/$house/dues-rates", $d, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.id');
        $d['nominal'] = 50000;
        $this->postJson("/api/v1/admin/houses/$house/dues-rates/$special/correct", $d, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        Dues::generate(Dues::period('2026-11'));
        $this->assertSame(50000, FlexibleDues::preview($house, ['2026-11'])['sisa']);
        $this->postJson("/api/v1/admin/houses/$house/dues-rates/$special/correct", $d, ['Idempotency-Key' => (string) Str::uuid()])->assertConflict();
        $this->assertSame(0, Reconciliation::run()['total_masalah']);
    }

    public function test_manual_cash_correction_failure_rolls_back_original_reversal(): void
    {
        [, $actor] = $this->setupFinance();
        $cash = Dues::recordCash($actor, 'pemasukan', 100000, '2026-10-26', 'Uji', 'Penerimaan uji');
        $d = ['versi_keuangan' => 2, 'jenis' => 'pengeluaran', 'nominal' => 150000, 'tanggal' => '2026-10-26',
            'kategori' => 'Uji', 'keterangan' => 'Koreksi pengeluaran uji', 'alasan' => 'Salah jenis pencatatan'];
        $this->postJson("/api/v1/admin/cash-transactions/{$cash->id}/correct", $d, ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422);
        $this->assertSame(100000, Dues::balance());
        $this->assertSame(1, DB::table('transaksi_kas')->count());
        $d['jenis'] = 'pemasukan';
        $d['nominal'] = 50000;
        $this->postJson("/api/v1/admin/cash-transactions/{$cash->id}/correct", $d, ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $this->assertSame(50000, Dues::balance());
        $this->assertSame(3, DB::table('transaksi_kas')->count());
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
    }
}
