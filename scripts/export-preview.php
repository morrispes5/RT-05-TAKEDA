<?php

// Render the actual Laravel routes without a database or a background server.
use App\Support\SiteContent;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';
$root = realpath(__DIR__.'/..');
foreach (['APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'APP_URL' => 'https://rt05takeda.vercel.app', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32))] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$fs = new Filesystem;
$normalize = static function (string $html): string {
    $html = str_replace(["\r\n", 'https://rt05takeda.vercel.app/'], ["\n", '/'], $html);
    $html = str_replace('content="/images/og-image.jpg"', 'content="https://rt05takeda.vercel.app/images/og-image.jpg"', $html);

    return rtrim(preg_replace('/[\t ]+$/m', '', $html))."\n";
};
$stage = $root.'/.preview-stage';
// These fixed generated directories must remain immediate children of this repo.
foreach ([$stage, $root.'/preview'] as $directory) {
    if (is_link($directory) || (file_exists($directory) && realpath(dirname($directory)) !== $root)) {
        throw new RuntimeException('Unsafe output directory');
    }
}
$fs->ensureDirectoryExists($stage);
$fs->cleanDirectory($stage);
foreach (SiteContent::paths() as $path) {
    $request = Request::create('https://rt05takeda.vercel.app'.$path);
    $response = $kernel->handle($request);
    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException("Export failed for $path: ".$response->getStatusCode());
    }
    $html = $normalize($response->getContent());
    $target = $stage.($path === '/' ? '/index.html' : $path.'.html');
    $fs->ensureDirectoryExists(dirname($target));
    $fs->put($target, $html);
    $kernel->terminate($request, $response);
    echo "Rendered $path\n";
}
$fs->put($stage.'/404.html', $normalize(view('errors.404')->render()));
$fs->copyDirectory($root.'/public/build', $stage.'/build');
$fs->copyDirectory($root.'/public/images', $stage.'/images');
$fs->delete($stage.'/build/manifest.json');
$fs->deleteDirectory($root.'/preview');
$fs->moveDirectory($stage, $root.'/preview');
echo count(SiteContent::paths())." routes exported to preview/.\n";
