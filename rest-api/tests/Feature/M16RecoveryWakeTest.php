<?php

namespace Tests\Feature;

use App\Domain\Services\Reports;
use App\Models\FotoPengaduan;
use App\Models\KategoriPengaduan;
use App\Support\IdleGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * M16 (sisa M15 #5): foto/push yang masih tertunda membuat profil VPS tetap aktif, sehingga pemulihan jalan di
 * sweep 10 menit berikutnya, bukan menunggu pengaman per jam. Tanpa kerja tertunda guard tidak diperpanjang.
 */
class M16RecoveryWakeTest extends TestCase
{
    use DatabaseTransactions;
    use Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Queue::fake();
        Storage::fake('local');
    }

    public function test_photo_still_processing_keeps_the_guard_active_until_it_finishes(): void
    {
        $pengaduan = Reports::createComplaint($this->warga('96'), [
            'kategori_id' => KategoriPengaduan::first()->id, 'judul' => 'Uji pemulihan foto',
            'deskripsi' => 'Laporan sintetis.', 'sembunyikan_identitas' => false,
        ], []);
        $foto = FotoPengaduan::create([
            'pengaduan_id' => $pengaduan->id, 'storage_key' => 'pengaduan/staging/pulih-sintetis', 'mime' => 'image/jpeg',
            'bytes' => 10, 'lebar' => 8, 'tinggi' => 8, 'urutan' => 1, 'status' => 'diproses',
        ]);
        Cache::flush();

        $this->artisan('rt05:media-recover')->assertExitCode(0);
        $this->assertTrue(IdleGuard::active(), 'foto belum selesai: sweep berikutnya 10 menit lagi');

        $foto->update(['status' => 'siap']);
        Cache::flush();
        $this->artisan('rt05:media-recover')->assertExitCode(0);
        $this->assertFalse(IdleGuard::active(), 'tidak ada foto tertunda: Neon boleh tidur');
    }

    public function test_recent_unprocessed_push_keeps_the_guard_active_but_old_ones_do_not(): void
    {
        $akun = $this->warga('97');
        $id = DB::table('notifikasi')->insertGetId([
            'id' => (string) Str::uuid(), 'event_id' => (string) Str::uuid(), 'penerima_id' => $akun->id,
            'jenis' => 'sistem', 'judul' => 'Uji push', 'isi' => 'Notifikasi sintetis.', 'created_at' => now()->subMinutes(15),
        ]);
        Cache::flush();

        $this->artisan('rt05:push-recover')->assertExitCode(0);
        $this->assertTrue(IdleGuard::active(), 'push belum terproses (<1 jam): tetap dipantau');

        // Lebih dari satu jam: serahkan ke pengaman per jam (PushDelivery melewatinya setelah 24 jam).
        DB::table('notifikasi')->where('id', $id)->update(['created_at' => now()->subMinutes(61)]);
        Cache::flush();
        $this->artisan('rt05:push-recover')->assertExitCode(0);
        $this->assertFalse(IdleGuard::active());
    }
}
