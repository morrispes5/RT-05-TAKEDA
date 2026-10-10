@extends('layouts.app')
@section('title', $article['title'].' — RT 05 Takeda')
@section('description', $article['summary'])
@section('og_type', 'article')
@section('content')
<article class="reading-page">
<header class="wrap reading-header"><p class="breadcrumb"><a href="/">Beranda</a> / <a href="/artikel">Artikel</a> / {{ $article['category'] }}</p><p class="section-label">{{ $article['category'] }} · {{ $article['minutes'] }} menit baca</p><h1>{{ $article['title'] }}</h1><p>{{ $article['summary'] }}</p><span class="editorial-label">Bacaan edukasi · Bukan pengumuman resmi RT</span></header>
<div class="reading-art article-art art-{{ $article['art'] }}"><x-article-art :kind="$article['art']" /></div>
<div class="wrap reading-layout"><aside><h2>Dalam bacaan ini</h2><nav aria-label="Daftar isi artikel">@foreach($article['sections'] as $section)<a href="#bagian-{{ $loop->iteration }}">{{ $section['title'] }}</a>@endforeach</nav><a href="/artikel" class="text-link">Semua bacaan <x-ikon nama="buku" /></a></aside><div class="prose"><p class="lead">{{ $article['intro'] }}</p>@foreach($article['sections'] as $section)<section id="bagian-{{ $loop->iteration }}"><h2>{{ $section['title'] }}</h2><p>{{ $section['body'] }}</p></section>@endforeach<div class="source-note"><h2>Catatan bacaan</h2><p>{{ $article['note'] }}</p>@if(isset($article['source']))<a href="{{ $article['source']['url'] }}" rel="noopener">{{ $article['source']['label'] }} <x-ikon nama="panah" /></a>@endif</div></div></div>
</article>
<section class="wrap section related"><div class="section-heading"><h2>Bacaan lainnya</h2><a href="/artikel" class="text-link">Lihat semua <x-ikon nama="panah" /></a></div><div class="article-grid">@foreach(array_slice(array_values(array_filter($articles, fn($item) => $item['slug'] !== $article['slug'])), 0, 3) as $relatedArticle) @include('partials.article-card', ['article' => $relatedArticle]) @endforeach</div></section>
@endsection
