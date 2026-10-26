<?php

namespace App\Domain\Finance;

use App\Domain\Services\Photo;
use App\Exceptions\DomainConflict;
use App\Models\Akun;
use App\Models\KonfirmasiIuran;
use App\Models\TagihanIuran;
use App\Support\Audit;
use App\Support\Outbox;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Konfirmasi pembayaran iuran dari warga (ADR35): foto bukti transfer untuk diperiksa pengurus. Ini bukan
 * pembayaran: status tagihan tidak berubah. Pengurus tetap mencatat pembayaran offline (Dues::recordPayment), lalu
 * menandai konfirmasi selesai atau menolaknya dengan catatan.
 *
 * Foto ditranskode langsung di request (JPEG ≤1600 px, EXIF terbuang) memakai Photo::stage/transcode yang sama dengan
 * pengaduan; satu foto per konfirmasi sehingga tidak perlu worker. Batas: 5 konfirmasi per akun per hari WIB.
 */
final class DuesConfirmations
{
    public const DAILY_LIMIT = 5;

    /** @param list<string> $periods 'YYYY-MM' */
    public static function submit(Akun $akun, UploadedFile $foto, array $periods, ?int $nominal, ?string $catatan): KonfirmasiIuran
    {
        $rumah = $akun->rumah();
        if (! $rumah) {
            throw new DomainConflict('HOUSEHOLD_NOT_LINKED', 'Akun belum terhubung dengan rumah.', 404);
        }
        $periods = array_values(array_unique($periods));
        if ($periods) {
            $known = TagihanIuran::where('rumah_id', $rumah->id)
                ->whereIn('periode', array_map(fn ($p) => $p.'-01', $periods))
                ->pluck('periode')->map(fn ($d) => substr((string) $d, 0, 7))->all();
            $unknown = array_values(array_diff($periods, $known));
            if ($unknown) {
                throw new DomainConflict('VALIDATION_FAILED', 'Periode '.implode(', ', $unknown).' tidak ada pada tagihan rumah ini.', 422);
            }
        }

        $staged = Photo::stage($foto, 'iuran/masuk');
        $id = (string) Str::orderedUuid();
        $target = 'iuran/'.$id.'.jpg';
        try {
            $result = Photo::transcode($staged['storage_key'], $target);
        } finally {
            Storage::disk(Photo::DISK)->delete($staged['storage_key']);
        }
        if (! $result) {
            throw new DomainConflict('UNSUPPORTED_MEDIA_TYPE', 'Foto tidak dapat dibaca. Gunakan JPG, PNG, atau WebP.', 415);
        }

        try {
            return DB::transaction(function () use ($akun, $rumah, $periods, $nominal, $catatan, $id, $target, $result) {
                Akun::whereKey($akun->id)->lockForUpdate()->first();
                $today = KonfirmasiIuran::where('akun_id', $akun->id)->where('created_at', '>=', now('Asia/Jakarta')->startOfDay()->utc())->count();
                if ($today >= self::DAILY_LIMIT) {
                    throw new DomainConflict('DAILY_QUOTA', 'Maksimal '.self::DAILY_LIMIT.' konfirmasi pembayaran per hari. Coba lagi besok.', 429);
                }
                $k = KonfirmasiIuran::create([
                    'id' => $id,
                    'akun_id' => $akun->id,
                    'rumah_id' => $rumah->id,
                    'periode' => $periods,
                    'nominal' => $nominal,
                    'catatan' => $catatan === null ? null : trim($catatan),
                    'storage_key' => $target,
                    'mime' => $result['mime'],
                    'bytes' => $result['bytes'],
                    'lebar' => $result['lebar'],
                    'tinggi' => $result['tinggi'],
                    'status' => 'diajukan',
                ]);
                Audit::record($akun, 'iuran.konfirmasi_dikirim', 'konfirmasi_iuran', $k->id, ['periode' => $periods]);
                Outbox::record('iuran.konfirmasi_dikirim', 'konfirmasi_iuran', $k->id);

                return $k;
            });
        } catch (\Throwable $e) {
            Storage::disk(Photo::DISK)->delete($target);
            throw $e;
        }
    }

    public static function process(Akun $pengurus, string $id, string $status, ?string $catatan): KonfirmasiIuran
    {
        if (config('finance.v2') && $status !== 'ditolak') {
            throw new DomainConflict('FINANCE_CLIENT_UPDATE_REQUIRED', 'Gunakan persetujuan dan pencatatan penerimaan pada aplikasi terbaru.', 422);
        }

        return DB::transaction(function () use ($pengurus, $id, $status, $catatan) {
            FlexibleDues::lock();
            $k = KonfirmasiIuran::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($k->status !== 'diajukan') {
                throw new DomainConflict('ALREADY_PROCESSED', 'Konfirmasi ini sudah diproses.');
            }
            $k->forceFill([
                'status' => $status,
                'catatan_pengurus' => $catatan === null ? null : trim($catatan),
                'diproses_oleh_id' => $pengurus->id,
                'diproses_at' => now(),
            ])->save();
            Audit::record($pengurus, 'iuran.konfirmasi_diproses', 'konfirmasi_iuran', $k->id, ['status' => $status]);
            Outbox::record('iuran.konfirmasi_diproses', 'konfirmasi_iuran', $k->id, ['status' => $status]);

            return $k;
        });
    }

    public static function approve(Akun $actor, string $id, array $data): KonfirmasiIuran
    {
        return DB::transaction(function () use ($actor, $id, $data) {
            FlexibleDues::lock();
            $confirmation = KonfirmasiIuran::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($confirmation->status !== 'diajukan' || $confirmation->pembayaran_id !== null) {
                throw new DomainConflict('ALREADY_PROCESSED', 'Konfirmasi ini sudah diproses.');
            }
            if ((int) $confirmation->nominal !== (int) $data['nominal'] && mb_strlen(trim($data['alasan'] ?? '')) < 5) {
                throw new DomainConflict('REASON_REQUIRED', 'Nominal terkoreksi wajib disertai alasan.', 422);
            }
            $payment = FlexibleDues::record($actor, $confirmation->rumah_id, $data['periode'], (int) $data['nominal'],
                $data['tanggal_bayar'], $data['perlakuan_kurang'] ?? null, $data['perlakuan_lebih'] ?? null,
                $data['alasan'] ?? null, $data['fingerprint']);
            $confirmation->forceFill(['status' => 'selesai', 'pembayaran_id' => $payment->id,
                'catatan_pengurus' => $data['alasan'] ?? null, 'diproses_oleh_id' => $actor->id, 'diproses_at' => now()])->save();
            Audit::record($actor, 'iuran.konfirmasi_disetujui', 'konfirmasi_iuran', $id, ['pembayaran_id' => $payment->id]);
            Outbox::record('iuran.konfirmasi_diproses', 'konfirmasi_iuran', $id, ['status' => 'selesai']);

            return $confirmation;
        });
    }
}
