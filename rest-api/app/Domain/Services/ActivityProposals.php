<?php

namespace App\Domain\Services;

use App\Exceptions\DomainConflict;
use App\Models\Agenda;
use App\Models\Akun;
use App\Models\DokumenUsulan;
use App\Models\RiwayatUsulanKegiatan;
use App\Models\UsulanKegiatan;
use App\Support\Audit;
use App\Support\Outbox;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Usulan kegiatan warga (bukan aspirasi dan bukan agenda).
 * Hanya usulan berstatus disetujui yang boleh menjadi satu baris agenda.
 * Setelah itu agenda adalah jadwal resmi; usulan hanya menyimpan pengajuan dan keputusan.
 */
final class ActivityProposals
{
    public const FLOW = [
        'diajukan' => ['ditinjau', 'perlu_revisi', 'disetujui', 'ditolak'],
        'ditinjau' => ['perlu_revisi', 'disetujui', 'ditolak'],
    ];

    public const EDITABLE = ['diajukan', 'perlu_revisi'];

    public const MAX_DOCUMENTS = 3;

    public const MAX_BYTES = 8 * 1024 * 1024;

    public const DISK = 'local';

    public const FOLDER = 'usulan/dokumen';

    /** @param list<UploadedFile> $documents */
    public static function create(Akun $akun, array $data, array $documents): UsulanKegiatan
    {
        self::assertDocumentCount(0, count($documents));
        $stored = [];
        try {
            foreach ($documents as $i => $file) {
                $stored[] = self::stage($file, $i + 1);
            }
            $usulan = DB::transaction(function () use ($akun, $data, $stored) {
                $usulan = UsulanKegiatan::create([
                    'pengusul_id' => $akun->id,
                    'rumah_id' => $akun->rumah()?->id,
                    'nama' => trim($data['nama']),
                    'deskripsi' => trim($data['deskripsi']),
                    'lokasi' => trim($data['lokasi']),
                    'mulai_at' => $data['mulai_at'],
                    'selesai_at' => $data['selesai_at'] ?? null,
                    'status' => 'diajukan',
                ]);
                foreach ($stored as $meta) {
                    DokumenUsulan::create($meta + ['usulan_kegiatan_id' => $usulan->id]);
                }
                self::history($usulan, $akun, null, 'diajukan', null);
                Audit::record($akun, 'usulan.dibuat', 'usulan_kegiatan', $usulan->id, ['dokumen' => count($stored)]);
                Outbox::record('usulan.diajukan', 'usulan_kegiatan', $usulan->id);

                return $usulan;
            });
        } catch (Throwable $e) {
            foreach ($stored as $meta) {
                Storage::disk(self::DISK)->delete($meta['storage_key']);
            }
            throw $e;
        }

        return $usulan->refresh();
    }

    /** @param list<UploadedFile> $documents */
    public static function updateOwn(Akun $akun, UsulanKegiatan $usulan, array $data, array $documents): UsulanKegiatan
    {
        $existing = $usulan->dokumen()->count();
        self::assertDocumentCount($existing, count($documents));
        $stored = [];
        try {
            foreach ($documents as $i => $file) {
                $stored[] = self::stage($file, $existing + $i + 1);
            }

            return DB::transaction(function () use ($akun, $usulan, $data, $stored) {
                $locked = UsulanKegiatan::whereKey($usulan->id)->lockForUpdate()->firstOrFail();
                if ($locked->pengusul_id !== $akun->id || ! in_array($locked->status, self::EDITABLE, true)) {
                    throw new DomainConflict('PROPOSAL_LOCKED', 'Usulan hanya dapat diubah pengusulnya selama diajukan atau perlu revisi.', 403);
                }
                self::assertVersion($locked->versi, $data['versi'] ?? null);
                $from = $locked->status;
                $locked->fill([
                    'nama' => trim($data['nama']),
                    'deskripsi' => trim($data['deskripsi']),
                    'lokasi' => trim($data['lokasi']),
                    'mulai_at' => $data['mulai_at'],
                    'selesai_at' => $data['selesai_at'] ?? null,
                ]);
                if ($from === 'perlu_revisi') {
                    $locked->status = 'diajukan';
                }
                $locked->versi++;
                $locked->save();
                foreach ($stored as $meta) {
                    DokumenUsulan::create($meta + ['usulan_kegiatan_id' => $locked->id]);
                }
                if ($from === 'perlu_revisi') {
                    self::history($locked, $akun, $from, 'diajukan', 'Usulan diperbaiki dan diajukan kembali.');
                    Outbox::record('usulan.status_berubah', 'usulan_kegiatan', $locked->id, ['status' => 'diajukan']);
                }
                Audit::record($akun, 'usulan.diubah_pengusul', 'usulan_kegiatan', $locked->id);

                return $locked;
            });
        } catch (Throwable $e) {
            foreach ($stored as $meta) {
                Storage::disk(self::DISK)->delete($meta['storage_key']);
            }
            throw $e;
        }
    }

    public static function changeStatus(Akun $pengurus, UsulanKegiatan $usulan, string $to, ?string $note, int $expectedVersion): UsulanKegiatan
    {
        return DB::transaction(function () use ($pengurus, $usulan, $to, $note, $expectedVersion) {
            $locked = UsulanKegiatan::whereKey($usulan->id)->lockForUpdate()->firstOrFail();
            if ($locked->agenda_id) {
                throw new DomainConflict('PROPOSAL_SCHEDULED', 'Usulan yang sudah masuk agenda tidak dapat diubah statusnya.', 409);
            }
            self::assertVersion($locked->versi, $expectedVersion);
            if (! in_array($to, self::FLOW[$locked->status] ?? [], true)) {
                throw new DomainConflict('INVALID_STATUS_TRANSITION', "Status tidak dapat diubah dari {$locked->status} ke $to.", 422);
            }
            if (in_array($to, ['perlu_revisi', 'ditolak'], true) && trim((string) $note) === '') {
                throw new DomainConflict('DECISION_NOTE_REQUIRED', 'Catatan keputusan wajib diisi.', 422);
            }
            $from = $locked->status;
            $locked->forceFill(['status' => $to, 'versi' => $locked->versi + 1])->save();
            self::history($locked, $pengurus, $from, $to, $note);
            Audit::record($pengurus, 'usulan.status', 'usulan_kegiatan', $locked->id, ['dari' => $from, 'ke' => $to]);
            Outbox::record('usulan.status_berubah', 'usulan_kegiatan', $locked->id, ['status' => $to]);

            return $locked;
        });
    }

    /** Salin sekali ke tabel agenda. Tidak membuat jadwal kedua pada usulan. */
    public static function schedule(Akun $pengurus, UsulanKegiatan $usulan, int $expectedVersion): UsulanKegiatan
    {
        return DB::transaction(function () use ($pengurus, $usulan, $expectedVersion) {
            $locked = UsulanKegiatan::whereKey($usulan->id)->lockForUpdate()->firstOrFail();
            self::assertVersion($locked->versi, $expectedVersion);
            if ($locked->status !== 'disetujui') {
                throw new DomainConflict('INVALID_STATUS_TRANSITION', 'Hanya usulan yang disetujui yang dapat dimasukkan ke agenda.', 422);
            }
            if ($locked->agenda_id) {
                throw new DomainConflict('ALREADY_SCHEDULED', 'Usulan ini sudah masuk agenda resmi.', 409);
            }
            $agenda = Agenda::create([
                'dibuat_oleh_id' => $pengurus->id,
                'nama' => $locked->nama,
                'deskripsi' => $locked->deskripsi,
                'lokasi' => $locked->lokasi,
                'mulai_at' => $locked->mulai_at,
                'selesai_at' => $locked->selesai_at,
            ]);
            $locked->forceFill(['agenda_id' => $agenda->id, 'versi' => $locked->versi + 1])->save();
            self::history($locked, $pengurus, 'disetujui', 'disetujui', 'Dimasukkan ke agenda resmi.');
            Audit::record($pengurus, 'agenda.dibuat', 'agenda', $agenda->id, ['usulan_kegiatan_id' => $locked->id]);
            Audit::record($pengurus, 'usulan.dijadwalkan', 'usulan_kegiatan', $locked->id, ['agenda_id' => $agenda->id]);
            Outbox::record('agenda.dibuat', 'agenda', $agenda->id);
            Outbox::record('usulan.dijadwalkan', 'usulan_kegiatan', $locked->id, ['agenda_id' => $agenda->id]);

            return $locked;
        });
    }

    /** @return array{storage_key:string,mime:string,bytes:int,urutan:int} */
    public static function stage(UploadedFile $file, int $order): array
    {
        if (! $file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw new DomainConflict('PAYLOAD_TOO_LARGE', 'Dokumen maksimal 8 MB.', 413);
        }
        $path = $file->getRealPath();
        $type = self::sniff($path);
        if (! $type) {
            throw new DomainConflict('UNSUPPORTED_MEDIA_TYPE', 'Dokumen harus PDF, JPG, PNG, atau WebP.', 415);
        }
        $key = self::FOLDER.'/'.Str::uuid();
        if ($type === 'pdf') {
            $bytes = (string) file_get_contents($path);
            Storage::disk(self::DISK)->put($key, $bytes);

            return ['storage_key' => $key, 'mime' => 'application/pdf', 'bytes' => strlen($bytes), 'urutan' => $order];
        }
        $jpeg = self::reencode($path, $type);
        Storage::disk(self::DISK)->put($key, $jpeg);

        return ['storage_key' => $key, 'mime' => 'image/jpeg', 'bytes' => strlen($jpeg), 'urutan' => $order];
    }

    private static function assertDocumentCount(int $existing, int $incoming): void
    {
        if ($existing + $incoming > self::MAX_DOCUMENTS) {
            throw new DomainConflict('TOO_MANY_DOCUMENTS', 'Maksimal 3 dokumen pendukung.', 422);
        }
    }

    private static function history(UsulanKegiatan $usulan, Akun $aktor, ?string $from, string $to, ?string $note): void
    {
        RiwayatUsulanKegiatan::create([
            'usulan_kegiatan_id' => $usulan->id,
            'aktor_id' => $aktor->id,
            'status_dari' => $from,
            'status_ke' => $to,
            'catatan' => $note !== null && trim($note) !== '' ? trim($note) : null,
        ]);
    }

    private static function assertVersion(int $current, mixed $expected): void
    {
        if ($expected === null || (int) $expected !== $current) {
            throw new DomainConflict('VERSION_CONFLICT', 'Data sudah berubah. Muat ulang lalu coba lagi.');
        }
    }

    private static function sniff(string|false $path): ?string
    {
        $head = $path ? (string) file_get_contents($path, false, null, 0, 16) : '';

        return match (true) {
            str_starts_with($head, '%PDF-') => 'pdf',
            str_starts_with($head, "\xFF\xD8\xFF") => 'jpeg',
            str_starts_with($head, "\x89PNG\r\n\x1A\n") => 'png',
            str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP' => 'webp',
            default => null,
        };
    }

    private static function reencode(string $path, string $type): string
    {
        $info = @getimagesize($path);
        if (! $info || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > 12_000_000) {
            throw new DomainConflict('PAYLOAD_TOO_LARGE', 'Resolusi gambar terlalu besar.', 413);
        }
        $source = match ($type) {
            'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => @imagecreatefromwebp($path),
            default => false,
        };
        if (! $source) {
            throw new DomainConflict('UNSUPPORTED_MEDIA_TYPE', 'Dokumen harus PDF, JPG, PNG, atau WebP.', 415);
        }
        [$w, $h] = [imagesx($source), imagesy($source)];
        $scale = min(1, 1600 / max($w, $h));
        [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
        $canvas = imagecreatetruecolor($nw, $nh);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($source);
        ob_start();
        imagejpeg($canvas, null, 82);
        $jpeg = (string) ob_get_clean();
        imagedestroy($canvas);

        return $jpeg;
    }
}
