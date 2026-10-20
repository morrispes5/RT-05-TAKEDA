<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sumber konten website. M14: artikel dan album dibaca dari API konten publik RT05 (snapshot terbit,
 * ADR28) bila `RT05_CONTENT_API` diset; hasil disimpan sebentar (fresh) dan sebagai last-good. API mati →
 * last-good → JSON baked di resources/data (sama dengan preview statis). Website tidak pernah membaca
 * draf atau database. Fasilitas dan profil tetap dari halaman yang sudah diterima (dikelola di mobile).
 */
class SiteContent
{
    private const FRESH = 'rt05:site-content:fresh';

    private const LAST_GOOD = 'rt05:site-content:last-good';

    /** Setelah gagal, jangan coba API lagi selama ini (detik) agar halaman tidak menunggu timeout tiap request. */
    private const DOWN = 'rt05:site-content:down';

    /** @var array{articles: array, albums: array, source: string}|null */
    private static ?array $loaded = null;

    public static function photos(): array
    {
        return json_decode(file_get_contents(resource_path('data/dokumentasi.json')), true, flags: JSON_THROW_ON_ERROR)['foto'];
    }

    public static function articles(): array
    {
        $articles = self::load()['articles'];
        foreach ($articles as &$article) {
            $text = $article['intro'].' '.implode(' ', array_column($article['sections'], 'body'));
            $article['minutes'] = max(1, (int) ceil(count(preg_split('/\\s+/u', trim($text))) / 180));
        }

        return $articles;
    }

    public static function albums(): array
    {
        return self::load()['albums'];
    }

    public static function paths(): array
    {
        return array_merge(
            ['/', '/profil', '/fasilitas', '/dokumentasi', '/artikel', '/kontak', '/pengurus'],
            array_map(fn ($a) => '/artikel/'.$a['slug'], self::articles()),
            array_map(fn ($a) => '/dokumentasi/'.$a['slug'], self::albums()),
            array_map(fn ($screen) => '/pengurus/'.$screen, ['masuk', 'artikel', 'artikel/editor', 'dokumentasi', 'dokumentasi/editor', 'pratinjau'])
        );
    }

    /** api | cache | baked — untuk diagnosis dan tes. */
    public static function source(): string
    {
        return self::load()['source'];
    }

    public static function forget(): void
    {
        self::$loaded = null;
    }

    private static function load(): array
    {
        if (self::$loaded !== null) {
            return self::$loaded;
        }
        $base = rtrim((string) config('services.rt05_content.api'), '/');
        if ($base === '') {
            return self::$loaded = self::baked();
        }
        $store = Cache::store('file');
        if (is_array($fresh = $store->get(self::FRESH))) {
            return self::$loaded = $fresh + ['source' => 'api'];
        }
        if ($store->has(self::DOWN)) {
            return self::$loaded = self::fallback($store);
        }
        try {
            $get = fn (string $path) => Http::baseUrl($base)->acceptJson()
                ->connectTimeout(max(1, (int) config('services.rt05_content.timeout', 3)))
                ->timeout(max(1, (int) config('services.rt05_content.timeout', 3)))
                ->get($path, ['per_page' => 100])->throw()->json('data');
            $data = [
                'articles' => array_map([self::class, 'article'], $get('/api/v1/public/articles') ?? []),
                'albums' => array_map([self::class, 'album'], $get('/api/v1/public/albums') ?? []),
            ];
            $store->put(self::FRESH, $data, max(1, (int) config('services.rt05_content.fresh_seconds', 60)));
            $store->forever(self::LAST_GOOD, $data);

            return self::$loaded = $data + ['source' => 'api'];
        } catch (Throwable $e) {
            Log::warning('site_content.api_unavailable', ['exception' => class_basename($e)]);
            $store->put(self::DOWN, true, 30);

            return self::$loaded = self::fallback($store);
        }
    }

    private static function fallback($store): array
    {
        $last = $store->get(self::LAST_GOOD);

        return is_array($last) ? $last + ['source' => 'cache'] : self::baked();
    }

    private static function baked(): array
    {
        $read = fn (string $f) => json_decode(file_get_contents(resource_path("data/$f")), true, flags: JSON_THROW_ON_ERROR);

        return ['articles' => $read('artikel.json'), 'albums' => $read('album.json'), 'source' => 'baked'];
    }

    /** Snapshot artikel API → bentuk yang dipakai view. */
    private static function article(array $a): array
    {
        return [
            'slug' => $a['slug'], 'title' => $a['title'], 'category' => $a['category'], 'art' => $a['art'] ?? 'home',
            'summary' => $a['summary'], 'intro' => $a['intro'], 'sections' => $a['sections'] ?? [],
            'note' => $a['note'] ?? null, 'source' => $a['source'] ?? null,
            // UAT M15: foto sampul asli dari CMS menggantikan ilustrasi SVG; tanpa sampul tetap SVG.
            'cover' => empty($a['cover']['url']) ? null : [
                'src' => $a['cover']['url'], 'srcset' => $a['cover']['srcset'] ?? null,
                'width' => $a['cover']['lebar'] ?? null, 'height' => $a['cover']['tinggi'] ?? null,
                'alt' => $a['cover_alt'] ?? $a['title'],
            ],
        ];
    }

    /** Snapshot album API → bentuk view (foto statis /images/… atau /api/v1/public/media/{id}). */
    private static function album(array $a): array
    {
        return [
            'id' => 'album-'.$a['slug'], 'slug' => $a['slug'], 'title' => $a['title'], 'description' => $a['description'],
            'date' => $a['date'] ?? null, 'coverId' => $a['cover']['id'] ?? null,
            'photos' => array_map(fn (array $p) => [
                'id' => $p['id'], 'src' => $p['url'], 'srcset' => $p['srcset'] ?? null,
                'width' => $p['lebar'], 'height' => $p['tinggi'], 'alt' => $p['alt'], 'caption' => $p['caption'] ?? null,
            ], $a['photos'] ?? []),
        ];
    }
}
