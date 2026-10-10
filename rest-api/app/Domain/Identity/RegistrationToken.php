<?php

namespace App\Domain\Identity;

use App\Models\Akun;
use App\Models\TokenRegistrasi;
use App\Support\Audit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Token registrasi RT 6 digit (BR03/BR04): acak kriptografis, string agar nol di depan tetap,
 * satu token aktif, rotasi mencabut token lama. Disimpan sebagai ciphertext (lihat/salin
 * pengurus) + HMAC digest (verifikasi). Bukan OTP login, bukan API token, tidak memberi hak admin.
 */
final class RegistrationToken
{
    public static function digest(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    public static function active(): ?TokenRegistrasi
    {
        return TokenRegistrasi::where('aktif', true)
            ->where(fn ($q) => $q->whereNull('berlaku_sampai')->orWhere('berlaku_sampai', '>', now()))
            ->first();
    }

    /** Rotasi: cabut token aktif lalu buat token baru, atomik. Mengembalikan kode plaintext. */
    public static function rotate(?Akun $actor, string $reason = 'manual'): string
    {
        return DB::transaction(function () use ($actor, $reason) {
            DB::table('token_registrasi')->where('aktif', true)->lockForUpdate()->get();
            DB::table('token_registrasi')->where('aktif', true)->update(['aktif' => false, 'dicabut_at' => now()]);

            $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
            $token = TokenRegistrasi::create([
                'dibuat_oleh_id' => $actor?->id,
                'kode_terenkripsi' => Crypt::encryptString($code),
                'kode_digest' => self::digest($code),
                'aktif' => true,
                'berlaku_sampai' => now()->addDays(8),
            ]);

            // Audit tanpa nilai token.
            Audit::record($actor, 'token_registrasi.dirotasi', 'token_registrasi', $token->id, ['alasan' => $reason]);

            return $code;
        });
    }

    /** Mencocokkan kode terhadap token aktif. Mengembalikan token atau null (pesan generik di pemanggil). */
    public static function verify(string $code): ?TokenRegistrasi
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $token = self::active();

        return $token && hash_equals($token->kode_digest, self::digest($code)) ? $token : null;
    }

    public static function reveal(TokenRegistrasi $token): string
    {
        return Crypt::decryptString($token->kode_terenkripsi);
    }
}
