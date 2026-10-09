@extends('layouts.app')
@section('content')
<section class="home-hero">
    <div class="wrap hero-intro">
        <p class="place-line"><span></span> RT 05 RW 07 · Taman Kedaung, Ciputat</p>
        <h1>Tempat tinggal.<br>Tempat kita bertetangga.</h1>
        <p>Selamat datang di RT 05 Takeda. Kenali lingkungan, ruang bersama,<br class="desktop-break"> dan hal-hal kecil yang membuat kita merasa di rumah.</p>
        <a class="button primary" href="/profil">Kenali lingkungan kami <x-ikon nama="panah" /></a>
    </div>
    <div class="wrap hero-collage">
        <figure class="hero-side hero-garden"><x-foto slug="taman-bermain" :data="$foto['taman-bermain']" sizes="(max-width: 600px) 43vw, 25vw" /><figcaption>Ruang bermain, di bawah rindang pohon.</figcaption></figure>
        <figure class="hero-main"><x-foto slug="gapura" :data="$foto['gapura']" :utama="true" sizes="(max-width: 600px) 94vw, 50vw" /><figcaption><span>Selamat datang di Takeda</span><x-ikon nama="lokasi" /></figcaption></figure>
        <figure class="hero-side hero-post"><x-foto slug="sekretariat" :data="$foto['sekretariat']" sizes="(max-width: 600px) 43vw, 25vw" /><figcaption>Sudut untuk bertemu dan berbincang.</figcaption></figure>
    </div>
    <div class="wrap hero-foot"><span>Sepotong cerita dari lingkungan kami</span><a href="/dokumentasi">Lihat dari dekat <x-ikon nama="panah" /></a></div>
</section>
<section class="wrap section intro-grid">
    <div><p class="section-label">Tentang Takeda</p><h2>Dekat rumah,<br>dekat satu sama lain.</h2></div>
    <div class="intro-copy"><p>Takeda adalah singkatan dari Taman Kedaung. Di lingkungan RT 05 RW 07, Ciputat, jalan-jalan rumah terhubung dengan ruang yang bisa dinikmati bersama.</p><p>Dari sekretariat di sisi lapangan hingga taman bermain yang teduh, inilah sudut-sudut yang menjadi bagian dari keseharian lingkungan.</p><a class="text-link" href="/profil">Cerita tentang RT 05 <x-ikon nama="panah" /></a></div>
</section>
<section class="facility-section">
    <div class="wrap section">
        <div class="section-heading"><div><p class="section-label">Sarana & prasarana</p><h2>Ruang bersama,<br>kita jaga bersama.</h2></div><a class="text-link" href="/fasilitas">Jelajahi fasilitas <x-ikon nama="panah" /></a></div>
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
<section class="wrap section journal-section">
    <div class="section-heading"><div><p class="section-label">Bacaan warga</p><h2>Kebiasaan kecil.<br>Lingkungan lebih nyaman.</h2></div><a class="text-link" href="/artikel">Semua artikel <x-ikon nama="panah" /></a></div>
    <div class="article-grid">@foreach(array_slice($articles, 0, 3) as $article) @include('partials.article-card') @endforeach</div>
</section>
<section class="wrap mobile-note">
    <div class="mobile-note-icon"><x-ikon nama="aspirasi" /></div><div><p class="section-label">Rencana layanan warga</p><h2>Informasi di web.<br>Layanan pribadi lewat aplikasi.</h2><p>Pengaduan, aspirasi, agenda, dan riwayat iuran direncanakan melalui aplikasi mobile warga. Saat ini, website ini menjadi tempat mengenal lingkungan RT 05.</p><a class="text-link" href="/kontak">Informasi dari pengurus <x-ikon nama="panah" /></a></div><span class="status-label">Dalam pengembangan</span>
</section>
@endsection
