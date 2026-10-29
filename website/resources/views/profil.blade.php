@extends('layouts.app')

@section('title', 'Profil RT05 — RT 05 Takeda')

@section('content')

    {{-- Page Intro --}}
    <section class="wrap page-intro">
        <p class="breadcrumb">
            <a href="/">Beranda</a> / Profil RT05
        </p>

        <h1>Kenal tempatnya.<br>Kenal lingkungannya.</h1>

        <p>
            {{ $profil['wilayah'] }}<br>
            {{ $profil['ringkasan'] }}
        </p>
    </section>

    {{-- Panorama --}}
    <div class="wrap profile-panorama">
        <x-foto slug="jalan" :data="$foto['jalan']" :utama="true" sizes="92vw" />
        <span>Jalan lingkungan RT 05</span>
    </div>

    {{-- Alamat lingkungan (ADR47): fakta tetap di halaman, bukan di CMS tentang. --}}
    <section class="wrap section intro-grid">
        <div>
            <p class="section-label">Alamat lingkungan</p>
            <h2>RT 05 RW 07<br>Perum Taman Kedaung.</h2>
        </div>

        <div class="intro-copy">
            <p>
                Kel. Kedaung, Kec. Pamulang<br>
                Kota Tangerang Selatan 15445
            </p>

            <p>
                Lingkungan ini terdiri dari 7 gang/jalan: Mawar 8, Mawar 9, Mawar 10, Mawar 11, Mawar 12, Mawar 12A/13, dan Mawar 14.
            </p>

            <x-tautan-maps class="text-link">
                Lihat di Google Maps <x-ikon nama="panah" />
            </x-tautan-maps>
        </div>
    </section>

    {{-- Tentang Takeda --}}
    <section class="wrap section intro-grid">
        <div>
            <p class="section-label">Nama yang dekat</p>
            <h2>Taman Kedaung.<br>Kita menyebutnya Takeda.</h2>
        </div>

        <div class="intro-copy">
            @forelse($profil['tentang'] as $paragraf)
                <p>{{ $paragraf }}</p>
            @empty
                <p>
                    Takeda merupakan singkatan dari Taman Kedaung. Website ini memperkenalkan lingkungan RT 05 RW 07 di Ciputat melalui foto asli, informasi fasilitas, dan bacaan yang bermanfaat untuk keseharian.
                </p>
            @endforelse

            <p>
                Gapura menyambut di pintu masuk. Sekretariat, lapangan, dan taman bermain menjadi bagian dari ruang bersama di lingkungan ini.
            </p>

            <a href="/dokumentasi" class="text-link">
                Lihat dokumentasi lingkungan <x-ikon nama="panah" />
            </a>
        </div>
    </section>

    {{-- Pengurus --}}
    <section class="soft-section">
        <div class="wrap section">
            <div class="section-heading">
                <div>
                    <p class="section-label">Pengurus lingkungan</p>
                    <h2>Orang di balik<br>pelayanan RT.</h2>
                </div>

                <p>
                    Informasi kepengurusan dilengkapi<br>
                    secara bertahap bersama pengurus.
                </p>
            </div>

            <div class="people-grid">
                @foreach($profil['pengurus'] as $orang)
                    <div class="person">
                        <x-ikon nama="warga" />
                        <p>{{ $orang['jabatan'] }}</p>
                        <h3>{{ $orang['nama'] }}</h3>

                        @if(filled($orang['catatan']))
                            <small>{{ $orang['catatan'] }}</small>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Yang Kita Rawat --}}
    <section class="wrap section intro-grid">
        <h2>Yang kita<br>rawat bersama.</h2>

        <div class="intro-copy">
            <p>
                Kebersihan, kepedulian pada tetangga, dan penggunaan fasilitas dengan tertib bisa dimulai dari hal sederhana: mengembalikan barang setelah dipakai dan meninggalkan ruang bersama dalam keadaan bersih.
            </p>

            <p>
                Ini adalah ajakan dalam bacaan website, bukan rumusan visi atau peraturan resmi baru.
            </p>

            <a href="/fasilitas" class="text-link">
                Kenali ruang bersama <x-ikon nama="panah" />
            </a>
        </div>
    </section>

@endsection