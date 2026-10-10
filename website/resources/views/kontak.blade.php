@extends('layouts.app')
@section('title', 'Kontak & Informasi Lingkungan — RT 05 Takeda')
@section('content')
<section class="wrap page-intro"><p class="breadcrumb"><a href="/">Beranda</a> / Kontak</p><h1>Informasi yang jelas,<br>dari pengurus lingkungan.</h1><p>Kenali sekretariat RT 05 RW 07<br>Taman Kedaung, Ciputat.</p></section>
<section class="wrap contact-layout"><figure><x-foto slug="sekretariat" :data="$foto['sekretariat']" :utama="true" sizes="(max-width: 768px) 92vw, 50vw" /><figcaption>{{ $foto['sekretariat']['keterangan'] }}</figcaption></figure><div><p class="section-label">Kontak pengurus</p><h2>Kanal resmi<br>sedang dilengkapi.</h2><p>Informasi kontak akan diumumkan pengurus.</p><dl class="contact-details"><div><dt>Lingkungan</dt><dd>RT 05 RW 07 Taman Kedaung, Ciputat</dd></div><div><dt>Telepon / WhatsApp</dt><dd>Belum diumumkan</dd></div><div><dt>Jam pelayanan</dt><dd>Menunggu informasi pengurus</dd></div></dl><p class="small-note">Belum tersedia formulir layanan pada website. Layanan pribadi warga direncanakan melalui aplikasi mobile.</p></div></section>
<section class="wrap care-note"><x-ikon nama="buku" /><div><h2>Sambil mengenal lingkungan.</h2><p>Temukan foto fasilitas dan bacaan praktis yang dapat diakses tanpa akun.</p></div><a class="text-link" href="/artikel">Jelajahi bacaan <x-ikon nama="panah" /></a></section>
@endsection
