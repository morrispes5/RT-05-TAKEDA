@extends('layouts.app')
@section('title', 'Sarana & Prasarana — RT 05 Takeda')
@section('content')
<section class="wrap page-intro"><p class="breadcrumb"><a href="/">Beranda</a> / Fasilitas</p><h1>Ruang yang menjadi<br>bagian dari keseharian.</h1><p>Kenali sarana dan sudut lingkungan RT 05<br>melalui dokumentasi asli Taman Kedaung.</p></section>
<div class="wrap facility-stories">
@foreach(['lapangan' => ['Lapangan serbaguna', 'Ruang terbuka dengan permukaan beton di lingkungan RT 05. Menara air dan sekretariat tampak di sisinya. Gunakan fasilitas dengan memperhatikan peraturan yang terpasang.'], 'taman-bermain' => ['Bermain di bawah pepohonan', 'Taman bermain dengan kubah panjat, perosotan, dan area ban berwarna. Mari dampingi anak saat bermain dan jaga kebersihan area setelah digunakan.'], 'sekretariat' => ['Sekretariat RT 05/07', 'Bangunan dengan rangka kuning dan warna biru yang khas. Sekretariat menjadi tempat pengurus dan warga bertemu. Informasi jam pelayanan akan dilengkapi pengurus.'], 'area-hijau' => ['Area hijau', 'Rumput dan pepohonan menghiasi tepi lapangan, berdekatan dengan rumah-rumah di lingkungan. Ruang ini ikut memberi suasana teduh pada area bersama.']] as $slug => [$title, $description])
<section id="{{ $slug }}" class="facility-story"><x-foto :slug="$slug" :data="$foto[$slug]" :utama="$loop->first" sizes="(max-width: 768px) 92vw, 55vw" /><div><p class="section-label">{{ $foto[$slug]['label'] }}</p><h2>{{ $title }}</h2><p>{{ $description }}</p><a href="/dokumentasi#{{ $slug }}" class="text-link">Lihat dokumentasi <x-ikon nama="foto" /></a></div></section>
@endforeach
</div>
<section class="wrap care-note"><x-ikon nama="rumah" /><div><h2>Nyaman dipakai, bersih ditinggalkan.</h2><p>Buang sampah pada tempatnya, gunakan barang sesuai fungsinya, dan kembalikan setelah selesai. Perhatikan papan peraturan di lokasi.</p></div><a href="/dokumentasi#peraturan-lapangan" class="text-link">Lihat papan peraturan <x-ikon nama="panah" /></a></section>
@endsection
