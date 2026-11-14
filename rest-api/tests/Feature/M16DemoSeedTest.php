<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 10 Nov 2026: `rt05:demo-seed` (server uji lokal/emulator) gagal sejak M15b/M18: pemohon tambahan tanpa nama kepala,
 * tarif ledger diubah langsung (ditolak trigger), dan rumah ADR58 tanpa tagihan. Tes ini menjaga seed tetap jalan.
 */
class M16DemoSeedTest extends TestCase
{
    use DatabaseTransactions;

    protected array $connectionsToTransact = ['pgsql'];

    public function test_demo_seed_runs_on_current_schema_and_rules(): void
    {
        $this->artisan('rt05:demo-seed', ['--password' => 'DemoUji12345'])->assertSuccessful();
        $this->assertSame(5, DB::table('akun')->where('email', 'like', '%@demo.rt05.test')->count());
        $this->assertGreaterThan(0, DB::table('tagihan_iuran')->count());
        $this->assertGreaterThan(0, DB::table('pembayaran_iuran')->count());
        $this->assertSame(1, DB::table('permohonan_akun')->where('status', 'diajukan')->count());
    }
}
