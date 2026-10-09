@extends('layouts.app')
@section('title', 'Dokumentasi Lingkungan — RT 05 Takeda')
@section('content')
<section class="wrap page-intro"><p class="breadcrumb"><a href="/">Beranda</a> / Dokumentasi</p><h1>Melihat Takeda,<br>lebih dekat.</h1><p>Gapura, jalan, dan ruang bersama dalam foto.<br>Dokumentasi lingkungan oleh Kelompok 8, Oktober 2026.</p></section>
<section class="wrap gallery-section" aria-label="Galeri lingkungan">
<div class="filter-bar" data-filters="photos" aria-label="Filter foto"><button type="button" aria-pressed="true" data-filter="all">Semua foto</button><button type="button" aria-pressed="false" data-filter="lingkungan">Lingkungan</button><button type="button" aria-pressed="false" data-filter="bermain">Ruang bermain</button><button type="button" aria-pressed="false" data-filter="fasilitas">Fasilitas bersama</button></div>
<p class="filter-status" data-filter-status="photos" aria-live="polite">{{ count($foto) }} foto ditampilkan</p>
<div class="photo-gallery">
@foreach($foto as $slug => $data)
@php($category = in_array($slug, ['taman-bermain', 'kubah-panjat', 'ban-warna']) ? 'bermain' : (in_array($slug, ['gapura', 'jalan', 'area-hijau']) ? 'lingkungan' : 'fasilitas'))
<figure id="{{ $slug }}" data-filter-item="photos" data-category="{{ $category }}">
<button type="button" class="photo-trigger" data-lightbox="lingkungan" data-src="/images/dokumentasi/{{ $slug }}-{{ max($data['ukuran']) }}.webp" data-alt="{{ $data['alt'] }}" data-caption="{{ $data['keterangan'] }}" aria-label="Perbesar foto {{ $data['label'] }}"><x-foto :slug="$slug" :data="$data" :utama="$loop->first" sizes="(max-width: 600px) 92vw, (max-width: 1024px) 45vw, 30vw" /><span><x-ikon nama="foto" /> Perbesar</span></button>
<figcaption><h2>{{ $data['label'] }}</h2><p>{{ $data['keterangan'] }}</p></figcaption>
</figure>
@endforeach
</div>
</section>
<section class="wrap care-note"><x-ikon nama="agenda" /><div><h2>Cerita kegiatan menyusul.</h2><p>Galeri ini mendokumentasikan lingkungan dan fasilitas. Album kegiatan akan ditambahkan setelah foto, keterangan, dan izin publikasinya tersedia.</p></div></section>
<dialog id="lightbox" aria-label="Foto lingkungan diperbesar"><div class="lightbox-toolbar"><span>Dokumentasi RT 05</span><button type="button" data-lightbox-close>Tutup <span aria-hidden="true">×</span></button></div><figure><img data-lightbox-img alt=""><figcaption data-lightbox-caption></figcaption></figure><div class="lightbox-controls"><button type="button" data-lightbox-prev>Foto sebelumnya</button><button type="button" data-lightbox-next>Foto berikutnya</button></div></dialog>
@endsection
