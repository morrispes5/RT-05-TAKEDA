@extends('layouts.app')
@section('content')
<section class="home-hero">
    <div class="hero-watermark" aria-hidden="true">
        <svg viewBox="0 0 1440 720" preserveAspectRatio="xMidYMid slice">
            <g fill="none" stroke="#123e65" stroke-width="1.5">
                <path d="M-60 720 V380 A 240 240 0 0 1 420 380 V720"/>
                <path d="M-20 720 V400 A 200 200 0 0 1 380 400 V720"/>
                <path d="M1080 720 V300 A 260 260 0 0 1 1600 300 V720"/>
                <path d="M1120 720 V330 A 215 215 0 0 1 1550 330 V720"/>
            </g>
        </svg>
    </div>
    <div class="wrap hero-grid">
        <div class="hero-copy">
            <svg class="hero-arc-line" viewBox="0 0 340 74" aria-hidden="true">
                <path d="M6 68 Q 170 -14 334 68" fill="none" stroke="#f2cd5a" stroke-width="5" stroke-linecap="round"/>
            </svg>
            <h1><span class="line"><span>Tempat tinggal.</span></span><span class="line"><span class="hollow">Tempat kita bertetangga.</span></span></h1>
            <p>Selamat datang di RT 05 Takeda. Kenali lingkungan, ruang bersama, dan hal-hal kecil yang membuat kita merasa di rumah.</p>
            <div class="hero-cta">
                <a class="button primary" href="/profil">Kenali lingkungan kami <x-ikon nama="panah" /></a>
                <a class="text-link" href="/dokumentasi">Lihat dokumentasi <x-ikon nama="panah" /></a>
            </div>
        </div>
        <div class="hero-visual" data-parallax-scene>
            <figure class="hero-arch" data-parallax="14" data-drag-pan>
                <x-foto slug="gapura" :data="$foto['gapura']" :utama="true" sizes="(max-width: 1024px) 92vw, 46vw" />
                <figcaption><span>Selamat datang di Takeda</span><x-ikon nama="lokasi" /></figcaption>
            </figure>
            <div class="hero-badge" data-parallax="46" aria-hidden="true">
                <svg viewBox="0 0 132 132">
                    <defs><path id="badge-circle" d="M66 66 m -46 0 a 46 46 0 1 1 92 0 a 46 46 0 1 1 -92 0"/></defs>
                    <text><textPath href="#badge-circle">Selamat datang di Takeda · RT 05 RW 07 · Taman Kedaung ·</textPath></text>
                </svg>
                <img src="/images/logo/rt05-mark.svg" alt="" width="40" height="40">
            </div>
        </div>
    </div>
    <div class="hero-marquee" aria-hidden="true" hidden>
        <div class="hero-marquee-track">
            @foreach([0, 1] as $copy)
            <span>Selamat datang di Takeda <svg viewBox="0 0 24 24" class="mq-gapura"><path d="M3 10 12 3l9 7v1.4H3z" fill="#e0607e"/><rect x="5" y="11.4" width="3" height="8" rx=".7" fill="#c4dcee"/><rect x="16" y="11.4" width="3" height="8" rx=".7" fill="#c4dcee"/><rect x="2" y="19.4" width="20" height="1.6" rx=".8" fill="#f2cd5a"/></svg> RT 05 RW 07 <svg viewBox="0 0 24 24" class="mq-gapura"><path d="M3 10 12 3l9 7v1.4H3z" fill="#e0607e"/><rect x="5" y="11.4" width="3" height="8" rx=".7" fill="#c4dcee"/><rect x="16" y="11.4" width="3" height="8" rx=".7" fill="#c4dcee"/><rect x="2" y="19.4" width="20" height="1.6" rx=".8" fill="#f2cd5a"/></svg> Taman Kedaung, Ciputat <svg viewBox="0 0 24 24" class="mq-gapura"><path d="M3 10 12 3l9 7v1.4H3z" fill="#e0607e"/><rect x="5" y="11.4" width="3" height="8" rx=".7" fill="#c4dcee"/><rect x="16" y="11.4" width="3" height="8" rx=".7" fill="#c4dcee"/><rect x="2" y="19.4" width="20" height="1.6" rx=".8" fill="#f2cd5a"/></svg> Ruang bersama kita jaga bersama <svg viewBox="0 0 24 24" class="mq-gapura"><path d="M3 10 12 3l9 7v1.4H3z" fill="#e0607e"/><rect x="5" y="11.4" width="3" height="8" rx=".7" fill="#c4dcee"/><rect x="16" y="11.4" width="3" height="8" rx=".7" fill="#c4dcee"/><rect x="2" y="19.4" width="20" height="1.6" rx=".8" fill="#f2cd5a"/></svg> </span>
            @endforeach
        </div>
    </div>
</section>
<nav class="wrap home-shortcuts" aria-label="Temukan informasi"><span>Yang ingin kamu lihat</span><a href="/artikel">Bacaan warga <x-ikon nama="buku" /></a><a href="/dokumentasi">Foto & kegiatan <x-ikon nama="foto" /></a><a href="/kontak">Kontak pengurus <x-ikon nama="panah" /></a></nav>
<section class="wrap section intro-grid" data-reveal>
    <div>
        <p class="section-label">Tentang Takeda</p><h2>Dekat rumah,<br>dekat satu sama lain.</h2>
        <svg class="intro-art" viewBox="0 0 340 210" aria-hidden="true">
            <g fill="none" stroke="#123e65" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 172 H 326"/>
                <path d="M30 118 L 78 78 L 126 118"/><path d="M42 118 v54 h72 v-54"/><path d="M70 172 v-30 h16 v30"/>
                <path d="M262 172 v-52"/><circle cx="262" cy="96" r="26"/><path d="M262 120 l -14 12 M262 120 l 14 12"/>
                <circle cx="180" cy="106" r="11"/><path d="M180 117 v34 M180 128 l -16 12 M180 128 l 16 12 M180 151 l -10 21 M180 151 l 10 21"/>
                <circle cx="216" cy="112" r="9"/><path d="M216 121 v30 M216 131 l -13 10 M216 131 l 12 10 M216 151 l -8 21 M216 151 l 9 21"/>
                <path d="M196 132 q 8 6 16 2"/>
            </g>
            <circle cx="310" cy="34" r="13" fill="none" stroke="#f2cd5a" stroke-width="4"/>
            <path d="M310 12 v-6 M310 62 v-6 M288 34 h-6 M338 34 h-6" stroke="#f2cd5a" stroke-width="4" stroke-linecap="round"/>
        </svg>
    </div>
    <div class="intro-copy"><p>Takeda adalah singkatan dari Taman Kedaung. Di lingkungan RT 05 RW 07, Ciputat, jalan-jalan rumah terhubung dengan ruang yang bisa dinikmati bersama.</p><p>Dari sekretariat di sisi lapangan hingga taman bermain yang teduh, inilah sudut-sudut yang menjadi bagian dari keseharian lingkungan.</p><a class="text-link" href="/profil">Cerita tentang RT 05 <x-ikon nama="panah" /></a></div>
</section>
<section class="facility-section" data-reveal>
    <div class="wrap section">
        <div class="section-heading"><div><p class="section-label">Sarana & prasarana</p><h2>Ruang bersama,<br>kita jaga bersama.</h2><svg class="squiggle" viewBox="0 0 210 14" aria-hidden="true"><path d="M4 9 Q 30 2 56 8 T 108 8 T 160 8 T 206 7" fill="none" stroke="#f2cd5a" stroke-width="5" stroke-linecap="round"/></svg></div><a class="text-link" href="/fasilitas">Jelajahi fasilitas <x-ikon nama="panah" /></a></div>
        <div class="facility-editorial">
            <a href="/fasilitas#lapangan" class="facility-lead"><x-foto slug="lapangan" :data="$foto['lapangan']" sizes="(max-width: 768px) 92vw, 57vw" /><div><h3>Lapangan serbaguna</h3><p>Ruang terbuka di tengah lingkungan.</p><x-ikon nama="panah" /></div></a>
            <div class="facility-list">
                @foreach(['taman-bermain' => ['Ruang untuk bermain', 'Kubah panjat, perosotan, dan pepohonan yang menaungi.'], 'sekretariat' => ['Sekretariat RT', 'Tempat pengurus dan warga bertemu.'], 'area-hijau' => ['Sudut yang lebih hijau', 'Rumput dan pepohonan di tepi lapangan.']] as $slug => [$title, $desc])
                <a href="/fasilitas#{{ $slug }}"><x-foto :slug="$slug" :data="$foto[$slug]" sizes="150px" /><div><h3>{{ $title }}</h3><p>{{ $desc }}</p></div><x-ikon nama="panah" /></a>
                @endforeach
            </div>
        </div>
    </div>
</section>
<section class="wrap section home-albums"><div class="section-heading"><h2>Momen yang<br>kita simpan bersama.</h2><a class="text-link" href="/dokumentasi">Semua dokumentasi <x-ikon nama="panah" /></a></div>@foreach(array_slice($albums, 0, 1) as $album) @include("partials.album-feature") @endforeach</section>
<section class="wrap section journal-section" data-reveal>
    <div class="section-heading"><div><p class="section-label">Bacaan warga</p><h2>Kebiasaan kecil.<br>Lingkungan lebih nyaman.</h2><svg class="squiggle" viewBox="0 0 210 14" aria-hidden="true"><path d="M4 9 Q 30 2 56 8 T 108 8 T 160 8 T 206 7" fill="none" stroke="#f2cd5a" stroke-width="5" stroke-linecap="round"/></svg></div><a class="text-link" href="/artikel">Semua artikel <x-ikon nama="panah" /></a></div>
    <div class="article-grid">@foreach(array_slice($articles, 0, 3) as $article) @include('partials.article-card') @endforeach</div>
</section>
<section class="wrap home-contact"><div><h2>Kenali tempat<br>kita bertemu.</h2><p>Sekretariat RT berada di sisi lapangan. Lihat informasi lingkungan dan kontak yang tersedia dari pengurus.</p><a class="button primary" href="/kontak">Informasi kontak <x-ikon nama="panah" /></a></div><figure><x-foto slug="sekretariat" :data="$foto['sekretariat']" sizes="(max-width: 768px) 92vw, 40vw" /><figcaption>Sekretariat RT 05 RW 07</figcaption></figure></section>
@endsection
