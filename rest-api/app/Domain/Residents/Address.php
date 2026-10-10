<?php

namespace App\Domain\Residents;

use Illuminate\Support\Str;

/** Normalisasi alamat rumah agar "Jl. Melati 5" dan "jalan melati no 5" menjadi satu kunci. */
final class Address
{
    public static function key(?string $blok, string $jalan, string $nomor): string
    {
        $norm = fn (?string $v) => trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]/', ' ',
            Str::of((string) $v)->lower()->ascii()->toString())));

        $jalanNorm = preg_replace('/^(jalan|jln|jl)\s+/', '', $norm($jalan));
        $nomorNorm = preg_replace('/^(no|nomor|nomer)\s+/', '', $norm($nomor));

        return implode('|', [$norm($blok), $jalanNorm, str_replace(' ', '', $nomorNorm)]);
    }

    /** Kode rumah stabil untuk transparansi iuran (tanpa nama/alamat lengkap). */
    public static function code(?string $blok, string $nomor): string
    {
        $part = fn (?string $v) => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $v));

        return trim(($part($blok) !== '' ? $part($blok).'-' : 'R-').$part($nomor), '-');
    }
}
