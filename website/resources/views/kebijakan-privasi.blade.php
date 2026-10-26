@extends('layouts.app')
@section('title', 'Kebijakan Privasi — RT05 TAKEDA')
@section('description', 'Kebijakan Privasi aplikasi dan website RT05 TAKEDA: data yang dipakai, siapa yang dapat melihatnya, penyimpanan, dan hak warga RT 05 RW 07 Taman Kedaung.')
@section('content')
<article class="reading-page">
<header class="wrap reading-header"><p class="breadcrumb"><a href="/">Beranda</a> / Kebijakan Privasi</p><p class="section-label">Aplikasi & website RT05 TAKEDA</p><h1>Kebijakan Privasi.</h1><p>Data apa yang dipakai aplikasi RT05 TAKEDA, siapa yang dapat melihatnya, dan apa hak Anda sebagai warga.</p><span class="editorial-label">{{ $privasi['label'] }} · Data dan privasi warga</span></header>
<div class="wrap reading-layout"><aside><h2>Isi kebijakan</h2><nav aria-label="Daftar isi Kebijakan Privasi">@foreach($privasi['bagian'] as $bagian)<a href="#bagian-{{ $loop->iteration }}">{{ $bagian['judul'] }}</a>@endforeach</nav><a href="/aplikasi" class="text-link">Unduh aplikasi <x-ikon nama="panah" /></a></aside>
<div class="prose">@foreach($privasi['bagian'] as $bagian)<section id="bagian-{{ $loop->iteration }}"><h2>{{ $bagian['judul'] }}</h2><ul class="bullets">@foreach($bagian['butir'] as $butir)<li>{{ $butir }}</li>@endforeach</ul></section>@endforeach</div></div>
</article>
@endsection
