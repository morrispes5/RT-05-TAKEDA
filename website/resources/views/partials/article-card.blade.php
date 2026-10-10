<article class="article-card" data-filter-item="articles" data-category="{{ $article['category'] }}">
<a href="/artikel/{{ $article['slug'] }}" class="article-art art-{{ $article['art'] }}" aria-label="Baca {{ $article['title'] }}"><x-article-art :kind="$article['art']" /></a>
<div class="article-meta"><span>{{ $article['category'] }}</span><span>{{ $article['minutes'] }} menit baca</span></div><h3><a href="/artikel/{{ $article['slug'] }}">{{ $article['title'] }}</a></h3><p>{{ $article['summary'] }}</p><a class="text-link" href="/artikel/{{ $article['slug'] }}">Baca artikel <x-ikon nama="panah" /><span class="sr-only">: {{ $article['title'] }}</span></a>
</article>
