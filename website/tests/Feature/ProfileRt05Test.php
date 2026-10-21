<?php

namespace Tests\Feature;

use App\Support\SiteContent;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Permintaan Pak RT (ADR35): halaman Profil RT05 menampilkan cerita Taman Kedaung dan pengurus yang dapat diedit
 * pengurus lewat API konten. Tanpa API atau profil belum terbit → cadangan resources/data/profil.json.
 */
class ProfileRt05Test extends TestCase
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

    public function test_nav_and_page_use_profil_rt05_with_baked_story(): void
    {
        $page = $this->get('/profil')->assertOk()
            ->assertSee('Profil RT05 — RT 05 Takeda', false)
            ->assertSee('Kecamatan Pamulang')
            ->assertSee('Agus Ferdiansyah')
            ->assertSee('Nama belum dipublikasikan');
        $this->assertSame(3, substr_count($page->getContent(), 'class="person"'), 'tiga pengurus dari cadangan');
        $this->get('/')->assertOk()->assertSee('Profil RT05')->assertDontSee('>Profil RT<', false);
    }

    public function test_published_profile_from_api_replaces_story_and_committee(): void
    {
        config(['services.rt05_content.api' => 'http://api.internal']);
        Http::fake([
            'api.internal/api/v1/public/site-profile*' => Http::response(['data' => [
                'slug' => 'profil', 'nama' => 'RT05 TAKEDA', 'singkatan' => 'Taman Kedaung',
                'wilayah' => 'RT 05 RW 07 Taman Kedaung, Pamulang.', 'ringkasan' => 'Ringkasan baru dari pengurus.',
                'tentang' => "Paragraf pertama dari pengurus.\n\nParagraf kedua <b>tanpa HTML</b>.",
                'pengurus' => [
                    ['jabatan' => 'Ketua RT', 'nama' => 'Nama Ketua Uji', 'catatan' => null],
                    ['jabatan' => 'Bendahara RT', 'nama' => 'Nama Bendahara Uji', 'catatan' => 'Mengelola iuran.'],
                    ['jabatan' => 'Pengurus aplikasi', 'nama' => 'Nama Admin Uji', 'catatan' => null],
                    ['jabatan' => 'Humas', 'nama' => 'Nama Humas Uji', 'catatan' => null],
                ],
            ]]),
            '*' => Http::response(['data' => [], 'meta' => []]),
        ]);

        $page = $this->get('/profil')->assertOk()
            ->assertSee('Ringkasan baru dari pengurus.')
            ->assertSee('Paragraf pertama dari pengurus.')
            ->assertSee('&lt;b&gt;tanpa HTML&lt;/b&gt;', false)
            ->assertSee('Nama Bendahara Uji')
            ->assertSee('Mengelola iuran.')
            ->assertDontSee('Kecamatan Pamulang')
            ->assertDontSee('Agus Ferdiansyah');
        $this->assertSame(4, substr_count($page->getContent(), 'class="person"'), 'pengurus bisa ditambah');
    }

    public function test_unpublished_or_broken_profile_falls_back_without_breaking_articles(): void
    {
        config(['services.rt05_content.api' => 'http://api.internal']);
        Http::fake([
            'api.internal/api/v1/public/site-profile*' => Http::response(['error' => ['code' => 'NOT_FOUND']], 404),
            '*' => Http::response(['data' => [], 'meta' => []]),
        ]);
        $this->get('/profil')->assertOk()->assertSee('Agus Ferdiansyah')->assertSee('Kecamatan Pamulang');
        $this->assertSame('api', SiteContent::source(), 'artikel/album tetap dari API walau profil 404');

        SiteContent::forget();
        Cache::store('file')->flush();
        Http::swap(new Factory);
        Http::fake(['*' => Http::response('down', 503)]);
        $this->get('/profil')->assertOk()->assertSee('Agus Ferdiansyah');
    }

    public function test_cms_has_profil_rt05_screen(): void
    {
        $this->get('/pengurus/profil')->assertOk()->assertSee('data-cms="profil"', false)->assertSee('Profil RT05');
        $this->assertContains('/pengurus/profil', SiteContent::paths());
    }

    public function test_paragraphs_split_on_blank_lines_only(): void
    {
        $this->assertSame(['Satu baris', 'Dua'], SiteContent::paragraphs("Satu baris\n\n\nDua\n"));
        $this->assertSame(["Baris a\nbaris b"], SiteContent::paragraphs("Baris a\nbaris b"));
        $this->assertSame([], SiteContent::paragraphs(null));
    }
}
