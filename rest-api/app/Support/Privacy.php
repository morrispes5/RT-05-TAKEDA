<?php

namespace App\Support;

use App\Models\Akun;

/**
 * Kebijakan Privasi aplikasi (ADR35). Teks lengkap ada di aplikasi mobile dan docs/PRIVACY_NOTICE.md; server hanya
 * menyimpan versi yang disetujui tiap akun. version() memilih versi sesuai aktivasi keuangan agar teks dan gate seragam.
 */
final class Privacy
{
    public const VERSION = '2026-10-25';

    public const FINANCE_VERSION = '2026-10-26';

    public const PANIC_VERSION = '2026-10-27';

    public static function version(): string
    {
        if (config('panic.enabled')) {
            return self::PANIC_VERSION;
        }

        return config('finance.v2') ? self::FINANCE_VERSION : self::VERSION;
    }

    public static function needsConsent(Akun $akun): bool
    {
        return $akun->privasi_versi !== self::version();
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
