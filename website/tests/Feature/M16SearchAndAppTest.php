<?php

namespace Tests\Feature;

use App\Support\SiteContent;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** M16: hanya production yang diindeks Google; halaman unduh aplikasi resmi dan Kebijakan Privasi publik. */
class M16SearchAndAppTest extends TestCase
{
    private string $meta;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://rt05.example', 'services.rt05_site.apk_meta' => $this->meta = storage_path('framework/testing/versi.json')]);
        File::ensureDirectoryExists(dirname($this->meta));
        File::delete($this->meta);
    }

    protected function tearDown(): void
    {
        File::delete($this->meta);
        parent::tearDown();
    }

    public function test_default_is_noindex_everywhere_and_no_sitemap(): void
    {
        config(['services.rt05_site.indexing' => false]);
        foreach (['/', '/profil', '/aplikasi', '/kebijakan-privasi', '/pengurus'] as $path) {
            $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
                ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        }
        $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSeeText("User-agent: *\nDisallow: /\n", false);
        $this->get('/sitemap.xml')->assertNotFound();
    }

    public function test_production_indexes_public_pages_but_never_the_management_area(): void
    {
        config(['services.rt05_site.indexing' => true]);
        $home = $this->get('/')->assertOk()->assertHeaderMissing('X-Robots-Tag')
            ->assertDontSee('name="robots"', false)
            ->assertSee('<link rel="canonical" href="https://rt05.example/">', false)
            ->assertSee('<meta property="og:site_name" content="RT05 TAKEDA">', false);
        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $home->getContent(), $m);
        $graph = json_decode($m[1] ?? '', true, flags: JSON_THROW_ON_ERROR)['@graph'];
        $this->assertSame('RT05 TAKEDA', $graph[0]['name']);
        $this->assertContains('RT 05 Taman Kedaung', $graph[0]['alternateName']);
        $this->assertSame('https://rt05.example/', $graph[1]['url']);

        $this->get('/profil')->assertSee('<link rel="canonical" href="https://rt05.example/profil">', false);
        // Host palsu tidak mengubah URL kanonik maupun og:url/og:image (aset lain mengikuti konfigurasi web server).
        $this->get('http://evil.example/profil')->assertSee('<link rel="canonical" href="https://rt05.example/profil">', false)
            ->assertSee('<meta property="og:url" content="https://rt05.example/profil">', false)
            ->assertSee('<meta property="og:image" content="https://rt05.example/images/og-image.jpg">', false);
        foreach (['/pengurus', '/pengurus/masuk'] as $path) {
            $this->get($path)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }

        $this->get('/robots.txt')->assertOk()->assertSeeText('Disallow: /pengurus', false)
            ->assertSeeText('Sitemap: https://rt05.example/sitemap.xml', false);
        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $urls = simplexml_load_string($xml);
        $locs = [];
        foreach ($urls->url as $url) {
            $locs[] = (string) $url->loc;
        }
        $this->assertContains('https://rt05.example/', $locs);
        $this->assertContains('https://rt05.example/aplikasi', $locs);
        $this->assertContains('https://rt05.example/kebijakan-privasi', $locs);
        $this->assertContains('https://rt05.example/artikel/'.SiteContent::articles()[0]['slug'], $locs);
        $this->assertEmpty(array_filter($locs, fn ($l) => str_contains($l, '/pengurus')));
    }

    public function test_article_has_article_structured_data(): void
    {
        $article = SiteContent::articles()[0];
        $html = $this->get('/artikel/'.$article['slug'])->assertOk()->getContent();
        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $m);
        $data = json_decode($m[1] ?? '', true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Article', $data['@type']);
        $this->assertSame($article['title'], $data['headline']);
        $this->assertSame('https://rt05.example/artikel/'.$article['slug'], $data['mainEntityOfPage']);
    }

    public function test_download_page_without_release_says_not_yet_available(): void
    {
        $this->get('/aplikasi')->assertOk()->assertSee('Aplikasi sedang disiapkan')
            ->assertDontSee('.apk"', false)->assertSee('Izinkan dari sumber ini');
    }

    public function test_download_page_lists_release_files_with_checksum_and_rejects_unsafe_links(): void
    {
        $hash = str_repeat('ab', 32);
        File::put($this->meta, json_encode([
            'versi' => '1.0.0', 'build' => 1012, 'tanggal' => '2026-10-23T03:00:00Z', 'catatan' => ['Halaman unduh resmi', 5],
            'berkas' => [
                ['abi' => 'armeabi-v7a', 'url' => '/unduh/rt05-takeda-prod-1012-armeabi-v7a.apk', 'ukuran' => 20971520, 'sha256' => $hash],
                ['abi' => 'arm64-v8a', 'url' => '/unduh/rt05-takeda-prod-1012-arm64-v8a.apk', 'ukuran' => 23068672, 'sha256' => $hash],
                ['abi' => 'arm64-v8a', 'url' => 'javascript:alert(1)//.apk', 'ukuran' => 1, 'sha256' => $hash],
                ['abi' => 'x86_64', 'url' => '/unduh/x.apk', 'ukuran' => 1, 'sha256' => $hash],
                ['abi' => 'arm64-v8a', 'url' => '/unduh/bad.apk', 'ukuran' => 1, 'sha256' => 'bukan-hash'],
            ],
        ]));
        $html = $this->get('/aplikasi')->assertOk()
            ->assertSee('Versi 1.0.0 (build 1012)')
            ->assertSee('23 Oktober 2026, 10.00 WIB')
            ->assertSee('href="/unduh/rt05-takeda-prod-1012-arm64-v8a.apk"', false)
            ->assertSee('href="/unduh/rt05-takeda-prod-1012-armeabi-v7a.apk"', false)
            ->assertSee('22,0 MB')->assertSee($hash)->assertSee('Halaman unduh resmi')
            ->assertDontSee('javascript:', false)->assertDontSee('x.apk')->assertDontSee('bad.apk')
            ->getContent();
        // 64-bit (HP kebanyakan) tampil lebih dulu.
        $this->assertLessThan(strpos($html, 'armeabi-v7a.apk'), strpos($html, 'arm64-v8a.apk'));
    }

    public function test_broken_metadata_falls_back_to_not_yet_available(): void
    {
        File::put($this->meta, '{rusak');
        $this->get('/aplikasi')->assertOk()->assertSee('Aplikasi sedang disiapkan');
    }

    public function test_public_privacy_policy_matches_the_app_and_server_version(): void
    {
        $privacy = SiteContent::privacy();
        $server = file_get_contents(base_path('../rest-api/app/Support/Privacy.php'));
        $this->assertStringContainsString("VERSION = '{$privacy['versi']}'", $server);

        // Butir kebijakan identik dengan teks yang disetujui warga di aplikasi (mobile/lib/features/auth/privacy_policy.dart).
        $dart = file_get_contents(base_path('../mobile/lib/features/auth/privacy_policy.dart'));
        $block = substr($dart, strpos($dart, 'const privacySections'));
        $block = substr($block, 0, strpos($block, "\n];"));
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $block, $m);
        $expected = array_map(fn ($s) => str_replace("\\'", "'", $s), $m[1]);
        $actual = [];
        foreach ($privacy['bagian'] as $section) {
            $actual[] = $section['judul'];
            array_push($actual, ...$section['butir']);
        }
        $this->assertSame($expected, $actual);

        $response = $this->get('/kebijakan-privasi')->assertOk()->assertSee($privacy['label']);
        foreach ($actual as $text) {
            $response->assertSee($text);
        }
    }
}
