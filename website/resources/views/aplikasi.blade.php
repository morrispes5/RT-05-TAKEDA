@extends('layouts.app')
@section('title', 'Unduh Aplikasi — RT05 TAKEDA')
@section('description', 'Unduh aplikasi Android resmi RT05 TAKEDA untuk warga RT 05 RW 07 Taman Kedaung: iuran rumah, pengaduan, aspirasi, agenda, dan notifikasi dari pengurus.')
@section('content')
<article class="reading-page">
<header class="wrap reading-header"><p class="breadcrumb"><a href="/">Beranda</a> / Aplikasi</p><p class="section-label">Aplikasi warga · Android</p><h1>Aplikasi RT05 TAKEDA untuk warga.</h1><p>Lihat tagihan iuran rumah, kirim pengaduan dan aspirasi, ikuti agenda, dan terima kabar dari pengurus langsung di HP Anda.</p><span class="editorial-label">{{ ($apk || empty($halamanUnduh)) ? 'Unduh hanya dari halaman resmi ini' : 'Unduh dari server RT' }} · Gratis · Tanpa pembayaran online</span></header>
<div class="wrap reading-layout"><aside><h2>Di halaman ini</h2><nav aria-label="Daftar isi halaman aplikasi"><a href="#unduh">Unduh aplikasi</a><a href="#pasang">Cara memasang</a><a href="#izin">Izin yang diminta</a><a href="#aman">Memastikan berkas asli</a></nav><a href="/kebijakan-privasi" class="text-link">Kebijakan Privasi <x-ikon nama="panah" /></a></aside>
<div class="prose">
<section id="unduh"><h2>Unduh aplikasi</h2>
@if($apk)
<p class="lead">Versi {{ $apk['versi'] }} (build {{ $apk['build'] }})@if($apk['tanggal'] !== '') · dirilis {{ \Illuminate\Support\Carbon::parse($apk['tanggal'])->timezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H.i') }} WIB @endif</p>
@foreach($apk['berkas'] as $berkas)
<div class="download-card">
<p><strong>{{ $berkas['abi'] === 'arm64-v8a' ? 'Untuk sebagian besar HP Android (64-bit)' : 'Untuk HP Android lama (32-bit)' }}</strong> · {{ number_format($berkas['ukuran'] / 1048576, 1, ',', '.') }} MB</p>
<a class="button {{ $berkas['abi'] === 'arm64-v8a' ? 'primary' : '' }}" href="{{ $berkas['url'] }}" download>Unduh {{ $berkas['abi'] === 'arm64-v8a' ? '64-bit' : '32-bit' }} <x-ikon nama="panah" /></a>
<p class="small-note">SHA-256: <code>{{ $berkas['sha256'] }}</code></p>
</div>
@endforeach
<p>Bingung memilih? Coba versi 64-bit dulu. Bila HP menolak memasang (“aplikasi tidak kompatibel”), pakai versi 32-bit.</p>
@if($apk['catatan'] !== [])
<h3>Yang baru di versi ini</h3><ul class="bullets">@foreach($apk['catatan'] as $catatan)<li>{{ $catatan }}</li>@endforeach</ul>
@endif
@elseif($halamanUnduh)
<p class="lead">Berkas aplikasi ada di server RT.</p><p>Halaman ini pratinjau website dan tidak menyimpan berkas aplikasi. Unduh dari halaman resmi di server RT. Jangan memasang aplikasi “RT05 TAKEDA” dari tautan lain.</p>
<div class="download-card"><a class="button primary" href="{{ $halamanUnduh }}">Buka halaman unduh <x-ikon nama="panah" /></a></div>
@else
<p class="lead">Aplikasi sedang disiapkan.</p><p>Tautan unduhan akan diumumkan pengurus RT melalui grup warga dan halaman ini. Jangan memasang aplikasi “RT05 TAKEDA” dari tautan lain.</p>
@endif
</section>
<section id="pasang"><h2>Cara memasang</h2>
<ol class="steps">
<li>@if(! $apk && $halamanUnduh)Buka <a href="{{ $halamanUnduh }}">halaman unduh resmi</a> dari HP Android, lalu ketuk tombol <strong>Unduh</strong>.@else Buka halaman ini dari HP Android, lalu ketuk tombol <strong>Unduh</strong>.@endif</li>
<li>Setelah unduhan selesai, ketuk berkas <strong>rt05-takeda-….apk</strong> di notifikasi atau folder Unduhan.</li>
<li>Android akan bertanya apakah browser boleh memasang aplikasi dari luar Play Store. Ketuk <strong>Setelan</strong>, aktifkan <strong>Izinkan dari sumber ini</strong>, lalu kembali. Izin ini wajar untuk aplikasi yang tidak dipasang lewat Play Store.</li>
<li>Bila Google Play Protect menawarkan pemindaian, pilih <strong>Pindai aplikasi</strong>, lalu ketuk <strong>Pasang</strong>.</li>
<li>Buka aplikasi, lalu daftar atau masuk. Akun baru aktif setelah disetujui pengurus.</li>
</ol>
<p>Untuk memperbarui, unduh versi terbaru dari @if(! $apk && $halamanUnduh)<a href="{{ $halamanUnduh }}">halaman unduh resmi</a>@else{{ 'halaman ini' }}@endif dan pasang di atas versi lama. Data akun Anda tetap tersimpan.</p>
</section>
<section id="izin"><h2>Izin yang diminta</h2>
<ul class="bullets">
<li><strong>Internet</strong>: untuk mengambil data iuran, agenda, dan laporan dari server RT.</li>
<li><strong>Kamera</strong>: hanya saat Anda memotret foto pengaduan atau bukti transfer. Anda juga bisa memilih foto dari galeri.</li>
<li><strong>Notifikasi</strong>: untuk kabar dari pengurus dan pengingat iuran. Boleh ditolak.</li>
</ul>
<p>Aplikasi tidak meminta lokasi, kontak, rekening bank, atau akses SMS, dan tidak ada pembayaran di dalam aplikasi.</p>
</section>
<section id="aman"><h2>Memastikan berkas asli</h2>
@if(! $apk && $halamanUnduh)
<p>Berkas resmi tersedia di <a href="{{ $halamanUnduh }}">halaman unduh server RT</a>. Kode SHA-256 di halaman itu adalah “sidik jari” berkas. Bila Anda mengirim berkas ke orang lain, mereka bisa mencocokkannya dengan kode di halaman itu.</p>
@else
<p>Berkas resmi hanya tersedia di halaman ini. Kode SHA-256 di atas adalah “sidik jari” berkas. Bila Anda mengirim berkas ke orang lain, mereka bisa mencocokkannya dengan kode di halaman ini.</p>
@endif
<div class="source-note"><h2>Masih ragu?</h2><p>Tanyakan langsung kepada pengurus RT 05 sebelum memasang. Pengurus tidak pernah meminta kata sandi, kode OTP, atau transfer uang lewat aplikasi.</p><a href="/kontak">Kontak pengurus <x-ikon nama="panah" /></a></div>
</section>
</div></div>
</article>
@endsection
