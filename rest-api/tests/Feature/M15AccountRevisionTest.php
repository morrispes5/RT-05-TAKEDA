<?php

namespace Tests\Feature;

use App\Models\PermohonanAkun;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * UAT M15 (20 Okt 2026): permohonan akun tambahan yang datanya tidak cocok dapat diminta perbaikan,
 * pemohon memperbaiki lalu mengajukan ulang; akun tetap menunggu sampai disetujui.
 */
class M15AccountRevisionTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    public function test_pengurus_requests_revision_and_applicant_resubmits(): void
    {
        $this->warga('81');
        $pemohon = $this->pemohon('99', 'salah-alamat@contoh.test', 'Nama Salah');
        $p = PermohonanAkun::where('akun_id', $pemohon->id)->firstOrFail();
        $this->assertNull($p->rumah_id, 'alamat salah: rumah tidak cocok');
        $admin = $this->pengurus('m15-revisi@contoh.test');

        $this->as($admin)->postJson("/api/v1/admin/additional-account-requests/{$p->id}/revision", ['catatan' => 'Nomor rumah salah, harusnya 81.'])
            ->assertOk()->assertJsonPath('data.status', 'perlu_revisi');
        $this->app['auth']->forgetGuards();
        $this->as($pemohon)->getJson('/api/v1/auth/additional-account-status')
            ->assertOk()->assertJsonPath('data.permohonan.status', 'perlu_revisi')->assertJsonPath('data.permohonan.catatan', 'Nomor rumah salah, harusnya 81.')
            ->assertJsonPath('data.permohonan.nama', 'Nama Salah');

        $this->as($pemohon)->patchJson('/api/v1/auth/additional-account-request', [
            'nama' => 'Nama Benar', 'hubungan_keluarga' => 'Anak', 'blok' => 'Z', 'jalan' => 'Jl. Uji', 'nomor' => '81',
        ])->assertOk()->assertJsonPath('data.status', 'diajukan');
        $p->refresh();
        $this->assertSame('diajukan', $p->status);
        $this->assertNotNull($p->rumah_id, 'alamat diperbaiki: rumah cocok');
        $this->assertSame('Nama Benar', $p->nama_pemohon);
        $this->assertNull($p->catatan);
        $this->assertSame('menunggu', $pemohon->refresh()->status);

        // Tidak bisa diperbaiki lagi tanpa diminta; pengurus lalu dapat menyetujui.
        $this->as($pemohon)->patchJson('/api/v1/auth/additional-account-request', [
            'nama' => 'Lagi', 'hubungan_keluarga' => 'Anak', 'jalan' => 'Jl. Uji', 'nomor' => '81',
        ])->assertStatus(409)->assertJsonPath('error.code', 'REQUEST_NOT_REVISABLE');
        $this->app['auth']->forgetGuards();
        $this->as($admin)->postJson("/api/v1/admin/additional-account-requests/{$p->id}/approve", [])->assertOk()->assertJsonPath('data.status', 'disetujui');
    }

    public function test_revision_requires_note_and_only_from_submitted(): void
    {
        $this->warga('82');
        $pemohon = $this->pemohon('82', 'cocok@contoh.test');
        $p = PermohonanAkun::where('akun_id', $pemohon->id)->firstOrFail();
        $admin = $this->pengurus('m15-revisi2@contoh.test');
        $this->as($admin)->postJson("/api/v1/admin/additional-account-requests/{$p->id}/revision", ['catatan' => ''])->assertStatus(422);
        $this->as($admin)->postJson("/api/v1/admin/additional-account-requests/{$p->id}/reject", ['alasan' => 'Bukan warga RT.'])->assertOk();
        $this->as($admin)->postJson("/api/v1/admin/additional-account-requests/{$p->id}/revision", ['catatan' => 'Coba perbaiki.'])
            ->assertStatus(409)->assertJsonPath('error.code', 'REQUEST_ALREADY_PROCESSED');
        $warga = $this->warga('83');
        $this->as($warga)->postJson("/api/v1/admin/additional-account-requests/{$p->id}/revision", ['catatan' => 'Coba perbaiki.'])->assertForbidden();
    }
}
