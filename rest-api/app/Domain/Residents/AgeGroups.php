<?php

namespace App\Domain\Residents;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * ADR42: kelompok umur warga untuk statistik pengurus. Dasar: siklus hidup Cek Kesehatan Gratis Kemenkes (balita/prasekolah,
 * usia sekolah & remaja, dewasa 18–59, lansia ≥60 sesuai UU 13/1998), dirinci untuk RT: anak-anak dipisah dari remaja dan lansia
 * 70+ dipisah. Umur penuh dihitung per tanggal hari ini di WIB; tidak disimpan.
 */
final class AgeGroups
{
    /** @var list<array{kode: string, label: string, min: int, max: ?int}> */
    public const GROUPS = [
        ['kode' => 'balita', 'label' => 'Balita', 'min' => 0, 'max' => 5],
        ['kode' => 'anak', 'label' => 'Anak-anak', 'min' => 6, 'max' => 12],
        ['kode' => 'remaja', 'label' => 'Remaja', 'min' => 13, 'max' => 17],
        ['kode' => 'dewasa', 'label' => 'Dewasa', 'min' => 18, 'max' => 59],
        ['kode' => 'lansia', 'label' => 'Lansia', 'min' => 60, 'max' => 69],
        ['kode' => 'lansia_70', 'label' => 'Lansia 70+', 'min' => 70, 'max' => null],
    ];

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now('Asia/Jakarta')->startOfDay();
    }

    /** Umur dalam tahun penuh pada $today; null bila tanggal lahir kosong. */
    public static function age(?DateTimeInterface $lahir, ?CarbonImmutable $today = null): ?int
    {
        if ($lahir === null) {
            return null;
        }
        $born = CarbonImmutable::parse($lahir->format('Y-m-d'), 'Asia/Jakarta');

        return max(0, (int) $born->diffInYears($today ?? self::today(), false));
    }

    public static function group(?int $age): ?string
    {
        if ($age === null) {
            return null;
        }
        foreach (self::GROUPS as $g) {
            if ($age >= $g['min'] && ($g['max'] === null || $age <= $g['max'])) {
                return $g['kode'];
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_column(self::GROUPS, 'kode');
    }
}
