@extends('layouts.app')
@section('title', 'Dokumentasi — RT 05 Takeda')
@section('content')
<section class="wrap page-intro"><p class="breadcrumb"><a href="/">Beranda</a> / Dokumentasi</p><h1>Yang kita jalani.<br>Yang kita kenang.</h1><p>Momen kebersamaan dan sudut-sudut lingkungan Takeda.<br>Cerita dalam foto, dari warga untuk warga.</p></section>
<section class="wrap documentation-section">
<div class="document-tabs" role="tablist" aria-label="Jenis dokumentasi"><button id="tab-kegiatan" type="button" role="tab" aria-selected="true" aria-controls="kegiatan" tabindex="0" data-document-tab="kegiatan">Kegiatan</button><button id="tab-lingkungan" type="button" role="tab" aria-selected="false" aria-controls="lingkungan" tabindex="-1" data-document-tab="lingkungan">Lingkungan</button></div>
<div id="kegiatan" role="tabpanel" aria-labelledby="tab-kegiatan" tabindex="0">@foreach($albums as $album) @include('partials.album-feature') @endforeach</div>
<div id="lingkungan" role="tabpanel" aria-labelledby="tab-lingkungan" tabindex="0">
<div class="section-heading"><h2>Sudut-sudut Takeda</h2><p>Dokumentasi lingkungan, Oktober 2026</p></div>
<div class="filter-bar" data-filters="photos" aria-label="Filter foto"><button type="button" aria-pressed="true" data-filter="all">Semua foto</button><button type="button" aria-pressed="false" data-filter="lingkungan">Lingkungan</button><button type="button" aria-pressed="false" data-filter="bermain">Ruang bermain</button><button type="button" aria-pressed="false" data-filter="fasilitas">Fasilitas bersama</button></div>
<p class="filter-status" data-filter-status="photos" aria-live="polite">{{ count($foto) }} foto ditampilkan</p><div class="photo-gallery">
@foreach($foto as $slug => $data)
@php($category = in_array($slug, ['taman-bermain', 'kubah-panjat', 'ban-warna']) ? 'bermain' : (in_array($slug, ['gapura', 'jalan', 'area-hijau']) ? 'lingkungan' : 'fasilitas'))
<figure id="{{ $slug }}" data-filter-item="photos" data-category="{{ $category }}"><button type="button" class="photo-trigger" data-lightbox="lingkungan" data-src="/images/dokumentasi/{{ $slug }}-{{ max($data['ukuran']) }}.webp" data-alt="{{ $data['alt'] }}" data-caption="{{ $data['keterangan'] }}" aria-label="Perbesar foto {{ $data['label'] }}"><x-foto :slug="$slug" :data="$data" sizes="(max-width: 600px) 92vw, (max-width: 1024px) 45vw, 30vw" /><span><x-ikon nama="foto" /> Perbesar</span></button><figcaption><h2>{{ $data['label'] }}</h2><p>{{ $data['keterangan'] }}</p></figcaption></figure>
@endforeach
</div></div></section>
@include('partials.lightbox')
@endsection
