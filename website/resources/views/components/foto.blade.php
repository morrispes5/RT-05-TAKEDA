@props(['slug', 'data', 'sizes' => '100vw', 'utama' => false])

@php
    $url = fn (int $w) => asset("images/dokumentasi/{$slug}-{$w}.webp");
    $srcset = collect($data['ukuran'])->map(fn ($w) => $url($w)." {$w}w")->implode(', ');
@endphp

<img
    src="{{ $url(min(960, max($data['ukuran']))) }}"
    srcset="{{ $srcset }}"
    sizes="{{ $sizes }}"
    width="{{ $data['lebar'] }}"
    height="{{ $data['tinggi'] }}"
    alt="{{ $data['alt'] }}"
    decoding="async"
    @if ($utama) fetchpriority="high" @else loading="lazy" @endif
    {{ $attributes }}
>
