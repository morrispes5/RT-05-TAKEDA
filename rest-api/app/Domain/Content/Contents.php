<?php

namespace App\Domain\Content;

use App\Exceptions\DomainConflict;
use App\Http\Responses\Present;
use App\Models\Akun;
use App\Models\Konten;
use App\Models\KontenMedia;
use App\Support\Audit;
use App\Support\Outbox;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Konten publik M13 (ADR28): artikel, album, fasilitas, profil situs.
 * - Draf diedit dengan versi optimistik (409 VERSION_CONFLICT); `terbit` adalah snapshot terpisah
 *   yang hanya berubah saat diterbitkan, jadi pengunjung tidak pernah membaca draf.
 * - Terbit memvalidasi payload penuh dan memastikan semua media siap; snapshot memuat URL media
 *   yang sudah diselesaikan sehingga tetap stabil walau draf berubah.
 * - Slug unik per jenis; slug terbit lama disimpan untuk redirect permanen.
 * - Terbit/arsip mencatat outbox `konten.*` untuk invalidasi cache publik (website M14).
 */
final class Contents
{
    /** Jenis URL (API) → jenis tabel. */
    public const TYPES = ['articles' => 'artikel', 'albums' => 'album', 'facilities' => 'fasilitas'];

    /** Ilustrasi artikel yang tersedia di website (komponen article-art). */
    public const ART = ['hands', 'waste', 'water', 'home', 'park'];

    /** @return array<string, mixed> aturan validasi payload per jenis */
    public static function rules(string $jenis): array
    {
        return match ($jenis) {
            'artikel' => [
                'title' => ['required', 'string', 'min:5', 'max:160'],
                'category' => ['required', 'string', 'min:2', 'max:40'],
                'art' => ['nullable', 'string', 'in:'.implode(',', self::ART)],
                'summary' => ['required', 'string', 'min:10', 'max:300'],
                'intro' => ['required', 'string', 'min:10', 'max:2000'],
                'sections' => ['required', 'array', 'min:1', 'max:12'],
                'sections.*.title' => ['required', 'string', 'min:2', 'max:120'],
                'sections.*.body' => ['required', 'string', 'min:10', 'max:5000'],
                'note' => ['nullable', 'string', 'max:500'],
                'source' => ['nullable', 'array'],
                'source.url' => ['nullable', 'url:https', 'max:300'],
                'source.label' => ['nullable', 'string', 'max:120'],
                'cover_media_id' => ['nullable', 'uuid'],
                'cover_alt' => ['required_with:cover_media_id', 'nullable', 'string', 'min:5', 'max:250'],
            ],
            'album' => [
                'title' => ['required', 'string', 'min:3', 'max:160'],
                'description' => ['required', 'string', 'min:10', 'max:500'],
                'date' => ['nullable', 'date_format:Y-m-d'],
                'photos' => ['required', 'array', 'min:1', 'max:60'],
                'photos.*.media_id' => ['required', 'uuid', 'distinct'],
                'photos.*.alt' => ['required', 'string', 'min:5', 'max:250'],
                'photos.*.caption' => ['nullable', 'string', 'max:250'],
                'cover_media_id' => ['nullable', 'uuid'],
            ],
            'fasilitas' => [
                'title' => ['required', 'string', 'min:3', 'max:120'],
                'description' => ['required', 'string', 'min:10', 'max:2000'],
                'media_id' => ['nullable', 'uuid'],
                'alt' => ['required_with:media_id', 'nullable', 'string', 'min:5', 'max:250'],
            ],
            'profil' => [
                'nama' => ['required', 'string', 'min:3', 'max:80'],
                'singkatan' => ['required', 'string', 'min:3', 'max:80'],
                'wilayah' => ['required', 'string', 'min:5', 'max:200'],
                'ringkasan' => ['required', 'string', 'min:5', 'max:500'],
                // ADR35: cerita singkat Taman Kedaung di halaman Profil RT05, dapat diedit pengurus (paragraf dipisah baris kosong).
                'tentang' => ['nullable', 'string', 'max:3000'],
                'pengurus' => ['required', 'array', 'min:1', 'max:12'],
                'pengurus.*.jabatan' => ['required', 'string', 'min:2', 'max:60'],
                'pengurus.*.nama' => ['required', 'string', 'min:2', 'max:80'],
                'pengurus.*.catatan' => ['nullable', 'string', 'max:200'],
            ],
        };
    }

    /** Hanya kunci yang dikenal disimpan (tanpa field liar dari client). */
    public static function payload(string $jenis, array $input): array
    {
        $data = Validator::make($input, self::rules($jenis))->validate();
        if ($jenis === 'album' && ($data['cover_media_id'] ?? null) && ! in_array($data['cover_media_id'], array_column($data['photos'], 'media_id'), true)) {
            throw new DomainConflict('VALIDATION_FAILED', 'Sampul album harus salah satu foto album.', 422);
        }
        $keep = array_unique(array_map(fn ($k) => explode('.', $k)[0], array_keys(self::rules($jenis))));

        return array_intersect_key($data, array_flip($keep));
    }

    public static function create(Akun $actor, string $jenis, string $slug, array $input): Konten
    {
        $payload = self::payload($jenis, $input);
        self::assertSlugFree($jenis, $slug, null);
        try {
            return DB::transaction(function () use ($actor, $jenis, $slug, $payload) {
                $k = Konten::create(['jenis' => $jenis, 'slug' => $slug, 'draf' => $payload, 'status' => 'draf', 'versi' => 1, 'sumber' => 'pengurus', 'urutan' => 0, 'dibuat_oleh_id' => $actor->id, 'diubah_oleh_id' => $actor->id]);
                Audit::record($actor, 'konten.dibuat', 'konten', $k->id, ['jenis' => $jenis, 'slug' => $slug]);

                return $k;
            });
        } catch (UniqueConstraintViolationException) {
            throw self::slugTaken();
        }
    }

    public static function update(Akun $actor, Konten $konten, int $versi, string $slug, array $input): Konten
    {
        $payload = self::payload($konten->jenis, $input);
        try {
            return DB::transaction(function () use ($actor, $konten, $versi, $slug, $payload) {
                $k = self::lock($konten->id, $versi);
                if ($k->status === 'arsip') {
                    throw new DomainConflict('CONTENT_ARCHIVED', 'Konten sudah diarsipkan dan tidak dapat diubah.');
                }
                if ($k->jenis === 'profil' && $slug !== $k->slug) {
                    throw new DomainConflict('VALIDATION_FAILED', 'Slug profil tidak dapat diubah.', 422);
                }
                self::assertSlugFree($k->jenis, $slug, $k->id);
                $k->forceFill(['slug' => $slug, 'draf' => $payload, 'versi' => $k->versi + 1, 'diubah_oleh_id' => $actor->id])->save();
                Audit::record($actor, 'konten.diubah', 'konten', $k->id, ['jenis' => $k->jenis, 'versi' => $k->versi]);

                return $k;
            });
        } catch (UniqueConstraintViolationException) {
            throw self::slugTaken();
        }
    }

    public static function publish(?Akun $actor, Konten $konten, int $versi): Konten
    {
        return DB::transaction(function () use ($actor, $konten, $versi) {
            $k = self::lock($konten->id, $versi);
            if ($k->status === 'arsip') {
                throw new DomainConflict('CONTENT_ARCHIVED', 'Konten sudah diarsipkan. Buat konten baru untuk menerbitkan lagi.');
            }
            $payload = self::payload($k->jenis, $k->draf);
            $snapshot = Media::resolve($k->jenis, $payload);

            if ($k->slug_terbit && $k->slug_terbit !== $k->slug) {
                // URL lama tetap berguna: redirect permanen ke slug baru (BUSINESS_RULES konten).
                DB::table('konten_slug_lama')->insertOrIgnore(['id' => (string) Str::uuid(), 'konten_id' => $k->id, 'jenis' => $k->jenis, 'slug' => $k->slug_terbit]);
            }
            DB::table('konten_slug_lama')->where('jenis', $k->jenis)->where('slug', $k->slug)->where('konten_id', $k->id)->delete();

            $k->forceFill([
                'status' => 'terbit',
                'terbit' => $snapshot,
                'slug_terbit' => $k->slug,
                'versi_terbit' => $k->versi,
                'published_at' => $k->published_at ?? now(),
                'diubah_oleh_id' => $actor?->id ?? $k->diubah_oleh_id,
            ])->save();
            Audit::record($actor, 'konten.diterbitkan', 'konten', $k->id, ['jenis' => $k->jenis, 'versi' => $k->versi]);
            Outbox::record('konten.diterbitkan', 'konten', $k->id, ['jenis' => $k->jenis, 'slug' => $k->slug]);

            return $k;
        });
    }

    public static function archive(Akun $actor, Konten $konten, int $versi): Konten
    {
        return DB::transaction(function () use ($actor, $konten, $versi) {
            $k = self::lock($konten->id, $versi);
            if ($k->jenis === 'profil') {
                throw new DomainConflict('VALIDATION_FAILED', 'Profil situs tidak dapat diarsipkan.', 422);
            }
            if ($k->status === 'arsip') {
                throw new DomainConflict('CONTENT_ARCHIVED', 'Konten sudah diarsipkan.');
            }
            $k->forceFill(['status' => 'arsip', 'archived_at' => now(), 'versi' => $k->versi + 1, 'diubah_oleh_id' => $actor->id])->save();
            Audit::record($actor, 'konten.diarsipkan', 'konten', $k->id, ['jenis' => $k->jenis]);
            Outbox::record('konten.diarsipkan', 'konten', $k->id, ['jenis' => $k->jenis, 'slug' => $k->slug_terbit ?? $k->slug]);

            return $k;
        });
    }

    /**
     * Pulihkan konten arsip menjadi draf (BUSINESS_RULES BR18, ADR34). Tidak langsung tampil di website:
     * pengurus meninjau lalu menekan Terbitkan. Slug tetap miliknya karena slug arsip tidak pernah dilepas.
     */
    public static function restore(Akun $actor, Konten $konten, int $versi): Konten
    {
        return DB::transaction(function () use ($actor, $konten, $versi) {
            $k = self::lock($konten->id, $versi);
            if ($k->status !== 'arsip') {
                throw new DomainConflict('NOT_ARCHIVED', 'Konten tidak sedang diarsipkan.');
            }
            $k->forceFill(['status' => 'draf', 'archived_at' => null, 'versi' => $k->versi + 1, 'diubah_oleh_id' => $actor->id])->save();
            Audit::record($actor, 'konten.dipulihkan', 'konten', $k->id, ['jenis' => $k->jenis]);

            return $k;
        });
    }

    private static function lock(string $id, int $versi): Konten
    {
        $k = Konten::whereKey($id)->lockForUpdate()->firstOrFail();
        if ($k->versi !== $versi) {
            throw new DomainConflict('VERSION_CONFLICT', 'Konten sudah diubah pengurus lain. Muat ulang lalu ulangi perubahan Anda.');
        }

        return $k;
    }

    /** Slug baru tidak boleh dipakai konten lain atau menjadi redirect milik konten lain. */
    private static function assertSlugFree(string $jenis, string $slug, ?string $selfId): void
    {
        if (! preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) || strlen($slug) > 120) {
            throw new DomainConflict('VALIDATION_FAILED', 'Slug hanya huruf kecil, angka, dan tanda hubung (maks. 120).', 422);
        }
        $taken = Konten::where('jenis', $jenis)->where('slug', $slug)->when($selfId, fn ($q) => $q->whereKeyNot($selfId))->exists()
            || DB::table('konten_slug_lama')->where('jenis', $jenis)->where('slug', $slug)->when($selfId, fn ($q) => $q->where('konten_id', '!=', $selfId))->exists();
        if ($taken) {
            throw self::slugTaken();
        }
    }

    private static function slugTaken(): DomainConflict
    {
        return new DomainConflict('SLUG_TAKEN', 'Slug sudah dipakai konten lain. Pilih slug lain.');
    }

    /** Status untuk admin: ada perubahan draf yang belum terbit. */
    public static function adminView(Konten $k): array
    {
        return [
            'id' => $k->id,
            'jenis' => $k->jenis,
            'slug' => $k->slug,
            'status' => $k->status,
            'versi' => $k->versi,
            'draf' => $k->draf,
            'slug_terbit' => $k->slug_terbit,
            'ada_perubahan_belum_terbit' => $k->status === 'terbit' && $k->versi !== $k->versi_terbit,
            'sumber' => $k->sumber,
            'published_at' => Present::ts($k->published_at),
            'updated_at' => Present::ts($k->updated_at ?? $k->created_at),
            'archived_at' => Present::ts($k->archived_at),
        ];
    }

    /** Bentuk publik: hanya snapshot terbit, tanpa ID akun pembuat/pengubah. */
    public static function publicView(Konten $k): array
    {
        return ['slug' => $k->slug_terbit, 'published_at' => Present::ts($k->published_at)] + $k->terbit;
    }

    /** Media yang dirujuk payload (untuk pemeriksaan siap/terbit). @return list<string> */
    public static function mediaIds(string $jenis, array $payload): array
    {
        return array_values(array_filter(match ($jenis) {
            'artikel' => [$payload['cover_media_id'] ?? null],
            'album' => array_column($payload['photos'] ?? [], 'media_id'),
            'fasilitas' => [$payload['media_id'] ?? null],
            default => [],
        }));
    }

    public static function mediaExists(string $id): bool
    {
        return KontenMedia::whereKey($id)->exists();
    }
}
