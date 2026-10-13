<?php

namespace Tests\Feature;

use App\Domain\Operations\OutboxDispatcher;
use App\Jobs\ProcessComplaintPhoto;
use App\Models\FotoPengaduan;
use App\Models\KategoriPengaduan;
use App\Models\Pengaduan;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\Fixtures;
use Tests\Support\OpenApi;
use Tests\TestCase;

/**
 * M08 (ROADMAP gate): foto diproses worker-media dengan status, metadata dibuang, spoof/oversized
 * ditolak, retry aman, referensi parent asing ditolak, anonimitas tanpa petunjuk tidak langsung,
 * akun pending/nonaktif ditolak, arsip mempertahankan histori.
 */
class M08ComplaintMediaTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function complaint($akun, bool $anon = true, array $photos = [])
    {
        return $this->as($akun)->post('/api/v1/complaints', [
            'kategori_id' => KategoriPengaduan::first()->id, 'judul' => 'Uji saluran tersumbat', 'deskripsi' => 'Deskripsi uji M08',
            'sembunyikan_identitas' => $anon ? '1' : '0', 'foto' => $photos,
        ], ['Accept' => 'application/json']);
    }

    /** JPEG dengan segmen APP1 Exif berisi penanda GPS sintetis. */
    private function jpegWithExif(int $w = 2400, int $h = 1200): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        ob_start();
        imagejpeg($img);
        $jpeg = (string) ob_get_clean();
        $payload = "Exif\0\0".'MM'.str_repeat("\0", 6).'RT05-GPS-SINTETIS-6.2S106.7E';
        $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;
        $path = tempnam(sys_get_temp_dir(), 'm08');
        file_put_contents($path, substr($jpeg, 0, 2).$app1.substr($jpeg, 2));

        return new UploadedFile($path, 'rumah-pelapor-uji.jpg', 'image/jpeg', null, true);
    }

    /** PNG yang header IHDR-nya menyatakan dimensi besar (tanpa alokasi piksel nyata). */
    private function pngHeader(int $w, int $h): UploadedFile
    {
        $ihdr = pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0);
        $bytes = "\x89PNG\r\n\x1A\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr)).pack('N', 0).'IEND'.pack('N', crc32('IEND'));

        return UploadedFile::fake()->createWithContent('besar.png', $bytes);
    }

    public function test_photo_waits_for_worker_then_is_stripped_and_ready_and_retry_is_safe(): void
    {
        Queue::fake();
        $a = $this->warga('81');
        $b = $this->warga('82');
        $admin = $this->pengurus();
        $res = $this->complaint($a, true, [$this->jpegWithExif()])->assertCreated();
        $id = $res->json('data.id');
        $res->assertJsonPath('data.foto.0.status', 'diproses')->assertJsonPath('data.foto.0.url', null);
        $this->assertSame([], OpenApi::errors($res->json(), 'ComplaintEnvelope'));

        $foto = FotoPengaduan::firstOrFail();
        Queue::assertPushedOn('media', ProcessComplaintPhoto::class, fn ($job) => $job->fotoId === $foto->id);
        $this->assertStringStartsWith('pengaduan/masuk/', $foto->storage_key);
        $this->assertStringNotContainsString('pelapor', $foto->storage_key);
        $raw = $foto->storage_key;

        // Belum siap: warga lain tidak melihat entri foto, endpoint unduh 404.
        $this->as($b)->getJson("/api/v1/complaints/$id")->assertJsonCount(0, 'data.foto');
        $this->as($b)->get("/api/v1/complaints/$id/photos/{$foto->id}")->assertNotFound();
        $this->as($admin)->getJson("/api/v1/complaints/$id")->assertJsonPath('data.foto.0.status', 'diproses');

        (new ProcessComplaintPhoto($foto->id))->handle();
        $foto->refresh();
        $this->assertSame('siap', $foto->status);
        $this->assertSame("pengaduan/{$foto->id}.jpg", $foto->storage_key);
        $this->assertSame([1600, 800], [$foto->lebar, $foto->tinggi]);
        Storage::disk('local')->assertMissing($raw);
        $out = Storage::disk('local')->get($foto->storage_key);
        $this->assertStringNotContainsString('RT05-GPS', $out);
        $this->assertStringNotContainsString('Exif', $out);

        // Job ganda/replay: foto siap dilewati, tidak ada perubahan.
        (new ProcessComplaintPhoto($foto->id))->handle();
        $this->assertSame(1, $foto->fresh()->percobaan);

        $shown = $this->as($b)->getJson("/api/v1/complaints/$id")->assertJsonPath('data.foto.0.status', 'siap')->assertJsonPath('data.pelapor', null);
        $this->assertSame([], OpenApi::errors($shown->json(), 'ComplaintEnvelope'));
        $this->assertSame([], OpenApi::errors($this->as($admin)->getJson("/api/v1/complaints/$id")->json(), 'ComplaintEnvelope'));
        $url = $shown->json('data.foto.0.url');
        $download = $this->as($b)->get($url)->assertOk()->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringNotContainsString('pelapor', (string) $download->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString($a->id, $url);
    }

    public function test_spoofed_disallowed_and_oversized_uploads_are_rejected_without_writes(): void
    {
        Queue::fake();
        $a = $this->warga('83');
        $cases = [
            [UploadedFile::fake()->createWithContent('foto.jpg', '<?php echo 1; ?>'), 415],
            [UploadedFile::fake()->createWithContent('foto.jpg', "\xFF\xD8\xFF\xE0".str_repeat('x', 64)), 415],
            [UploadedFile::fake()->createWithContent('gambar.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), 415],
            [UploadedFile::fake()->createWithContent('index.html', '<html><body>x</body></html>'), 415],
            [UploadedFile::fake()->image('animasi.gif', 10, 10), 415],
            [$this->pngHeader(8000, 7000), 413],
        ];
        foreach ($cases as [$file, $status]) {
            $this->complaint($a, false, [$file])->assertStatus($status);
        }
        $this->complaint($a, false, [UploadedFile::fake()->create('besar.jpg', 13 * 1024, 'image/jpeg')])->assertStatus(422);

        $this->assertSame(0, Pengaduan::count());
        $this->assertSame(0, FotoPengaduan::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        Queue::assertNothingPushed();
    }

    public function test_unreadable_and_exhausted_jobs_fail_without_touching_the_report(): void
    {
        Queue::fake();
        $a = $this->warga('84');
        $png = UploadedFile::fake()->image('a.png', 40, 40);
        $id = $this->complaint($a, false, [$png, UploadedFile::fake()->image('b.png', 40, 40)])->assertCreated()->json('data.id');
        [$f1, $f2] = FotoPengaduan::orderBy('urutan')->get()->all();

        Storage::disk('local')->delete($f1->storage_key);
        (new ProcessComplaintPhoto($f1->id))->handle();
        $this->assertSame(['gagal', 'UNREADABLE'], [$f1->fresh()->status, $f1->fresh()->kode_gagal]);

        (new ProcessComplaintPhoto($f2->id))->failed(new RuntimeException('disk'));
        $this->assertSame('gagal', $f2->fresh()->status);
        Storage::disk('local')->assertMissing($f2->storage_key);

        $this->as($a)->getJson("/api/v1/complaints/$id")->assertOk()
            ->assertJsonPath('data.status', 'diajukan')
            ->assertJsonPath('data.foto.0.status', 'gagal')->assertJsonPath('data.foto.0.url', null);
        $this->as($this->warga('85'))->getJson("/api/v1/complaints/$id")->assertJsonCount(0, 'data.foto');
    }

    public function test_enqueue_failure_after_commit_keeps_report_and_recovery_requeues(): void
    {
        $this->mock(Dispatcher::class)->shouldReceive('dispatch')->andThrow(new RuntimeException('redis down'));
        $a = $this->warga('86');
        $this->complaint($a, false, [UploadedFile::fake()->image('a.png', 40, 40)])->assertCreated();
        $foto = FotoPengaduan::firstOrFail();
        $this->assertSame('diproses', $foto->status);

        // Pulihkan dispatcher asli (instance() melepas alias kontrak → singleton bus).
        $this->app->forgetInstance(Dispatcher::class);
        $this->app->alias(\Illuminate\Bus\Dispatcher::class, Dispatcher::class);
        Queue::fake();
        FotoPengaduan::whereKey($foto->id)->update(['updated_at' => now()->subMinutes(30)]);
        $this->artisan('rt05:media-recover')->assertSuccessful();
        Queue::assertPushedOn('media', ProcessComplaintPhoto::class, fn ($job) => $job->fotoId === $foto->id);

        // Percobaan habis → gagal; berkas mentah yatim >24 jam dihapus, yang masih dirujuk tidak.
        FotoPengaduan::whereKey($foto->id)->update(['percobaan' => 5, 'updated_at' => now()->subMinutes(30)]);
        Storage::disk('local')->put('pengaduan/masuk/yatim', 'x');
        touch(Storage::disk('local')->path('pengaduan/masuk/yatim'), now()->subDays(2)->getTimestamp());
        $this->artisan('rt05:media-recover')->assertSuccessful();
        $this->assertSame(['gagal', 'PROCESSING_STALLED'], [$foto->fresh()->status, $foto->fresh()->kode_gagal]);
        $this->assertSame([], Storage::disk('local')->allFiles('pengaduan/masuk'));
    }

    public function test_photo_of_another_complaint_cannot_be_read_through_a_foreign_parent(): void
    {
        $a = $this->warga('87');
        $b = $this->warga('88');
        $idA = $this->complaint($a, false, [UploadedFile::fake()->image('a.jpg', 40, 40)])->json('data.id');
        $idB = $this->complaint($b, false)->json('data.id');
        $fotoA = FotoPengaduan::where('pengaduan_id', $idA)->firstOrFail();
        $this->assertSame('siap', $fotoA->status);

        $this->as($b)->get("/api/v1/complaints/$idA/photos/{$fotoA->id}")->assertOk();
        $this->as($b)->get("/api/v1/complaints/$idB/photos/{$fotoA->id}")->assertNotFound();
        $this->as($a)->patchJson("/api/v1/complaints/$idB", ['versi' => 1, 'judul' => 'diambil alih'])->assertForbidden();
    }

    public function test_anonymous_report_by_a_pengurus_leaves_no_indirect_identity(): void
    {
        $admin = $this->pengurus('pelapor-pengurus@contoh.test');
        $other = $this->pengurus('pengurus-lain@contoh.test');
        $b = $this->warga('89');
        $id = $this->complaint($admin, true)->assertCreated()->json('data.id');
        $this->as($other)->postJson("/api/v1/admin/complaints/$id/status", ['status' => 'diproses', 'expected_version' => 1, 'catatan' => 'Dicek'])->assertOk();

        $history = $this->as($b)->getJson("/api/v1/complaints/$id/history")->assertOk()->json('data');
        $this->assertSame(['nama' => 'Warga', 'peran' => 'warga'], $history[0]['aktor']);
        $this->assertSame(['nama' => 'Pengurus RT', 'peran' => 'pengurus'], $history[1]['aktor']);
        foreach (["/api/v1/complaints/$id", '/api/v1/complaints', "/api/v1/complaints/$id/history"] as $url) {
            $body = $this->as($b)->getJson($url)->assertOk()->getContent();
            $this->assertStringNotContainsString($admin->id, $body, $url);
            $this->assertStringNotContainsString('pelapor-pengurus', $body, $url);
        }
        // Pengurus lain tetap melihat pelapor (BR09).
        $this->as($other)->getJson("/api/v1/complaints/$id")->assertJsonPath('data.pelapor.id', $admin->id);
    }

    public function test_pending_and_inactive_accounts_are_denied(): void
    {
        $a = $this->warga('90');
        $id = $this->complaint($a, false)->json('data.id');
        $pending = $this->pemohon('90', 'tambahan90@contoh.test');
        $inactive = $this->warga('91');
        $inactive->forceFill(['status' => 'nonaktif'])->save();

        foreach ([$pending, $inactive] as $akun) {
            $this->as($akun)->getJson('/api/v1/complaints')->assertForbidden();
            $this->as($akun)->getJson("/api/v1/complaints/$id")->assertForbidden();
            $this->as($akun)->getJson("/api/v1/complaints/$id/history")->assertForbidden();
            $this->complaint($akun, false)->assertForbidden();
        }
        $this->assertSame(1, Pengaduan::count());
    }

    public function test_archive_preserves_history_and_blocks_further_changes(): void
    {
        $a = $this->warga('92');
        $admin = $this->pengurus();
        $id = $this->complaint($a, false, [UploadedFile::fake()->image('a.jpg', 40, 40)])->json('data.id');
        $this->as($admin)->postJson("/api/v1/admin/complaints/$id/status", ['status' => 'diproses', 'expected_version' => 1])->assertOk();
        $this->postJson("/api/v1/admin/complaints/$id/archive", ['alasan' => 'Duplikat laporan lain'])->assertNoContent();

        $this->postJson("/api/v1/admin/complaints/$id/archive", ['alasan' => 'lagi'])->assertStatus(409)->assertJsonPath('error.code', 'ALREADY_ARCHIVED');
        $this->postJson("/api/v1/admin/complaints/$id/status", ['status' => 'selesai', 'expected_version' => 2])->assertStatus(409)->assertJsonPath('error.code', 'ALREADY_ARCHIVED');
        $this->getJson('/api/v1/complaints?arsip=1')->assertJsonPath('data.0.id', $id);
        $this->getJson("/api/v1/complaints/$id/history")->assertJsonCount(2, 'data');
        $this->assertSame(1, FotoPengaduan::where('pengaduan_id', $id)->count());
        $this->assertTrue(DB::table('audit_logs')->where('entity_id', $id)->where('action', 'pengaduan.diarsipkan')->exists());

        $this->as($a)->getJson('/api/v1/complaints?milik_saya=1')->assertJsonCount(0, 'data');
        $this->as($a)->patchJson("/api/v1/complaints/$id", ['versi' => 2, 'judul' => 'x'])->assertNotFound();
    }

    public function test_late_outbox_delivery_names_each_transition_not_the_current_status(): void
    {
        $a = $this->warga('94');
        $admin = $this->pengurus();
        $id = $this->complaint($a, false)->json('data.id');
        $this->as($admin);
        $this->postJson("/api/v1/admin/complaints/$id/status", ['status' => 'diproses', 'expected_version' => 1])->assertOk();
        $this->postJson("/api/v1/admin/complaints/$id/status", ['status' => 'selesai', 'expected_version' => 2])->assertOk();
        $this->postJson("/api/v1/admin/complaints/$id/status", ['status' => 'diproses', 'expected_version' => 3, 'catatan' => 'Buka ulang'])->assertOk();

        OutboxDispatcher::dispatch();

        // Satu transaksi tes → now() sama; urutan tidak dibandingkan, hanya isi tiap langkah.
        $labels = DB::table('notifikasi')->where('penerima_id', $a->id)->where('pengaduan_id', $id)->pluck('isi')
            ->map(fn ($t) => preg_match('/berstatus (\w+)\./', $t, $m) ? $m[1] : null)->sort()->values()->all();
        $this->assertSame(['Diproses', 'Diproses', 'Selesai'], $labels);
    }

    public function test_owner_edit_stale_version_conflicts_then_succeeds_with_fresh_version(): void
    {
        $a = $this->warga('93');
        $id = $this->complaint($a, true)->json('data.id');
        $this->as($a)->patchJson("/api/v1/complaints/$id", ['versi' => 1, 'judul' => 'Perbaikan pertama'])->assertOk()->assertJsonPath('data.versi', 2);
        $this->patchJson("/api/v1/complaints/$id", ['versi' => 1, 'judul' => 'Dari perangkat lama'])->assertStatus(409)->assertJsonPath('error.code', 'VERSION_CONFLICT');
        $this->patchJson("/api/v1/complaints/$id", ['versi' => 2, 'judul' => 'Dari perangkat lama', 'sembunyikan_identitas' => false])
            ->assertOk()->assertJsonPath('data.judul', 'Dari perangkat lama')->assertJsonPath('data.sembunyikan_identitas', false);
    }
}
