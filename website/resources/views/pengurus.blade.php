@extends('layouts.app')
@section('title', 'Area Pengurus — RT 05 Takeda')
@section('body_class', 'cms-body')
@section('management', 'true')
@section('content')
@php($titles = ['ringkasan' => 'Cerita Takeda, dalam satu tempat.', 'masuk' => 'Ruang untuk pengurus.', 'artikel' => 'Bacaan untuk warga.', 'artikel/editor' => 'Tulis sebuah bacaan.', 'dokumentasi' => 'Momen yang kita simpan.', 'dokumentasi/editor' => 'Susun cerita dalam foto.', 'pratinjau' => 'Pratinjau konten.'])
<div class="cms-layout">
<aside class="cms-sidebar">
<div class="cms-brand-row"><a class="brand" href="/pengurus"><img src="/images/logo/rt05-mark.svg" width="44" height="44" alt=""><span>RT 05 Takeda<small>Area Pengurus</small></span></a><button class="menu-toggle" type="button" data-menu-toggle aria-expanded="false" aria-controls="pengurus-navigation">Menu <x-ikon nama="menu" /></button></div>
<nav id="pengurus-navigation" class="cms-nav" aria-label="Navigasi Pengurus" data-menu>
@foreach(['ringkasan' => ['Ringkasan', 'rumah', '/pengurus'], 'artikel' => ['Artikel', 'buku', '/pengurus/artikel'], 'dokumentasi' => ['Dokumentasi', 'foto', '/pengurus/dokumentasi']] as $key => [$label, $icon, $href])
<a href="{{ $href }}" @if($screen === $key || str_starts_with($screen, $key.'/')) aria-current="page" @endif><x-ikon :nama="$icon" />{{ $label }}</a>
@endforeach
<a class="cms-return" href="/"><x-ikon nama="panah" />Lihat website</a>
</nav>
<div class="cms-sidebar-note"><span class="preview-label">Pratinjau</span><p>Coba menulis dan menyusun album. Draf hanya disimpan di browser ini.</p><a href="/pengurus/masuk">Tentang akses pengurus</a></div>
</aside>
<div class="cms-workspace">
<header class="cms-topbar"><span>Artikel & dokumentasi</span><a href="/">Kembali ke website <x-ikon nama="panah" /></a></header>
<main id="konten" tabindex="-1" class="cms-main" data-cms="{{ $screen }}">
<div class="cms-notice"><span class="preview-label">Pratinjau</span><p>Perubahanmu tersimpan di perangkat ini dan tidak mengubah website publik.</p></div>
<header class="cms-heading"><div><h1 data-cms-heading>{{ $titles[$screen] }}</h1><p data-cms-subtitle>Kelola bacaan dan foto kegiatan dengan langkah yang sederhana.</p></div><div data-cms-actions></div></header>
<div data-cms-content><p role="status">Menyiapkan ruang konten…</p></div>
<noscript><div class="cms-panel"><p>Aktifkan JavaScript untuk mencoba editor. Halaman publik tetap bisa dibaca tanpa editor.</p><a href="/">Kembali ke website</a></div></noscript>
<p class="cms-footnote">RT 05 RW 07 Taman Kedaung. Pratinjau ini tidak menyediakan login atau publikasi online.</p>
</main>
</div>
</div>
<script type="application/json" id="cms-seeds">{!! json_encode(['articles' => array_map(fn ($a) => array_merge($a, ['id' => 'article-'.$a['slug'], 'status' => 'example']), $articles), 'albums' => array_map(fn ($a) => array_merge($a, ['status' => 'example']), $albums)], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
@endsection
