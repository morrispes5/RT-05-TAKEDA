<?php

namespace Tests\Feature;

use App\Support\SiteContent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * M14: dengan API konten, area pengurus memakai mode server (login cookie API + CSRF di browser,
 * tanpa draf IndexedDB dan tanpa salinan konten publik di halaman). Website sendiri tetap tidak
 * menerima tulis (POST 405) dan tidak menyimpan kredensial; preview statis tetap mode demo.
 */
class CmsServerModeTest extends TestCase
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

    public function test_server_mode_uses_api_session_and_never_embeds_drafts_or_seeds(): void
    {
        config(['services.rt05_content.api' => 'http://api.internal']);
        Http::fake(['*' => Http::response(['data' => [], 'meta' => []])]);

        foreach (['/pengurus', '/pengurus/masuk', '/pengurus/artikel', '/pengurus/dokumentasi', '/pengurus/artikel/editor', '/pengurus/dokumentasi/editor', '/pengurus/pratinjau'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('data-cms-mode="server"', false)
                ->assertSee('data-cms-account', false)
                ->assertSee('Hanya pengurus aktif yang dapat masuk dan menerbitkan konten.')
                ->assertDontSee('id="cms-seeds"', false)
                ->assertDontSee('tidak mengubah website publik')
                ->assertDontSee('href="/pengurus/warga"', false);
            $this->post($path)->assertStatus(405);
        }
    }

    public function test_without_api_the_pengurus_area_stays_the_local_demo(): void
    {
        $this->get('/pengurus')->assertOk()->assertSee('data-cms-mode="demo"', false)
            ->assertSee('id="cms-seeds"', false)->assertSee('tidak mengubah website publik')
            ->assertDontSee('data-cms-account', false);
    }
}
