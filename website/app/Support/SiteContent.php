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
 * draf atau database. Fasilitas tetap dari halaman yang sudah diterima. ADR35: profil RT05 (cerita Taman Kedaung dan
 * pengurus) dibaca dari /api/v1/public/site-profile dengan cadangan yang sama; profil belum terbit → resources/data/profil.json.
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

    /** Profil RT05 terbit: wilayah, ringkasan, tentang (paragraf), pengurus[]. */
    public static function profile(): array
    {
        $profile = self::load()['profile'] ?? null;

        return is_array($profile) ? $profile : self::bakedProfile();
    }

    /** Paragraf dari teks yang dipisah baris kosong (cerita Taman Kedaung yang diedit pengurus). */
    public static function paragraphs(?string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/u', (string) $text) ?: []), fn ($p) => $p !== ''));
    }

    public static function paths(): array
    {
        return array_merge(
            ['/', '/profil', '/fasilitas', '/dokumentasi', '/artikel', '/kontak', '/aplikasi', '/kebijakan-privasi', '/pengurus'],
            array_map(fn ($a) => '/artikel/'.$a['slug'], self::articles()),
            array_map(fn ($a) => '/dokumentasi/'.$a['slug'], self::albums()),
            array_map(fn ($screen) => '/pengurus/'.$screen, ['masuk', 'artikel', 'artikel/editor', 'dokumentasi', 'dokumentasi/editor', 'profil', 'pratinjau'])
        );
    }

    /** M16: halaman publik untuk sitemap.xml (tanpa area pengurus). */
    public static function publicPaths(): array
    {
        return array_values(array_filter(self::paths(), fn (string $path) => ! str_starts_with($path, '/pengurus')));
    }

    /** M16: mesin pencari boleh mengindeks hanya di production (RT05_SEARCH_INDEXING=true). */
    public static function indexing(): bool
    {
        return (bool) config('services.rt05_site.indexing');
    }

    /** Kebijakan Privasi publik; isi sama dengan aplikasi (mobile/lib/features/auth/privacy_policy.dart, ADR35). */
    public static function privacy(): array
    {
        return json_decode(file_get_contents(resource_path('data/privasi.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * M16: metadata APK rilis (versi.json dari workflow Mobile release APK). Sumber RT05_APK_META: path berkas atau URL;
     * kosong = public/unduh/versi.json. Tidak ada atau rusak → null (halaman unduh menampilkan "segera tersedia").
     *
     * @return array{versi: string, build: int, tanggal: string, catatan: list<string>, berkas: list<array{abi: string, url: string, ukuran: int, sha256: string}>}|null
     */
    public static function apk(): ?array
    {
        $source = trim((string) config('services.rt05_site.apk_meta'));
        try {
            if (preg_match('#^https?://#', $source)) {
                $raw = Cache::store('file')->remember('rt05:apk-meta', 300, fn () => Http::acceptJson()->connectTimeout(2)->timeout(3)->get($source)->throw()->body());
            } else {
                $path = $source !== '' ? $source : public_path('unduh/versi.json');
                $raw = is_file($path) ? file_get_contents($path) : null;
            }

            return is_string($raw) ? self::apkShape(json_decode($raw, true, flags: JSON_THROW_ON_ERROR)) : null;
        } catch (Throwable $e) {
            Log::info('site_content.apk_meta_unavailable', ['exception' => class_basename($e)]);

            return null;
        }
    }

    private static function apkShape(mixed $m): ?array
    {
        if (! is_array($m) || ! is_string($m['versi'] ?? null) || ! is_int($m['build'] ?? null) || ! is_array($m['berkas'] ?? null)) {
            return null;
        }
        $files = [];
        foreach ($m['berkas'] as $f) {
            $url = is_array($f) ? (string) ($f['url'] ?? '') : '';
            // Hanya tautan ke folder unduhan situs sendiri atau HTTPS; tanpa javascript:/data:.
            if (! in_array($f['abi'] ?? null, ['arm64-v8a', 'armeabi-v7a'], true) || ! preg_match('#^(/unduh/|https://)[^\s"<>]+\.apk$#', $url)
                || ! is_int($f['ukuran'] ?? null) || ! preg_match('/^[0-9a-f]{64}$/', (string) ($f['sha256'] ?? ''))) {
                continue;
            }
            $files[] = ['abi' => $f['abi'], 'url' => $url, 'ukuran' => $f['ukuran'], 'sha256' => $f['sha256']];
        }
        usort($files, fn ($a, $b) => $a['abi'] === 'arm64-v8a' ? -1 : ($b['abi'] === 'arm64-v8a' ? 1 : 0));

        return $files === [] ? null : [
            'versi' => $m['versi'], 'build' => $m['build'], 'tanggal' => (string) ($m['tanggal'] ?? ''),
            'catatan' => array_values(array_filter($m['catatan'] ?? [], 'is_string')), 'berkas' => $files,
        ];
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
                'profile' => self::remoteProfile($base),
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

        return ['articles' => $read('artikel.json'), 'albums' => $read('album.json'), 'profile' => self::bakedProfile(), 'source' => 'baked'];
    }

    private static function bakedProfile(): array
    {
        return self::profileShape(json_decode(file_get_contents(resource_path('data/profil.json')), true, flags: JSON_THROW_ON_ERROR));
    }

    /** Profil belum pernah terbit (404) atau rusak: pakai cadangan tanpa menggagalkan artikel/album. */
    private static function remoteProfile(string $base): array
    {
        try {
            $data = Http::baseUrl($base)->acceptJson()
                ->connectTimeout(max(1, (int) config('services.rt05_content.timeout', 3)))
                ->timeout(max(1, (int) config('services.rt05_content.timeout', 3)))
                ->get('/api/v1/public/site-profile')->throw()->json('data');

            return is_array($data) && ! empty($data['pengurus']) ? self::profileShape($data) : self::bakedProfile();
        } catch (Throwable $e) {
            Log::info('site_content.profile_unavailable', ['exception' => class_basename($e)]);

            return self::bakedProfile();
        }
    }

    private static function profileShape(array $p): array
    {
        return [
            'wilayah' => (string) ($p['wilayah'] ?? ''),
            'ringkasan' => (string) ($p['ringkasan'] ?? ''),
            'tentang' => self::paragraphs($p['tentang'] ?? null),
            'pengurus' => array_values(array_map(fn (array $o) => [
                'jabatan' => (string) ($o['jabatan'] ?? ''), 'nama' => (string) ($o['nama'] ?? ''), 'catatan' => $o['catatan'] ?? null,
            ], array_filter($p['pengurus'] ?? [], 'is_array'))),
        ];
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
