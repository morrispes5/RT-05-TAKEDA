<?php

namespace App\Http\Controllers;

use App\Support\SiteContent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

class PublicPageController extends Controller
{
    public function page(string $page = 'home'): View
    {
        return view($page, ['foto' => SiteContent::photos(), 'articles' => SiteContent::articles(), 'albums' => SiteContent::albums(), 'profil' => SiteContent::profile()]);
    }

    public function article(string $slug): View
    {
        $article = collect(SiteContent::articles())->firstWhere('slug', $slug);
        abort_unless($article, 404);

        return view('article', ['article' => $article, 'articles' => SiteContent::articles()]);
    }

    public function album(string $slug): View
    {
        $album = collect(SiteContent::albums())->firstWhere('slug', $slug);
        abort_unless($album, 404);

        return view('album', compact('album'));
    }

    public function app(): View
    {
        return view('aplikasi', ['apk' => SiteContent::apk()]);
    }

    public function privacy(): View
    {
        return view('kebijakan-privasi', ['privasi' => SiteContent::privacy()]);
    }

    /** M16: staging/preview menolak semua perayap; production membuka halaman publik dan menutup area pengurus. */
    public function robots(): Response
    {
        $lines = SiteContent::indexing()
            ? ['User-agent: *', 'Disallow: /pengurus', 'Disallow: /api/', '', 'Sitemap: '.self::origin().'/sitemap.xml']
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        abort_unless(SiteContent::indexing(), 404);

        return response()->view('sitemap', ['origin' => self::origin(), 'paths' => SiteContent::publicPaths()], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** Origin kanonik dari APP_URL, bukan header Host permintaan. */
    public static function origin(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public function management(string $screen = 'ringkasan'): View
    {
        return view('pengurus', ['screen' => $screen, 'articles' => SiteContent::articles(), 'albums' => SiteContent::albums()]);
    }
}
