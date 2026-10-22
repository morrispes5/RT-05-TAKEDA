<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'RT 05 Takeda — Taman Kedaung, Ciputat')</title>
    <meta name="description" content="@yield('description', 'Kenali RT 05 RW 07 Taman Kedaung, Ciputat. Profil lingkungan, fasilitas bersama, dokumentasi, dan bacaan untuk warga.')">
    <meta name="theme-color" content="#123e65">
    @php($canonical = \App\Http\Controllers\PublicPageController::origin().(request()->getPathInfo() === '/' ? '/' : request()->getPathInfo()))
    @if(! \App\Support\SiteContent::indexing() || request()->is('pengurus', 'pengurus/*'))
    <meta name="robots" content="noindex, nofollow">
    @endif
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:locale" content="id_ID">
    <meta property="og:site_name" content="RT05 TAKEDA">
    <meta property="og:title" content="@yield('title', 'RT 05 Takeda — Taman Kedaung, Ciputat')">
    <meta property="og:description" content="@yield('description', 'Kenali RT 05 RW 07 Taman Kedaung, Ciputat. Profil lingkungan, fasilitas bersama, dokumentasi, dan bacaan untuk warga.')">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ \App\Http\Controllers\PublicPageController::origin() }}/images/og-image.jpg">
    @stack('jsonld')
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
