<?php

/*
| Bootstrap PHPUnit: migration dijalankan sekali melalui koneksi pgsql_migrations (role pemilik),
| lalu tes berjalan memakai role runtime DML seperti produksi. Menolak database yang bukan
| *_test pada host lokal agar tes tidak pernah menyentuh Neon dev/staging/production.
*/

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Support\ConfigurationUrlParser;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

foreach (['pgsql', 'pgsql_migrations'] as $connection) {
    $config = (new ConfigurationUrlParser)->parseConfiguration(config("database.connections.$connection"));
    $local = in_array($config['host'] ?? '', ['127.0.0.1', 'localhost', '::1', 'postgres'], true);
    if (! $local || ! str_ends_with((string) ($config['database'] ?? ''), '_test')) {
        fwrite(STDERR, "Tes ditolak: koneksi $connection harus database *_test pada host lokal.\n");
        exit(1);
    }
}

if (getenv('RT05_SKIP_TEST_MIGRATIONS') !== '1') {
    $exit = $app->make(Kernel::class)->call('migrate:fresh', ['--database' => 'pgsql_migrations', '--force' => true]);
    if ($exit !== 0) {
        fwrite(STDERR, "Migration tes gagal.\n");
        exit(1);
    }
}

$app->flush();
// Kembalikan error/exception handler global agar PHPUnit tidak menandai tes sebagai risky.
HandleExceptions::flushState();
