<?php

namespace Tests\Feature;

use App\Support\SiteContent;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    public function test_all_public_and_demo_routes_render_without_database(): void
    {
        foreach (SiteContent::paths() as $path) {
            $response = $this->get($path);
            $response->assertOk()->assertSee('lang="id"', false);
            $this->assertSame(1, substr_count($response->getContent(), '<h1'), $path);
        }
    }

    public function test_unknown_routes_and_unknown_content_are_not_silently_served(): void
    {
        foreach (['/admin', '/artikel/tidak-ada', '/pengurus/tidak-ada', '/tidak-ada'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_pengurus_is_read_only_and_truthful(): void
    {
        foreach (array_keys(SiteContent::modules()) as $module) {
            $this->get('/pengurus/'.$module)->assertOk()
                ->assertSee('Pratinjau antarmuka — fitur belum aktif')
                ->assertSee('disabled', false)
                ->assertDontSee('<form', false);
            $this->post('/pengurus/'.$module)->assertStatus(405);
        }
    }

    public function test_profile_has_confirmed_chairman_and_anonymous_officers(): void
    {
        $this->get('/profil')->assertSee('Agus Ferdiansyah')
            ->assertSee('Nama belum dipublikasikan')
            ->assertDontSee('menunggu konfirmasi pengurus');
    }

    public function test_photo_variants_exist_and_do_not_request_nonexistent_1600_portrait(): void
    {
        foreach (SiteContent::photos() as $slug => $photo) {
            foreach ($photo['ukuran'] as $width) {
                $this->assertFileExists(public_path("images/dokumentasi/$slug-$width.webp"));
            }
        }
        $this->get('/dokumentasi')->assertDontSee('peraturan-lapangan-1600.webp');
    }

    public function test_articles_use_calculated_reading_time_and_health_source(): void
    {
        foreach (SiteContent::articles() as $article) {
            $this->get('/artikel/'.$article['slug'])->assertOk()
                ->assertSee($article['minutes'].' menit baca')
                ->assertSee('Bukan pengumuman resmi RT');
        }
        $this->get('/artikel/cara-mencuci-tangan')->assertSee('20 detik')
            ->assertSee('https://www.cdc.gov/clean-hands/about/index.html');
    }
}
