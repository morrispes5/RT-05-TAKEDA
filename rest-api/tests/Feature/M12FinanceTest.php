<?php

namespace Tests\Feature;

use App\Domain\Finance\Dues;
use App\Domain\Finance\Reconciliation;
use App\Models\PembayaranIuran;
use App\Models\TagihanIuran;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * M12 (TESTING.md FIN-12/15/16/17 dan gate CSV): saldo negatif wajib konfirmasi, rekonsiliasi buku,
 * arsip rumah tidak mengubah histori, ekspor sama dengan ledger dan hanya untuk pengurus.
 * Konkurensi antarproses di M12ConcurrencyTest.
 */
class M12FinanceTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    private function house(string $nomor = '1'): array
    {
        $warga = $this->warga($nomor);
        $rumah = $warga->rumah();
        DB::table('rumah')->where('id', $rumah->id)->update(['mulai_tagih' => '2026-10-01']);

        return [$warga, $rumah];
    }

    private function pay($rumah, array $periode)
    {
        return $this->postJson('/api/v1/admin/dues-payments', [
            'rumah_id' => $rumah->id, 'periode' => $periode, 'nominal' => 75000 * count($periode), 'tanggal_bayar' => now('Asia/Jakarta')->toDateString(),
        ], ['Idempotency-Key' => (string) Str::uuid()]);
    }

    private function cash(array $body, ?string $key = null)
    {
        return $this->postJson('/api/v1/admin/cash-transactions', $body + [
            'tanggal' => now('Asia/Jakarta')->toDateString(), 'kategori' => 'Operasional', 'keterangan' => 'Beli lampu jalan gang',
        ], ['Idempotency-Key' => $key ?? (string) Str::uuid()]);
    }

    public function test_expense_that_makes_balance_negative_requires_confirmation_and_is_not_blocked(): void
    {
        $this->as($this->pengurus());
        $this->cash(['jenis' => 'pemasukan', 'nominal' => 50000])->assertCreated();

        $key = (string) Str::uuid();
        $this->cash(['jenis' => 'pengeluaran', 'nominal' => 80000], $key)
            ->assertStatus(422)->assertJsonPath('error.code', 'NEGATIVE_BALANCE_CONFIRMATION_REQUIRED');
        $this->assertSame(50000, Dues::balance(), 'ditolak tanpa tulisan apa pun');
        $this->assertStringContainsString('-Rp30.000', $this->cash(['jenis' => 'pengeluaran', 'nominal' => 80000])->json('error.message'));

        // Kunci yang sama boleh dipakai ulang setelah penolakan (reservasi ikut dibatalkan).
        $this->cash(['jenis' => 'pengeluaran', 'nominal' => 80000, 'konfirmasi_saldo_negatif' => true], $key)
            ->assertCreated()->assertJsonPath('meta.peringatan', 'Saldo kas menjadi negatif setelah pencatatan ini.');
        $this->assertSame(-30000, Dues::balance());
        // Pengeluaran yang tidak membuat negatif tidak perlu konfirmasi.
        $this->cash(['jenis' => 'pemasukan', 'nominal' => 100000])->assertCreated();
        $this->cash(['jenis' => 'pengeluaran', 'nominal' => 20000])->assertCreated();
        $this->cash(['jenis' => 'pengeluaran', 'nominal' => 20000, 'keterangan' => 'x'])->assertStatus(422);
    }

    public function test_cash_list_marks_which_entries_can_still_be_reversed(): void
    {
        [, $rumah] = $this->house();
        Dues::generate(Dues::period('2026-10'));
        $this->as($this->pengurus());
        $this->pay($rumah, ['2026-10'])->assertCreated();
        $manual = $this->cash(['jenis' => 'pemasukan', 'nominal' => 40000])->assertCreated()->json('data.id');
        $other = $this->cash(['jenis' => 'pemasukan', 'nominal' => 10000])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admin/cash-transactions/$manual/reverse", ['alasan' => 'Salah catat'], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();

        $rows = collect($this->getJson('/api/v1/admin/cash-transactions')->assertOk()->json('data'))->keyBy('id');
        $this->assertFalse($rows[$manual]['dapat_dibalik'], 'sudah dibalik');
        $this->assertTrue($rows[$other]['dapat_dibalik']);
        $this->assertFalse($rows->firstWhere('sumber', 'iuran')['dapat_dibalik'], 'kas iuran dibatalkan lewat pembayaran');
        $this->assertFalse($rows->firstWhere('jenis', 'pembalikan')['dapat_dibalik']);
        $summary = collect($this->getJson('/api/v1/admin/cash/summary')->assertOk()->json('data.mutasi_terakhir'))->keyBy('id');
        $this->assertFalse($summary[$manual]['dapat_dibalik']);
    }

    public function test_reconciliation_is_clean_after_payment_reversal_and_correction_and_detects_tampering(): void
    {
        [, $a] = $this->house('1');
        [, $b] = $this->house('2');
        Dues::generate(Dues::period('2026-10'));
        Dues::generate(Dues::period('2026-11'));
        $admin = $this->pengurus();
        $this->as($admin);
        $p1 = $this->pay($a, ['2026-10', '2026-11'])->assertCreated()->json('data.id');
        $p2 = $this->pay($b, ['2026-10'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admin/dues-payments/$p2/reverse", ['alasan' => 'Salah rumah'], ['Idempotency-Key' => (string) Str::uuid()])->assertSuccessful();
        $this->postJson("/api/v1/admin/dues-payments/$p1/correct", [
            'alasan' => 'Hanya Oktober', 'periode' => ['2026-10'], 'nominal' => 75000, 'tanggal_bayar' => now('Asia/Jakarta')->toDateString(),
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertSuccessful();
        $this->cash(['jenis' => 'saldo_awal', 'nominal' => 500000])->assertCreated();

        $clean = Reconciliation::run();
        $this->assertSame(0, $clean['total_masalah'], json_encode($clean['masalah']));
        $this->assertSame(575000, $clean['saldo']);
        $this->artisan('rt05:rekonsiliasi-keuangan')->assertSuccessful();

        // Manipulasi langsung (bypass aplikasi): tagihan ditandai lunas tanpa pembayaran.
        TagihanIuran::where('rumah_id', $b->id)->where('periode', '2026-11-01')->update(['status' => 'lunas']);
        $dirty = Reconciliation::run();
        $this->assertSame(1, $dirty['masalah']['lunas_tanpa_alokasi']);
        $this->artisan('rt05:rekonsiliasi-keuangan')->assertFailed();
        $this->assertStringNotContainsString($b->kode_rumah, json_encode($dirty), 'keluaran tanpa kode rumah');
    }

    public function test_archived_house_keeps_payment_history_ledger_and_export(): void
    {
        [$warga, $rumah] = $this->house();
        Dues::generate(Dues::period('2026-10'));
        $this->as($this->pengurus());
        $this->pay($rumah, ['2026-10'])->assertCreated();
        $before = Dues::balance();

        DB::table('rumah')->where('id', $rumah->id)->update(['archived_at' => now()]);
        $this->assertSame(0, Dues::generate(Dues::period('2026-11')), 'rumah arsip tidak ditagih lagi');

        $this->assertSame($before, Dues::balance());
        $this->assertSame(1, PembayaranIuran::where('rumah_id', $rumah->id)->where('status', 'tercatat')->count());
        $this->getJson("/api/v1/admin/houses/{$rumah->id}/dues")->assertOk()->assertJsonPath('data.tagihan.0.status', 'lunas');
        $csv = $this->get('/api/v1/admin/exports/iuran')->assertOk()->streamedContent();
        $this->assertStringContainsString($rumah->kode_rumah.',2026-10,75000,lunas', $csv);
        $this->assertSame(0, Reconciliation::run()['total_masalah']);
    }

    public function test_exports_match_the_ledger_and_are_pengurus_only(): void
    {
        [$warga, $rumah] = $this->house();
        Dues::generate(Dues::period('2026-10'));
        $this->as($this->pengurus());
        $this->pay($rumah, ['2026-10'])->assertCreated();
        $this->cash(['jenis' => 'pemasukan', 'nominal' => 25000, 'keterangan' => '=1+1 sumbangan'])->assertCreated();
        $this->cash(['jenis' => 'pengeluaran', 'nominal' => 15000])->assertCreated();

        $response = $this->get('/api/v1/admin/exports/kas')->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $lines = array_values(array_filter(explode("\n", ltrim($response->streamedContent(), "\u{FEFF}"))));
        $this->assertSame('tanggal,jenis,sumber,kategori,nominal,keterangan', trim($lines[0]));
        $sum = array_sum(array_map(fn ($l) => (int) str_getcsv($l)[4], array_slice($lines, 1)));
        $this->assertSame(Dues::balance(), $sum, 'FIN-17: total ekspor kas = saldo ledger');
        $this->assertStringContainsString("'=1+1 sumbangan", $response->streamedContent(), 'formula diberi prefiks');
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'ekspor.csv')->count());

        $this->as($warga);
        foreach (['iuran', 'kas', 'rumah'] as $jenis) {
            $this->get("/api/v1/admin/exports/$jenis", ['Accept' => 'application/json'])->assertForbidden();
        }
        $this->as($this->pengurus('lain@contoh.test'));
        $this->get('/api/v1/admin/exports/warga', ['Accept' => 'application/json'])->assertNotFound();
    }
}
