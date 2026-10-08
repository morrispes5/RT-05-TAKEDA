@php
    $links = [
        ['Beranda', '#beranda'],
        ['Tentang RT', '#tentang'],
        ['Fasilitas', '#fasilitas'],
        ['Kabar RT', '#kabar'],
        ['Galeri', '#galeri'],
    ];
@endphp

<header class="sticky top-0 z-40 border-b border-brand-100 bg-white/90 backdrop-blur">
    <div class="mx-auto flex h-[4.5rem] max-w-7xl items-center justify-between gap-6 px-4 sm:px-6 lg:px-8">
        <a href="#beranda" class="flex items-center gap-3" aria-label="RT 05 Takeda, ke beranda">
            <span class="grid size-10 place-items-center rounded-xl bg-brand-600 text-white">
                <svg viewBox="0 0 24 24" fill="currentColor" class="size-6" aria-hidden="true">
                    <path d="M12 3 2 11.5h3V20h5v-5.5h4V20h5v-8.5h3z"/>
                </svg>
            </span>
            <span class="leading-tight">
                <span class="block text-base font-extrabold text-ink">RT 05 Takeda</span>
                <span class="block text-xs font-medium text-ink-soft">Layanan Pintar</span>
            </span>
        </a>

        <nav class="hidden items-center gap-1 lg:flex" aria-label="Navigasi utama">
            @foreach ($links as [$label, $href])
                <a href="{{ $href }}" class="rounded-full px-4 py-2 text-sm font-semibold text-ink-soft transition-colors hover:bg-brand-50 hover:text-brand-700">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <div class="flex items-center gap-3">
            <a href="#aplikasi" class="btn btn-primary hidden !py-2.5 lg:inline-flex">Aplikasi warga</a>

            <button type="button" data-menu-toggle aria-expanded="false" aria-controls="menu-mobile"
                    class="grid size-11 place-items-center rounded-xl border border-brand-100 text-ink hover:bg-brand-50 lg:hidden">
                <span class="sr-only">Buka menu</span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-6" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16"/>
                </svg>
            </button>
        </div>
    </div>

    <div id="menu-mobile" data-menu class="hidden border-t border-brand-100 bg-white lg:hidden">
        <nav class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-4 sm:px-6" aria-label="Navigasi mobile">
            @foreach ($links as [$label, $href])
                <a href="{{ $href }}" class="rounded-xl px-4 py-3 text-base font-semibold text-ink hover:bg-brand-50">{{ $label }}</a>
            @endforeach
            <a href="#aplikasi" class="btn btn-primary mt-2">Aplikasi warga</a>
        </nav>
    </div>
</header>
