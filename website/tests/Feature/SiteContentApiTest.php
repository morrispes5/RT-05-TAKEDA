<?php

namespace Tests\Feature;

use App\Support\SiteContent;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * M14: website membaca artikel/album terbit dari API konten; API mati → last-good → JSON baked.
 * Tanpa RT05_CONTENT_API (preview statis Vercel) perilaku lama tidak berubah.
 */
class SiteContentApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::store('file')->flush();
        SiteContent::forget();
    }

    protected function tearDown(): void
    {
        Cache::store('file')->flush();
        SiteContent::forget();
        parent::tearDown();
    }

    private function api(): void
    {
        config(['services.rt05_content.api' => 'http://api.internal']);
        Http::fake([
            'api.internal/api/v1/public/articles*' => Http::response(['data' => [[
                'slug' => 'kerja-bakti-oktober', 'title' => 'Kerja bakti Oktober bersama warga', 'category' => 'Lingkungan', 'art' => 'park',
                'summary' => 'Ringkasan kerja bakti.', 'intro' => 'Warga membersihkan saluran.', 'sections' => [['title' => 'Waktu', 'body' => 'Minggu pagi.']],
                'note' => null, 'source' => null, 'published_at' => '2026-10-18T00:00:00Z',
            ]], 'meta' => []]),
            'api.internal/api/v1/public/albums*' => Http::response(['data' => [[
                'slug' => 'kerja-bakti', 'title' => 'Album kerja bakti', 'description' => 'Dokumentasi kerja bakti.', 'date' => null,
                'cover' => ['id' => 'm-1', 'url' => '/api/v1/public/media/m-1', 'srcset' => null, 'lebar' => 1600, 'tinggi' => 1200],
                'photos' => [['id' => 'm-1', 'url' => '/api/v1/public/media/m-1', 'srcset' => null, 'lebar' => 1600, 'tinggi' => 1200, 'alt' => 'Warga membersihkan saluran', 'caption' => null]],
            ]], 'meta' => []]),
        ]);
    }

    public function test_without_api_config_the_baked_catalog_is_used(): void
    {
        Http::fake();
        $this->assertSame('baked', SiteContent::source());
        $this->get('/artikel')->assertOk()->assertSee('Tangan bersih', false);
        Http::assertNothingSent();
    }

    public function test_published_api_content_is_rendered_and_cached_as_last_good(): void
    {
        $this->api();
        $this->get('/artikel')->assertOk()->assertSee('Kerja bakti Oktober bersama warga');
        $this->assertSame('api', SiteContent::source());
        SiteContent::forget();
        $this->get('/artikel/kerja-bakti-oktober')->assertOk()->assertSee('Minggu pagi.');
        $this->get('/dokumentasi/kerja-bakti')->assertOk()->assertSee('/api/v1/public/media/m-1', false);
        $this->get('/artikel/cara-mencuci-tangan')->assertNotFound();
        Http::assertSentCount(3); // artikel, album, profil sekali ambil; sisanya dari cache fresh
    }

    public function test_api_outage_falls_back_to_last_good_then_to_baked(): void
    {
        $this->api();
        $this->get('/artikel')->assertOk();
        Cache::store('file')->forget('rt05:site-content:fresh');
        SiteContent::forget();
        Http::swap(new Factory);
        Http::fake(['*' => Http::response('down', 503)]);
        $this->get('/artikel')->assertOk()->assertSee('Kerja bakti Oktober bersama warga');
        $this->assertSame('cache', SiteContent::source());
        // Selama penanda gagal masih berlaku, request berikutnya tidak menunggu API lagi.
        SiteContent::forget();
        $this->get('/artikel/kerja-bakti-oktober')->assertOk();
        Http::assertSentCount(1);

        Cache::store('file')->flush();
        SiteContent::forget();
        $this->get('/artikel')->assertOk()->assertSee('Tangan bersih', false);
        $this->assertSame('baked', SiteContent::source());
    }

    /** M16: cache fresh habis → konten terakhir disajikan tanpa menunggu API; pembaruan berjalan setelah response. */
    public function test_expired_cache_serves_last_good_and_refreshes_after_the_response(): void
    {
        $this->api();
        $this->get('/artikel')->assertOk()->assertSee('Kerja bakti Oktober bersama warga');
        Cache::store('file')->forget('rt05:site-content:fresh');
        SiteContent::forget();
        Http::swap(new Factory);
        Http::fake([
            'api.internal/api/v1/public/articles*' => Http::response(['data' => [[
                'slug' => 'ronda-malam', 'title' => 'Jadwal ronda malam baru', 'category' => 'Keamanan', 'art' => 'home',
                'summary' => 'Ringkasan ronda.', 'intro' => 'Ronda bergiliran.', 'sections' => [], 'note' => null, 'source' => null,
            ]], 'meta' => []]),
            'api.internal/api/v1/public/albums*' => Http::response(['data' => [], 'meta' => []]),
            'api.internal/api/v1/public/site-profile*' => Http::response(['data' => null], 404),
        ]);

        // Pengunjung pertama setelah kedaluwarsa: konten terakhir, sumber cache.
        $this->get('/artikel')->assertOk()->assertSee('Kerja bakti Oktober bersama warga')->assertDontSee('Jadwal ronda malam baru');
        $this->assertSame('cache', SiteContent::source());
        // Pembaruan sudah berjalan setelah response (terminating): pengunjung berikutnya melihat konten baru.
        SiteContent::forget();
        $this->get('/artikel')->assertOk()->assertSee('Jadwal ronda malam baru');
        $this->assertSame('api', SiteContent::source());
        Http::assertSentCount(3);
    }

    /** UAT M15: foto sampul asli yang diunggah lewat CMS tampil menggantikan SVG; artikel tanpa sampul tetap SVG. */
    public function test_article_cover_photo_replaces_svg_illustration(): void
    {
        config(['services.rt05_content.api' => 'http://api.internal']);
        $article = fn (string $slug, ?array $cover) => [
            'slug' => $slug, 'title' => 'Judul '.$slug, 'category' => 'Lingkungan', 'art' => 'hands', 'summary' => 'Ringkasan '.$slug.'.',
            'intro' => 'Pembuka artikel.', 'sections' => [['title' => 'Bagian', 'body' => 'Isi bagian.']], 'note' => null, 'source' => null,
            'cover' => $cover, 'cover_alt' => $cover ? 'Warga bergotong royong di taman' : null, 'published_at' => '2026-10-19T15:43:00Z',
        ];
        Http::fake([
            'api.internal/api/v1/public/articles*' => Http::response(['data' => [
                $article('dengan-sampul', ['id' => 'm-9', 'url' => '/api/v1/public/media/m-9', 'srcset' => null, 'lebar' => 1600, 'tinggi' => 1000]),
                $article('tanpa-sampul', null),
            ], 'meta' => []]),
            'api.internal/api/v1/public/albums*' => Http::response(['data' => [], 'meta' => []]),
        ]);

        $page = $this->get('/artikel/dengan-sampul')->assertOk()->getContent();
        $this->assertStringContainsString('<div class="reading-art article-art art-hands"><img class="article-cover" src="/api/v1/public/media/m-9" alt="Warga bergotong royong di taman" width="1600" height="1000" loading="eager" decoding="async"></div>', $page);
        $this->assertStringNotContainsString('<div class="reading-art article-art art-hands"><svg', $page);

        $list = $this->get('/artikel')->assertOk()->getContent();
        $this->assertStringContainsString('<a href="/artikel/dengan-sampul" class="article-art art-hands" aria-label="Baca Judul dengan-sampul"><img class="article-cover" src="/api/v1/public/media/m-9"', $list);
        $this->assertStringContainsString('aria-label="Baca Judul tanpa-sampul"><svg', $list);

        $this->assertStringContainsString('<div class="reading-art article-art art-hands"><svg', $this->get('/artikel/tanpa-sampul')->assertOk()->getContent());
    }
}
