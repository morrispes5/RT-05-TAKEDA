@php($cover = collect($album['photos'])->firstWhere('id', $album['coverId']) ?? $album['photos'][0])
<a class="album-feature" href="/dokumentasi/{{ $album['slug'] }}">
    <div class="album-feature-photo"><img src="{{ $cover['src'] }}" srcset="{{ $cover['srcset'] }}" sizes="(max-width: 768px) 92vw, 60vw" alt="{{ $cover['alt'] }}" width="{{ $cover['width'] }}" height="{{ $cover['height'] }}" loading="lazy"></div>
    <div class="album-feature-copy"><span class="album-kind">Dokumentasi kegiatan</span><h2>{{ $album['title'] }}</h2><p>{{ $album['description'] }}</p><span class="album-open">Lihat album <x-ikon nama="panah" /></span><small>{{ count($album['photos']) }} foto @if($album['date']) — {{ $album['date'] }} @endif</small></div>
</a>
