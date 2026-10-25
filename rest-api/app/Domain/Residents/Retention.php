<?php

namespace App\Domain\Residents;

use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ADR43 butir 7 (Kebijakan Privasi): data pribadi arsip dianonimkan 12 bulan setelah diarsipkan. Catatan iuran, kas, dan audit
 * tetap (merujuk ID, bukan nama). Warga: nama, WhatsApp, NIK, tanggal lahir. Keluarga: nomor KK. Akun arsip: nama, email,
 * kata sandi. Arsip yang sudah dianonimkan tidak dapat dipulihkan.
 */
final class Retention
{
    public const MONTHS = 12;

    /** @return array{warga: int, keluarga: int, akun: int} */
    public static function anonymize(): array
    {
        $cutoff = now()->subMonths(self::MONTHS);

        return DB::transaction(function () use ($cutoff) {
            $warga = DB::table('warga')->whereNull('dianonimkan_at')->where(function ($q) use ($cutoff) {
                $q->where('archived_at', '<', $cutoff)->orWhere(function ($q) use ($cutoff) {
                    // Anggota yang keluar (pindah/meninggal) tanpa keanggotaan aktif, keluarnya lebih dari 12 bulan.
                    $q->whereIn('status', ['pindah', 'meninggal'])
                        ->whereNotExists(fn ($s) => $s->from('keanggotaan_keluarga')->whereColumn('warga_id', 'warga.id')->whereNull('selesai_at'))
                        ->whereNotExists(fn ($s) => $s->from('keanggotaan_keluarga')->whereColumn('warga_id', 'warga.id')->where('selesai_at', '>=', $cutoff));
                });
            })->pluck('id');
            if ($warga->isNotEmpty()) {
                DB::table('warga')->whereIn('id', $warga)->update(['nama' => 'Warga dianonimkan', 'whatsapp' => null, 'nik' => null,
                    'tanggal_lahir' => null, 'dianonimkan_at' => now()]);
            }

            $keluarga = DB::table('keluarga')->whereNull('dianonimkan_at')->where('archived_at', '<', $cutoff)->pluck('id');
            if ($keluarga->isNotEmpty()) {
                DB::table('keluarga')->whereIn('id', $keluarga)->update(['nomor_kk' => null, 'dianonimkan_at' => now()]);
            }

            $akun = DB::table('akun')->whereNull('dianonimkan_at')->where('status', 'diarsipkan')->where('archived_at', '<', $cutoff)->pluck('id');
            foreach ($akun as $id) {
                DB::table('akun')->where('id', $id)->update(['nama' => 'Akun dianonimkan', 'email' => 'dianonimkan-'.$id.'@rt05.invalid',
                    'password_hash' => Hash::make(Str::random(64)), 'remember_token' => null, 'dianonimkan_at' => now()]);
            }

            $hasil = ['warga' => $warga->count(), 'keluarga' => $keluarga->count(), 'akun' => $akun->count()];
            if (array_sum($hasil) > 0) {
                Audit::record(null, 'arsip.dianonimkan', 'retensi', null, $hasil);
            }

            return $hasil;
        });
    }
}
