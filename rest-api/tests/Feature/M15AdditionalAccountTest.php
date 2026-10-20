<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\PermohonanAkun;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * UAT M15 20 Okt (ADR34): akun tambahan divalidasi dari nama pemohon, hubungan, dan nama kepala keluarga.
 * Alamat opsional; rumah dicocokkan otomatis dari kepala keluarga dan hasilnya tidak diberitahukan ke pemohon.
 */
class M15AdditionalAccountTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    private function register(array $over = [])
    {
        $this->tokenCode ??= $this->freshToken();

        return $this->postJson('/api/v1/auth/additional-register', $over + [
            'email' => 'tambahan'.random_int(1000, 9999).'@contoh.test', 'password' => 'Rahasia123', 'password_confirmation' => 'Rahasia123',
            'registration_token' => $this->tokenCode, 'device_name' => 'hp',
            'nama' => 'Uji Tambahan', 'hubungan_keluarga' => 'Anak', 'nama_kepala' => 'Uji Kepala 71',
        ]);
    }

    private function permohonan(string $email): PermohonanAkun
    {
        return PermohonanAkun::where('akun_id', Akun::where('email', $email)->value('id'))->firstOrFail();
    }

    public function test_only_name_relationship_and_head_name_are_required_and_house_matches_from_head(): void
    {
        $rumah = $this->warga('71')->rumah();

        // Tanpa alamat sama sekali: tetap diterima, rumah cocok dari nama kepala (huruf besar/kecil dan spasi ganda diabaikan).
        $this->register(['email' => 'a@contoh.test', 'nama_kepala' => '  uji   KEPALA 71 '])->assertStatus(202);
        $p = $this->permohonan('a@contoh.test');
        $this->assertSame($rumah->id, $p->rumah_id);
        $this->assertNull($p->alamat_diajukan, 'alamat opsional: tidak dikarang');
        $this->assertSame('uji   KEPALA 71', $p->nama_kepala_diajukan);
        $this->assertSame('diajukan', $p->status);

        // Pemohon tidak diberi tahu rumah mana yang cocok.
        $this->register(['email' => 'b@contoh.test'])->assertStatus(202)->assertJsonMissingPath('data.permohonan.rumah_cocok');
    }

    public function test_each_required_field_is_validated_and_address_stays_optional(): void
    {
        $this->warga('72');
        foreach (['nama', 'hubungan_keluarga', 'nama_kepala'] as $field) {
            $this->register(['email' => "tanpa-$field@contoh.test", $field => ''])->assertStatus(422)->assertJsonPath("error.fields.$field.0", fn ($m) => is_string($m));
        }
        $this->register(['email' => 'pendek@contoh.test', 'nama_kepala' => 'A'])->assertStatus(422)->assertJsonPath('error.fields.nama_kepala.0', fn ($m) => is_string($m));
        // Alamat sebagian atau kosong tidak pernah menjadi alasan penolakan (IP lain: throttle registrasi per IP).
        $this->withServerVariables(['REMOTE_ADDR' => '10.9.1.2']);
        $this->register(['email' => 'alamat-kosong@contoh.test', 'nama_kepala' => 'Uji Kepala 72', 'blok' => '', 'jalan' => '', 'nomor' => ''])->assertStatus(202);
        $this->register(['email' => 'alamat-sebagian@contoh.test', 'nama_kepala' => 'Uji Kepala 72', 'jalan' => 'Jl. Uji'])->assertStatus(202);
    }

    public function test_full_address_wins_and_unknown_or_ambiguous_head_leaves_house_for_the_committee(): void
    {
        $rumahA = $this->warga('73')->rumah();
        $rumahB = $this->warga('74', ['nama_kepala' => 'Budi Santoso'])->rumah();
        $rumahC = $this->warga('75', ['nama_kepala' => 'budi  santoso'])->rumah();

        // Alamat lengkap yang cocok dipakai lebih dulu, walau nama kepala berbeda tulisan.
        $this->register(['email' => 'alamat@contoh.test', 'nama_kepala' => 'Salah Ketik', 'blok' => 'Z', 'jalan' => 'Jl. Uji', 'nomor' => '73'])->assertStatus(202);
        $this->assertSame($rumahA->id, $this->permohonan('alamat@contoh.test')->rumah_id);

        // Nama kepala ganda di dua rumah: tidak ditebak, pengurus memilih rumah.
        $this->register(['email' => 'ganda@contoh.test', 'nama_kepala' => 'Budi Santoso'])->assertStatus(202);
        $ganda = $this->permohonan('ganda@contoh.test');
        $this->assertNull($ganda->rumah_id);

        // Nama tidak dikenal: rumah kosong.
        $this->register(['email' => 'asing@contoh.test', 'nama_kepala' => 'Orang Asing'])->assertStatus(202);
        $this->assertNull($this->permohonan('asing@contoh.test')->rumah_id);

        // Pengurus tetap bisa menyetujui dengan memilih rumah.
        $admin = $this->pengurus();
        $this->as($admin)->postJson("/api/v1/admin/additional-account-requests/{$ganda->id}/approve", [])->assertStatus(422)->assertJsonPath('error.code', 'HOUSE_NOT_OCCUPIED');
        $this->postJson("/api/v1/admin/additional-account-requests/{$ganda->id}/approve", ['rumah_id' => $rumahB->id])->assertOk()->assertJsonPath('data.status', 'disetujui');
        $this->assertNotSame($rumahC->id, $rumahB->id);
        $this->getJson('/api/v1/admin/additional-account-requests?status=disetujui')->assertOk()
            ->assertJsonFragment(['nama_kepala_diajukan' => 'Budi Santoso']);
    }

    public function test_archived_house_or_family_is_never_matched_by_head_name(): void
    {
        $admin = $this->pengurus();
        $warga = $this->warga('76', ['nama_kepala' => 'Kepala Arsip']);
        $rumah = $warga->rumah();
        $this->as($admin)->postJson("/api/v1/admin/houses/{$rumah->id}/archive", ['alasan' => 'Uji'])->assertNoContent();

        $this->register(['email' => 'arsip@contoh.test', 'nama_kepala' => 'Kepala Arsip'])->assertStatus(202);
        $this->assertNull($this->permohonan('arsip@contoh.test')->rumah_id);
    }

    public function test_revision_needs_only_head_name_and_status_prefills_it(): void
    {
        $this->warga('77');
        $pemohon = $this->pemohon('99', 'revisi-kepala@contoh.test', 'Nama Salah');
        $p = $this->permohonan('revisi-kepala@contoh.test');
        $this->assertNull($p->rumah_id);
        $admin = $this->pengurus();
        $this->as($admin)->postJson("/api/v1/admin/additional-account-requests/{$p->id}/revision", ['catatan' => 'Nama kepala keluarga salah.'])->assertOk();
        $this->app['auth']->forgetGuards();

        $this->as($pemohon)->getJson('/api/v1/auth/additional-account-status')->assertOk()
            ->assertJsonPath('data.permohonan.nama_kepala_keluarga', 'Uji Kepala 99');
        $this->as($pemohon)->patchJson('/api/v1/auth/additional-account-request', ['nama' => 'Nama Benar', 'hubungan_keluarga' => 'Anak'])
            ->assertStatus(422)->assertJsonPath('error.fields.nama_kepala.0', fn ($m) => is_string($m));
        $this->as($pemohon)->patchJson('/api/v1/auth/additional-account-request', ['nama' => 'Nama Benar', 'hubungan_keluarga' => 'Anak', 'nama_kepala' => 'Uji Kepala 77'])
            ->assertOk()->assertJsonPath('data.status', 'diajukan');
        $p->refresh();
        $this->assertNotNull($p->rumah_id, 'rumah cocok dari nama kepala yang diperbaiki, tanpa alamat');
        $this->assertSame('Uji Kepala 77', $p->nama_kepala_diajukan);
        $this->assertNull($p->alamat_diajukan);
    }
}
