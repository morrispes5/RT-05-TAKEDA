<?php

namespace App\Http\Controllers;

use App\Support\SiteContent;
use Illuminate\Contracts\View\View;

class PublicPageController extends Controller
{
    public function page(string $page = 'home'): View
    {
        return view($page, ['foto' => SiteContent::photos(), 'articles' => SiteContent::articles(), 'albums' => SiteContent::albums()]);
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

    public function management(string $screen = 'ringkasan'): View
    {
        return view('pengurus', ['screen' => $screen, 'articles' => SiteContent::articles(), 'albums' => SiteContent::albums()]);
    }
}
