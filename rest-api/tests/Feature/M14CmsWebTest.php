<?php

namespace Tests\Feature;

use App\Domain\Content\Media;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Browser;
use Tests\Support\Fixtures;
use Tests\TestCase;

/**
 * M14 gate dari sisi API: CMS web memakai sesi cookie + CSRF (bukan bearer) untuk draf, unggah
 * foto, terbit, dan arsip konten. Mutation tanpa CSRF/origin CMS ditolak; akun non-pengurus tidak
 * mendapat sesi; draf tidak pernah terlihat di endpoint publik yang dibaca website.
 */
class M14CmsWebTest extends TestCase
{
    use Browser, DatabaseTransactions, Fixtures;

    protected array $connectionsToTransact = ['pgsql'];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->setUpBrowser();
    }

    private function article(string $title = 'Jadwal kerja bakti bulanan'): array
    {
        return [
            'title' => $title, 'category' => 'Lingkungan', 'art' => 'park',
            'summary' => 'Kerja bakti rutin setiap bulan untuk lingkungan bersih.',
            'intro' => 'Warga diajak membersihkan saluran dan area bersama.',
            'sections' => [['title' => 'Waktu', 'body' => 'Minggu pertama setiap bulan pukul 07.00 WIB.']],
            'note' => 'Bacaan edukasi. Bukan pengumuman resmi RT.', 'source' => null,
        ];
    }

    private function publicGet(string $uri)
    {
        $this->app['auth']->forgetGuards();

        return $this->getJson($uri);
    }

    public function test_cms_cookie_session_drafts_uploads_publishes_and_archives(): void
    {
        $this->pengurus('cms@contoh.test');
        $this->cmsLogin('cms@contoh.test')->assertOk();

        // Tanpa CSRF: ditolak, tidak ada konten dibuat.
        $this->browser('POST', '/api/v1/admin/articles', ['slug' => 'kerja-bakti', 'draf' => $this->article()], csrf: false)->assertStatus(419);
        $this->browser('GET', '/api/v1/admin/articles')->assertOk()->assertJsonCount(0, 'data');

        $id = $this->browser('POST', '/api/v1/admin/articles', ['slug' => 'kerja-bakti', 'draf' => $this->article()])
            ->assertCreated()->assertJsonPath('data.status', 'draf')->assertJsonPath('data.versi', 1)->json('data.id');
        $this->publicGet('/api/v1/public/articles/kerja-bakti')->assertNotFound();
        $this->assertSame([], $this->publicGet('/api/v1/public/articles')->json('data'));

        $this->browser('POST', "/api/v1/admin/articles/$id/publish", ['versi' => 1])->assertOk()->assertJsonPath('data.status', 'terbit');
        $this->publicGet('/api/v1/public/articles/kerja-bakti')->assertOk()->assertJsonPath('data.title', 'Jadwal kerja bakti bulanan');

        // Draf baru setelah terbit tidak mengubah snapshot publik sampai diterbitkan lagi.
        $this->browser('PUT', "/api/v1/admin/articles/$id", ['versi' => 1, 'slug' => 'kerja-bakti', 'draf' => $this->article('Jadwal kerja bakti diperbarui')])
            ->assertOk()->assertJsonPath('data.ada_perubahan_belum_terbit', true);
        $this->publicGet('/api/v1/public/articles/kerja-bakti')->assertJsonPath('data.title', 'Jadwal kerja bakti bulanan');
        // Versi basi (tab lain) → 409, bukan menimpa.
        $this->browser('PUT', "/api/v1/admin/articles/$id", ['versi' => 1, 'slug' => 'kerja-bakti', 'draf' => $this->article()])->assertStatus(409);

        // Unggah foto lewat sesi cookie (multipart + CSRF), pratinjau privat, album terbit.
        $this->browser('POST', '/api/v1/admin/content-media', ['foto' => UploadedFile::fake()->image('kegiatan.jpg', 1200, 800)], multipart: true, csrf: false)->assertStatus(419);
        $media = $this->browser('POST', '/api/v1/admin/content-media', ['foto' => UploadedFile::fake()->image('kegiatan.jpg', 1200, 800)], multipart: true)
            ->assertCreated()->json('data');
        $this->browser('GET', $media['pratinjau_url'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->publicGet("/api/v1/public/media/{$media['id']}")->assertNotFound();

        $album = $this->browser('POST', '/api/v1/admin/albums', ['slug' => 'kerja-bakti-oktober', 'draf' => [
            'title' => 'Kerja bakti Oktober', 'description' => 'Dokumentasi kerja bakti warga.', 'date' => '2026-10-18',
            'photos' => [['media_id' => $media['id'], 'alt' => 'Warga membersihkan saluran', 'caption' => null]], 'cover_media_id' => $media['id'],
        ]])->assertCreated()->json('data.id');
        $this->browser('POST', "/api/v1/admin/albums/$album/publish", ['versi' => 1])->assertOk();
        $this->publicGet('/api/v1/public/albums/kerja-bakti-oktober')->assertOk()->assertJsonPath('data.cover.url', "/api/v1/public/media/{$media['id']}");
        $this->publicGet("/api/v1/public/media/{$media['id']}")->assertOk();

        $this->browser('POST', "/api/v1/admin/albums/$album/archive", ['versi' => 1])->assertOk()->assertJsonPath('data.status', 'arsip');
        $this->publicGet('/api/v1/public/albums/kerja-bakti-oktober')->assertNotFound();
    }

    public function test_static_website_photo_preview_redirects_to_the_accepted_image_path(): void
    {
        $this->pengurus('cms@contoh.test');
        $this->cmsLogin('cms@contoh.test')->assertOk();
        $m = Media::staticPhoto('/images/dokumentasi/contoh-1.webp', 1600, 1200);

        $this->browser('GET', "/api/v1/admin/content-media/{$m->id}/file")->assertRedirect('/images/dokumentasi/contoh-1.webp');
    }

    public function test_non_admin_foreign_origin_and_logged_out_browsers_are_denied(): void
    {
        $this->warga('41');
        $this->cmsLogin('uji41@contoh.test')->assertStatus(422)->assertJsonPath('error.fields.email.0', 'Email atau kata sandi salah.');
        $this->browser('GET', '/api/v1/admin/articles')->assertUnauthorized();
        $this->browser('POST', '/api/v1/admin/articles', ['slug' => 'x', 'draf' => $this->article()])->assertUnauthorized();

        $this->pengurus('cms@contoh.test');
        $this->cmsLogin('cms@contoh.test', jar: 'b')->assertOk();
        // Cookie dari origin lain tidak dipakai sama sekali.
        $this->browser('POST', '/api/v1/admin/articles', ['slug' => 'kerja-bakti', 'draf' => $this->article()], jar: 'b', origin: 'https://jahat.test')->assertUnauthorized();
        $this->browser('POST', '/api/v1/auth/logout', jar: 'b')->assertNoContent();
        $this->browser('GET', '/api/v1/admin/articles', jar: 'b')->assertUnauthorized();
    }
}
