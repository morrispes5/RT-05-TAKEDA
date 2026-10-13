<?php

namespace App\Domain\Services;

use App\Exceptions\DomainConflict;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Foto lampiran warga (FR21, SECURITY.md). Dua tahap:
 * 1. stage() di request: ≤12 MB, magic bytes JPG/PNG/WebP yang cocok dengan header gambar,
 *    ≤50 MP; berkas mentah disalin ke folder privat masuk/ dengan nama acak tanpa ekstensi.
 * 2. transcode() di worker-media: decode lalu encode ulang ke JPEG ≤1600 px sehingga EXIF/GPS
 *    terbuang. Hasil bernama <id foto>.jpg (deterministik → retry menimpa berkas yang sama).
 * Disk privat di luar document root; dilayani hanya lewat endpoint ber-policy.
 */
final class Photo
{
    public const DISK = 'local';

    public const STAGING = 'pengaduan/masuk';

    private const MAX_BYTES = 12 * 1024 * 1024;

    private const MAX_PIXELS = 50_000_000;

    private const MAX_EDGE = 1600;

    /** IMAGETYPE_* dari getimagesize harus sama dengan magic bytes (PNG berisi JPEG ditolak). */
    private const TYPES = ['jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'webp' => IMAGETYPE_WEBP];

    /** @return array{storage_key:string,mime:string,bytes:int,lebar:int,tinggi:int,status:string} */
    public static function stage(UploadedFile $file, string $folder = self::STAGING): array
    {
        if (! $file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw new DomainConflict('PAYLOAD_TOO_LARGE', 'Foto maksimal 12 MB.', 413);
        }
        $path = $file->getRealPath();
        $type = self::sniff($path);
        $info = $type ? @getimagesize($path) : false;
        if (! $type || ! $info || $info[2] !== self::TYPES[$type] || $info[0] < 1 || $info[1] < 1) {
            throw new DomainConflict('UNSUPPORTED_MEDIA_TYPE', 'Format foto harus JPG, PNG, atau WebP.', 415);
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new DomainConflict('PAYLOAD_TOO_LARGE', 'Resolusi foto terlalu besar.', 413);
        }

        $key = trim($folder, '/').'/'.Str::uuid();
        $stream = fopen($path, 'rb');
        try {
            Storage::disk(self::DISK)->writeStream($key, $stream);
        } finally {
            is_resource($stream) && fclose($stream);
        }

        return ['storage_key' => $key, 'mime' => 'image/'.$type, 'bytes' => (int) $file->getSize(), 'lebar' => $info[0], 'tinggi' => $info[1], 'status' => 'diproses'];
    }

    /**
     * Transkode berkas mentah ke $targetKey. null = berkas tidak dapat dibaca sebagai gambar
     * yang diizinkan (kegagalan permanen, tidak di-retry).
     *
     * @return array{mime:string,bytes:int,lebar:int,tinggi:int}|null
     */
    public static function transcode(string $sourceKey, string $targetKey): ?array
    {
        $disk = Storage::disk(self::DISK);
        $path = $disk->path($sourceKey);
        $type = self::sniff($path);
        $info = $type ? @getimagesize($path) : false;
        if (! $type || ! $info || $info[2] !== self::TYPES[$type] || $info[0] * $info[1] > self::MAX_PIXELS) {
            return null;
        }

        $source = match ($type) {
            'jpeg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => @imagecreatefromwebp($path),
        };
        if (! $source) {
            return null;
        }

        [$w, $h] = [imagesx($source), imagesy($source)];
        $scale = min(1, self::MAX_EDGE / max($w, $h));
        [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
        $canvas = imagecreatetruecolor($nw, $nh);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($source);

        ob_start();
        imagejpeg($canvas, null, 82);
        $jpeg = (string) ob_get_clean();
        imagedestroy($canvas);

        if (! $disk->put($targetKey, $jpeg)) {
            throw new \RuntimeException('media_write_failed');
        }

        return ['mime' => 'image/jpeg', 'bytes' => strlen($jpeg), 'lebar' => $nw, 'tinggi' => $nh];
    }

    private static function sniff(string|false $path): ?string
    {
        $head = $path ? (string) @file_get_contents($path, false, null, 0, 16) : '';

        return match (true) {
            str_starts_with($head, "\xFF\xD8\xFF") => 'jpeg',
            str_starts_with($head, "\x89PNG\r\n\x1A\n") => 'png',
            str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP' => 'webp',
            default => null,
        };
    }
}
