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
        foreach (['/admin', '/artikel/tidak-ada', '/dokumentasi/tidak-ada', '/pengurus/tidak-ada', '/pengurus/iuran', '/pengurus/warga', '/tidak-ada'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_pengurus_prototype_only_has_content_tools_and_no_server_writes(): void
    {
        foreach (['/pengurus', '/pengurus/artikel', '/pengurus/dokumentasi', '/pengurus/artikel/editor', '/pengurus/dokumentasi/editor'] as $path) {
            $this->get($path)->assertOk()->assertSee('Pratinjau')
                ->assertSee('tidak mengubah website publik')
                ->assertDontSee('href="/pengurus/warga"', false)
                ->assertDontSee('href="/pengurus/iuran"', false);
            $this->post($path)->assertStatus(405);
        }
        $this->get('/pengurus/masuk')->assertDontSee('type="password"', false);
    }

    public function test_event_album_uses_real_assets_without_an_unconfirmed_year(): void
    {
        $this->get('/dokumentasi/perayaan-17-agustus')->assertOk()->assertSee('Perayaan 17 Agustus')->assertDontSee('17 Agustus 2026');
        foreach (SiteContent::albums() as $album) {
            foreach ($album['photos'] as $photo) {
                $this->assertFileExists(public_path(ltrim($photo['src'], '/')));
                $this->assertNotEmpty($photo['alt']);
            }
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
