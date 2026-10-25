<?php

namespace App\Domain\Residents;

use App\Models\Akun;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * M17 (permintaan pemilik 25 Okt 2026): foto profil keluarga dipilih dari 15 avatar bawaan aplikasi, bukan foto galeri,
 * sehingga tidak ada unggahan wajah warga. Kode harus sama dengan aset mobile `assets/avatars/<kode>.svg`.
 */
final class FamilyAvatars
{
    public const CODES = [
        'ayah', 'ibu', 'ayah_peci', 'ibu_hijab', 'kakek', 'nenek', 'nenek_hijab', 'kakak_laki', 'kakak_perempuan',
        'remaja_hijab', 'adik_laki', 'adik_perempuan', 'bayi', 'pasangan', 'keluarga',
    ];

    /** Avatar kosmetik: tidak menaikkan versi keluarga agar tidak bentrok dengan isian data keluarga yang sedang diubah. */
    public static function set(Akun $actor, string $keluargaId, ?string $avatar): void
    {
        DB::transaction(function () use ($actor, $keluargaId, $avatar) {
            DB::table('keluarga')->where('id', $keluargaId)->update(['avatar' => $avatar]);
            Audit::record($actor, 'keluarga.avatar_diubah', 'keluarga', $keluargaId, ['avatar' => $avatar]);
        });
    }
}
