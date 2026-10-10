<?php

namespace App\Domain\Services;

use App\Exceptions\DomainConflict;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Foto lampiran warga (FR21, SECURITY.md): hanya JPG/PNG/WebP berdasarkan magic bytes, ≤12 MB,
 * ≤50 MP. Gambar di-decode lalu di-encode ulang ke JPEG ≤1600 px sehingga EXIF/GPS terbuang.
 * Disimpan privat dengan nama acak (bukan nama/email pelapor), dilayani hanya lewat endpoint ber-policy.
 */
final class Photo
{
    public const DISK = 'local';

    private const MAX_BYTES = 12 * 1024 * 1024;

    private const MAX_PIXELS = 50_000_000;

    private const MAX_EDGE = 1600;

    /** @return array{storage_key:string,mime:string,bytes:int,lebar:int,tinggi:int} */
    public static function store(UploadedFile $file, string $folder): array
    {
        if (! $file->isValid() || $file->getSize() > self::MAX_BYTES) {
            throw new DomainConflict('PAYLOAD_TOO_LARGE', 'Foto maksimal 12 MB.', 413);
        }

        $head = (string) file_get_contents($file->getRealPath(), false, null, 0, 16);
        $type = match (true) {
            str_starts_with($head, "\xFF\xD8\xFF") => 'jpeg',
            str_starts_with($head, "\x89PNG\r\n\x1A\n") => 'png',
            str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP' => 'webp',
            default => null,
        };
        $info = $type ? @getimagesize($file->getRealPath()) : false;
        if (! $type || ! $info || $info[0] < 1 || $info[1] < 1) {
            throw new DomainConflict('UNSUPPORTED_MEDIA_TYPE', 'Format foto harus JPG, PNG, atau WebP.', 415);
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new DomainConflict('PAYLOAD_TOO_LARGE', 'Resolusi foto terlalu besar.', 413);
        }

        $source = match ($type) {
            'jpeg' => @imagecreatefromjpeg($file->getRealPath()),
            'png' => @imagecreatefrompng($file->getRealPath()),
            'webp' => @imagecreatefromwebp($file->getRealPath()),
        };
        if (! $source) {
            throw new DomainConflict('UNSUPPORTED_MEDIA_TYPE', 'Foto tidak dapat dibaca.', 415);
        }

        [$w, $h] = [imagesx($source), imagesy($source)];
        $scale = min(1, self::MAX_EDGE / max($w, $h));
        [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
        $canvas = imagecreatetruecolor($nw, $nh);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($canvas, null, 82);
        $jpeg = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        $key = trim($folder, '/').'/'.Str::uuid().'.jpg';
        Storage::disk(self::DISK)->put($key, $jpeg);

        return ['storage_key' => $key, 'mime' => 'image/jpeg', 'bytes' => strlen($jpeg), 'lebar' => $nw, 'tinggi' => $nh];
    }
}
