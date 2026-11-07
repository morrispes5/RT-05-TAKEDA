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
     * ADR58: unit rumah = blok + nomor, tanpa jalan. Di data RT (187 rumah, D1–D7) blok + nomor unik, sedangkan jalan
     * sering keliru (satu blok melintasi beberapa jalan). "Blok D 5" = "d5", "021" = "21". Null bila blok/nomor kosong.
     *
     * @return array{0:string,1:string}|null
     */
    public static function unit(?string $blok, string $nomor): ?array
    {
        [$b, , $n] = explode('|', self::key($blok, 'x', $nomor));
        $b = str_replace(' ', '', (string) preg_replace('/^blok\s*/', '', $b));
        if ($b === '' || $n === '') {
            return null;
        }
        $n = ltrim($n, '0');

        return [$b, $n === '' ? '0' : $n];
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
