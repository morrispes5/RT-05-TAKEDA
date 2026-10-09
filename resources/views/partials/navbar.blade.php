@php($menu = ['/' => 'Beranda', '/profil' => 'Profil RT', '/fasilitas' => 'Fasilitas', '/dokumentasi' => 'Dokumentasi', '/artikel' => 'Artikel'])
<header class="site-header">
    <div class="wrap nav-row">
        <a href="/" class="brand" aria-label="RT 05 Takeda — Beranda"><img src="/images/logo/rt05-mark.svg" width="44" height="44" alt=""><span>RT 05 Takeda<small>Taman Kedaung · RW 07</small></span></a>
        <button class="menu-toggle" type="button" data-menu-toggle aria-expanded="false" aria-controls="main-navigation">Menu <x-ikon nama="menu" /></button>
        <nav id="main-navigation" class="main-nav" aria-label="Navigasi utama" data-menu>
            @foreach($menu as $path => $label)
                <a href="{{ $path }}" @if(request()->getPathInfo() === $path || ($path === '/artikel' && request()->is('artikel/*'))) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
            <a href="/kontak" class="nav-contact" @if(request()->is('kontak')) aria-current="page" @endif>Kontak <x-ikon nama="panah" /></a>
        </nav>
    </div>
</header>
