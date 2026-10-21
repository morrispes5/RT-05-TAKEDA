<?php

namespace Tests\Feature;

use App\Domain\Finance\Dues;
use App\Models\Akun;
use App\Models\AuditLog;
use App\Models\Keluarga;
use App\Models\KonfirmasiIuran;
use App\Models\Konten;
use App\Models\OutboxEvent;
use App\Models\TagihanIuran;
use App\Models\Warga;
use App\Support\Privacy;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * Permintaan Pak RT 20 Okt (ADR35): profil RT05 editable, transparansi iuran khusus pengurus, bukti transfer,
 * kontak bendahara untuk WhatsApp, NIK/KK opsional terenkripsi, dan persetujuan Kebijakan Privasi.
 */
class M15bRequestsTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function period(): string
    {
        return CarbonImmutable::now('Asia/Jakarta')->format('Y-m');
    }

    // ---------- Profil RT05

    public function test_profile_story_is_seeded_editable_and_kept_when_old_clients_omit_it(): void
    {
        $this->artisan('rt05:konten-impor')->assertSuccessful();
        $profil = Konten::where('jenis', 'profil')->sole();
        $this->assertStringContainsString('Kecamatan Pamulang', $profil->draf['tentang']);
        $admin = $this->pengurus();
        $draf = $profil->draf;

        $draf['tentang'] = "Cerita baru dari pengurus.\n\nParagraf kedua.";
        $draf['pengurus'][] = ['jabatan' => 'Pengurus aplikasi', 'nama' => 'Nama Uji', 'catatan' => null];
        $this->as($admin)->putJson('/api/v1/admin/site-profile', ['versi' => $profil->versi, 'draf' => $draf])->assertOk()
            ->assertJsonPath('data.draf.tentang', "Cerita baru dari pengurus.\n\nParagraf kedua.")
            ->assertJsonCount(4, 'data.draf.pengurus');
        $this->getJson('/api/v1/public/site-profile')->assertOk()->assertJsonPath('data.tentang', "Cerita baru dari pengurus.\n\nParagraf kedua.");

        // Aplikasi lama (tanpa kunci `tentang`) tidak menghapus cerita.
        $versi = Konten::where('jenis', 'profil')->value('versi');
        unset($draf['tentang']);
        $this->putJson('/api/v1/admin/site-profile', ['versi' => $versi, 'draf' => $draf])->assertOk()
            ->assertJsonPath('data.draf.tentang', "Cerita baru dari pengurus.\n\nParagraf kedua.");
        // Terlalu panjang ditolak.
        $draf['tentang'] = str_repeat('a', 3001);
        $this->putJson('/api/v1/admin/site-profile', ['versi' => $versi + 1, 'draf' => $draf])->assertStatus(422);
    }

    public function test_profile_migration_backfills_story_only_where_missing(): void
    {
        $this->artisan('rt05:konten-impor')->assertSuccessful();
        $profil = Konten::where('jenis', 'profil')->sole();
        $draf = $profil->draf;
        unset($draf['tentang']);
        $profil->forceFill(['draf' => $draf, 'terbit' => $draf])->save();

        $migration = require database_path('migrations/2026_10_21_000200_konten_profil_tentang.php');
        $migration->up();
        $profil->refresh();
        $this->assertStringContainsString('Taman Kedaung', $profil->draf['tentang']);
        $this->assertStringContainsString('Taman Kedaung', $profil->terbit['tentang']);

        $edited = $profil->draf;
        $edited['tentang'] = 'Sudah diedit.';
        $profil->forceFill(['draf' => $edited])->save();
        $migration->up();
        $this->assertSame('Sudah diedit.', $profil->refresh()->draf['tentang'], 'tidak menimpa teks pengurus');
    }

    // ---------- Transparansi dan kontak bendahara

    public function test_dues_transparency_is_committee_only(): void
    {
        $warga = $this->warga('1');
        $this->as($warga)->getJson('/api/v1/dues/transparency')->assertForbidden();
        $this->as($this->pengurus())->getJson('/api/v1/dues/transparency')->assertOk();
    }

    public function test_treasurer_whatsapp_is_editable_by_committee_and_readable_by_residents(): void
    {
        $warga = $this->warga('1');
        $this->as($warga)->getJson('/api/v1/settings/contacts')->assertOk()->assertJsonPath('data.bendahara_whatsapp', null);
        $this->putJson('/api/v1/admin/settings/contacts', ['bendahara_whatsapp' => '0812 3456 7890'])->assertForbidden();

        $admin = $this->pengurus();
        $this->as($admin)->putJson('/api/v1/admin/settings/contacts', ['bendahara_whatsapp' => 'bukan nomor'])->assertStatus(422);
        $this->putJson('/api/v1/admin/settings/contacts', ['bendahara_whatsapp' => '0812-3456-7890'])->assertOk()
            ->assertJsonPath('data.bendahara_whatsapp', '6281234567890');
        $this->as($warga)->getJson('/api/v1/settings/contacts')->assertJsonPath('data.bendahara_whatsapp', '6281234567890');
        $this->as($admin)->putJson('/api/v1/admin/settings/contacts', ['bendahara_whatsapp' => '+62 812 3456 7891'])->assertJsonPath('data.bendahara_whatsapp', '6281234567891');
        $this->putJson('/api/v1/admin/settings/contacts', ['bendahara_whatsapp' => null])->assertJsonPath('data.bendahara_whatsapp', null);

        $audit = AuditLog::where('action', 'pengaturan.kontak_diubah')->latest('created_at')->first();
        $this->assertNotNull($audit);
        $this->assertStringNotContainsString('812', json_encode($audit->safe_diff), 'nomor tidak masuk audit');
        $this->assertSame(3, (int) DB::table('system_settings')->where('key', 'kontak.pengurus')->value('version'));
    }

    // ---------- Bukti transfer

    public function test_resident_sends_receipt_committee_reviews_and_bill_status_never_changes_by_itself(): void
    {
        $admin = $this->pengurus();
        $warga = $this->warga('1');
        Dues::generate(Dues::period($this->period()), $admin);
        $tagihan = TagihanIuran::where('rumah_id', $warga->rumah()->id)->sole();

        $this->as($warga)->post('/api/v1/dues/confirmations', [
            'foto' => UploadedFile::fake()->image('bukti.jpg', 900, 700), 'periode' => ['2001-01'],
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $id = $this->post('/api/v1/dues/confirmations', [
            'foto' => UploadedFile::fake()->image('bukti.jpg', 900, 700), 'periode' => [$this->period()], 'nominal' => 75000, 'catatan' => 'Transfer BCA',
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.status', 'diajukan')->assertJsonPath('data.periode.0', $this->period())
            ->assertJsonMissingPath('data.rumah')->json('data.id');
        $k = KonfirmasiIuran::findOrFail($id);
        Storage::disk('local')->assertExists($k->storage_key);
        $this->assertSame([], Storage::disk('local')->files('iuran/masuk'), 'berkas mentah dihapus setelah transkode');
        $this->assertSame('belum_bayar', $tagihan->fresh()->status, 'konfirmasi bukan pembayaran');
        $this->assertTrue(OutboxEvent::where('type', 'iuran.konfirmasi_dikirim')->where('aggregate_id', $id)->exists());

        $this->getJson('/api/v1/dues/confirmations')->assertOk()->assertJsonPath('data.0.id', $id);
        $this->get("/api/v1/dues/confirmations/$id/photo")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->as($this->warga('2'))->get("/api/v1/dues/confirmations/$id/photo")->assertNotFound();
        $this->getJson('/api/v1/admin/dues-confirmations')->assertForbidden();

        $this->as($admin)->get("/api/v1/dues/confirmations/$id/photo")->assertOk();
        $this->getJson('/api/v1/admin/dues-confirmations')->assertOk()
            ->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.rumah.kode_rumah', $warga->rumah()->kode_rumah)
            ->assertJsonPath('data.0.pengirim.nama', $warga->nama);
        $this->postJson("/api/v1/admin/dues-confirmations/$id/process", ['status' => 'ditolak'])->assertStatus(422);
        $this->postJson("/api/v1/admin/dues-confirmations/$id/process", ['status' => 'selesai'])->assertOk()->assertJsonPath('data.status', 'selesai');
        $this->postJson("/api/v1/admin/dues-confirmations/$id/process", ['status' => 'ditolak', 'catatan' => 'x'])->assertStatus(409)->assertJsonPath('error.code', 'ALREADY_PROCESSED');
        $this->assertSame('belum_bayar', $tagihan->fresh()->status, 'pengurus tetap mencatat pembayaran secara terpisah');
        $this->assertTrue(OutboxEvent::where('type', 'iuran.konfirmasi_diproses')->where('aggregate_id', $id)->exists());
        $this->getJson('/api/v1/admin/dues-confirmations?status=selesai')->assertJsonPath('data.0.id', $id);
    }

    public function test_receipt_rejects_non_images_and_limits_five_per_day(): void
    {
        $warga = $this->warga('1');
        $this->as($warga)->post('/api/v1/dues/confirmations', ['foto' => UploadedFile::fake()->createWithContent('bukti.jpg', 'bukan gambar')], ['Accept' => 'application/json'])
            ->assertStatus(415);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/api/v1/dues/confirmations', ['foto' => UploadedFile::fake()->image("b$i.jpg", 200, 200)], ['Accept' => 'application/json'])->assertCreated();
        }
        $this->post('/api/v1/dues/confirmations', ['foto' => UploadedFile::fake()->image('b6.jpg', 200, 200)], ['Accept' => 'application/json'])
            ->assertStatus(429)->assertJsonPath('error.code', 'DAILY_QUOTA');
        $this->assertSame(5, KonfirmasiIuran::where('akun_id', $warga->id)->count());
        $this->assertCount(5, Storage::disk('local')->files('iuran'), 'foto yang ditolak kuota tidak tertinggal');
    }

    // ---------- NIK dan KK

    public function test_primary_account_and_committee_fill_optional_nik_and_kk_encrypted_and_masked(): void
    {
        $utama = $this->warga('1');
        $k = $utama->keluarga();
        $kepala = Warga::where('keluarga_id', $k->id)->where('is_kepala', true)->sole();

        $this->as($utama)->getJson('/api/v1/household')->assertOk()
            ->assertJsonPath('data.nomor_kk_tersamar', null)->assertJsonPath('data.anggota.0.nik_tersamar', null);
        $this->patchJson("/api/v1/household/members/{$kepala->id}", ['nik' => '12345', 'versi' => $k->versi])->assertStatus(422);
        $this->patchJson("/api/v1/household/members/{$kepala->id}", ['nik' => '3674010101900001', 'versi' => $k->versi])->assertOk()
            ->assertJsonPath('data.nik_tersamar', '3674••••••••0001');
        $this->patchJson('/api/v1/household', ['nomor_kk' => '3674012345678901', 'versi' => $k->fresh()->versi])->assertOk()
            ->assertJsonPath('data.nomor_kk_tersamar', '3674••••••••8901');

        $raw = DB::table('warga')->where('id', $kepala->id)->value('nik');
        $this->assertNotSame('3674010101900001', $raw, 'tersimpan terenkripsi');
        $this->assertSame('3674010101900001', $kepala->fresh()->nik);
        $this->assertNotSame('3674012345678901', DB::table('keluarga')->where('id', $k->id)->value('nomor_kk'));
        $json = $this->getJson('/api/v1/household')->getContent();
        $this->assertStringNotContainsString('3674010101900001', $json);
        $this->assertStringNotContainsString('3674012345678901', $json);
        foreach (AuditLog::whereIn('entity_id', [$kepala->id, $k->id])->get() as $audit) {
            $this->assertStringNotContainsString('36740', json_encode($audit->safe_diff));
        }

        // Akun tambahan hanya membaca (tersamar), tidak mengubah.
        $tambahan = $this->tambahan('1', 'anak@contoh.test');
        $this->as($tambahan)->getJson('/api/v1/household')->assertJsonPath('data.nomor_kk_tersamar', '3674••••••••8901');
        $this->patchJson('/api/v1/household', ['nomor_kk' => '3674012345678902', 'versi' => $k->fresh()->versi])->assertForbidden();

        // Pengurus mengisi untuk warga yang tidak mengisi sendiri, dan bisa mengosongkan.
        $admin = $this->pengurus();
        $anak = Warga::where('keluarga_id', $k->id)->where('is_kepala', false)->where('nama', 'Uji Anak 1')->sole();
        $this->as($admin)->patchJson("/api/v1/admin/residents/{$anak->id}", ['nik' => '3674010101100002', 'versi' => $k->fresh()->versi])->assertOk();
        $this->assertSame('3674010101100002', $anak->fresh()->nik);
        $this->patchJson("/api/v1/admin/families/{$k->id}", ['nomor_kk' => null, 'versi' => $k->fresh()->versi])->assertOk()
            ->assertJsonPath('data.nomor_kk_tersamar', null);
        $this->assertNull(Keluarga::find($k->id)->nomor_kk);
        $this->patchJson("/api/v1/admin/families/{$k->id}", ['versi' => $k->fresh()->versi])->assertStatus(422);
    }

    // ---------- Kebijakan Privasi

    public function test_privacy_consent_is_required_once_per_version(): void
    {
        $warga = $this->warga('1');
        $this->as($warga)->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('data.privasi_perlu_setuju', true)->assertJsonPath('data.privasi_versi', Privacy::VERSION);
        $this->postJson('/api/v1/auth/privacy-consent', ['versi' => '2020-01-01'])->assertStatus(409)->assertJsonPath('error.code', 'PRIVACY_VERSION_OUTDATED');
        $this->postJson('/api/v1/auth/privacy-consent', ['versi' => Privacy::VERSION])->assertOk()->assertJsonPath('data.privasi_perlu_setuju', false);
        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.privasi_perlu_setuju', false);
        $this->assertNotNull(Akun::find($warga->id)->privasi_disetujui_at);
        $this->assertTrue(AuditLog::where('action', 'akun.privasi_disetujui')->where('entity_id', $warga->id)->exists());

        // Akun menunggu persetujuan juga dapat menyetujui.
        $pending = $this->pemohon('1', 'tunggu@contoh.test');
        $this->as($pending)->postJson('/api/v1/auth/privacy-consent', ['versi' => Privacy::VERSION])->assertOk();
    }
}
