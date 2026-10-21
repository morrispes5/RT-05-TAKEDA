<?php

namespace App\Support;

use App\Models\Akun;
use Illuminate\Support\Facades\DB;

/**
 * Pengaturan aplikasi yang dapat diubah pengurus (tabel system_settings, ADR35). Nilai disimpan sebagai JSON per kunci
 * dengan versi naik tiap perubahan dan teraudit. Nomor kontak tidak disimpan di Git: diisi pengurus lewat aplikasi.
 */
final class Settings
{
    public const CONTACTS = 'kontak.pengurus';

    /** @return array{bendahara_whatsapp: ?string} */
    public static function contacts(): array
    {
        $row = DB::table('system_settings')->where('key', self::CONTACTS)->first();
        $value = $row ? json_decode($row->value, true) : [];

        return ['bendahara_whatsapp' => $value['bendahara_whatsapp'] ?? null];
    }

    /** @param array{bendahara_whatsapp: ?string} $contacts */
    public static function putContacts(Akun $actor, array $contacts): array
    {
        $value = ['bendahara_whatsapp' => self::normalizePhone($contacts['bendahara_whatsapp'] ?? null)];
        DB::transaction(function () use ($actor, $value) {
            $current = DB::table('system_settings')->where('key', self::CONTACTS)->lockForUpdate()->first();
            $json = json_encode($value, JSON_UNESCAPED_UNICODE);
            if ($current) {
                DB::table('system_settings')->where('key', self::CONTACTS)
                    ->update(['value' => $json, 'version' => $current->version + 1, 'updated_by' => $actor->id, 'updated_at' => now()]);
            } else {
                DB::table('system_settings')->insert(['key' => self::CONTACTS, 'value' => $json, 'version' => 1, 'updated_by' => $actor->id, 'updated_at' => now()]);
            }
            // Audit tanpa nomor (data kontak), cukup penanda kolom yang berubah.
            Audit::record($actor, 'pengaturan.kontak_diubah', 'system_settings', null, ['field' => ['bendahara_whatsapp'], 'kosong' => $value['bendahara_whatsapp'] === null]);
        });

        return $value;
    }

    /** Nomor Indonesia → digit internasional tanpa '+' (format wa.me): 0812… / +62 812… / 62812… → 62812…. */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return $digits;
    }
}
