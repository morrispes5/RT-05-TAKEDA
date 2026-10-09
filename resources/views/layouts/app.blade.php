<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'RT 05 Takeda — Taman Kedaung, Ciputat')</title>
    <meta name="description" content="@yield('description', 'Kenali RT 05 RW 07 Taman Kedaung, Ciputat. Profil lingkungan, fasilitas bersama, dokumentasi, dan bacaan untuk warga.')">
    <meta name="theme-color" content="#123e65">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="@yield('title', 'RT 05 Takeda — Taman Kedaung, Ciputat')">
    <meta property="og:image" content="{{ asset('images/og-image.jpg') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/rt05-mark.svg') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="@yield('body_class')">
    <a href="#konten" class="skip-link">Lewati ke konten</a>
    @hasSection('management')
        @yield('content')
    @else
        @include('partials.navbar')
        <main id="konten" tabindex="-1">@yield('content')</main>
        @include('partials.footer')
    @endif
</body>
</html>
