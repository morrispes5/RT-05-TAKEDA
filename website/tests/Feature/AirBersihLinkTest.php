<?php

namespace Tests\Feature;

use App\Support\SiteContent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ADR41: menu "Air Bersih KSM" (aplikasi terpisah dengan login sendiri) hanya untuk pengurus. Di mode server
 * menu dirender tersembunyi dan baru dibuka cms-preview.js setelah sesi terbukti peran pengurus; pratinjau
 * statis tanpa login dan halaman publik tidak memuatnya sama sekali.
 */
class AirBersihLinkTest extends TestCase
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

    private function serverMode(): void
    {
        config(['services.rt05_content.api' => 'http://api.internal']);
        Http::fake(['*' => Http::response(['data' => [], 'meta' => []])]);
    }

    public function test_server_mode_renders_hidden_menu_with_ksm_logo_in_new_tab(): void
    {
        $this->serverMode();
        foreach (['/pengurus', '/pengurus/artikel', '/pengurus/profil', '/pengurus/masuk'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('<a href="https://airbersih-ksm-claude.vercel.app" target="_blank" rel="noopener" data-air-bersih-ksm hidden>', false)
                ->assertSee('<img src="/images/logo/air-bersih-ksm.png" width="24" height="24" alt="">Air Bersih KSM', false);
        }
    }

    public function test_menu_is_revealed_only_after_pengurus_session_check(): void
    {
        $js = file_get_contents(resource_path('js/cms-preview.js'));
        $check = strpos($js, "if (me.peran !== 'pengurus') throw");
        $reveal = strpos($js, "querySelector('[data-air-bersih-ksm]')?.removeAttribute('hidden')");
        $this->assertNotFalse($check);
        $this->assertNotFalse($reveal);
        $this->assertGreaterThan($check, $reveal);
        $this->assertSame(1, substr_count($js, 'data-air-bersih-ksm'));
        $this->assertStringContainsString('.cms-nav a[hidden]{display:none}', file_get_contents(resource_path('css/content-preview.css')));
    }

    public function test_url_follows_config(): void
    {
        $this->serverMode();
        config(['services.air_bersih_ksm.url' => 'https://air.example.test']);
        $this->get('/pengurus')->assertOk()->assertSee('href="https://air.example.test"', false);
    }

    public function test_demo_preview_without_login_does_not_contain_the_menu(): void
    {
        foreach (['/pengurus', '/pengurus/artikel', '/pengurus/masuk'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('data-air-bersih-ksm', false)->assertDontSee('Air Bersih KSM');
        }
    }

    public function test_public_pages_do_not_show_the_menu(): void
    {
        foreach (['/', '/profil', '/artikel'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('data-air-bersih-ksm', false);
        }
    }

    public function test_logo_is_the_ksm_banyu_bening_mark(): void
    {
        $this->assertFileExists(public_path('images/logo/air-bersih-ksm.png'));
        $this->assertSame("\x89PNG", substr(file_get_contents(public_path('images/logo/air-bersih-ksm.png')), 0, 4));
    }
}
