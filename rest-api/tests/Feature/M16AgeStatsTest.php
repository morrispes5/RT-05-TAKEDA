<?php

namespace Tests\Feature;

use App\Domain\Residents\AgeGroups;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * ADR42 (permintaan Pak RT 24 Okt 2026): tanggal lahir anggota keluarga dan statistik kelompok umur untuk pengurus.
 */
class M16AgeStatsTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    protected function setUp(): void
    {
        parent::setUp();
        // Tanggal tetap di WIB agar batas umur deterministik.
        $this->travelTo(CarbonImmutable::parse('2026-10-24 10:00', 'Asia/Jakarta'));
    }

    public function test_age_groups_follow_birthday_boundaries(): void
    {
        $today = AgeGroups::today();
        $age = fn (string $d) => AgeGroups::age(CarbonImmutable::parse($d), $today);
        $this->assertSame(60, $age('1966-10-24'), 'ulang tahun ke-60 hari ini');
        $this->assertSame(59, $age('1966-10-25'), 'besok baru 60');
        $this->assertSame(0, $age('2026-10-24'));
        $this->assertSame('balita', AgeGroups::group(5));
        $this->assertSame('anak', AgeGroups::group(6));
        $this->assertSame('remaja', AgeGroups::group(17));
        $this->assertSame('dewasa', AgeGroups::group(59));
        $this->assertSame('lansia', AgeGroups::group(60));
        $this->assertSame('lansia', AgeGroups::group(69));
        $this->assertSame('lansia_70', AgeGroups::group(70));
        $this->assertNull(AgeGroups::group(null));
    }

    public function test_head_of_family_records_birth_dates_and_sees_age_but_not_in_future(): void
    {
        $w = $this->warga('181');
        $versi = $this->as($w)->getJson('/api/v1/household')->assertOk()->json('data.versi');
        $this->postJson('/api/v1/household/members', ['nama' => 'Nenek Uji', 'hubungan_keluarga' => 'Orang tua', 'tanggal_lahir' => '2030-01-01', 'versi' => $versi])
            ->assertStatus(422)->assertJsonPath('error.fields.tanggal_lahir.0', fn ($m) => is_string($m));
        $nenek = $this->postJson('/api/v1/household/members', ['nama' => 'Nenek Uji', 'hubungan_keluarga' => 'Orang tua', 'tanggal_lahir' => '1958-03-01', 'versi' => $versi])
            ->assertCreated()->assertJsonPath('data.umur', 68)->assertJsonPath('data.kelompok_usia', 'lansia')->json('data');

        $kepala = collect($this->getJson('/api/v1/household')->json('data.anggota'))->firstWhere('is_kepala', true);
        $this->patchJson('/api/v1/household/members/'.$kepala['id'], ['tanggal_lahir' => '1985-06-15', 'versi' => $nenek['versi_keluarga']])
            ->assertOk()->assertJsonPath('data.umur', 41)->assertJsonPath('data.kelompok_usia', 'dewasa');
        // Mengosongkan lagi boleh.
        $versi = $this->getJson('/api/v1/household')->json('data.versi');
        $this->patchJson('/api/v1/household/members/'.$kepala['id'], ['tanggal_lahir' => null, 'versi' => $versi])
            ->assertOk()->assertJsonPath('data.tanggal_lahir', null)->assertJsonPath('data.umur', null);
    }

    public function test_pengurus_fills_birth_dates_and_reads_age_statistics_with_filters(): void
    {
        $admin = $this->pengurus('m16-usia@contoh.test');
        $a = $this->warga('182');
        $b = $this->warga('183');
        // Warga biasa tidak boleh membaca statistik.
        $this->as($a)->getJson('/api/v1/admin/age-stats')->assertForbidden();

        $this->as($admin);
        $kepalaA = DB::table('warga')->where('keluarga_id', $a->keluarga()->id)->where('is_kepala', true)->value('id');
        $versiA = $this->getJson('/api/v1/admin/families/'.$a->keluarga()->id)->json('data.versi');
        $this->patchJson("/api/v1/admin/residents/$kepalaA", ['tanggal_lahir' => '1962-05-01', 'versi' => $versiA])->assertOk();
        $versiA = $this->getJson('/api/v1/admin/families/'.$a->keluarga()->id)->json('data.versi');
        $this->postJson('/api/v1/admin/families/'.$a->keluarga()->id.'/members', ['nama' => 'Cucu Uji', 'hubungan_keluarga' => 'Cucu', 'tanggal_lahir' => '2023-01-10', 'versi' => $versiA])
            ->assertCreated()->assertJsonPath('data.kelompok_usia', 'balita');
        $versiB = $this->getJson('/api/v1/admin/families/'.$b->keluarga()->id)->json('data.versi');
        $this->postJson('/api/v1/admin/families/'.$b->keluarga()->id.'/members', ['nama' => 'Kakek Uji', 'hubungan_keluarga' => 'Orang tua', 'tanggal_lahir' => '1950-02-02', 'versi' => $versiB])
            ->assertCreated()->assertJsonPath('data.kelompok_usia', 'lansia_70');

        $stats = $this->getJson('/api/v1/admin/age-stats')->assertOk()->assertJsonPath('data.dihitung_pada', '2026-10-24')->json('data');
        $jumlah = collect($stats['kelompok'])->pluck('jumlah', 'kode');
        $this->assertGreaterThanOrEqual(1, $jumlah['lansia']);
        $this->assertGreaterThanOrEqual(1, $jumlah['balita']);
        $this->assertGreaterThanOrEqual(1, $jumlah['lansia_70']);
        $this->assertSame($stats['total_warga'], $jumlah->sum() + $stats['belum_diisi']);
        $rumahA = collect($stats['rumah'])->firstWhere('keluarga_id', $a->keluarga()->id);
        $this->assertSame(1, $rumahA['kelompok']['lansia']);
        $this->assertSame(1, $rumahA['kelompok']['balita']);

        // Rentang bebas 60–65: hanya kepala A (64 tahun), bukan kakek 76 tahun.
        $range = collect($this->getJson('/api/v1/admin/age-stats?min=60&max=65')->json('data.warga'));
        $this->assertContains($kepalaA, $range->pluck('id'));
        $this->assertNotContains('Kakek Uji', $range->pluck('nama'));
        $this->assertTrue($range->every(fn ($r) => $r['umur'] >= 60 && $r['umur'] <= 65));
        $this->assertSame(['Kakek Uji'], collect($this->getJson('/api/v1/admin/age-stats?kelompok=lansia_70')->json('data.warga'))
            ->pluck('nama')->intersect(['Kakek Uji', 'Cucu Uji'])->values()->all());
        $kosong = collect($this->getJson('/api/v1/admin/age-stats?belum_diisi=1')->json('data.warga'));
        $this->assertTrue($kosong->every(fn ($r) => $r['umur'] === null));
        $this->getJson('/api/v1/admin/age-stats?min=70&max=60')->assertStatus(422);
        $this->getJson('/api/v1/admin/age-stats?kelompok=bayi')->assertStatus(422);
    }
}
