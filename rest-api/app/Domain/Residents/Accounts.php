<?php

namespace App\Domain\Residents;

use App\Exceptions\DomainConflict;
use App\Models\Akun;
use App\Models\PermohonanAkun;
use App\Models\TautanAkun;
use App\Support\Audit;
use App\Support\Outbox;
use Illuminate\Support\Facades\DB;

/**
 * Status akun oleh pengurus (BR v1): nonaktif dapat diaktifkan lagi; diarsipkan final.
 * Peran tidak pernah diterima dari request; grant/revoke pengurus hanya command operator,
 * sehingga akun pengurus tidak diarsipkan lewat API. Akses dicabut (bearer + sesi) saat
 * nonaktif/arsip; perubahan tautan lain dihitung ulang per request dari tautan aktif.
 */
final class Accounts
{
    public static function setStatus(Akun $actor, string $akunId, string $status, string $alasan): Akun
    {
        return Links::transaction(function () use ($actor, $akunId, $status, $alasan) {
            $akun = Akun::whereKey($akunId)->lockForUpdate()->first() ?? throw new DomainConflict('NOT_FOUND', 'Data tidak ditemukan.', 404);
            self::assertManageable($actor, $akun);
            if ($akun->status === 'menunggu') {
                throw new DomainConflict('PENDING_ACCOUNT', 'Akun menunggu diproses melalui permohonan.', 422);
            }
            $akun->forceFill(['status' => $status])->save();
            // Nonaktif: cabut bearer dan sesi browser. Perubahan status apa pun mengakhiri sesi CMS lama.
            $status === 'nonaktif' ? $akun->revokeAllAccess() : $akun->endBrowserSessions();
            Audit::record($actor, 'akun.status', 'akun', $akun->id, ['status' => $status, 'alasan' => $alasan]);

            return $akun;
        });
    }

    /**
     * Arsip akun warga: tautan aktif ditutup (keluarga kehilangan akun utama bila akun ini utama;
     * pengurus dapat menetapkan pengganti), seluruh akses dicabut, histori tetap.
     * Urutan lock keluarga → tautan → akun (sama dengan pindah utama dan pindah anggota). Bila
     * tautan berpindah keluarga di antara pembacaan awal dan lock, hasilnya 409 CONFLICT tanpa
     * perubahan; client cukup mengulang.
     */
    public static function archive(Akun $actor, string $akunId, string $alasan): void
    {
        Links::transaction(function () use ($actor, $akunId, $alasan) {
            $keluargaId = TautanAkun::where('akun_id', $akunId)->whereNull('selesai_at')->value('keluarga_id');
            $k = $keluargaId ? Links::lockFamilies([$keluargaId])[$keluargaId] ?? null : null;
            $link = TautanAkun::where('akun_id', $akunId)->whereNull('selesai_at')->lockForUpdate()->first();
            $akun = Akun::whereKey($akunId)->lockForUpdate()->first() ?? throw new DomainConflict('NOT_FOUND', 'Data tidak ditemukan.', 404);
            self::assertManageable($actor, $akun);
            if ($akun->isPengurus()) {
                throw new DomainConflict('ADMIN_ARCHIVE_OPERATOR_ONLY', 'Akun pengurus dicabut melalui command operator lebih dulu.', 422);
            }
            if (PermohonanAkun::where('akun_id', $akun->id)->where('status', 'diajukan')->exists()) {
                throw new DomainConflict('REQUEST_PENDING', 'Proses permohonan akun ini lebih dulu.');
            }
            if ($link?->keluarga_id !== $k?->id) {
                throw new DomainConflict('CONFLICT', 'Tautan akun berubah bersamaan. Coba lagi.');
            }
            if ($link) {
                Links::endLink($link, 'akun_diarsipkan', $actor);
                Links::bump($k);
            }
            $akun->forceFill(['status' => 'diarsipkan', 'archived_at' => now()])->save();
            $akun->revokeAllAccess();
            Audit::record($actor, 'akun.diarsipkan', 'akun', $akun->id, ['tautan_ditutup' => (bool) $link, 'utama' => (bool) $link?->is_utama, 'alasan' => $alasan]);
        });
    }

    /**
     * Pulihkan akun arsip (ADR34). Akun kembali **nonaktif**: belum bisa masuk sampai pengurus mengaktifkannya.
     * Tautan keluarga yang ditutup saat arsip tidak dibuka otomatis; pengurus menautkan ulang secara eksplisit.
     */
    public static function restore(Akun $actor, string $akunId, string $alasan): void
    {
        Links::transaction(function () use ($actor, $akunId, $alasan) {
            $akun = Akun::whereKey($akunId)->lockForUpdate()->first() ?? throw new DomainConflict('NOT_FOUND', 'Data tidak ditemukan.', 404);
            if ($akun->id === $actor->id) {
                throw new DomainConflict('SELF_STATUS_CHANGE', 'Tidak dapat mengubah status akun sendiri.', 422);
            }
            if ($akun->status !== 'diarsipkan') {
                throw new DomainConflict('NOT_ARCHIVED', 'Akun tidak sedang diarsipkan.');
            }
            if ($akun->dianonimkan_at) {
                throw new DomainConflict('ANONYMIZED', 'Arsip ini sudah dianonimkan (lebih dari 12 bulan) dan tidak dapat dipulihkan.');
            }
            $akun->forceFill(['status' => 'nonaktif', 'archived_at' => null])->save();
            Audit::record($actor, 'akun.dipulihkan', 'akun', $akun->id, ['status' => 'nonaktif', 'alasan' => $alasan]);
        });
    }

    /**
     * ADR43: super admin mengangkat warga menjadi pengurus atau menurunkannya. Diserialkan dengan perintah operator
     * `rt05:pengurus` (lock yang sama), pengurus aktif terakhir dilindungi, semua token/sesi akun target dicabut, diaudit.
     */
    public static function setRole(Akun $actor, string $akunId, string $peran, string $alasan): Akun
    {
        if (! $actor->isSuperAdmin()) {
            throw new DomainConflict('SUPER_ADMIN_REQUIRED', 'Hanya super admin yang dapat mengubah peran pengurus.', 403);
        }

        return DB::transaction(function () use ($actor, $akunId, $peran, $alasan) {
            DB::select("SELECT pg_advisory_xact_lock(hashtext('rt05:peran-pengurus'))");
            $akun = Akun::whereKey($akunId)->lockForUpdate()->first() ?? throw new DomainConflict('NOT_FOUND', 'Data tidak ditemukan.', 404);
            if ($akun->id === $actor->id) {
                throw new DomainConflict('SELF_ROLE_CHANGE', 'Tidak dapat mengubah peran akun sendiri.', 422);
            }
            if ($akun->peran === $peran) {
                return $akun;
            }
            if ($peran === 'pengurus' && $akun->status !== 'aktif') {
                throw new DomainConflict('ACCOUNT_NOT_ACTIVE', 'Hanya akun aktif yang dapat dijadikan pengurus.', 422);
            }
            if ($peran === 'warga' && ! Akun::where('peran', 'pengurus')->where('status', 'aktif')->whereKeyNot($akun->id)->exists()) {
                throw new DomainConflict('LAST_PENGURUS', 'Tidak dapat mencabut pengurus aktif terakhir.', 422);
            }
            $akun->peran = $peran;
            $akun->save();
            $akun->revokeAllAccess();
            Audit::record($actor, 'akun.peran_'.$peran, 'akun', $akun->id, ['kanal' => 'aplikasi', 'alasan' => $alasan]);
            Outbox::record('akun.peran_diubah', 'akun', $akun->id, ['peran' => $peran]);

            return $akun;
        });
    }

    private static function assertManageable(Akun $actor, Akun $akun): void
    {
        if ($akun->id === $actor->id) {
            throw new DomainConflict('SELF_STATUS_CHANGE', 'Tidak dapat mengubah status akun sendiri.', 422);
        }
        if ($akun->status === 'diarsipkan') {
            throw new DomainConflict('ACCOUNT_ARCHIVED', 'Akun sudah diarsipkan.');
        }
    }
}
