@extends('layouts.app')
@section('title', 'Profil RT — RT 05 Takeda')
@section('content')
<section class="wrap page-intro"><p class="breadcrumb"><a href="/">Beranda</a> / Profil RT</p><h1>Kenal tempatnya.<br>Kenal lingkungannya.</h1><p>RT 05 RW 07 Taman Kedaung, Ciputat.<br>Sebuah lingkungan dengan ruang untuk bertemu.</p></section>
<div class="wrap profile-panorama"><x-foto slug="jalan" :data="$foto['jalan']" :utama="true" sizes="92vw" /><span>Jalan lingkungan RT 05</span></div>
<section class="wrap section intro-grid"><div><p class="section-label">Nama yang dekat</p><h2>Taman Kedaung.<br>Kita menyebutnya Takeda.</h2></div><div class="intro-copy"><p>Takeda merupakan singkatan dari Taman Kedaung. Website ini memperkenalkan lingkungan RT 05 RW 07 di Ciputat melalui foto asli, informasi fasilitas, dan bacaan yang bermanfaat untuk keseharian.</p><p>Gapura menyambut di pintu masuk. Sekretariat, lapangan, dan taman bermain menjadi bagian dari ruang bersama di lingkungan ini.</p><a href="/dokumentasi" class="text-link">Lihat dokumentasi lingkungan <x-ikon nama="panah" /></a></div></section>
<section class="soft-section"><div class="wrap section"><div class="section-heading"><div><p class="section-label">Pengurus lingkungan</p><h2>Orang di balik<br>pelayanan RT.</h2></div><p>Informasi kepengurusan dilengkapi<br>secara bertahap bersama pengurus.</p></div><div class="people-grid">
@foreach([['Ketua RT', 'Agus Ferdiansyah', 'Ketua RT 05 RW 07 Taman Kedaung.'], ['Sekretaris RT', 'Nama belum dipublikasikan', 'Identitas sekretaris belum ditampilkan pada website.'], ['Bendahara RT', 'Nama belum dipublikasikan', 'Identitas bendahara belum ditampilkan pada website.']] as [$role, $name, $note])
<div class="person"><x-ikon nama="warga" /><p>{{ $role }}</p><h3>{{ $name }}</h3><small>{{ $note }}</small></div>
@endforeach
</div></div></section>
<section class="wrap section intro-grid"><h2>Yang kita<br>rawat bersama.</h2><div class="intro-copy"><p>Kebersihan, kepedulian pada tetangga, dan penggunaan fasilitas dengan tertib bisa dimulai dari hal sederhana: mengembalikan barang setelah dipakai dan meninggalkan ruang bersama dalam keadaan bersih.</p><p>Ini adalah ajakan dalam bacaan website, bukan rumusan visi atau peraturan resmi baru.</p><a href="/fasilitas" class="text-link">Kenali ruang bersama <x-ikon nama="panah" /></a></div></section>
@endsection
