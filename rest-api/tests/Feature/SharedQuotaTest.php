<?php

namespace Tests\Feature;

use App\Domain\Services\Reports;
use App\Exceptions\DomainConflict;
use App\Models\FotoPengaduan;
use App\Models\KategoriPengaduan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures;
use Tests\TestCase;

/** Kuota harian dan transkode setelah response hanya pada profil shared. */
class SharedQuotaTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'rt05.deployment_profile' => 'shared',
            'queue.default' => 'database',
            'rt05.outbox.queue_connection' => 'database',
            'cache.default' => 'file',
            'session.driver' => 'file',
            'rt05.heartbeat.store' => 'file',
        ]);
        Storage::fake('local');
    }

    public function test_sixth_complaint_in_a_day_is_rejected(): void
    {
        $akun = $this->warga('93');
        $data = [
            'kategori_id' => KategoriPengaduan::first()->id,
            'judul' => 'Uji kuota',
            'deskripsi' => 'Laporan sintetis.',
            'sembunyikan_identitas' => true,
        ];
        for ($i = 0; $i < Reports::SHARED_DAILY_REPORTS; $i++) {
            Reports::createComplaint($akun, $data, []);
        }

        try {
            Reports::createComplaint($akun, $data, []);
            $this->fail('Pengaduan keenam harus ditolak.');
        } catch (DomainConflict $e) {
            $this->assertSame('DAILY_QUOTA', $e->errorCode);
            $this->assertSame(429, $e->status);
        }
    }

    public function test_photo_over_the_daily_cap_is_rejected_before_staging(): void
    {
        $akun = $this->warga('94');
        $pengaduan = Reports::createComplaint($akun, [
            'kategori_id' => KategoriPengaduan::first()->id,
            'judul' => 'Uji kuota foto',
            'deskripsi' => 'Laporan sintetis.',
            'sembunyikan_identitas' => true,
        ], []);
        for ($i = 1; $i <= Reports::SHARED_DAILY_PHOTOS; $i++) {
            FotoPengaduan::create([
                'pengaduan_id' => $pengaduan->id,
                'storage_key' => "pengaduan/kuota-sintetis-$i",
                'mime' => 'image/jpeg',
                'bytes' => 10,
                'lebar' => 8,
                'tinggi' => 8,
                'urutan' => $i,
                'status' => 'siap',
            ]);
        }

        try {
            Reports::createComplaint($akun, [
                'kategori_id' => KategoriPengaduan::first()->id,
                'judul' => 'Uji kuota foto dua',
                'deskripsi' => 'Laporan sintetis.',
                'sembunyikan_identitas' => true,
            ], [UploadedFile::fake()->image('tambahan.jpg', 20, 20)]);
            $this->fail('Foto di atas kuota harian harus ditolak.');
        } catch (DomainConflict $e) {
            $this->assertSame('DAILY_QUOTA', $e->errorCode);
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_photo_is_reencoded_after_the_response_without_a_queue_job(): void
    {
        $akun = $this->warga('95');
        $pengaduan = Reports::createComplaint($akun, [
            'kategori_id' => KategoriPengaduan::first()->id,
            'judul' => 'Uji transkode',
            'deskripsi' => 'Laporan sintetis.',
            'sembunyikan_identitas' => true,
        ], [UploadedFile::fake()->image('foto.jpg', 2000, 1000)]);
        $this->assertSame('diproses', $pengaduan->foto()->first()->status);

        $this->app->terminate();

        $foto = $pengaduan->foto()->first();
        $this->assertSame('siap', $foto->status);
        $this->assertSame(1600, $foto->lebar);
        $this->assertSame('image/jpeg', $foto->mime);
        $this->assertSame(0, DB::table('jobs')->count());
    }
}
