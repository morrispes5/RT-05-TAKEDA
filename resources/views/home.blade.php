@extends('layouts.app')

@php
    $layanan = [
        [
            'judul' => 'Website publik',
            'untuk' => 'Untuk siapa saja',
            'isi' => 'Profil RT, fasilitas bersama, dan dokumentasi kegiatan. Bisa dibuka tanpa akun.',
            'siap' => true,
        ],
        [
            'judul' => 'Aplikasi warga',
            'untuk' => 'Untuk warga terdaftar',
            'isi' => 'Pengaduan, aspirasi, agenda kegiatan, dan status iuran rumah. Daftar memakai kode dari pengurus.',
            'siap' => false,
        ],
        [
            'judul' => 'Dashboard pengurus',
            'untuk' => 'Untuk pengurus RT',
            'isi' => 'Pendataan warga dan rumah, pencatatan iuran dan kas, serta pengelolaan isi website.',
            'siap' => false,
        ],
    ];

    // Urutan dan lebar kolom galeri (grid 12 kolom di layar md ke atas).
    $galeri = [
        ['sekretariat', 'md:col-span-7'],
        ['taman-bermain', 'md:col-span-5'],
        ['lapangan', 'md:col-span-4'],
        ['jalan', 'md:col-span-4'],
        ['ban-warna', 'md:col-span-4'],
        ['menara-air', 'md:col-span-4'],
        ['kubah-panjat', 'md:col-span-4'],
        ['papan-takeda', 'md:col-span-4'],
    ];

    $fasilitas = [
        ['Sekretariat RT 05/07', 'Pos tempat pengurus bertugas dan warga berkumpul, di sisi lapangan.'],
        ['Lapangan serbaguna', 'Lapangan beton untuk kegiatan warga. Bukan untuk sepak bola, sesuai papan di sekretariat.'],
        ['Taman bermain anak', 'Kubah panjat, perosotan, dan area bermain dari ban bekas.'],
        ['Area hijau', 'Rumput dan pepohonan di tepi lapangan.'],
    ];

    // Ditulis sesuai papan yang dipasang Pengurus RT 05/007.
    $peraturan = [
        'Buanglah sampah pada tempatnya.',
        'Cuci gelas, piring, sendok masing-masing setelah digunakan.',
        'Kembalikan barang yang digunakan ke tempat semula.',
        'Gunakan alat dan barang sesuai fungsinya.',
        'Jagalah kebersihan pos.',
    ];

    $fitur = [
        ['Pengaduan', 'Laporkan masalah lingkungan dengan foto. Nama bisa disembunyikan dari warga lain; pengurus tetap tahu siapa pelapornya.'],
        ['Aspirasi', 'Kirim kritik, saran, atau usulan dan pantau tanggapannya sampai selesai.'],
        ['Agenda', 'Lihat jadwal kegiatan RT dan tambahkan ke Google Calendar.'],
        ['Iuran', 'Cek status iuran rumah dan riwayat pembayaran sampai setahun ke belakang.'],
        ['Pengumuman', 'Informasi resmi pengurus yang tidak tenggelam di percakapan grup.'],
        ['Notifikasi', 'Pengingat agenda, iuran, dan perubahan status laporanmu.'],
    ];

    $besar = fn (string $slug) => asset("images/dokumentasi/{$slug}-".max($foto[$slug]['ukuran']).'.webp');
@endphp

@section('content')

{{-- Hero --}}
<section class="overflow-x-clip border-b border-garis">
    <div class="wrap grid items-center gap-14 py-12 sm:py-16 lg:grid-cols-12 lg:gap-10 lg:py-20">
        <div class="hero-teks lg:col-span-6">
            <h1 class="text-[clamp(3rem,9vw,6.5rem)] leading-[0.9] font-extrabold tracking-[-0.04em]">
                RT 05<br>Taman Kedaung
            </h1>
            <p class="mt-8 max-w-[34rem] text-lg text-abu sm:text-xl sm:leading-relaxed">
                Kabar lingkungan, fasilitas bersama, dan kegiatan warga RT 05 RW 07 Takeda, Ciputat.
                Terbuka untuk siapa saja, tanpa perlu daftar.
            </p>
            <div class="mt-10 flex flex-wrap items-center gap-x-8 gap-y-5">
                <a href="#lingkungan" class="tombol">Lihat lingkungan</a>
                <a href="#aplikasi" class="tautan">Kenali aplikasi warga</a>
            </div>
        </div>

        <figure class="pl-2.5 lg:col-span-6 lg:pl-8">
            <div class="hero-foto rel-kuning">
                <x-foto slug="gapura" :data="$foto['gapura']" utama
                        sizes="(min-width: 1024px) 34rem, 100vw"
                        class="aspect-[4/3] w-full rounded-md object-cover object-[50%_40%] lg:aspect-[6/5]" />
            </div>
            <figcaption class="mt-8 text-sm text-abu lg:mt-10">{{ $foto['gapura']['keterangan'] }}</figcaption>
        </figure>
    </div>
</section>

{{-- Layanan --}}
<section id="layanan" class="py-20 sm:py-24" aria-labelledby="judul-layanan">
    <div class="wrap">
        <div class="max-w-2xl">
            <h2 id="judul-layanan" class="judul-bagian">Satu sistem, tiga pintu</h2>
            <p class="mt-4 text-lg text-abu">
                Website ini bagian dari layanan digital RT 05. Informasi umum ada di sini; layanan pribadi
                seperti pengaduan dan iuran ada di aplikasi warga.
            </p>
        </div>

        <div class="mt-12 grid border-t-2 border-tinta md:grid-cols-3">
            @foreach ($layanan as $item)
                <div class="border-b border-garis py-8 md:border-b-0 md:border-l md:px-8 md:first:border-l-0 md:first:pl-0">
                    <h3 class="text-2xl font-bold tracking-[-0.01em]">{{ $item['judul'] }}</h3>
                    <p class="mt-1 font-medium text-biru">{{ $item['untuk'] }}</p>
                    <p class="mt-4 text-abu">{{ $item['isi'] }}</p>
                    <p class="mt-6 flex items-center gap-2 text-sm font-medium">
                        <span class="size-2.5 rounded-full {{ $item['siap'] ? 'bg-biru' : 'bg-kuning' }}" aria-hidden="true"></span>
                        {{ $item['siap'] ? 'Sudah bisa dipakai' : 'Sedang dikembangkan' }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>
</section>

{{-- Galeri lingkungan --}}
<section id="lingkungan" class="border-t border-garis py-20 sm:py-24" aria-labelledby="judul-lingkungan">
    <div class="wrap">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h2 id="judul-lingkungan" class="judul-bagian">Sudut-sudut RT 05</h2>
            <p class="text-abu">Difoto Kelompok 8 saat survei lapangan, Oktober 2026.</p>
        </div>

        <ul class="-mx-4 mt-10 flex snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto px-4 pb-2 sm:-mx-6 sm:scroll-px-6 sm:px-6 md:mx-0 md:grid md:grid-cols-12 md:gap-x-4 md:gap-y-8 md:overflow-visible md:px-0 md:pb-0">
            @foreach ($galeri as [$slug, $kolom])
                <li class="w-[82%] shrink-0 snap-start {{ $kolom }} md:w-auto">
                    <button type="button" class="block w-full cursor-zoom-in text-left" data-lightbox="galeri"
                            data-src="{{ $besar($slug) }}" data-alt="{{ $foto[$slug]['alt'] }}" data-caption="{{ $foto[$slug]['keterangan'] }}">
                        <x-foto :slug="$slug" :data="$foto[$slug]"
                                sizes="(min-width: 768px) 40vw, 82vw"
                                class="aspect-[4/3] w-full rounded-md object-cover md:aspect-auto md:h-64 lg:h-80" />
                        <span class="mt-3 block text-sm text-abu">{{ $foto[$slug]['keterangan'] }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
    </div>
</section>

{{-- Fasilitas --}}
<section id="fasilitas" class="bg-beton py-20 sm:py-24" aria-labelledby="judul-fasilitas">
    <div class="wrap grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-7">
            <h2 id="judul-fasilitas" class="judul-bagian">Fasilitas bersama</h2>
            <p class="mt-4 max-w-xl text-lg text-abu">Dipakai dan dirawat bersama oleh warga dan pengurus.</p>

            <dl class="mt-10 grid gap-x-10 sm:grid-cols-2">
                @foreach ($fasilitas as [$nama, $isi])
                    <div class="border-t border-garis py-5">
                        <dt class="font-display text-xl font-bold">{{ $nama }}</dt>
                        <dd class="mt-2 text-abu">{{ $isi }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="lg:col-span-5">
            <div class="rounded-md bg-kapur p-6 sm:p-8">
                <h3 class="text-2xl font-bold">Peraturan lapangan</h3>
                <ol class="mt-5 list-decimal space-y-2 pl-5 marker:font-semibold marker:text-biru">
                    @foreach ($peraturan as $aturan)
                        <li>{{ $aturan }}</li>
                    @endforeach
                </ol>
                <p class="mt-5 text-sm text-abu">Pengurus RT 05/007</p>
                <button type="button" class="tautan mt-6 text-left" data-lightbox="peraturan"
                        data-src="{{ $besar('peraturan-lapangan') }}" data-alt="{{ $foto['peraturan-lapangan']['alt'] }}"
                        data-caption="{{ $foto['peraturan-lapangan']['keterangan'] }}">
                    Lihat foto papan aslinya
                </button>
            </div>
        </div>
    </div>
</section>

{{-- Tentang --}}
<section id="tentang" class="py-20 sm:py-24" aria-labelledby="judul-tentang">
    <div class="wrap grid items-center gap-12 lg:grid-cols-12">
        <div class="lg:order-2 lg:col-span-7 lg:pl-6">
            <h2 id="judul-tentang" class="judul-bagian">Tentang RT 05</h2>
            <div class="mt-6 max-w-[62ch] space-y-4 text-lg text-abu">
                <p>
                    RT 05 berada di lingkungan RW 07 Taman Kedaung, Ciputat. Nama Takeda yang tertulis di gapura
                    dan papan sekretariat adalah singkatan dari Taman Kedaung.
                </p>
                <p>
                    Selama ini pengumuman, laporan warga, dan catatan iuran tersebar di grup percakapan dan catatan
                    yang terpisah. Layanan Pintar menyatukannya dalam satu sumber data: website untuk informasi
                    umum, aplikasi untuk warga, dan dashboard untuk pengurus.
                </p>
            </div>
        </div>
        <figure class="lg:order-1 lg:col-span-5">
            <x-foto slug="area-hijau" :data="$foto['area-hijau']" sizes="(min-width: 1024px) 28rem, 100vw"
                    class="aspect-[4/3] w-full rounded-md object-cover" />
            <figcaption class="mt-3 text-sm text-abu">{{ $foto['area-hijau']['keterangan'] }}</figcaption>
        </figure>
    </div>
</section>

{{-- Aplikasi warga --}}
<section id="aplikasi" class="bg-biru py-20 text-kapur sm:py-24" aria-labelledby="judul-aplikasi">
    <div class="wrap grid gap-12 lg:grid-cols-12">
        <div class="lg:col-span-5">
            <h2 id="judul-aplikasi" class="judul-bagian">Aplikasi warga sedang disiapkan</h2>
            <p class="mt-5 text-lg text-kapur/85">
                Layanan pribadi untuk warga RT 05 akan tersedia di aplikasi mobile. Akun dibuat memakai kode
                registrasi dari pengurus dan aktif setelah disetujui.
            </p>
            <p class="mt-6 text-lg">
                Butuh kode registrasi? Tanyakan ke pengurus di Sekretariat RT 05/07.
            </p>
        </div>

        <ul class="grid gap-x-10 sm:grid-cols-2 lg:col-span-7">
            @foreach ($fitur as [$nama, $isi])
                <li class="border-t border-kapur/25 py-5">
                    <h3 class="text-xl font-bold">{{ $nama }}</h3>
                    <p class="mt-1 text-kapur/80">{{ $isi }}</p>
                </li>
            @endforeach
        </ul>
    </div>
</section>

{{-- Lightbox foto --}}
<dialog id="lightbox" class="m-auto max-h-none max-w-none bg-transparent p-0 text-kapur" aria-label="Foto lingkungan">
    <figure class="flex h-dvh w-screen flex-col items-center justify-center gap-4 p-4 sm:p-10">
        <img data-lightbox-img alt="" class="max-h-[80dvh] w-auto max-w-full rounded-md object-contain">
        <figcaption data-lightbox-caption class="max-w-2xl text-center"></figcaption>
    </figure>
    <div class="absolute inset-x-0 top-0 flex justify-end gap-2 p-3 sm:p-5">
        <button type="button" data-lightbox-prev class="min-h-11 rounded-md bg-kapur/10 px-4 font-semibold hover:bg-kapur/20">Sebelumnya</button>
        <button type="button" data-lightbox-next class="min-h-11 rounded-md bg-kapur/10 px-4 font-semibold hover:bg-kapur/20">Berikutnya</button>
        <button type="button" data-lightbox-close class="min-h-11 rounded-md bg-kapur px-4 font-semibold text-tinta">Tutup</button>
    </div>
</dialog>

@endsection
