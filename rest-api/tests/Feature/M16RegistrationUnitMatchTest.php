<?php

namespace Tests\Feature;

use App\Domain\Residents\Address;
use App\Models\Rumah;
use App\Models\TagihanIuran;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * ADR58 (regresi soft launch 7 Nov 2026): rekap iuran mencatat D5 No. 21 di "Mawar 12", pendaftar mengetik "Mawar 10"
 * → dulu rumah ganda D5-21-2 dengan tagihan November ganda. Kini rumah dicocokkan lewat blok + nomor, jalan isian
 * warga dipakai, dan rumah di luar data RT dibuat tanpa tagihan sampai pengurus menyalakannya.
 */
class M16RegistrationUnitMatchTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    private function imported(string $blok, string $jalan, string $nomor): Rumah
    {
        return Rumah::create(['kode_rumah' => Address::code($blok, $nomor), 'blok' => $blok, 'jalan' => $jalan, 'nomor' => $nomor,
            'alamat_key' => Address::key($blok, $jalan, $nomor), 'tagihan_aktif' => true, 'mulai_tagih' => '2026-01-01']);
    }

    private function register(array $overrides = [])
    {
        return $this->postJson('/api/v1/auth/register', array_merge([
            'email' => 'unit@contoh.test', 'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123',
            'registration_token' => $this->tokenCode, 'device_name' => 'hp uji', 'nama_kepala' => 'Uji Unit',
            'blok' => 'D5', 'jalan' => 'Mawar 10', 'nomor' => '21', 'jenis_hunian' => 'pemilik',
        ], $overrides));
    }

    public function test_unit_key_ignores_street_spacing_prefix_and_leading_zero(): void
    {
        $this->assertSame(['d5', '21'], Address::unit('D5', '21'));
        $this->assertSame(['d5', '21'], Address::unit('Blok D 5', 'No. 021'));
        $this->assertNull(Address::unit(null, '21'));
    }

    public function test_wrong_street_in_recap_still_occupies_the_imported_house_without_duplicate(): void
    {
        $this->freshToken();
        $rumah = $this->imported('D5', 'Mawar 12', '21');
        $tagihan = TagihanIuran::where('rumah_id', $rumah->id)->count();

        $this->register(['blok' => 'd5', 'nomor' => '021'])->assertCreated();

        $this->assertSame(1, Rumah::whereNull('archived_at')->where('blok', 'D5')->where('nomor', '21')->count(), 'Tidak ada rumah ganda');
        $this->assertSame(1, DB::table('penghunian')->where('rumah_id', $rumah->id)->whereNull('selesai_tanggal')->count());
        $rumah->refresh();
        $this->assertSame('Mawar 10', $rumah->jalan, 'Jalan isian warga dipakai');
        $this->assertSame('d5|mawar 10|21', $rumah->alamat_key);
        $this->assertTrue($rumah->tagihan_aktif);
        $this->assertSame('2026-01-01', substr((string) $rumah->mulai_tagih, 0, 10), 'Riwayat tagihan rumah tidak diubah');
        $this->assertGreaterThanOrEqual($tagihan, TagihanIuran::where('rumah_id', $rumah->id)->count());
        $this->assertTrue(DB::table('audit_logs')->where('action', 'rumah.jalan_dikoreksi_registrasi')->where('entity_id', $rumah->id)->exists());
    }

    public function test_second_head_for_same_unit_is_sent_to_additional_account(): void
    {
        $this->freshToken();
        $this->imported('D5', 'Mawar 12', '21');
        $this->register()->assertCreated();
        $this->app['auth']->forgetGuards();

        $this->register(['email' => 'kedua@contoh.test', 'jalan' => 'Mawar 9'])
            ->assertStatus(409)->assertJsonPath('error.code', 'HOUSE_ALREADY_REGISTERED');
        $this->assertSame(1, Rumah::where('blok', 'D5')->where('nomor', '21')->count());
    }

    public function test_unknown_unit_is_accepted_but_not_billed_until_pengurus_checks(): void
    {
        $this->freshToken();
        $this->imported('D5', 'Mawar 12', '21');

        $this->register(['nomor' => '99'])->assertCreated();

        $baru = Rumah::where('blok', 'D5')->where('nomor', '99')->firstOrFail();
        $this->assertFalse($baru->tagihan_aktif);
        $this->assertNull($baru->mulai_tagih);
        $this->assertSame(0, TagihanIuran::where('rumah_id', $baru->id)->count(), 'Tidak ada tagihan tak jelas');
    }

    public function test_additional_account_matches_house_by_unit_too(): void
    {
        $this->freshToken();
        $rumah = $this->imported('D5', 'Mawar 12', '21');
        $this->register()->assertCreated();
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/v1/auth/additional-register', [
            'email' => 'anak@contoh.test', 'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123',
            'registration_token' => $this->tokenCode, 'device_name' => 'hp uji', 'nama' => 'Uji Anak', 'hubungan_keluarga' => 'Anak',
            'nama_kepala' => 'Nama Lain Di Rekap', 'blok' => 'D5', 'jalan' => 'Mawar 12', 'nomor' => '21',
        ])->assertStatus(202);

        $this->assertSame($rumah->id, DB::table('permohonan_akun')->where('jenis', 'tambahan')->value('rumah_id'));
    }
}
