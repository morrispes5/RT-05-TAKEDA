<?php

namespace App\Domain\Content;

use App\Models\Konten;
use Illuminate\Support\Facades\DB;

/**
 * Impor katalog konten yang sudah diterima di website (M13): artikel.json dan album.json salinan
 * byte-identik dari website/resources/data, fasilitas.json dan profil.json diekstrak kata per kata
 * dari Blade website. Tidak mengarang fakta. Idempotent: slug yang sudah ada (atau pernah dipakai
 * lalu diganti pengurus) dilewati sehingga suntingan pengurus tidak pernah ditimpa.
 */
final class Catalog
{
    public static function path(): string
    {
        return database_path('seeders/konten');
    }

    /** @return array{dibuat: int, dilewati: int} */
    public static function import(?string $dir = null): array
    {
        $dir ??= self::path();
        $read = fn (string $f) => json_decode((string) file_get_contents("$dir/$f"), true, 512, JSON_THROW_ON_ERROR);
        $made = $skipped = 0;
        $put = function (string $jenis, string $slug, int $urutan, callable $payload) use (&$made, &$skipped) {
            $exists = Konten::where('jenis', $jenis)->where(fn ($q) => $q->where('slug', $slug)->orWhere('slug_terbit', $slug))->exists()
                || DB::table('konten_slug_lama')->where('jenis', $jenis)->where('slug', $slug)->exists()
                || ($jenis === 'profil' && Konten::where('jenis', 'profil')->exists());
            if ($exists) {
                $skipped++;

                return;
            }
            DB::transaction(function () use ($jenis, $slug, $urutan, $payload) {
                $data = Contents::payload($jenis, $payload());
                $k = Konten::create(['jenis' => $jenis, 'slug' => $slug, 'draf' => $data, 'status' => 'draf', 'versi' => 1, 'sumber' => 'impor', 'urutan' => $urutan]);
                Contents::publish(null, $k, 1);
            });
            $made++;
        };

        foreach ($read('artikel.json') as $i => $a) {
            $put('artikel', $a['slug'], $i + 1, fn () => [
                'title' => $a['title'], 'category' => $a['category'], 'art' => $a['art'] ?? null, 'summary' => $a['summary'],
                'intro' => $a['intro'], 'sections' => $a['sections'], 'note' => $a['note'] ?? null, 'source' => $a['source'] ?? null,
            ]);
        }
        foreach ($read('album.json') as $i => $al) {
            $put('album', $al['slug'], $i + 1, function () use ($al) {
                $photos = array_map(function ($p) {
                    $m = Media::staticPhoto($p['src'], (int) $p['width'], (int) $p['height'], ['srcset' => $p['srcset'] ?? null]);

                    return ['ref' => $p['id'], 'media_id' => $m->id, 'alt' => $p['alt'], 'caption' => $p['caption'] ?? null];
                }, $al['photos']);
                $cover = collect($photos)->firstWhere('ref', $al['coverId'] ?? null)['media_id'] ?? null;

                return [
                    'title' => $al['title'], 'description' => $al['description'], 'date' => $al['date'] ?? null, 'cover_media_id' => $cover,
                    'photos' => array_map(fn ($p) => array_diff_key($p, ['ref' => 1]), $photos),
                ];
            });
        }
        foreach ($read('fasilitas.json') as $i => $f) {
            $put('fasilitas', $f['slug'], $i + 1, function () use ($f) {
                $ph = $f['photo'];
                $url = fn (int $w) => "/images/dokumentasi/{$ph['slug']}-{$w}.webp";
                $m = Media::staticPhoto($url(min(960, max($ph['ukuran']))), (int) $ph['lebar'], (int) $ph['tinggi'],
                    ['srcset' => implode(', ', array_map(fn ($w) => $url($w)." {$w}w", $ph['ukuran']))]);

                return ['title' => $f['title'], 'description' => $f['description'], 'media_id' => $m->id, 'alt' => $ph['alt']];
            });
        }
        $put('profil', 'profil', 0, fn () => $read('profil.json'));

        return ['dibuat' => $made, 'dilewati' => $skipped];
    }
}
