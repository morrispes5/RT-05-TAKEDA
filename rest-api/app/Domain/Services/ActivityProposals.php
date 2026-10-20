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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Usulan kegiatan warga (bukan aspirasi dan bukan agenda), docs/USULAN_KEGIATAN.md.
 * Persetujuan pengurus langsung menyalin usulan sekali ke agenda resmi dalam transaksi yang sama.
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

    /** PDF dan TXT disimpan apa adanya, jadi dibatasi 1 MB (UAT M15); gambar boleh 8 MB karena diperkecil ulang. */
    public const MAX_TEXT_BYTES = 1024 * 1024;

    public const JENIS = ['olahraga', 'kerja_bakti', 'keagamaan', 'sosial', 'rapat', 'lainnya'];

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
                    ...self::fields($data),
                    'pernyataan_at' => now(),
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
                $locked->fill(self::fields($data) + ['pernyataan_at' => now()]);
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
                    // Pengurus perlu tahu ada perbaikan untuk ditinjau ulang.
                    Outbox::record('usulan.diperbaiki', 'usulan_kegiatan', $locked->id);
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
            if ($to === 'disetujui' && $locked->mulai_at->isPast()) {
                throw new DomainConflict('PROPOSAL_DATE_PASSED', 'Waktu kegiatan sudah lewat. Minta pengusul merevisi tanggal.', 422);
            }
            $from = $locked->status;
            $locked->forceFill(['status' => $to, 'versi' => $locked->versi + 1])->save();
            self::history($locked, $pengurus, $from, $to, $note);
            Audit::record($pengurus, 'usulan.status', 'usulan_kegiatan', $locked->id, ['dari' => $from, 'ke' => $to]);
            Outbox::record('usulan.status_berubah', 'usulan_kegiatan', $locked->id, ['status' => $to]);
            if ($to === 'disetujui') {
                self::copyToAgenda($pengurus, $locked);
            }

            return $locked;
        });
    }

    /**
     * Agenda aktif lain yang waktunya beririsan (peringatan bagi pengurus, bukan pemblokir).
     * Usulan atau agenda tanpa jam selesai dianggap berlangsung 2 jam.
     *
     * @return Collection<int, Agenda>
     */
    public static function overlappingAgendas(UsulanKegiatan $usulan): Collection
    {
        $end = $usulan->selesai_at ?? $usulan->mulai_at->copy()->addHours(2);

        return Agenda::whereNull('archived_at')
            ->when($usulan->agenda_id, fn ($q, $id) => $q->whereKeyNot($id))
            ->where('mulai_at', '<', $end)
            ->whereRaw("COALESCE(selesai_at, mulai_at + interval '2 hours') > ?", [$usulan->mulai_at])
            ->orderBy('mulai_at')
            ->limit(5)
            ->get(['id', 'nama', 'lokasi', 'mulai_at', 'selesai_at']);
    }

    /** Salin sekali ke tabel agenda dalam transaksi persetujuan. Tidak ada jadwal kedua pada usulan. */
    private static function copyToAgenda(Akun $pengurus, UsulanKegiatan $locked): void
    {
        $agenda = Agenda::create([
            'dibuat_oleh_id' => $pengurus->id,
            'nama' => $locked->nama,
            'deskripsi' => $locked->deskripsi,
            'lokasi' => $locked->lokasi,
            'mulai_at' => $locked->mulai_at,
            'selesai_at' => $locked->selesai_at,
        ]);
        $locked->forceFill(['agenda_id' => $agenda->id])->save();
        Audit::record($pengurus, 'agenda.dibuat', 'agenda', $agenda->id, ['usulan_kegiatan_id' => $locked->id]);
        // agenda.dibuat → notifikasi ke semua warga aktif (Notifications::agenda).
        Outbox::record('agenda.dibuat', 'agenda', $agenda->id);
    }

    /** @return array<string, mixed> */
    private static function fields(array $data): array
    {
        $kebutuhan = trim((string) ($data['kebutuhan'] ?? ''));

        return [
            'jenis' => $data['jenis'],
            'nama' => trim($data['nama']),
            'deskripsi' => trim($data['deskripsi']),
            'lokasi' => trim($data['lokasi']),
            'mulai_at' => $data['mulai_at'],
            'selesai_at' => $data['selesai_at'] ?? null,
            'perkiraan_peserta' => $data['perkiraan_peserta'] ?? null,
            'kebutuhan' => $kebutuhan === '' ? null : $kebutuhan,
        ];
    }

    /** @return array{storage_key:string,mime:string,bytes:int,urutan:int} */
    public static function stage(UploadedFile $file, int $order): array
    {
        if (! $file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw new DomainConflict('PAYLOAD_TOO_LARGE', 'Dokumen maksimal 8 MB.', 413);
        }
        $path = $file->getRealPath();
        $type = self::sniff($path);
        // Teks hanya diterima sebagai .txt: berkas bernama .pdf/.docx yang ternyata teks adalah spoof.
        if ($type === 'txt' && strtolower($file->getClientOriginalExtension()) !== 'txt') {
            $type = null;
        }
        if (! $type) {
            throw new DomainConflict('UNSUPPORTED_MEDIA_TYPE', 'Dokumen harus PDF, TXT, JPG, PNG, atau WebP.', 415);
        }
        $key = self::FOLDER.'/'.Str::uuid();
        if ($type === 'txt') {
            $text = (string) file_get_contents($path);
            if (strlen($text) > self::MAX_TEXT_BYTES) {
                throw new DomainConflict('PAYLOAD_TOO_LARGE', 'Dokumen teks maksimal 1 MB.', 413);
            }
            Storage::disk(self::DISK)->put($key, $text);

            return ['storage_key' => $key, 'mime' => 'text/plain', 'bytes' => strlen($text), 'urutan' => $order];
        }
        if ($type === 'pdf') {
            $bytes = (string) file_get_contents($path);
            if (strlen($bytes) > self::MAX_TEXT_BYTES) {
                throw new DomainConflict('PAYLOAD_TOO_LARGE', 'Dokumen PDF maksimal 1 MB. Perkecil atau pisahkan berkasnya.', 413);
            }
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
            $path && self::isPlainText($path) => 'txt',
            default => null,
        };
    }

    /** Teks polos: UTF-8 valid, tidak kosong, tanpa byte NUL atau kontrol selain tab dan baris baru. */
    private static function isPlainText(string $path): bool
    {
        $text = (string) file_get_contents($path, false, null, 0, self::MAX_TEXT_BYTES + 1);
        // Markup (HTML/SVG/XML) bukan teks polos walaupun valid UTF-8.
        $markup = str_starts_with(ltrim($text), '<') || preg_match('/<\s*(script|html|svg|iframe)\b/i', $text) === 1;

        return trim($text) !== '' && ! $markup && mb_check_encoding($text, 'UTF-8') && ! preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text);
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
