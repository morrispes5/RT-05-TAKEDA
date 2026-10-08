<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'RT 05 Takeda — Layanan Pintar')</title>
    <meta name="description" content="@yield('description', 'Website resmi RT 05 Taman Kedaung (TAKEDA), Ciputat. Kenali lingkungan, fasilitas, pengurus, dan kegiatan warga dalam satu tempat.')">
    <meta name="theme-color" content="#0070c4">

    <meta property="og:type" content="website">
    <meta property="og:title" content="RT 05 Takeda — Layanan Pintar">
    <meta property="og:description" content="Lingkungan yang akrab. Informasi yang dekat. Website publik RT 05 Taman Kedaung, Ciputat.">
    <meta property="og:locale" content="id_ID">

    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%230070c4'/%3E%3Cpath d='M16 7 6 15.5h3V24h5.5v-5.5h3V24H23v-8.5h3z' fill='white'/%3E%3C/svg%3E">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:text-brand-700 focus:shadow-lg">
        Lewati ke konten
    </a>

    @include('partials.navbar')

    <main id="konten">
        @yield('content')
    </main>

    @include('partials.footer')
</body>
</html>
