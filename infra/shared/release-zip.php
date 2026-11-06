<?php

declare(strict_types=1);

/*
 * RT05 TAKEDA — kemas dan periksa paket rilis shared (M16.3). Dipakai build-release.sh dan workflow Shared release.
 *   php infra/shared/release-zip.php pack <folder-paket> <berkas.zip>
 *   php infra/shared/release-zip.php check <berkas.zip>
 */

[$cmd, $a, $b] = array_pad(array_slice($argv, 1), 3, null);

function fail(string $msg): never
{
    fwrite(STDERR, "GAGAL: $msg\n");
    exit(1);
}

if ($cmd === 'pack' && $a !== null && $b !== null) {
    $root = realpath($a) ?: fail("folder $a tidak ada");
    $zip = new ZipArchive;
    if ($zip->open($b, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        fail("tidak bisa menulis $b");
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );
    $n = 0;
    foreach ($it as $file) {
        $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        if ($file->isDir()) {
            $zip->addEmptyDir($rel);
        } else {
            $zip->addFile($file->getPathname(), $rel);
            $zip->setExternalAttributesName($rel, ZipArchive::OPSYS_UNIX, 0644 << 16);
            $n++;
        }
    }
    $zip->close();
    echo "dikemas $n berkas → $b\n";
    exit(0);
}

if ($cmd === 'check' && $a !== null) {
    $zip = new ZipArchive;
    if ($zip->open($a) !== true) {
        fail("tidak bisa membuka $a");
    }
    $names = []; // path => true bila folder
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $raw = (string) $zip->getNameIndex($i);
        $names[rtrim($raw, '/')] = str_ends_with($raw, '/');
    }
    $required = [
        'rt05/RELEASE.json',
        'rt05/api/artisan', 'rt05/api/vendor/autoload.php', 'rt05/api/bootstrap/app.php',
        'rt05/api/bin/neon-ws-bridge.php', 'rt05/api/storage/framework/cache', 'rt05/api/bootstrap/cache',
        'rt05/web/artisan', 'rt05/web/vendor/autoload.php', 'rt05/web/bootstrap/app.php',
        'rt05/web/resources/data', 'rt05/web/storage/framework/views',
        'rt05/infra/sql/grant-runtime-role.sql',
        'public_html/index.php', 'public_html/index-api.php', 'public_html/.htaccess',
        'public_html/build/manifest.json', 'public_html/auth-assets', 'public_html/images', 'public_html/favicon.ico',
    ];
    foreach ($required as $path) {
        if (! isset($names[$path])) {
            fail("wajib ada: $path");
        }
    }
    foreach ($names as $path => $isDir) {
        $base = basename($path);
        if ($base === '.env' || preg_match('/^\.env\.(?!example$)/', $base)) {
            fail("berkas env terlarang: $path");
        }
        if (preg_match('#(^|/)(node_modules|tests|\.git)(/|$)#', $path)) {
            fail("folder pengembangan terlarang: $path");
        }
        if (preg_match('#^rt05/(api|web)/vendor/(phpunit|mockery|fakerphp|laravel/pint|nunomaduro/collision)(/|$)#', $path)) {
            fail("paket dev di vendor: $path");
        }
        if (preg_match('#^rt05/(api|web)/public(/|$)#', $path)) {
            fail("public aplikasi harus dipindah ke public_html: $path");
        }
        if (preg_match('#^public_html/.*\.(sql|log|sqlite|key|pem)$#i', $path) || str_starts_with($path, 'public_html/vendor')) {
            fail("berkas privat di public_html: $path");
        }
        if (! $isDir && preg_match('#^rt05/(api|web)/storage/(logs|framework/(cache|sessions|views)|app)/.+#', $path)
            && $base !== '.gitignore') {
            fail("isi storage ikut terkemas: $path");
        }
    }
    $htaccess = (string) $zip->getFromName('public_html/.htaccess');
    foreach (['index-api.php [L,QSA]', 'sanctum/csrf-cookie', 'Options -Indexes'] as $needle) {
        if (! str_contains($htaccess, $needle)) {
            fail(".htaccess tanpa: $needle");
        }
    }
    foreach (['public_html/index.php' => '/rt05/web', 'public_html/index-api.php' => '/rt05/api'] as $file => $needle) {
        if (! str_contains((string) $zip->getFromName($file), $needle)) {
            fail("$file tidak menunjuk $needle");
        }
    }
    $release = json_decode((string) $zip->getFromName('rt05/RELEASE.json'), true);
    if (! is_array($release) || ! preg_match('/^[0-9a-f]{40}$/', (string) ($release['commit'] ?? ''))) {
        fail('RELEASE.json tanpa commit');
    }
    echo 'OK: '.count($names)." entri, commit {$release['commit']}, sha256 ".hash_file('sha256', $a)."\n";
    exit(0);
}

fail('pakai: pack <folder> <zip> | check <zip>');
