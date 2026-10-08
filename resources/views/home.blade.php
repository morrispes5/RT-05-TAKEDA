@extends('layouts.app')

@php
    $layanan = [
        ['pengaduan', 'Pengaduan', 'Laporkan masalah lingkungan dengan foto. Nama bisa disembunyikan dari warga lain; pengurus tetap tahu pelapornya.', false],
        ['aspirasi', 'Aspirasi', 'Kirim kritik, saran, atau usulan dan pantau statusnya dari terkirim sampai selesai.', false],
        ['agenda', 'Agenda kegiatan', 'Lihat jadwal kegiatan RT dan tambahkan ke Google Calendar.', false],
        ['iuran', 'Iuran rumah', 'Cek status iuran Rp75.000 per rumah per bulan dan riwayat pembayaran setahun ke belakang.', true],
        ['pengumuman', 'Pengumuman', 'Informasi resmi pengurus yang tidak tenggelam di percakapan grup.', false],
        ['notifikasi', 'Notifikasi', 'Pengingat agenda, iuran, dan perubahan status laporanmu.', false],
    ];

    $fasilitas = [
        ['sekretariat', 'Sekretariat RT 05/07', 'Pos tempat pengurus bertugas dan warga berkumpul, di sisi lapangan.'],
        ['lapangan', 'Lapangan serbaguna', 'Lapangan beton untuk kegiatan warga. Bukan untuk sepak bola, sesuai papan di sekretariat.'],
        ['taman-bermain', 'Taman bermain anak', 'Kubah panjat, perosotan, dan area bermain dari ban bekas di bawah pohon rindang.'],
    ];

    // Ditulis sesuai papan yang dipasang Pengurus RT 05/007.
    $peraturan = [
        'Buanglah sampah pada tempatnya.',
        'Cuci gelas, piring, sendok masing-masing setelah digunakan.',
        'Kembalikan barang yang digunakan ke tempat semula.',
        'Gunakan alat dan barang sesuai fungsinya.',
        'Jagalah kebersihan pos.',
    ];

    // Kolase galeri: slug, kelas grid di layar md ke atas.
    $galeri = [
        ['jalan', 'md:col-span-5 md:row-span-2'],
        ['ban-warna', 'md:col-span-4'],
        ['papan-takeda', 'md:col-span-3'],
        ['kubah-panjat', 'md:col-span-3'],
        ['menara-air', 'md:col-span-4'],
        ['sekretariat', 'md:col-span-4'],
        ['lapangan', 'md:col-span-4'],
        ['taman-bermain', 'md:col-span-4'],
    ];

    $fitur = ['Pengaduan dengan foto', 'Aspirasi warga', 'Agenda dan kalender', 'Status iuran rumah', 'Pengumuman resmi', 'Notifikasi'];

    $besar = fn (string $slug) => asset("images/dokumentasi/{$slug}-".max($foto[$slug]['ukuran']).'.webp');
@endphp

@section('content')

{{-- Hero --}}
<section class="relative overflow-hidden bg-surface-sky">
    <div class="pointer-events-none absolute -top-40 -right-40 size-[34rem] rounded-full bg-sky-100" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -bottom-24 -left-24 size-72 rounded-full border-[3rem] border-sky-100" aria-hidden="true"></div>

    <div class="wrap relative grid items-center gap-14 py-14 sm:py-20 lg:grid-cols-12 lg:gap-10 lg:py-24">
        <div class="hero-masuk lg:col-span-6">
            <span class="rt-label">RT 05 RW 07 Takeda, Ciputat</span>
            <h1 class="mt-6 text-[clamp(2.75rem,6.2vw,4.5rem)] leading-[1.02] font-extrabold tracking-[-0.035em]">
                Lingkungan yang dirawat bersama warga
            </h1>
            <p class="mt-6 max-w-[34rem] text-lg leading-[1.7] text-ink-muted">
                Kabar lingkungan, fasilitas bersama, dan layanan warga RT 05 Taman Kedaung dalam satu tempat.
                Terbuka untuk siapa saja, tanpa perlu daftar.
            </p>
            <div class="mt-9 flex flex-wrap gap-3">
                <a href="#layanan" class="rt-btn rt-btn--primary">Lihat layanan warga</a>
                <a href="#galeri" class="rt-btn rt-btn--secondary">Jelajahi lingkungan</a>
            </div>
            <ul class="mt-10 flex flex-wrap gap-x-6 gap-y-3 text-[15px] font-medium text-ink">
                @foreach (['Terbuka tanpa akun', 'Foto asli lingkungan', 'Data pribadi tidak ditampilkan'] as $poin)
                    <li class="flex items-center gap-2">
                        <span class="grid size-6 place-items-center rounded-full bg-brand text-white"><x-ikon nama="cek" class="size-3.5" /></span>
                        {{ $poin }}
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="hero-masuk relative h-[24rem] sm:h-[30rem] lg:col-span-6 lg:h-[34rem]">
            <figure class="rt-photo absolute top-0 right-0 h-[82%] w-full sm:w-[80%]">
                <x-foto slug="gapura" :data="$foto['gapura']" utama sizes="(min-width: 1024px) 30rem, 90vw" class="size-full object-cover object-[50%_40%]" />
            </figure>
            <figure class="rt-photo absolute bottom-0 left-0 hidden h-[44%] w-[48%] border-8 border-surface-sky sm:block">
                <x-foto slug="sekretariat" :data="$foto['sekretariat']" utama sizes="16rem" class="size-full object-cover" />
            </figure>
            <div class="rt-float absolute top-[8%] left-0 sm:-left-2">
                <span class="grid size-11 flex-none place-items-center rounded-[var(--radius-sm)] bg-sky-100 text-brand"><x-ikon nama="pengaduan" class="size-5" /></span>
                <span><b class="block text-[15px] leading-5">Laporan warga dipantau</b><span class="block text-[13px] text-ink-muted">Diajukan, diproses, selesai</span></span>
            </div>
            <div class="rt-float absolute right-3 bottom-[6%] sm:right-0 sm:bottom-[22%]">
                <span class="grid size-11 flex-none place-items-center rounded-[var(--radius-sm)] bg-kuning text-ink"><x-ikon nama="iuran" class="size-5" /></span>
                <span><b class="block text-[15px] leading-5">Iuran Rp75.000</b><span class="block text-[13px] text-ink-muted">Per rumah per bulan</span></span>
            </div>
        </div>
    </div>
</section>

{{-- Layanan --}}
<section id="layanan" class="seksi bg-surface" aria-labelledby="judul-layanan">
    <div class="wrap">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div class="max-w-2xl">
                <span class="rt-label">Layanan warga</span>
                <h2 id="judul-layanan" class="rt-judul">Urusan RT, cukup dari satu aplikasi</h2>
                <p class="rt-lead">Layanan pribadi tersedia untuk warga terdaftar di aplikasi warga. Akun dibuat dengan kode dari pengurus dan aktif setelah disetujui.</p>
            </div>
            <a href="#aplikasi" class="rt-btn rt-btn--ghost">Tentang aplikasi warga</a>
        </div>

        <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($layanan as [$ikon, $judul, $isi, $sorot])
                <article class="rt-service {{ $sorot ? 'rt-service--sorot' : '' }}">
                    <span class="rt-ikon"><x-ikon :nama="$ikon" /></span>
                    <h3 class="text-xl leading-[1.3] font-bold tracking-[-0.01em]">{{ $judul }}</h3>
                    <p class="text-ink-muted">{{ $isi }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

{{-- Tentang --}}
<section id="tentang" class="seksi bg-surface-sky" aria-labelledby="judul-tentang">
    <div class="wrap grid items-center gap-14 lg:grid-cols-12">
        <div class="relative pb-10 lg:col-span-6 lg:pb-12">
            <figure class="rt-photo h-[20rem] w-[86%] sm:h-[26rem]">
                <x-foto slug="area-hijau" :data="$foto['area-hijau']" sizes="(min-width: 1024px) 30rem, 86vw" class="size-full object-cover" />
            </figure>
            <figure class="rt-photo absolute right-0 bottom-0 h-40 w-[48%] border-8 border-surface-sky sm:h-52">
                <x-foto slug="kubah-panjat" :data="$foto['kubah-panjat']" sizes="16rem" class="size-full object-cover" />
            </figure>
            <span class="absolute top-6 right-[8%] size-6 rounded-full bg-kuning" aria-hidden="true"></span>
        </div>

        <div class="lg:col-span-6">
            <span class="rt-label">Tentang RT 05</span>
            <h2 id="judul-tentang" class="rt-judul">Satu sumber informasi untuk warga Taman Kedaung</h2>
            <p class="rt-lead">
                RT 05 berada di lingkungan RW 07 Taman Kedaung, Ciputat. Nama Takeda di gapura dan papan sekretariat
                adalah singkatan dari Taman Kedaung. Selama ini pengumuman, laporan, dan catatan iuran tersebar di
                grup percakapan; Layanan Pintar menyatukannya.
            </p>
            <ul class="mt-8 grid gap-4">
                @foreach ([
                    'Informasi umum terbuka untuk siapa saja di website ini.',
                    'Layanan pribadi lewat akun warga yang disetujui pengurus.',
                    'Data rumah, keluarga, dan keuangan tidak dipublikasikan.',
                ] as $poin)
                    <li class="flex items-start gap-3">
                        <span class="mt-0.5 grid size-8 flex-none place-items-center rounded-[var(--radius-sm)] bg-sky-100 text-brand"><x-ikon nama="cek" class="size-4" /></span>
                        <span class="text-[17px]">{{ $poin }}</span>
                    </li>
                @endforeach
            </ul>
            <a href="#fasilitas" class="rt-btn rt-btn--primary mt-9">Lihat fasilitas</a>
        </div>
    </div>
</section>

{{-- Fasilitas --}}
<section id="fasilitas" class="seksi bg-surface" aria-labelledby="judul-fasilitas">
    <div class="wrap">
        <div class="max-w-2xl">
            <span class="rt-label">Fasilitas bersama</span>
            <h2 id="judul-fasilitas" class="rt-judul">Dirawat bersama, dipakai bersama</h2>
        </div>

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($fasilitas as [$slug, $nama, $isi])
                <article>
                    <figure class="rt-photo aspect-[4/3]">
                        <x-foto :slug="$slug" :data="$foto[$slug]" sizes="(min-width: 1024px) 18rem, (min-width: 640px) 45vw, 100vw" class="size-full object-cover" />
                    </figure>
                    <h3 class="mt-5 text-xl font-bold tracking-[-0.01em]">{{ $nama }}</h3>
                    <p class="mt-2 text-ink-muted">{{ $isi }}</p>
                </article>
            @endforeach

            <article class="rt-service rt-service--sorot">
                <span class="rt-ikon"><x-ikon nama="pengumuman" /></span>
                <h3 class="text-xl font-bold tracking-[-0.01em]">Peraturan lapangan</h3>
                <ol class="list-decimal space-y-1.5 pl-5 text-[15px] marker:font-semibold marker:text-brand">
                    @foreach ($peraturan as $aturan)
                        <li>{{ $aturan }}</li>
                    @endforeach
                </ol>
                <p class="text-sm text-ink-muted">Pengurus RT 05/007</p>
                <button type="button" class="justify-self-start font-semibold text-brand underline decoration-2 underline-offset-4 hover:decoration-kuning" data-lightbox="peraturan"
                        data-src="{{ $besar('peraturan-lapangan') }}" data-alt="{{ $foto['peraturan-lapangan']['alt'] }}" data-caption="{{ $foto['peraturan-lapangan']['keterangan'] }}">
                    Lihat papan aslinya
                </button>
            </article>
        </div>
    </div>
</section>

{{-- Galeri --}}
<section id="galeri" class="seksi bg-surface-sky" aria-labelledby="judul-galeri">
    <div class="wrap">
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div class="max-w-2xl">
                <span class="rt-label">Galeri lingkungan</span>
                <h2 id="judul-galeri" class="rt-judul">Sudut-sudut RT 05</h2>
            </div>
            <p class="flex items-center gap-2 text-ink-muted"><x-ikon nama="foto" class="size-5 text-brand" /> Difoto Kelompok 8, Oktober 2026</p>
        </div>

        <ul class="mt-12 grid grid-cols-2 gap-3 sm:gap-4 md:auto-rows-[15rem] md:grid-cols-12">
            @foreach ($galeri as $i => [$slug, $kelas])
                <li class="{{ $i === 0 || $loop->last ? 'col-span-2' : '' }} {{ $kelas }}">
                    <button type="button" class="rt-photo block h-44 w-full cursor-zoom-in text-left sm:h-56 md:h-full {{ $i === 0 ? 'h-64 sm:h-80' : '' }}" data-lightbox="galeri"
                            data-src="{{ $besar($slug) }}" data-alt="{{ $foto[$slug]['alt'] }}" data-caption="{{ $foto[$slug]['keterangan'] }}">
                        <x-foto :slug="$slug" :data="$foto[$slug]" sizes="(min-width: 768px) 30vw, 50vw" class="size-full object-cover transition-transform duration-300 hover:scale-[1.03]" />
                        <span class="absolute bottom-3 left-3 hidden max-w-[calc(100%-1.5rem)] rounded-full bg-surface px-3 py-1.5 text-[13px] leading-4 font-medium text-ink sm:block">{{ $foto[$slug]['label'] }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
    </div>
</section>

{{-- Aplikasi warga --}}
<section id="aplikasi" class="seksi bg-surface" aria-labelledby="judul-aplikasi">
    <div class="wrap">
        <div class="relative overflow-hidden rounded-[var(--radius-lg)] bg-brand-strong px-6 py-12 text-white sm:px-12 lg:px-16 lg:py-16">
            <div class="pointer-events-none absolute -top-24 -right-16 size-80 rounded-full bg-brand" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -right-10 -bottom-28 size-64 rounded-full border-[2.5rem] border-sky-400/25" aria-hidden="true"></div>

            <div class="relative grid items-center gap-12 lg:grid-cols-12">
                <div class="lg:col-span-6">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-[13px] font-semibold text-sky-100">
                        <span class="size-2 rounded-full bg-kuning"></span> Sedang dikembangkan
                    </span>
                    <h2 id="judul-aplikasi" class="mt-5 text-[clamp(2rem,4vw,2.75rem)] leading-[1.08] font-extrabold tracking-[-0.025em]">Aplikasi warga RT 05 sedang disiapkan</h2>
                    <p class="mt-5 max-w-xl text-lg leading-[1.7] text-sky-100">
                        Pengaduan, aspirasi, agenda, dan status iuran rumah akan tersedia di aplikasi mobile.
                        Butuh kode registrasi? Tanyakan ke pengurus di Sekretariat RT 05/07.
                    </p>
                    <a href="#layanan" class="rt-btn rt-btn--inverse mt-8">Lihat semua layanan</a>
                </div>
                <ul class="grid gap-3 sm:grid-cols-2 lg:col-span-6">
                    @foreach ($fitur as $f)
                        <li class="flex items-center gap-3 rounded-[var(--radius-md)] bg-white/[0.08] px-4 py-4 font-medium">
                            <span class="grid size-8 flex-none place-items-center rounded-full bg-sky-200 text-brand-strong"><x-ikon nama="cek" class="size-4" /></span>
                            {{ $f }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- Lightbox foto --}}
<dialog id="lightbox" class="m-auto max-h-none max-w-none bg-transparent p-0 text-white" aria-label="Foto lingkungan">
    <figure class="flex h-dvh w-screen flex-col items-center justify-center gap-4 p-4 sm:p-10">
        <img data-lightbox-img alt="" class="max-h-[80dvh] w-auto max-w-full rounded-[var(--radius-lg)] object-contain">
        <figcaption data-lightbox-caption class="max-w-2xl text-center"></figcaption>
    </figure>
    <div class="absolute inset-x-0 top-0 flex justify-end gap-2 p-3 sm:p-5">
        <button type="button" data-lightbox-prev class="min-h-11 rounded-full bg-white/10 px-5 font-semibold hover:bg-white/20">Sebelumnya</button>
        <button type="button" data-lightbox-next class="min-h-11 rounded-full bg-white/10 px-5 font-semibold hover:bg-white/20">Berikutnya</button>
        <button type="button" data-lightbox-close class="min-h-11 rounded-full bg-white px-5 font-semibold text-ink">Tutup</button>
    </div>
</dialog>

@endsection
