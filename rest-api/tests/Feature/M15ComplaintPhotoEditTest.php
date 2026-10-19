<?php

namespace Tests\Feature;

use App\Domain\Services\Reports;
use App\Jobs\ProcessComplaintPhoto;
use App\Models\FotoPengaduan;
use App\Models\KategoriPengaduan;
use App\Models\Pengaduan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures;
use Tests\Support\OpenApi;
use Tests\TestCase;

/**
 * UAT M15 (19 Okt 2026): pelapor dapat menambah/menghapus foto selama pengaduan diajukan; batas
 * 5 foto per pengaduan, 25 foto dan 5 pengaduan per akun per hari, 5 MB per foto di semua profil.
 */
class M15ComplaintPhotoEditTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
    }

    private function create($akun, int $photos = 1): array
    {
        return $this->as($akun)->post('/api/v1/complaints', [
            'kategori_id' => KategoriPengaduan::first()->id, 'judul' => 'Uji edit foto', 'deskripsi' => 'Deskripsi uji edit foto',
            'foto' => array_map(fn ($i) => UploadedFile::fake()->image("f$i.jpg", 800, 600), range(1, max(1, $photos))),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
    }

    private function add($akun, string $id, int $versi, int $count)
    {
        return $this->as($akun)->post("/api/v1/complaints/$id/photos", [
            'versi' => $versi, 'foto' => array_map(fn ($i) => UploadedFile::fake()->image("t$i.jpg", 800, 600), range(1, $count)),
        ], ['Accept' => 'application/json']);
    }

    public function test_owner_adds_and_removes_photos_while_submitted(): void
    {
        $a = $this->warga('61');
        $c = $this->create($a, 2);

        $res = $this->add($a, $c['id'], $c['versi'], 2)->assertOk();
        $this->assertSame([], OpenApi::errors($res->json(), 'ComplaintEnvelope'));
        $this->assertSame($c['versi'] + 1, $res->json('data.versi'));
        $this->assertSame(4, FotoPengaduan::where('pengaduan_id', $c['id'])->count());
        $this->assertSame([1, 2, 3, 4], FotoPengaduan::where('pengaduan_id', $c['id'])->orderBy('urutan')->pluck('urutan')->all());
        Queue::assertPushed(ProcessComplaintPhoto::class, 4);

        // Total maks. 5: menambah 2 lagi ditolak, tanpa baris/berkas baru.
        $files = count(Storage::disk('local')->allFiles());
        $this->add($a, $c['id'], $res->json('data.versi'), 2)->assertStatus(422)->assertJsonPath('error.code', 'TOO_MANY_PHOTOS');
        $this->assertSame($files, count(Storage::disk('local')->allFiles()));

        $foto = FotoPengaduan::where('pengaduan_id', $c['id'])->orderBy('urutan')->first();
        $key = $foto->storage_key;
        $this->as($a)->deleteJson("/api/v1/complaints/{$c['id']}/photos/{$foto->id}?versi=".$res->json('data.versi'))
            ->assertOk()->assertJsonPath('data.versi', $res->json('data.versi') + 1);
        $this->assertNull(FotoPengaduan::find($foto->id));
        Storage::disk('local')->assertMissing($key);

        // Urutan baru setelah celah: max+1, tidak bentrok unik (pengaduan_id, urutan).
        $this->add($a, $c['id'], $res->json('data.versi') + 1, 2)->assertOk();
        $this->assertSame([2, 3, 4, 5, 6], FotoPengaduan::where('pengaduan_id', $c['id'])->orderBy('urutan')->pluck('urutan')->all());
    }

    public function test_only_owner_while_submitted_and_with_current_version(): void
    {
        $a = $this->warga('62');
        $b = $this->warga('63');
        $c = $this->create($a);

        $this->add($b, $c['id'], $c['versi'], 1)->assertStatus(403)->assertJsonPath('error.code', 'COMPLAINT_LOCKED');
        $this->add($a, $c['id'], $c['versi'] + 5, 1)->assertStatus(409);
        $foto = FotoPengaduan::where('pengaduan_id', $c['id'])->first();
        $this->as($b)->deleteJson("/api/v1/complaints/{$c['id']}/photos/{$foto->id}?versi={$c['versi']}")->assertStatus(403);

        Pengaduan::whereKey($c['id'])->update(['status' => 'diproses']);
        $this->add($a, $c['id'], $c['versi'], 1)->assertStatus(403)->assertJsonPath('error.code', 'COMPLAINT_LOCKED');
        $this->as($a)->deleteJson("/api/v1/complaints/{$c['id']}/photos/{$foto->id}?versi={$c['versi']}")->assertStatus(403);
        $this->assertNotNull(FotoPengaduan::find($foto->id));
    }

    public function test_daily_limits_apply_on_vps_profile_too(): void
    {
        $a = $this->warga('64');
        for ($i = 0; $i < Reports::DAILY_REPORTS; $i++) {
            $this->create($a, 5);
        }
        // 5 pengaduan × 5 foto = 25: pengaduan keenam dan foto tambahan ditolak 429.
        $this->as($a)->post('/api/v1/complaints', [
            'kategori_id' => KategoriPengaduan::first()->id, 'judul' => 'Uji keenam', 'deskripsi' => 'Deskripsi uji keenam',
        ], ['Accept' => 'application/json'])->assertStatus(429)->assertJsonPath('error.code', 'DAILY_QUOTA');

        $b = $this->warga('65');
        $c = $this->create($b, 1);
        $this->travel(1)->days();
        $this->add($b, $c['id'], $c['versi'], 1)->assertOk();
    }

    public function test_oversized_photo_is_rejected(): void
    {
        $a = $this->warga('66');
        $c = $this->create($a);
        $big = UploadedFile::fake()->create('besar.jpg', 6 * 1024, 'image/jpeg');
        $this->as($a)->post("/api/v1/complaints/{$c['id']}/photos", ['versi' => $c['versi'], 'foto' => [$big]], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->assertSame(1, FotoPengaduan::where('pengaduan_id', $c['id'])->count());
    }
}
