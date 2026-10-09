<?php

namespace App\Http\Controllers;

use App\Support\SiteContent;
use Illuminate\Contracts\View\View;

class PublicPageController extends Controller
{
    public function page(string $page = 'home'): View
    {
        return view($page, ['foto' => SiteContent::photos(), 'articles' => SiteContent::articles()]);
    }

    public function article(string $slug): View
    {
        $article = collect(SiteContent::articles())->firstWhere('slug', $slug);
        abort_unless($article, 404);

        return view('article', ['article' => $article, 'articles' => SiteContent::articles()]);
    }

    public function management(?string $module = null): View
    {
        $modules = SiteContent::modules();
        abort_if($module !== null && ! isset($modules[$module]), 404);

        return view('pengurus', compact('modules', 'module'));
    }
}
