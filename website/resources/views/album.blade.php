@extends('layouts.app')
@section('title', $album['title'].' — Dokumentasi RT 05 Takeda')
@section('description', $album['description'])
@section('content')
<header class="wrap page-intro album-intro"><p class="breadcrumb"><a href="/">Beranda</a> / <a href="/dokumentasi">Dokumentasi</a> / Kegiatan</p><h1>{{ $album['title'] }}</h1><p>{{ $album['description'] }}</p><span class="album-kind">{{ count($album['photos']) }} foto @if($album['date']) — {{ $album['date'] }} @endif</span></header>
<section class="wrap album-photos" aria-label="Foto {{ $album['title'] }}">
@foreach($album['photos'] as $photo)
<figure><button type="button" class="photo-trigger" data-lightbox="kegiatan" data-src="{{ $photo['src'] }}" data-alt="{{ $photo['alt'] }}" data-caption="{{ $photo['caption'] }}" aria-label="Perbesar foto {{ $photo['caption'] }}"><img src="{{ $photo['src'] }}" srcset="{{ $photo['srcset'] }}" sizes="(max-width: 768px) 92vw, 1100px" width="{{ $photo['width'] }}" height="{{ $photo['height'] }}" alt="{{ $photo['alt'] }}" @if($loop->first) fetchpriority="high" @else loading="lazy" @endif><span><x-ikon nama="foto" /> Perbesar foto</span></button><figcaption>{{ $photo['caption'] }}</figcaption></figure>
@endforeach
</section>
<div class="wrap album-end"><p>Dokumentasi warga RT 05 Takeda.</p><a href="/dokumentasi" class="text-link">Kembali ke dokumentasi <x-ikon nama="panah" /></a></div>
@include('partials.lightbox')
@endsection
