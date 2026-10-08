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

    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%230b3f8c'/%3E%3Cpath d='M7 25V9h4v16zm7 0V9h11v4h-7v2h6v4h-6v6z' fill='%23f2c21a'/%3E%3C/svg%3E">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:bg-kapur focus:px-4 focus:py-3 focus:font-semibold focus:text-biru">
        Lewati ke konten
    </a>

    @include('partials.navbar')

    <main id="konten">
        @yield('content')
    </main>

    @include('partials.footer')
</body>
</html>
