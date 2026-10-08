@extends('layouts.app')

@php
    // Ikon (path SVG 24x24, stroke) dipakai ulang di beberapa bagian.
    $icons = [
        'building' => 'M3 21h18M5 21V7l7-4 7 4v14M9 9h.01M9 13h.01M9 17h.01M15 9h.01M15 13h.01M15 17h.01',
        'camera' => 'M4 8h3l2-3h6l2 3h3v11H4zM12 17a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
        'users' => 'M16 19v-1a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v1M10 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zM20 19v-1a4 4 0 0 0-3-3.9M16 4.2a3.5 3.5 0 0 1 0 6.6',
        'news' => 'M5 4h11a2 2 0 0 1 2 2v13H7a2 2 0 0 1-2-2zM18 8h2v9a2 2 0 0 1-2 2M8 8h6M8 12h6M8 16h3',
        'flag' => 'M5 21V4m0 0h12l-2 4 2 4H5',
        'chat' => 'M4 5h16v11H9l-5 4zM8 9h8M8 12h5',
        'calendar' => 'M5 6h14v14H5zM5 10h14M9 3v4M15 3v4',
        'wallet' => 'M4 7h15a1 1 0 0 1 1 1v11H5a1 1 0 0 1-1-1zM4 7l12-3v3M16 13h2',
        'megaphone' => 'M4 13v-2a1 1 0 0 1 1-1h3l8-4v12l-8-4H5a1 1 0 0 1-1-1zM8 14l1 5h3l-1-4.5',
        'bell' => 'M6 17V11a6 6 0 0 1 12 0v6l2 2H4zM10 21h4',
        'lock' => 'M6 11h12v9H6zM8 11V8a4 4 0 0 1 8 0v3',
        'globe' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18',
        'link' => 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1',
    ];

    $dekat = [
        ['building', 'Fasilitas lingkungan', 'Kenali sarana, lokasi, dan kondisi fasilitas.', '#fasilitas'],
        ['camera', 'Cerita kebersamaan', 'Dokumentasi kegiatan dan momen warga.', '#galeri'],
        ['users', 'Pengurus yang hadir', 'Kenali peran dan saluran kontak pengurus.', '#pengurus'],
    ];

    $tentang = [
        ['globe', 'Terbuka untuk umum', 'Website ini bisa dibuka siapa saja tanpa login atau registrasi.'],
        ['link', 'Satu sumber informasi', 'Profil, fasilitas, kabar, dan dokumentasi kegiatan RT terkumpul di satu tempat, tidak lagi tertumpuk di percakapan grup.'],
        ['lock', 'Layanan pribadi di aplikasi', 'Pengaduan, iuran, dan data keluarga hanya tersedia di aplikasi warga untuk akun yang sudah disetujui pengurus.'],
    ];

    // Bagian konten publik. Isinya dikelola pengurus (lihat Dokumen Perancangan, bagian D);
    // sementara belum ada data, tampil sebagai keadaan kosong yang jujur, bukan data karangan.
    $konten = [
        ['fasilitas', 'building', 'Fasilitas lingkungan', 'Sarana dan prasarana RT', 'Nama, lokasi, kondisi, dan foto setiap fasilitas akan ditampilkan di sini setelah pengurus mengisi datanya.'],
        ['pengurus', 'users', 'Pengurus RT', 'Susunan dan kontak publik', 'Jabatan pengurus dan saluran kontak publik RT akan ditampilkan di sini.'],
        ['kabar', 'news', 'Kabar RT', 'Artikel dan informasi lingkungan', 'Artikel tentang kegiatan dan lingkungan RT akan tampil di sini setelah dipublikasikan pengurus.'],
        ['galeri', 'camera', 'Galeri kegiatan', 'Dokumentasi foto warga', 'Album foto kegiatan RT akan tampil di sini setelah dipublikasikan pengurus.'],
    ];

    $layanan = [
        ['flag', 'Pengaduan warga', 'Laporkan masalah lingkungan lengkap dengan foto. Nama bisa disembunyikan dari warga lain, dan perkembangannya bisa dipantau.'],
        ['chat', 'Aspirasi', 'Kirim kritik, saran, dan usulan, lalu pantau statusnya dari terkirim sampai selesai.'],
        ['calendar', 'Agenda kegiatan', 'Lihat kegiatan yang akan datang dan tambahkan ke Google Calendar.'],
        ['wallet', 'Iuran & kas RT', 'Cek status iuran rumah dan riwayat pembayaran, plus transparansi kas RT.'],
        ['megaphone', 'Pengumuman', 'Informasi resmi pengurus yang rapi dan mudah dicari kembali.'],
        ['bell', 'Notifikasi', 'Pengingat agenda, perubahan status pengaduan, dan informasi baru.'],
    ];
@endphp

@section('content')

{{-- ============ HERO ============ --}}
<section id="beranda" class="relative overflow-hidden bg-gradient-to-b from-brand-50 via-white to-white">
    <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-2 lg:gap-8 lg:px-8 lg:py-28">
        <div class="reveal">
            <p class="eyebrow">Selamat datang di Taman Kedaung</p>
            <h1 class="mt-5 text-4xl font-extrabold leading-[1.1] tracking-tight text-ink sm:text-5xl lg:text-6xl">
                Lingkungan yang akrab.
                <span class="block text-brand-600">Informasi yang dekat.</span>
            </h1>
            <p class="mt-6 max-w-xl text-lg leading-8 text-ink-soft">
                Kenali RT 05 Taman Kedaung, temukan fasilitas lingkungan, dan ikuti cerita kegiatan warga dalam satu tempat.
            </p>
            <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                <a href="#tentang" class="btn btn-primary">Kenali lingkungan</a>
                <a href="#kabar" class="btn btn-secondary">Lihat kabar RT</a>
            </div>
        </div>

        <div class="reveal" aria-hidden="true">
            <svg viewBox="0 0 584 352" class="mx-auto w-full max-w-xl" role="img">
                <rect width="584" height="352" rx="28" fill="#e0f0fe"/>
                <circle cx="470" cy="74" r="34" fill="#fde68a"/>
                <path d="M60 92c24-18 56-18 80 0 22-12 50-10 66 8H46c0-4 6-8 14-8z" fill="#fff" opacity=".9"/>
                <path d="M330 60c18-12 40-12 56 0 14-7 32-4 42 8H318c0-4 6-6 12-8z" fill="#fff" opacity=".85"/>
                <path d="M0 262c80-34 160-34 240-10s170 30 344-8v108H0z" fill="#bae0fd"/>
                <rect x="0" y="288" width="584" height="64" fill="#7cc8fb" opacity=".55"/>
                {{-- rumah kiri --}}
                <rect x="58" y="188" width="132" height="102" fill="#fff"/>
                <path d="M46 192 124 126l78 66z" fill="#0070c4"/>
                <rect x="84" y="226" width="30" height="64" rx="3" fill="#36aaf5"/>
                <rect x="132" y="214" width="38" height="34" rx="3" fill="#bae0fd"/>
                {{-- rumah tengah --}}
                <rect x="232" y="164" width="150" height="126" fill="#fff"/>
                <path d="M218 170 307 94l89 76z" fill="#064b83"/>
                <rect x="288" y="228" width="38" height="62" rx="3" fill="#0c8ee6"/>
                <rect x="250" y="190" width="30" height="30" rx="3" fill="#bae0fd"/>
                <rect x="334" y="190" width="30" height="30" rx="3" fill="#bae0fd"/>
                {{-- rumah kanan --}}
                <rect x="426" y="196" width="104" height="94" fill="#fff"/>
                <path d="M414 200 478 142l64 58z" fill="#0b3f6c"/>
                <rect x="458" y="238" width="26" height="52" rx="3" fill="#36aaf5"/>
                <rect x="494" y="220" width="24" height="26" rx="3" fill="#bae0fd"/>
                {{-- pohon --}}
                <rect x="204" y="252" width="8" height="38" fill="#0b3f6c"/>
                <circle cx="208" cy="240" r="22" fill="#0c8ee6"/>
                <circle cx="198" cy="250" r="14" fill="#36aaf5"/>
            </svg>
            <p class="mt-5 text-center text-sm font-semibold text-ink-soft">Bersama merawat tempat kita pulang.</p>
        </div>
    </div>
</section>

{{-- ============ TENTANG ============ --}}
<section id="tentang" class="bg-white py-20 sm:py-24">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="reveal max-w-3xl">
            <p class="eyebrow">Tentang RT</p>
            <h2 class="section-title mt-3">RT 05 Taman Kedaung, Ciputat</h2>
            <p class="section-lead">
                TAKEDA adalah singkatan dari Taman Kedaung. Website ini adalah lapisan informasi publik dari platform
                layanan dan administrasi digital RT 05, supaya informasi lingkungan mudah ditemukan dan tidak lagi
                bercampur dengan percakapan grup.
            </p>
        </div>

        <ul class="mt-12 grid gap-6 md:grid-cols-3">
            @foreach ($tentang as [$ikon, $judul, $isi])
                <li class="reveal rounded-3xl border border-brand-100 bg-brand-50/60 p-7">
                    <span class="grid size-12 place-items-center rounded-2xl bg-white text-brand-600 shadow-sm">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6" aria-hidden="true"><path d="{{ $icons[$ikon] }}"/></svg>
                    </span>
                    <h3 class="mt-5 text-lg font-bold text-ink">{{ $judul }}</h3>
                    <p class="mt-2 leading-7 text-ink-soft">{{ $isi }}</p>
                </li>
            @endforeach
        </ul>
    </div>
</section>

{{-- ============ LEBIH DEKAT ============ --}}
<section class="bg-brand-50 py-20 sm:py-24" aria-labelledby="judul-dekat">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="reveal max-w-2xl">
            <h2 id="judul-dekat" class="section-title">Lebih dekat dengan RT 05</h2>
            <p class="section-lead">Informasi terbuka untuk semua. Layanan pribadi tersedia di aplikasi warga.</p>
        </div>

        <div class="mt-12 grid gap-6 md:grid-cols-3">
            @foreach ($dekat as [$ikon, $judul, $isi, $href])
                <a href="{{ $href }}" class="reveal group rounded-3xl border border-brand-100 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:border-brand-300 hover:shadow-lg hover:shadow-brand-600/10">
                    <span class="grid size-12 place-items-center rounded-2xl bg-brand-100 text-brand-700 transition-colors group-hover:bg-brand-600 group-hover:text-white">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6" aria-hidden="true"><path d="{{ $icons[$ikon] }}"/></svg>
                    </span>
                    <h3 class="mt-6 text-xl font-bold text-ink">{{ $judul }}</h3>
                    <p class="mt-2 leading-7 text-ink-soft">{{ $isi }}</p>
                    <span class="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-brand-700">
                        Selengkapnya <span aria-hidden="true" class="transition-transform group-hover:translate-x-1">→</span>
                    </span>
                </a>
            @endforeach
        </div>
    </div>
</section>

{{-- ============ KONTEN PUBLIK (dikelola pengurus) ============ --}}
@foreach ($konten as [$id, $ikon, $judul, $sub, $kosong])
    <section id="{{ $id }}" class="{{ $loop->even ? 'bg-brand-50/60' : 'bg-white' }} py-16 sm:py-20" aria-labelledby="judul-{{ $id }}">
        <div class="mx-auto grid max-w-7xl items-center gap-8 px-4 sm:px-6 md:grid-cols-[1fr_1.4fr] lg:px-8">
            <div class="reveal">
                <p class="eyebrow">{{ $sub }}</p>
                <h2 id="judul-{{ $id }}" class="section-title mt-3">{{ $judul }}</h2>
            </div>
            <div class="reveal flex items-start gap-4 rounded-3xl border border-dashed border-brand-300 bg-white p-6 sm:p-8">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-brand-100 text-brand-700">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6" aria-hidden="true"><path d="{{ $icons[$ikon] }}"/></svg>
                </span>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-brand-600">Segera diisi</p>
                    <p class="mt-1 leading-7 text-ink-soft">{{ $kosong }}</p>
                </div>
            </div>
        </div>
    </section>
@endforeach

{{-- ============ APLIKASI WARGA ============ --}}
<section id="aplikasi" class="bg-white py-20 sm:py-24" aria-labelledby="judul-aplikasi">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="reveal overflow-hidden rounded-[2rem] bg-gradient-to-br from-brand-700 to-brand-900 p-8 text-white sm:p-12">
            <div class="max-w-2xl">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-200">Aplikasi warga</p>
                <h2 id="judul-aplikasi" class="mt-3 text-3xl font-extrabold tracking-tight sm:text-4xl">
                    Warga RT 05? Yuk, pakai Layanan Pintar.
                </h2>
                <p class="mt-4 text-lg leading-8 text-brand-100">
                    Pengaduan, agenda, informasi iuran, dan kabar RT lebih mudah dipantau lewat aplikasi mobile.
                    Pendaftaran memakai kode registrasi dari pengurus, lalu disetujui pengurus sebelum akun aktif.
                </p>
                <p class="mt-6 inline-flex rounded-full bg-white/15 px-4 py-2 text-sm font-semibold">
                    Aplikasi sedang dikembangkan, segera hadir.
                </p>
            </div>

            <ul class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($layanan as [$ikon, $judul, $isi])
                    <li class="rounded-2xl bg-white/10 p-5 ring-1 ring-white/15">
                        <div class="flex items-center gap-3">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="size-6 text-brand-200" aria-hidden="true"><path d="{{ $icons[$ikon] }}"/></svg>
                            <h3 class="font-bold">{{ $judul }}</h3>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-brand-100">{{ $isi }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>

@endsection
