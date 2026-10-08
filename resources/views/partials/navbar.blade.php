@php
    $menu = [
        ['Lingkungan', '#lingkungan'],
        ['Layanan', '#layanan'],
        ['Fasilitas', '#fasilitas'],
        ['Tentang', '#tentang'],
    ];
@endphp

<header class="sticky top-0 z-40 border-b border-garis bg-kapur/95 backdrop-blur-sm">
    <div class="wrap flex h-16 items-center justify-between gap-6">
        <a href="{{ route('home') }}" class="font-display leading-none">
            <span class="block text-xl font-extrabold tracking-[-0.02em]">RT 05</span>
            <span class="block text-sm font-medium text-abu">Taman Kedaung</span>
        </a>

        <nav class="hidden items-center gap-8 md:flex" aria-label="Menu utama">
            @foreach ($menu as [$label, $href])
                <a href="{{ $href }}" class="font-medium text-tinta transition-colors hover:text-biru">{{ $label }}</a>
            @endforeach
            <a href="#aplikasi" class="tombol min-h-10 px-4 text-[15px]">Aplikasi warga</a>
        </nav>

        <button type="button" data-menu-toggle aria-expanded="false" aria-controls="menu-mobile"
                class="-mr-2 inline-flex min-h-11 items-center gap-2 rounded-md px-2 font-semibold md:hidden">
            Menu
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" class="size-5" aria-hidden="true">
                <path d="M3 6h14M3 10h14M3 14h14" stroke-linecap="round" />
            </svg>
        </button>
    </div>

    <nav id="menu-mobile" data-menu class="hidden border-t border-garis bg-kapur md:hidden" aria-label="Menu utama">
        <div class="wrap flex flex-col py-3">
            @foreach ($menu as [$label, $href])
                <a href="{{ $href }}" class="border-b border-garis py-3 text-lg font-medium">{{ $label }}</a>
            @endforeach
            <a href="#aplikasi" class="tombol mt-4">Aplikasi warga</a>
        </div>
    </nav>
</header>
