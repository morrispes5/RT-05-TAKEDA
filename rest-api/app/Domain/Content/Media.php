<?php

namespace App\Domain\Content;

use App\Domain\Services\Photo;
use App\Exceptions\DomainConflict;
use App\Models\Akun;
use App\Models\KontenMedia;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Media konten publik (M13). Unggahan pengurus diproses di request yang sama (validasi magic bytes,
 * decode lalu encode ulang JPEG ≤1600 px sehingga EXIF/GPS terbuang), disimpan di disk privat
 * dengan nama acak. Berkas unggahan hanya dilayani publik bila dirujuk snapshot konten terbit;
 * pratinjau draf hanya untuk pengurus. Foto statis website yang sudah diterima dirujuk lewat path.
 */
final class Media
{
    public const FOLDER = 'konten';

    public static function upload(Akun $actor, UploadedFile $file): KontenMedia
    {
        $staged = Photo::stage($file, self::FOLDER.'/masuk');
        $id = (string) Str::uuid();
        $key = self::FOLDER.'/'.$id.'.jpg';
        try {
            $result = Photo::transcode($staged['storage_key'], $key);
        } finally {
            Storage::disk(Photo::DISK)->delete($staged['storage_key']);
        }
        if (! $result) {
            throw new DomainConflict('UNSUPPORTED_MEDIA_TYPE', 'Berkas bukan gambar JPG/PNG/WebP yang valid.', 415);
        }

        return DB::transaction(function () use ($actor, $id, $key, $result) {
            $m = KontenMedia::create(['id' => $id, 'sumber' => 'unggah', 'storage_key' => $key, 'status' => 'siap', 'dibuat_oleh_id' => $actor->id] + $result);
            Audit::record($actor, 'konten_media.diunggah', 'konten_media', $m->id, ['bytes' => $m->bytes]);

            return $m;
        });
    }

    /** Foto statis website (impor baseline); idempotent berdasarkan path. */
    public static function staticPhoto(string $path, int $lebar, int $tinggi, ?array $srcset = null): KontenMedia
    {
        if (! str_starts_with($path, '/images/') || str_contains($path, '..')) {
            throw new DomainConflict('VALIDATION_FAILED', 'Path foto statis harus di bawah /images/.', 422);
        }
        DB::table('konten_media')->insertOrIgnore([
            'id' => (string) Str::uuid(), 'sumber' => 'statis', 'path_statis' => $path, 'ukuran_statis' => json_encode($srcset),
            'mime' => 'image/webp', 'lebar' => $lebar, 'tinggi' => $tinggi, 'status' => 'siap',
        ]);

        return KontenMedia::where('path_statis', $path)->firstOrFail();
    }

    /** Bentuk media di snapshot/respons: URL relatif (website atau API publik). */
    public static function view(KontenMedia $m): array
    {
        return [
            'id' => $m->id,
            'url' => $m->sumber === 'statis' ? $m->path_statis : '/api/v1/public/media/'.$m->id,
            'srcset' => $m->sumber === 'statis' ? ($m->ukuran_statis['srcset'] ?? null) : null,
            'lebar' => $m->lebar,
            'tinggi' => $m->tinggi,
        ];
    }

    /**
     * Snapshot terbit: payload + objek media yang sudah diselesaikan. Media hilang/gagal → 422.
     *
     * @return array<string, mixed>
     */
    public static function resolve(string $jenis, array $payload): array
    {
        $ids = Contents::mediaIds($jenis, $payload);
        $media = KontenMedia::whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) {
            if (! isset($media[$id]) || $media[$id]->status !== 'siap') {
                throw new DomainConflict('MEDIA_NOT_READY', 'Ada foto yang belum siap atau tidak ditemukan. Unggah ulang foto tersebut lalu terbitkan lagi.', 422);
            }
        }
        $view = fn (?string $id) => $id ? self::view($media[$id]) : null;

        return match ($jenis) {
            'artikel' => array_diff_key($payload, ['cover_media_id' => 1]) + ['cover' => $view($payload['cover_media_id'] ?? null)],
            'album' => [
                'title' => $payload['title'], 'description' => $payload['description'], 'date' => $payload['date'] ?? null,
                'cover' => $view($payload['cover_media_id'] ?? $payload['photos'][0]['media_id']),
                'photos' => array_map(fn ($p) => $view($p['media_id']) + ['alt' => $p['alt'], 'caption' => $p['caption'] ?? null], $payload['photos']),
            ],
            'fasilitas' => array_diff_key($payload, ['media_id' => 1]) + ['photo' => $view($payload['media_id'] ?? null)],
            default => $payload,
        };
    }

    /** Berkas unggahan boleh dilayani publik hanya bila dirujuk snapshot konten terbit. */
    public static function isPublic(KontenMedia $m): bool
    {
        return $m->sumber === 'unggah' && $m->status === 'siap'
            && DB::table('konten')->where('status', 'terbit')->whereRaw('terbit::text LIKE ?', ['%"'.$m->id.'"%'])->exists();
    }
}
