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

        // ADR43: "mawar8" dan "Mawar 8" satu kunci (spasi di antara huruf dan angka nama jalan).
        $jalanNorm = preg_replace('/([a-z])(\d)/', '$1 $2', preg_replace('/^(jalan|jln|jl)\s+/', '', $norm($jalan)));
        $nomorNorm = preg_replace('/^(no|nomor|nomer)\s+/', '', $norm($nomor));

        return implode('|', [$norm($blok), $jalanNorm, str_replace(' ', '', $nomorNorm)]);
    }

    /**
     * ADR43: tampilan nama jalan baku. Jalan RT 05 = Mawar 8–14: "jl. mawar8", "JALAN MAWAR 8" → "Mawar 8". Jalan lain hanya
     * dirapikan spasinya (tidak diubah isinya).
     */
    public static function street(string $jalan): string
    {
        $clean = trim(preg_replace('/\s+/', ' ', $jalan));
        if (preg_match('/^(?:jalan|jln\.?|jl\.?)?\s*mawar\s*(\d{1,2})$/i', $clean, $m)) {
            return 'Mawar '.(int) $m[1];
        }

        return $clean;
    }

    /** Kode rumah stabil untuk transparansi iuran (tanpa nama/alamat lengkap). */
    public static function code(?string $blok, string $nomor): string
    {
        $part = fn (?string $v) => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $v));

        return trim(($part($blok) !== '' ? $part($blok).'-' : 'R-').$part($nomor), '-');
    }
}
