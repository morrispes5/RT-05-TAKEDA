<?php

namespace App\Support;

use App\Models\Akun;

/**
 * Kebijakan Privasi aplikasi (ADR35). Teks lengkap ada di aplikasi mobile dan docs/PRIVACY_NOTICE.md; server hanya
 * menyimpan versi yang disetujui tiap akun. Naikkan VERSION bila isi kebijakan berubah agar warga diminta setuju lagi.
 */
final class Privacy
{
    public const VERSION = '2026-10-21';

    public static function needsConsent(Akun $akun): bool
    {
        return $akun->privasi_versi !== self::VERSION;
    }

    /** Tampilan tersamar untuk NIK/KK: 4 digit awal dan 4 digit akhir. Nilai utuh tidak pernah dikirim. */
    public static function mask(?string $digits): ?string
    {
        if ($digits === null || $digits === '') {
            return null;
        }

        return strlen($digits) <= 8 ? str_repeat('•', strlen($digits)) : substr($digits, 0, 4).str_repeat('•', strlen($digits) - 8).substr($digits, -4);
    }
}
