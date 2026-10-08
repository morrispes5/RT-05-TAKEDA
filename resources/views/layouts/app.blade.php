<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'RT 05 Taman Kedaung')</title>
    <meta name="description" content="@yield('description', 'Website RT 05 RW 07 Taman Kedaung (Takeda), Ciputat: kabar lingkungan, fasilitas bersama, dan kegiatan warga.')">
    <meta name="theme-color" content="#0b3f8c">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="RT 05 Taman Kedaung">
    <meta property="og:description" content="Kabar lingkungan, fasilitas bersama, dan kegiatan warga RT 05 RW 07 Takeda, Ciputat.">
    <meta property="og:image" content="{{ asset('images/og-image.jpg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/rt05-mark.svg') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-full focus:bg-surface focus:px-4 focus:py-3 focus:font-semibold focus:text-brand">
        Lewati ke konten
    </a>

    @include('partials.navbar')

    <main id="konten">
        @yield('content')
    </main>

    @include('partials.footer')
</body>
</html>
