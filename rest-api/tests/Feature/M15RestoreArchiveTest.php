<?php

namespace Tests\Feature;

use App\Models\Akun;
use App\Models\AuditLog;
use App\Models\KeanggotaanKeluarga;
use App\Models\Penghunian;
use App\Models\Rumah;
use App\Models\TautanAkun;
use App\Models\Warga;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * M15 UAT 20 Okt: arsip tidak boleh satu arah. Pulihkan konten, rumah, akun, dan keluarga (ADR34) tanpa
 * membuka diam-diam hunian, tautan akun, tagihan, atau akses login.
 */
class M15RestoreArchiveTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function audited(string $action, string $entityId): void
    {
        $this->assertTrue(AuditLog::where('action', $action)->where('entity_id', $entityId)->exists(), "audit $action");
    }

    public function test_archived_content_returns_as_draft_and_stays_off_the_website_until_published(): void
    {
        $admin = $this->pengurus();
        $draf = [
            'title' => 'Jadwal kerja bakti bulanan', 'category' => 'Lingkungan', 'art' => 'park',
            'summary' => 'Kerja bakti rutin setiap bulan untuk lingkungan bersih.',
            'intro' => 'Warga diajak membersihkan saluran dan area bersama.',
            'sections' => [['title' => 'Waktu', 'body' => 'Minggu pertama setiap bulan pukul 07.00 WIB.']],
        ];
        $id = $this->as($admin)->postJson('/api/v1/admin/articles', ['slug' => 'kerja-bakti', 'draf' => $draf])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admin/articles/$id/publish", ['versi' => 1])->assertOk();
        $this->getJson('/api/v1/public/articles/kerja-bakti')->assertOk();

        // Belum diarsipkan: tidak ada yang dipulihkan.
        $this->postJson("/api/v1/admin/articles/$id/restore", ['versi' => 1])->assertStatus(409)->assertJsonPath('error.code', 'NOT_ARCHIVED');

        $this->postJson("/api/v1/admin/articles/$id/archive", ['versi' => 1])->assertOk()->assertJsonPath('data.status', 'arsip');
        $this->getJson('/api/v1/public/articles/kerja-bakti')->assertNotFound();

        // Versi basi ditolak; versi benar memulihkan sebagai draf, bukan terbit.
        $this->postJson("/api/v1/admin/articles/$id/restore", ['versi' => 1])->assertStatus(409)->assertJsonPath('error.code', 'VERSION_CONFLICT');
        $this->postJson("/api/v1/admin/articles/$id/restore", ['versi' => 2])->assertOk()
            ->assertJsonPath('data.status', 'draf')->assertJsonPath('data.archived_at', null)->assertJsonPath('data.versi', 3);
        $this->getJson('/api/v1/public/articles/kerja-bakti')->assertNotFound();
        $this->audited('konten.dipulihkan', $id);

        // Bisa diedit dan diterbitkan lagi dengan slug yang sama.
        $this->putJson("/api/v1/admin/articles/$id", ['slug' => 'kerja-bakti', 'versi' => 3, 'draf' => ['title' => 'Judul baru'] + $draf])->assertOk();
        $this->postJson("/api/v1/admin/articles/$id/publish", ['versi' => 4])->assertOk()->assertJsonPath('data.status', 'terbit');
        $this->getJson('/api/v1/public/articles/kerja-bakti')->assertOk()->assertJsonPath('data.title', 'Judul baru');
    }

    public function test_content_restore_is_admin_only(): void
    {
        $this->postJson('/api/v1/admin/articles/'.fake()->uuid().'/restore', ['versi' => 1])->assertUnauthorized();
        $warga = $this->warga('1');
        $this->as($warga)->postJson('/api/v1/admin/articles/'.fake()->uuid().'/restore', ['versi' => 1])->assertForbidden();
    }

    public function test_house_restore_clears_archive_but_keeps_billing_off_and_occupancy_closed(): void
    {
        $admin = $this->pengurus();
        $utama = $this->warga('1');
        $rumah = $utama->rumah();

        $this->as($admin)->postJson("/api/v1/admin/houses/{$rumah->id}/restore", ['alasan' => 'x'])->assertStatus(409)->assertJsonPath('error.code', 'NOT_ARCHIVED');
        $this->postJson("/api/v1/admin/houses/{$rumah->id}/archive", ['alasan' => 'Salah arsip'])->assertNoContent();
        $versi = Rumah::find($rumah->id)->versi;

        $this->postJson("/api/v1/admin/houses/{$rumah->id}/restore", [])->assertStatus(422);
        $this->postJson("/api/v1/admin/houses/{$rumah->id}/restore", ['alasan' => 'Salah arsip'])->assertNoContent();

        $fresh = Rumah::find($rumah->id);
        $this->assertNull($fresh->archived_at);
        $this->assertSame($versi + 1, $fresh->versi);
        $this->assertFalse((bool) $fresh->tagihan_aktif, 'tagihan tidak menyala diam-diam');
        $this->assertNotNull(Penghunian::where('rumah_id', $rumah->id)->value('selesai_tanggal'), 'hunian tidak dibuka otomatis');
        $this->audited('rumah.dipulihkan', $rumah->id);

        // Rumah aktif lagi: bisa diubah dan tagihan bisa dinyalakan pengurus.
        $this->patchJson("/api/v1/admin/houses/{$rumah->id}", ['tagihan_aktif' => true, 'alasan' => 'Aktif lagi', 'versi' => $fresh->versi])->assertOk()
            ->assertJsonPath('data.tagihan_aktif', true);
    }

    public function test_account_restore_returns_inactive_account_without_login_or_family_link(): void
    {
        $admin = $this->pengurus();
        $warga = $this->warga('1');

        $this->as($admin)->postJson("/api/v1/admin/accounts/{$warga->id}/restore", ['alasan' => 'x'])->assertStatus(409)->assertJsonPath('error.code', 'NOT_ARCHIVED');
        $this->postJson("/api/v1/admin/accounts/{$admin->id}/restore", ['alasan' => 'x'])->assertStatus(422)->assertJsonPath('error.code', 'SELF_STATUS_CHANGE');
        $this->postJson("/api/v1/admin/accounts/{$warga->id}/archive", ['alasan' => 'Salah arsip'])->assertOk();

        $this->postJson("/api/v1/admin/accounts/{$warga->id}/restore", [])->assertStatus(422);
        $this->postJson("/api/v1/admin/accounts/{$warga->id}/restore", ['alasan' => 'Salah arsip'])->assertOk()
            ->assertJsonPath('data.status', 'nonaktif')->assertJsonPath('data.keluarga_id', null);

        $akun = Akun::find($warga->id);
        $this->assertNull($akun->archived_at);
        $this->assertSame(0, TautanAkun::where('akun_id', $warga->id)->whereNull('selesai_at')->count(), 'tautan tidak dibuka otomatis');
        $this->audited('akun.dipulihkan', $warga->id);

        // Nonaktif: belum bisa masuk, sampai pengurus mengaktifkan lewat jalur status biasa.
        $this->postJson('/api/v1/auth/mobile-login', ['email' => $warga->email, 'password' => 'Rahasia123', 'device_name' => 'hp'])
            ->assertStatus(422)->assertJsonMissingPath('data.token');
        $this->patchJson("/api/v1/admin/accounts/{$warga->id}", ['status' => 'aktif', 'alasan' => 'Pulih', 'versi' => 1])->assertOk()->assertJsonPath('data.status', 'aktif');
    }

    public function test_family_restore_brings_back_archived_members_but_not_those_who_left_earlier(): void
    {
        $admin = $this->pengurus();
        $utama = $this->warga('1');
        $k = $utama->keluarga();
        $anak = $this->as($admin)->postJson("/api/v1/admin/families/{$k->id}/members", ['nama' => 'Anak Uji', 'hubungan_keluarga' => 'anak', 'versi' => $k->versi])->assertCreated()->json('data.id');
        $pindah = $this->postJson("/api/v1/admin/families/{$k->id}/members", ['nama' => 'Sudah Pindah', 'hubungan_keluarga' => 'saudara', 'versi' => $k->fresh()->versi])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admin/residents/$pindah/end", ['status' => 'pindah', 'alasan' => 'Pindah kota', 'versi' => $k->fresh()->versi])->assertOk();

        $occupancy = Penghunian::where('keluarga_id', $k->id)->sole();
        $this->postJson("/api/v1/admin/occupancies/{$occupancy->id}/end", ['alasan' => 'Pindah keluar RT'])->assertOk();
        $this->postJson("/api/v1/admin/accounts/{$utama->id}/archive", ['alasan' => 'Pindah'])->assertOk();
        $this->postJson("/api/v1/admin/families/{$k->id}/archive", ['alasan' => 'Salah arsip'])->assertNoContent();
        $this->assertSame(0, KeanggotaanKeluarga::where('keluarga_id', $k->id)->whereNull('selesai_at')->count());

        $this->postJson("/api/v1/admin/families/{$k->id}/restore", [])->assertStatus(422);
        $sebelum = KeanggotaanKeluarga::where('keluarga_id', $k->id)->where('alasan_selesai', 'diarsipkan')->count();
        $this->assertSame(3, $sebelum, 'kepala, anggota fixture, dan anak ikut diarsipkan');
        $versi = $k->fresh()->versi;
        $this->postJson("/api/v1/admin/families/{$k->id}/restore", ['alasan' => 'Salah arsip'])->assertNoContent();

        $fresh = $k->fresh();
        $this->assertNull($fresh->archived_at);
        $this->assertSame($versi + 1, $fresh->versi);
        // Anggota yang ikut diarsipkan kembali aktif (kepala dan anak); yang sudah pindah lebih dulu tetap di luar.
        $aktif = KeanggotaanKeluarga::where('keluarga_id', $k->id)->whereNull('selesai_at')->pluck('warga_id')->all();
        $this->assertContains($anak, $aktif);
        $this->assertNotContains($pindah, $aktif);
        $this->assertCount(3, $aktif);
        $this->assertNull(Warga::find($anak)->archived_at);
        $this->assertSame('aktif', Warga::find($anak)->status);
        $this->assertNotNull(Warga::find($pindah)->archived_at);
        $this->assertSame('pindah', Warga::find($pindah)->status);
        // Hunian dan tautan akun tidak dibuka otomatis.
        $this->assertSame(0, Penghunian::where('keluarga_id', $k->id)->whereNull('selesai_tanggal')->count());
        $this->assertSame(0, TautanAkun::where('keluarga_id', $k->id)->whereNull('selesai_at')->count());
        $this->audited('keluarga.dipulihkan', $k->id);

        // Tidak bisa dipulihkan dua kali; dan keluarga aktif bisa diarsipkan lagi lalu dipulihkan lagi.
        $this->postJson("/api/v1/admin/families/{$k->id}/restore", ['alasan' => 'x'])->assertStatus(409)->assertJsonPath('error.code', 'NOT_ARCHIVED');
        $this->postJson("/api/v1/admin/families/{$k->id}/archive", ['alasan' => 'Arsip lagi'])->assertNoContent();
        $this->postJson("/api/v1/admin/families/{$k->id}/restore", ['alasan' => 'Pulih lagi'])->assertNoContent();
        $this->assertCount(3, KeanggotaanKeluarga::where('keluarga_id', $k->id)->whereNull('selesai_at')->get());
    }

    public function test_restore_routes_reject_non_admin(): void
    {
        $warga = $this->warga('1');
        $rumah = $warga->rumah();
        foreach (["houses/{$rumah->id}", "families/{$warga->keluarga()->id}", "accounts/{$warga->id}"] as $path) {
            $this->as($warga)->postJson("/api/v1/admin/$path/restore", ['alasan' => 'x'])->assertForbidden();
        }
    }
}
