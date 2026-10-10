<?php

namespace Tests\Support;

use App\Domain\Identity\Registration;
use App\Domain\Identity\RegistrationToken;
use App\Models\Akun;
use Laravel\Sanctum\Sanctum;

/** Fixture sintetis untuk tes (nama "Uji", alamat fiktif). */
trait Fixtures
{
    protected string $tokenCode;

    protected function freshToken(): string
    {
        return $this->tokenCode = RegistrationToken::rotate(null, 'tes');
    }

    protected function warga(string $nomor = '1', array $overrides = []): Akun
    {
        $code = $this->tokenCode ?? $this->freshToken();

        return Registration::primary(array_merge([
            'email' => "uji$nomor@contoh.test",
            'password' => 'Rahasia123',
            'nama_kepala' => "Uji Kepala $nomor",
            'whatsapp' => null,
            'blok' => 'Z',
            'jalan' => 'Jl. Uji',
            'nomor' => $nomor,
            'jenis_hunian' => 'pemilik',
            'anggota' => [['nama' => "Uji Anak $nomor", 'hubungan_keluarga' => 'Anak']],
        ], $overrides), $code);
    }

    protected function pengurus(string $email = 'pengurus@contoh.test'): Akun
    {
        $akun = new Akun(['nama' => 'Uji Pengurus', 'email' => $email, 'password_hash' => 'Rahasia123', 'jenis' => 'utama', 'status' => 'aktif']);
        $akun->peran = 'pengurus';
        $akun->save();

        return $akun;
    }

    protected function as(Akun $akun): static
    {
        Sanctum::actingAs($akun);

        return $this;
    }
}
