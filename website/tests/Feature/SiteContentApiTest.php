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
        Http::assertSentCount(2); // satu kali ambil, sisanya dari cache fresh
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

        Cache::store('file')->flush();
        SiteContent::forget();
        $this->get('/artikel')->assertOk()->assertSee('Tangan bersih', false);
        $this->assertSame('baked', SiteContent::source());
    }
}
