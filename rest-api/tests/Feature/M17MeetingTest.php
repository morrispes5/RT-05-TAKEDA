<?php

namespace Tests\Feature;

use App\Domain\Residents\Address;
use App\Domain\Residents\FamilyAvatars;
use App\Domain\Residents\Retention;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * M17 (ADR43, rapat RT 24 Okt 2026): super admin mengubah peran pengurus, kontak Ketua RT untuk website, jalan Mawar 8–14
 * baku, dan anonimisasi arsip 12 bulan (Kebijakan Privasi v3).
 */
class M17MeetingTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    private const SUPER = 'takedatamankedaung@gmail.com';

    public function test_only_super_admin_changes_roles_and_target_sessions_are_revoked(): void
    {
        $super = $this->pengurus(self::SUPER);
        $lain = $this->pengurus('m17-pengurus@contoh.test');
        $warga = $this->warga('171');
        $warga->createToken('ponsel');

        $this->as($super)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.super_admin', true);
        $this->as($lain)->getJson('/api/v1/auth/me')->assertJsonPath('data.super_admin', false);

        // Pengurus biasa (hak bisnis setara) tetap tidak boleh mengubah peran.
        $this->postJson("/api/v1/admin/accounts/{$warga->id}/role", ['peran' => 'pengurus', 'alasan' => 'uji'])
            ->assertForbidden()->assertJsonPath('error.code', 'SUPER_ADMIN_REQUIRED');
        $this->as($warga)->postJson("/api/v1/admin/accounts/{$warga->id}/role", ['peran' => 'pengurus', 'alasan' => 'uji'])->assertForbidden();

        $this->as($super)->postJson("/api/v1/admin/accounts/{$warga->id}/role", ['peran' => 'pengurus'])->assertStatus(422);
        $this->postJson("/api/v1/admin/accounts/{$warga->id}/role", ['peran' => 'pengurus', 'alasan' => 'Ditetapkan rapat RT'])
            ->assertOk()->assertJsonPath('data.peran', 'pengurus');
        $this->assertSame(0, $warga->tokens()->count(), 'sesi lama akun tujuan diakhiri agar peran baru berlaku saat masuk lagi');
        $this->assertDatabaseHas('audit_logs', ['action' => 'akun.peran_pengurus', 'entity_id' => $warga->id]);
        $this->assertDatabaseHas('outbox_events', ['type' => 'akun.peran_diubah', 'aggregate_id' => $warga->id]);

        $this->postJson("/api/v1/admin/accounts/{$warga->id}/role", ['peran' => 'warga', 'alasan' => 'Masa jabatan selesai'])
            ->assertOk()->assertJsonPath('data.peran', 'warga');
        $this->postJson("/api/v1/admin/accounts/{$super->id}/role", ['peran' => 'warga', 'alasan' => 'uji'])
            ->assertStatus(422)->assertJsonPath('error.code', 'SELF_ROLE_CHANGE');
    }

    public function test_super_admin_cannot_promote_inactive_accounts_or_demote_last_pengurus(): void
    {
        $super = $this->pengurus(self::SUPER);
        $warga = $this->warga('172');
        $warga->forceFill(['status' => 'nonaktif'])->save();
        $this->as($super)->postJson("/api/v1/admin/accounts/{$warga->id}/role", ['peran' => 'pengurus', 'alasan' => 'uji'])
            ->assertStatus(422)->assertJsonPath('error.code', 'ACCOUNT_NOT_ACTIVE');

        // Email super admin pada akun warga tidak memberi hak apa pun (harus pengurus aktif).
        $sebagaiWarga = $super->replicate();
        $sebagaiWarga->peran = 'warga';
        $this->assertFalse($sebagaiWarga->isSuperAdmin());
        $nonaktif = $super->replicate();
        $nonaktif->status = 'nonaktif';
        $this->assertFalse($nonaktif->isSuperAdmin());
        $this->assertTrue($super->isSuperAdmin());
    }

    public function test_ketua_contact_updates_independently_and_is_public_without_bendahara(): void
    {
        $admin = $this->pengurus('m17-kontak@contoh.test');
        $this->as($admin)->putJson('/api/v1/admin/settings/contacts', ['bendahara_whatsapp' => '0812-1111-2222'])->assertOk();
        $this->putJson('/api/v1/admin/settings/contacts', ['ketua_whatsapp' => '0813 3333 4444'])->assertOk()
            ->assertJsonPath('data.ketua_whatsapp', '6281333334444')
            ->assertJsonPath('data.bendahara_whatsapp', '6281211112222'); // kolom yang tidak dikirim tidak berubah
        $this->putJson('/api/v1/admin/settings/contacts', [])->assertStatus(422);
        $this->putJson('/api/v1/admin/settings/contacts', ['ketua_whatsapp' => 'bukan nomor'])->assertStatus(422);

        $this->app['auth']->forgetGuards();
        $public = $this->getJson('/api/v1/public/contacts')->assertOk()->assertJsonPath('data.ketua_whatsapp', '6281333334444');
        $this->assertArrayNotHasKey('bendahara_whatsapp', $public->json('data'), 'nomor bendahara hanya untuk warga yang masuk');

        $this->as($admin)->putJson('/api/v1/admin/settings/contacts', ['ketua_whatsapp' => null])->assertOk()->assertJsonPath('data.ketua_whatsapp', null);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/public/contacts')->assertJsonPath('data.ketua_whatsapp', null);
    }

    public function test_mawar_streets_are_canonical_and_share_one_address_key(): void
    {
        foreach (['jl. mawar8', 'Jalan Mawar 8', 'JL MAWAR  8', 'mawar 8', 'Jln. Mawar 08'] as $tulisan) {
            $this->assertSame('Mawar 8', Address::street($tulisan), $tulisan);
        }
        $this->assertSame('Mawar 14', Address::street('jalan mawar14'));
        $this->assertSame('Jl. Melati Raya', Address::street('  Jl.   Melati Raya '));
        $this->assertSame(Address::key('A', 'jl. mawar8', '5'), Address::key('a', 'Mawar 8', 'No. 5'));

        $w = $this->warga('173', ['jalan' => 'jl. mawar10']);
        $this->as($w)->getJson('/api/v1/household')->assertOk()->assertJsonPath('data.rumah.alamat', fn ($a) => str_contains($a, 'Mawar 10'));
    }

    public function test_archives_older_than_twelve_months_are_anonymized_and_cannot_be_restored(): void
    {
        $admin = $this->pengurus('m17-retensi@contoh.test');
        $w = $this->warga('174', ['whatsapp' => '081277778888']);
        $baru = $this->warga('175');
        $keluargaId = $w->keluarga()->id;
        $lama = now()->subMonths(13);

        // Anggota yang pindah 13 bulan lalu, keluarga diarsipkan 13 bulan lalu, akun diarsipkan 13 bulan lalu.
        $anak = DB::table('warga')->where('keluarga_id', $keluargaId)->where('is_kepala', false)->value('id');
        DB::table('keanggotaan_keluarga')->where('warga_id', $anak)->whereNull('selesai_at')
            ->update(['mulai_at' => now()->subYears(3), 'selesai_at' => $lama, 'alasan_selesai' => 'pindah']);
        DB::table('warga')->where('id', $anak)->update(['status' => 'pindah', 'nik' => '3674010101010001', 'tanggal_lahir' => '2010-01-01']);
        DB::table('keluarga')->where('id', $keluargaId)->update(['nomor_kk' => '3674010101010002', 'archived_at' => $lama]);
        DB::table('akun')->where('id', $w->id)->update(['status' => 'diarsipkan', 'archived_at' => $lama]);
        // Arsip baru (1 bulan) belum boleh disentuh.
        DB::table('akun')->where('id', $baru->id)->update(['status' => 'diarsipkan', 'archived_at' => now()->subMonth()]);

        $hasil = Retention::anonymize();
        $this->assertGreaterThanOrEqual(1, $hasil['warga']);
        $this->assertGreaterThanOrEqual(1, $hasil['keluarga']);
        $this->assertGreaterThanOrEqual(1, $hasil['akun']);

        $anakRow = DB::table('warga')->where('id', $anak)->first();
        $this->assertSame('Warga dianonimkan', $anakRow->nama);
        $this->assertNull($anakRow->nik);
        $this->assertNull($anakRow->tanggal_lahir);
        $this->assertNotNull($anakRow->dianonimkan_at);
        $this->assertNull(DB::table('keluarga')->where('id', $keluargaId)->value('nomor_kk'));
        $akunRow = DB::table('akun')->where('id', $w->id)->first();
        $this->assertSame('Akun dianonimkan', $akunRow->nama);
        $this->assertStringEndsWith('@rt05.invalid', $akunRow->email);
        $this->assertSame('uji175@contoh.test', DB::table('akun')->where('id', $baru->id)->value('email'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'arsip.dianonimkan']);

        // Idempoten: jalankan lagi tidak menyentuh baris yang sama.
        $this->assertSame(0, DB::table('warga')->where('id', $anak)->where('dianonimkan_at', '!=', $anakRow->dianonimkan_at)->count());
        Retention::anonymize();
        $this->assertSame($anakRow->dianonimkan_at, DB::table('warga')->where('id', $anak)->value('dianonimkan_at'));

        $this->as($admin)->postJson("/api/v1/admin/accounts/{$w->id}/restore", ['alasan' => 'uji'])
            ->assertStatus(409)->assertJsonPath('error.code', 'ANONYMIZED');
        $this->postJson("/api/v1/admin/families/$keluargaId/restore", ['alasan' => 'uji'])
            ->assertStatus(409)->assertJsonPath('error.code', 'ANONYMIZED');

        $this->artisan('rt05:anonimkan-arsip')->assertSuccessful();
    }

    public function test_primary_account_picks_one_of_fifteen_family_avatars(): void
    {
        $w = $this->warga('177');
        $this->as($w)->getJson('/api/v1/household')->assertOk()->assertJsonPath('data.avatar', null);
        $versi = $this->getJson('/api/v1/household')->json('data.versi');
        $this->putJson('/api/v1/household/avatar', ['avatar' => 'ibu_hijab'])->assertOk()->assertJsonPath('data.avatar', 'ibu_hijab')
            ->assertJsonPath('data.versi', $versi); // kosmetik: tidak menaikkan versi keluarga
        $this->putJson('/api/v1/household/avatar', ['avatar' => 'foto-galeri.jpg'])->assertStatus(422);
        $this->putJson('/api/v1/household/avatar', [])->assertStatus(422);
        $this->assertDatabaseHas('audit_logs', ['action' => 'keluarga.avatar_diubah', 'entity_id' => $w->keluarga()->id]);

        $admin = $this->pengurus('m17-avatar@contoh.test');
        $this->as($admin)->getJson('/api/v1/admin/families?q='.urlencode('Uji Kepala 177'))->assertOk()
            ->assertJsonPath('data.0.avatar', 'ibu_hijab');

        $this->as($w)->putJson('/api/v1/household/avatar', ['avatar' => null])->assertOk()->assertJsonPath('data.avatar', null);
    }

    public function test_avatar_codes_match_mobile_assets(): void
    {
        $files = array_map(fn ($f) => basename($f, '.svg'), glob(base_path('../mobile/assets/avatars/*.svg')));
        sort($files);
        $codes = FamilyAvatars::CODES;
        sort($codes);
        $this->assertCount(15, $codes);
        $this->assertSame($codes, $files);
    }
}
