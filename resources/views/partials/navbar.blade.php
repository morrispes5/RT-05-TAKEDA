@php
    $menu = [
        ['Layanan', '#layanan'],
        ['Tentang', '#tentang'],
        ['Fasilitas', '#fasilitas'],
        ['Galeri', '#galeri'],
    ];
@endphp

<div class="bg-brand-strong text-[13px] text-white">
    <div class="wrap flex min-h-9 items-center justify-between gap-4">
        <p class="flex items-center gap-1.5">
            <x-ikon nama="lokasi" class="size-4 text-sky-200" />
            Sekretariat RT 05/07, Taman Kedaung, Ciputat
        </p>
        <p class="hidden text-sky-200 sm:block">Website publik RT 05 RW 07 Takeda</p>
    </div>
</div>

<header class="sticky top-0 z-40 border-b border-line bg-surface/95 backdrop-blur-sm">
    <div class="wrap flex h-[4.5rem] items-center justify-between gap-6">
        <a href="{{ route('home') }}" class="flex items-center gap-3">
            <img src="{{ asset('images/logo/rt05-mark.svg') }}" alt="" width="44" height="44" class="size-11">
            <span class="leading-none">
                <span class="block font-display text-xl font-extrabold tracking-[-0.02em]">RT 05</span>
                <span class="mt-0.5 block text-[13px] font-medium text-ink-muted">Taman Kedaung</span>
            </span>
        </a>

        <nav class="hidden items-center gap-1 md:flex" aria-label="Menu utama">
            @foreach ($menu as [$label, $href])
                <a href="{{ $href }}" class="rounded-full px-4 py-2 font-medium text-ink transition-colors hover:bg-sky-100 hover:text-brand">{{ $label }}</a>
            @endforeach
            <a href="#aplikasi" class="rt-btn rt-btn--primary ml-3 min-h-11 px-5">Aplikasi warga</a>
        </nav>

        <button type="button" data-menu-toggle aria-expanded="false" aria-controls="menu-mobile"
                class="inline-flex min-h-11 items-center gap-2 rounded-full bg-sky-100 px-4 font-semibold text-brand md:hidden">
            Menu
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" class="size-5" aria-hidden="true">
                <path d="M3 6h14M3 10h14M3 14h14" stroke-linecap="round" />
            </svg>
        </button>
    </div>

    <nav id="menu-mobile" data-menu class="hidden border-t border-line bg-surface md:hidden" aria-label="Menu utama">
        <div class="wrap flex flex-col gap-1 py-3">
            @foreach ($menu as [$label, $href])
                <a href="{{ $href }}" class="rounded-[var(--radius-md)] px-4 py-3 text-lg font-medium hover:bg-sky-100">{{ $label }}</a>
            @endforeach
            <a href="#aplikasi" class="rt-btn rt-btn--primary mt-2">Aplikasi warga</a>
        </div>
    </nav>
</header>
