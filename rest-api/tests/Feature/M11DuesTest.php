<?php

namespace Tests\Feature;

use App\Domain\Finance\Dues;
use App\Models\TagihanIuran;
use App\Models\TarifIuran;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Fixtures;
use Tests\Support\OpenApi;
use Tests\TestCase;

/**
 * M11: tarif berlaku per periode, rekap bulanan pengurus, riwayat minimal 12 bulan. Generator
 * bersamaan diuji di M11ConcurrencyTest (proses terpisah, data commit).
 */
class M11DuesTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    private function house(string $nomor, string $start = '2026-10-01')
    {
        $warga = $this->warga($nomor);
        DB::table('rumah')->where('id', $warga->rumah()->id)->update(['mulai_tagih' => $start]);

        return [$warga, $warga->rumah()];
    }

    public function test_rates_are_listed_and_created_for_future_periods_only_once(): void
    {
        [$warga] = $this->house('1');
        Dues::generate(Dues::period('2026-10'));
        $admin = $this->pengurus();

        $this->as($warga)->postJson('/api/v1/admin/dues-rates', ['nominal' => 80000, 'mulai_periode' => '2027-01', 'alasan' => 'Kenaikan kas'])->assertForbidden();
        $this->as($admin)->postJson('/api/v1/admin/dues-rates', ['nominal' => 80000, 'mulai_periode' => '2027/01', 'alasan' => 'x'])->assertStatus(422);
        $this->as($admin)->postJson('/api/v1/admin/dues-rates', ['nominal' => 500, 'mulai_periode' => '2027-01', 'alasan' => 'x'])->assertStatus(422);
        $this->as($admin)->postJson('/api/v1/admin/dues-rates', ['nominal' => 80000, 'mulai_periode' => '2026-10', 'alasan' => 'Mundur'])
            ->assertStatus(422)->assertJsonPath('error.code', 'RATE_PERIOD_HAS_CHARGES');

        $created = $this->as($admin)->postJson('/api/v1/admin/dues-rates', ['nominal' => 80000, 'mulai_periode' => '2027-01', 'alasan' => 'Kenaikan kas'])
            ->assertCreated()->assertJsonPath('data.mulai_periode', '2027-01')->assertJsonPath('data.nominal', 80000)
            ->assertJsonPath('data.alasan', 'Kenaikan kas');
        $this->assertSame([], OpenApi::errors($created->json(), 'DuesRateEnvelope'));
        // Sebelumnya unique violation → 500; sekarang konflik yang jelas.
        $this->as($admin)->postJson('/api/v1/admin/dues-rates', ['nominal' => 85000, 'mulai_periode' => '2027-01', 'alasan' => 'Dobel'])
            ->assertStatus(409)->assertJsonPath('error.code', 'RATE_PERIOD_EXISTS');

        $rates = $this->as($admin)->getJson('/api/v1/admin/dues-rates')->assertOk()->json('data');
        $this->assertSame(['2027-01', '2026-10'], array_column($rates, 'mulai_periode'));
        $this->assertSame(75000, TagihanIuran::where('periode', '2026-10-01')->value('nominal_tagihan'), 'tagihan lama tetap snapshot');
    }

    public function test_monthly_recap_for_pengurus_shows_totals_arrears_and_no_names(): void
    {
        [, $a] = $this->house('1');
        [, $b] = $this->house('2');
        [$wargaC, $c] = $this->house('3');
        Dues::generate(Dues::period('2026-10'));
        Dues::generate(Dues::period('2026-11'));
        $admin = $this->pengurus();
        $this->as($admin);
        $pay = fn ($rumah, array $periode) => $this->postJson('/api/v1/admin/dues-payments', [
            'rumah_id' => $rumah->id, 'periode' => $periode, 'nominal' => 75000 * count($periode), 'tanggal_bayar' => now('Asia/Jakarta')->toDateString(),
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $pay($a, ['2026-10', '2026-11']);
        $pay($b, ['2026-10']);

        $recap = $this->getJson('/api/v1/admin/dues-recap?periode=2026-11')->assertOk();
        $this->assertSame([], OpenApi::errors($recap->json(), 'DuesRecapEnvelope'));
        $this->assertSame('2026-11', $recap->json('data.periode'));
        $this->assertSame(75000, $recap->json('data.tarif'));
        $this->assertSame(3, $recap->json('data.jumlah_rumah'));
        $this->assertSame(['rumah' => 1, 'nominal' => 75000], $recap->json('data.lunas'));
        $this->assertSame(['rumah' => 2, 'nominal' => 150000], $recap->json('data.belum_bayar'));
        $this->assertSame(['rumah' => 1, 'nominal' => 75000], $recap->json('data.tunggakan_sebelumnya'), 'hanya C belum bayar Oktober');
        $rows = collect($recap->json('data.rumah'))->keyBy('kode_rumah');
        $this->assertSame('lunas', $rows[$a->kode_rumah]['status']);
        $this->assertSame(now('Asia/Jakarta')->toDateString(), $rows[$a->kode_rumah]['tanggal_bayar']);
        $this->assertSame(1, $rows[$c->kode_rumah]['bulan_tunggakan_sebelumnya']);
        $this->assertNull($rows[$b->kode_rumah]['tanggal_bayar']);
        $this->assertStringNotContainsString($wargaC->nama, $recap->getContent());
        $this->assertStringNotContainsString('@contoh.test', $recap->getContent());

        $this->getJson('/api/v1/admin/dues-recap?periode=2026-13')->assertStatus(422);
        $this->getJson('/api/v1/admin/dues-recap?periode=2030-01')->assertOk()->assertJsonPath('data.jumlah_rumah', 0);
        $this->as($wargaC)->getJson('/api/v1/admin/dues-recap')->assertForbidden();
    }

    public function test_resident_sees_at_least_twelve_months_of_history(): void
    {
        TarifIuran::create(['nominal' => 70000, 'mulai_periode' => '2025-08-01', 'alasan' => 'Tarif lama (tes riwayat)']);
        [$warga, $rumah] = $this->house('1', '2025-08-01');
        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 10, 17)->setTime(10, 0));
        Dues::catchUp();

        $this->assertSame(15, TagihanIuran::where('rumah_id', $rumah->id)->count(), 'Agustus 2025 s.d. Oktober 2026');
        $this->assertSame(70000, TagihanIuran::where('rumah_id', $rumah->id)->where('periode', '2026-09-01')->value('nominal_tagihan'));
        $this->assertSame(75000, TagihanIuran::where('rumah_id', $rumah->id)->where('periode', '2026-10-01')->value('nominal_tagihan'));

        $this->as($warga)->getJson('/api/v1/dues/my')->assertOk()->assertJsonCount(15, 'data.tagihan')
            ->assertJsonPath('data.tunggakan.jumlah_bulan', 14)->assertJsonPath('data.bulan_berjalan.periode', '2026-10');
        // Permintaan kurang dari 12 bulan tetap diberi 12 bulan (BR14).
        $this->as($warga)->getJson('/api/v1/dues/my?bulan=3')->assertOk()->assertJsonCount(12, 'data.tagihan');
    }
}
