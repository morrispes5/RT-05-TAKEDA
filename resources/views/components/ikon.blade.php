@props(['nama'])

@php
    // Ikon garis 24px, stroke 2px (gaya Lucide), sesuai design system.
    $paths = [
        'pengaduan' => '<path d="M5 21V4"/><path d="M5 4h11l-2 4 2 4H5"/>',
        'aspirasi' => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9h8M8 12h5"/>',
        'agenda' => '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M4 10h16M9 3v4M15 3v4"/>',
        'iuran' => '<path d="M4 7h15a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1z"/><path d="M4 7l11-3v3"/><path d="M16 14h1"/>',
        'pengumuman' => '<path d="M4 13v-2a1 1 0 0 1 1-1h3l8-4v12l-8-4H5a1 1 0 0 1-1-1z"/><path d="M8 14l1 5h3l-1-4.5"/>',
        'notifikasi' => '<path d="M6 17v-6a6 6 0 0 1 12 0v6l2 2H4z"/><path d="M10 21h4"/>',
        'cek' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'lokasi' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'kunci' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'foto' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 8"/>',
    ];
@endphp

<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes->merge(['class' => 'size-6']) }}>{!! $paths[$nama] !!}</svg>
