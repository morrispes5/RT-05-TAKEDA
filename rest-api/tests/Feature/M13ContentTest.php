<?php

namespace Tests\Feature;

use App\Domain\Content\Catalog;
use App\Domain\Operations\OutboxDispatcher;
use App\Models\Konten;
use App\Models\OutboxEvent;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures;
use Tests\Support\OpenApi;
use Tests\TestCase;

/**
 * M13 gate: impor ganda aman; draf tidak bocor; terbit hanya dengan media siap; edit bersamaan 409;
 * snapshot stabil; redirect slug; fakta yang sudah diterima website tetap kata per kata.
 */
class M13ContentTest extends TestCase
{
    use DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function article(array $over = []): array
    {
        return $over + [
            'title' => 'Jadwal kerja bakti bulanan',
            'category' => 'Lingkungan',
            'art' => 'park',
            'summary' => 'Kerja bakti rutin setiap bulan untuk lingkungan bersih.',
            'intro' => 'Warga diajak membersihkan saluran dan area bersama.',
            'sections' => [['title' => 'Waktu', 'body' => 'Minggu pertama setiap bulan pukul 07.00 WIB.']],
        ];
    }

    private function create(string $slug = 'kerja-bakti', array $draf = [])
    {
        return $this->postJson('/api/v1/admin/articles', ['slug' => $slug, 'draf' => $draf ?: $this->article()]);
    }

    public function test_catalog_import_is_idempotent_and_keeps_accepted_facts_word_for_word(): void
    {
        $website = base_path('../website/resources/data');
        foreach (['artikel.json', 'album.json'] as $f) {
            $this->assertSame(file_get_contents("$website/$f"), file_get_contents(Catalog::path()."/$f"), "$f harus salinan byte-identik website");
        }
        $blade = file_get_contents(base_path('../website/resources/views/fasilitas.blade.php')).file_get_contents(base_path('../website/resources/views/profil.blade.php'));
        foreach (json_decode(file_get_contents(Catalog::path().'/fasilitas.json'), true) as $f) {
            $this->assertStringContainsString($f['description'], $blade, 'deskripsi fasilitas diambil kata per kata');
        }

        $this->artisan('rt05:konten-impor')->expectsOutputToContain('Konten baru: 13')->assertSuccessful();
        $this->artisan('rt05:konten-impor')->expectsOutputToContain('Konten baru: 0, dilewati (sudah ada): 13')->assertSuccessful();

        $this->assertSame(['album' => 3, 'artikel' => 5, 'fasilitas' => 4, 'profil' => 1],
            Konten::where('status', 'terbit')->selectRaw('jenis, count(*) n')->groupBy('jenis')->orderBy('jenis')->pluck('n', 'jenis')->map(fn ($n) => (int) $n)->all());
        $source = json_decode(file_get_contents("$website/artikel.json"), true)[0];
        $public = $this->getJson('/api/v1/public/articles/'.$source['slug'])->assertOk()->assertHeader('Cache-Control', 'max-age=60, public');
        // jsonb Postgres mengurutkan ulang kunci objek; isi kata per kata tetap sama.
        $this->assertEquals([$source['title'], $source['sections']], [$public->json('data.title'), $public->json('data.sections')]);

        $profile = $this->getJson('/api/v1/public/site-profile')->assertOk();
        $this->assertSame('Agus Ferdiansyah', $profile->json('data.pengurus.0.nama'));
        $this->assertSame('Nama belum dipublikasikan', $profile->json('data.pengurus.1.nama'), 'sekretaris tetap anonim');
        $this->assertStringNotContainsString('dibuat_oleh', $profile->getContent());

        $facilities = $this->getJson('/api/v1/public/facilities')->assertOk()->json('data');
        $this->assertSame(['lapangan', 'taman-bermain', 'sekretariat', 'area-hijau'], array_column($facilities, 'slug'), 'urutan katalog');
        $this->assertStringStartsWith('/images/dokumentasi/lapangan-', $facilities[0]['photo']['url']);
        $album = $this->getJson('/api/v1/public/albums/perayaan-17-agustus')->assertOk();
        $this->assertNull($album->json('data.date'), 'album 17 Agustus tanpa tahun');
        $this->assertSame('/images/kegiatan/perayaan-17-agustus-1280.webp', $album->json('data.cover.url'));

        // Suntingan pengurus tidak ditimpa impor berikutnya.
        $this->as($this->pengurus());
        $k = Konten::where('jenis', 'artikel')->where('slug', $source['slug'])->first();
        $this->putJson("/api/v1/admin/articles/{$k->id}", ['versi' => 1, 'slug' => $source['slug'], 'draf' => $this->article()])->assertOk();
        $this->artisan('rt05:konten-impor')->assertSuccessful();
        $this->assertSame('Jadwal kerja bakti bulanan', $k->refresh()->draf['title']);
    }

    public function test_drafts_never_leak_and_published_snapshot_is_stable_until_republished(): void
    {
        $this->as($this->pengurus());
        $res = $this->create()->assertCreated()->assertJsonPath('data.status', 'draf');
        $this->assertSame([], OpenApi::errors($res->json(), 'ContentAdminEnvelope'));
        $id = $res->json('data.id');

        $this->getJson('/api/v1/public/articles/kerja-bakti')->assertNotFound();
        $this->assertSame([], $this->getJson('/api/v1/public/articles')->json('data'));

        $this->postJson("/api/v1/admin/articles/$id/publish", ['versi' => 1])->assertOk()->assertJsonPath('data.status', 'terbit');
        $this->getJson('/api/v1/public/articles/kerja-bakti')->assertOk()->assertJsonPath('data.title', 'Jadwal kerja bakti bulanan');

        $this->putJson("/api/v1/admin/articles/$id", ['versi' => 1, 'slug' => 'kerja-bakti', 'draf' => $this->article(['title' => 'Draf judul rahasia baru'])])
            ->assertOk()->assertJsonPath('data.ada_perubahan_belum_terbit', true);
        $public = $this->getJson('/api/v1/public/articles/kerja-bakti')->assertOk();
        $this->assertSame('Jadwal kerja bakti bulanan', $public->json('data.title'), 'snapshot stabil');
        $this->assertStringNotContainsString('rahasia', $this->getJson('/api/v1/public/articles')->getContent());

        $this->postJson("/api/v1/admin/articles/$id/publish", ['versi' => 2])->assertOk();
        $this->getJson('/api/v1/public/articles/kerja-bakti')->assertJsonPath('data.title', 'Draf judul rahasia baru');
    }

    public function test_concurrent_edits_conflict_and_validation_rejects_bad_payloads(): void
    {
        $this->as($this->pengurus());
        $id = $this->create()->json('data.id');
        $this->putJson("/api/v1/admin/articles/$id", ['versi' => 1, 'slug' => 'kerja-bakti', 'draf' => $this->article(['title' => 'Versi pengurus A'])])->assertOk();
        $this->putJson("/api/v1/admin/articles/$id", ['versi' => 1, 'slug' => 'kerja-bakti', 'draf' => $this->article(['title' => 'Versi pengurus B'])])
            ->assertStatus(409)->assertJsonPath('error.code', 'VERSION_CONFLICT');
        $this->postJson("/api/v1/admin/articles/$id/publish", ['versi' => 1])->assertStatus(409);

        $this->create('Slug Salah')->assertStatus(422);
        $this->create('kerja-bakti')->assertStatus(409)->assertJsonPath('error.code', 'SLUG_TAKEN');
        $this->create('lain', $this->article(['art' => 'svg-liar']))->assertStatus(422);
        $this->create('lain', $this->article(['sections' => []]))->assertStatus(422);
        $this->create('lain', $this->article(['source' => ['url' => 'javascript:alert(1)', 'label' => 'x']]))->assertStatus(422);
        $this->postJson('/api/v1/admin/albums', ['slug' => 'album-x', 'draf' => [
            'title' => 'Album uji', 'description' => 'Deskripsi album uji.', 'cover_media_id' => '0199f0a1-0000-7000-8000-000000000009',
            'photos' => [['media_id' => '0199f0a1-0000-7000-8000-000000000001', 'alt' => 'Foto uji satu']],
        ]])->assertStatus(422);
    }

    public function test_publish_requires_ready_media_and_private_files_stay_private_until_published(): void
    {
        $admin = $this->pengurus();
        $this->as($admin);
        $this->post('/api/v1/admin/content-media', ['foto' => UploadedFile::fake()->createWithContent('a.jpg', 'bukan gambar')], ['Accept' => 'application/json'])
            ->assertStatus(415);
        $upload = $this->post('/api/v1/admin/content-media', ['foto' => UploadedFile::fake()->image('lapangan.jpg', 2400, 1600)], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.status', 'siap')->assertJsonPath('data.lebar', 1600);
        $this->assertSame([], OpenApi::errors($upload->json(), 'ContentMediaEnvelope'));
        $mediaId = $upload->json('data.id');

        $missing = $this->postJson('/api/v1/admin/facilities', ['slug' => 'pos-ronda', 'draf' => [
            'title' => 'Pos ronda', 'description' => 'Pos ronda warga di ujung jalan.', 'media_id' => '0199f0a1-0000-7000-8000-00000000dead', 'alt' => 'Pos ronda warga',
        ]])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/admin/facilities/$missing/publish", ['versi' => 1])->assertStatus(422)->assertJsonPath('error.code', 'MEDIA_NOT_READY');
        $this->getJson('/api/v1/public/facilities/pos-ronda')->assertNotFound();

        $id = $this->postJson('/api/v1/admin/facilities', ['slug' => 'pos-jaga', 'draf' => [
            'title' => 'Pos jaga', 'description' => 'Pos jaga warga di dekat gapura.', 'media_id' => $mediaId, 'alt' => 'Pos jaga warga',
        ]])->assertCreated()->json('data.id');
        $this->get("/api/v1/admin/content-media/$mediaId/file")->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->app['auth']->forgetGuards();
        $this->get("/api/v1/public/media/$mediaId")->assertNotFound();

        $this->as($admin)->postJson("/api/v1/admin/facilities/$id/publish", ['versi' => 1])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->get("/api/v1/public/media/$mediaId")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame("/api/v1/public/media/$mediaId", $this->getJson('/api/v1/public/facilities/pos-jaga')->json('data.photo.url'));

        $this->as($admin)->postJson("/api/v1/admin/facilities/$id/archive", ['versi' => 1])->assertOk()->assertJsonPath('data.status', 'arsip');
        $this->app['auth']->forgetGuards();
        $this->get("/api/v1/public/media/$mediaId")->assertNotFound();
        $this->getJson('/api/v1/public/facilities/pos-jaga')->assertNotFound();
        $this->as($admin)->putJson("/api/v1/admin/facilities/$id", ['versi' => 2, 'slug' => 'pos-jaga', 'draf' => [
            'title' => 'Pos jaga', 'description' => 'Pos jaga warga di dekat gapura.',
        ]])->assertStatus(409)->assertJsonPath('error.code', 'CONTENT_ARCHIVED');
    }

    public function test_slug_change_redirects_old_url_and_old_slug_cannot_be_reused(): void
    {
        $this->as($this->pengurus());
        $id = $this->create('kerja-bakti')->json('data.id');
        $this->postJson("/api/v1/admin/articles/$id/publish", ['versi' => 1])->assertOk();
        $this->putJson("/api/v1/admin/articles/$id", ['versi' => 1, 'slug' => 'kerja-bakti-bulanan', 'draf' => $this->article()])->assertOk();
        $this->getJson('/api/v1/public/articles/kerja-bakti')->assertOk(); // belum diterbitkan ulang
        $this->postJson("/api/v1/admin/articles/$id/publish", ['versi' => 2])->assertOk();

        $this->getJson('/api/v1/public/articles/kerja-bakti')->assertStatus(301)
            ->assertHeader('Location', '/api/v1/public/articles/kerja-bakti-bulanan')->assertJsonPath('data.redirect_slug', 'kerja-bakti-bulanan');
        $this->getJson('/api/v1/public/articles/kerja-bakti-bulanan')->assertOk();
        $this->create('kerja-bakti')->assertStatus(409)->assertJsonPath('error.code', 'SLUG_TAKEN');

        // Kembali ke slug lama milik sendiri boleh.
        $this->putJson("/api/v1/admin/articles/$id", ['versi' => 2, 'slug' => 'kerja-bakti', 'draf' => $this->article()])->assertOk();
        $this->postJson("/api/v1/admin/articles/$id/publish", ['versi' => 3])->assertOk();
        $this->getJson('/api/v1/public/articles/kerja-bakti')->assertOk();
        $this->getJson('/api/v1/public/articles/kerja-bakti-bulanan')->assertStatus(301);
    }

    public function test_profile_updates_publish_atomically_and_outbox_events_are_consumed(): void
    {
        Catalog::import();
        $admin = $this->pengurus();
        $this->as($admin);
        $p = $this->getJson('/api/v1/admin/site-profile')->assertOk();
        $draf = $p->json('data.draf');
        $draf['pengurus'][0]['catatan'] = 'Ketua RT 05 RW 07 Taman Kedaung, periode berjalan.';
        $this->putJson('/api/v1/admin/site-profile', ['versi' => $p->json('data.versi'), 'draf' => $draf])->assertOk()->assertJsonPath('data.status', 'terbit');
        $this->putJson('/api/v1/admin/site-profile', ['versi' => $p->json('data.versi'), 'draf' => $draf])->assertStatus(409);
        $this->getJson('/api/v1/public/site-profile')->assertJsonPath('data.pengurus.0.catatan', 'Ketua RT 05 RW 07 Taman Kedaung, periode berjalan.');

        OutboxDispatcher::dispatch();
        $this->assertSame(0, OutboxEvent::where('type', 'like', 'konten.%')->whereNull('completed_at')->count(), 'konsumen mengenal konten.*');
        $this->assertSame(0, OutboxEvent::whereNotNull('failed_at')->count());
    }

    public function test_access_boundaries(): void
    {
        $warga = $this->warga('1');
        $this->as($warga);
        $this->getJson('/api/v1/admin/articles')->assertForbidden();
        $this->postJson('/api/v1/admin/articles', ['slug' => 'x', 'draf' => []])->assertForbidden();
        $this->post('/api/v1/admin/content-media', [], ['Accept' => 'application/json'])->assertForbidden();
        $this->putJson('/api/v1/admin/site-profile', [])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/admin/articles')->assertUnauthorized();
        $this->getJson('/api/v1/public/articles')->assertOk();
        $this->getJson('/api/v1/public/site-profile')->assertNotFound();
    }
}
