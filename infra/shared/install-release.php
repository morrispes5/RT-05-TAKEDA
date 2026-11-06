<?php

declare(strict_types=1);

/*
 * RT05 TAKEDA — installer paket shared (M16.4). SEMENTARA: unggah ke public_html dengan nama acak, ganti TOKEN,
 * jalankan lewat browser/curl, lalu HAPUS. Tidak membaca atau mencetak kredensial.
 *
 *   ?k=TOKEN&step=status                       versi PHP, ekstensi, rilis terpasang, .env ada/tidak
 *   ?k=TOKEN&step=install&url=...&sha=...      unduh zip rilis GitHub, cek sha256, ekstrak ke home, buat .env bila belum ada
 *   ?k=TOKEN&step=finalize                     config/route/view cache, status jembatan, smoke database (setelah sandi DB diisi)
 *
 * .env API/web dibuat dari .env.example di paket + nilai production di bawah; APP_KEY acak dibuat di sini. DB_PASSWORD
 * berisi penanda ISI_SANDI_RT05_APP: pemilik menempel sandi rt05_app sendiri di File Manager.
 */

const TOKEN = '__GANTI_TOKEN__';
const PHP_CLI = '/opt/alt/php84/usr/bin/php';
const DOMAIN = 'rt05takeda.com';
const NEON_HOST = 'ep-snowy-union-b33dixmf-pooler.ap-southeast-1.aws.neon.tech';
const URL_PREFIX = 'https://github.com/morrispes5/RT-05-TAKEDA/releases/download/';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
if (TOKEN === '__GANTI_TOKEN__' || ! hash_equals(TOKEN, (string) ($_GET['k'] ?? ''))) {
    http_response_code(404);
    exit;
}
set_time_limit(300);
$home = dirname((string) $_SERVER['DOCUMENT_ROOT']);

function run(string $cmd): string
{
    exec($cmd.' 2>&1', $out, $code);

    return implode("\n", $out)."\n[exit $code]\n";
}

function env_set(string $env, array $values): string
{
    foreach ($values as $key => $value) {
        $line = $key.'='.$value;
        $pattern = '/^#?\s*'.preg_quote($key, '/').'=.*$/m';
        $env = preg_match($pattern, $env) ? (string) preg_replace($pattern, addcslashes($line, '\\$'), $env, 1) : rtrim($env)."\n".$line."\n";
    }

    return $env;
}

function write_env(string $dir, array $values): string
{
    $target = "$dir/.env";
    if (is_file($target)) {
        return "$target sudah ada (tidak diubah)\n";
    }
    $example = (string) @file_get_contents("$dir/.env.example");
    if ($example === '') {
        return "GAGAL: $dir/.env.example tidak ada\n";
    }
    $values = ['APP_KEY' => 'base64:'.base64_encode(random_bytes(32))] + $values;
    $old = umask(0077);
    file_put_contents($target, env_set($example, $values));
    umask($old);
    chmod($target, 0600);

    return "$target dibuat (APP_KEY baru, izin 0600)\n";
}

$step = (string) ($_GET['step'] ?? 'status');

if ($step === 'status') {
    echo 'waktu '.gmdate('c')."\nPHP web ".PHP_VERSION.' sapi='.PHP_SAPI.' opcache='.(function_exists('opcache_get_status') && @opcache_get_status(false) ? 'aktif' : 'mati')."\n";
    $missing = array_values(array_filter(['pdo_pgsql', 'intl', 'mbstring', 'gd', 'zip', 'fileinfo', 'openssl', 'curl', 'bcmath', 'sodium', 'dom', 'xml', 'tokenizer', 'ctype', 'pcntl', 'posix'], fn ($e) => ! extension_loaded($e)));
    echo 'ekstensi hilang (web): '.($missing === [] ? '-' : implode(',', $missing))."\n";
    echo 'exec di web: '.(function_exists('exec') ? 'ada' : 'DIBLOKIR (finalize lewat cron)')."\n";
    echo 'upload_max_filesize='.ini_get('upload_max_filesize').' post_max_size='.ini_get('post_max_size').' memory_limit='.ini_get('memory_limit')."\n";
    echo 'rilis: '.(@file_get_contents("$home/rt05/RELEASE.json") ?: '-')."\n";
    foreach (['api', 'web'] as $app) {
        $env = (string) @file_get_contents("$home/rt05/$app/.env");
        echo "$app/.env: ".($env === '' ? 'belum ada' : 'ada'.(str_contains($env, 'ISI_SANDI_RT05_APP') ? ' (sandi DB BELUM diisi)' : ''))."\n";
    }
    echo 'disk bebas home: '.round((float) disk_free_space($home) / 1e9, 1)." GB\n";
    exit;
}

if ($step === 'install') {
    $url = (string) ($_GET['url'] ?? '');
    $sha = strtolower((string) ($_GET['sha'] ?? ''));
    if (! preg_match('/^[0-9a-f]{64}$/', $sha)) {
        exit("GAGAL: sha256 64 hex wajib\n");
    }
    $zipPath = "$home/rt05-release.zip";
    $parts = glob("$home/rt05-upload/rt05-release.part-*") ?: [];
    sort($parts);
    if ($url === '' && $parts !== []) {
        // Zip diunggah lewat File Manager dalam potongan < 10 MB; sambung sesuai urutan nama.
        $out = fopen($zipPath, 'w');
        foreach ($parts as $part) {
            $in = fopen($part, 'r');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }
        fclose($out);
        echo 'disambung dari '.count($parts)." potongan\n";
    } elseif (str_starts_with($url, URL_PREFIX)) {
        $fh = fopen($zipPath, 'w');
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_FILE => $fh, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 180, CURLOPT_FAILONERROR => true, CURLOPT_USERAGENT => 'rt05-installer/1']);
        $ok = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fh);
        if (! $ok) {
            @unlink($zipPath);
            exit("GAGAL unduh: $err\n");
        }
    } else {
        exit("GAGAL: tidak ada potongan di ~/rt05-upload dan url bukan rilis GitHub repo RT05\n");
    }
    $got = hash_file('sha256', $zipPath);
    echo 'zip '.round(filesize($zipPath) / 1e6, 1)." MB sha256 $got\n";
    if (! hash_equals($sha, $got)) {
        @unlink($zipPath);
        exit("GAGAL: sha256 tidak cocok, tidak diekstrak\n");
    }
    $zip = new ZipArchive;
    if ($zip->open($zipPath) !== true) {
        exit("GAGAL: zip rusak\n");
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if (str_contains($name, '..') || str_starts_with($name, '/') || ! preg_match('#^(rt05|public_html)/#', $name)) {
            exit("GAGAL: entri zip tidak sah: $name\n");
        }
    }
    $zip->extractTo($home);
    $zip->close();
    unlink($zipPath);
    foreach ($parts as $part) {
        unlink($part);
    }
    @rmdir("$home/rt05-upload");
    echo "diekstrak ke $home (rt05/, public_html/)\n";
    echo 'rilis: '.file_get_contents("$home/rt05/RELEASE.json")."\n";

    echo write_env("$home/rt05/api", [
        'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_URL' => 'https://'.DOMAIN, 'LOG_LEVEL' => 'warning',
        'DB_CONNECTION' => 'pgsql', 'DATABASE_URL' => '', 'DATABASE_URL_UNPOOLED' => '',
        'DB_HOST' => "$home/rt05-pg", 'DB_PORT' => '5432', 'DB_DATABASE' => 'rt05_takeda', 'DB_USERNAME' => 'rt05_app',
        'DB_PASSWORD' => 'ISI_SANDI_RT05_APP', 'DB_SSLMODE' => 'disable', 'DB_SSLROOTCERT' => '',
        'RT05_DEPLOYMENT_PROFILE' => 'shared', 'QUEUE_CONNECTION' => 'database', 'RT05_OUTBOX_QUEUE_CONNECTION' => 'database',
        'CACHE_STORE' => 'file', 'SESSION_DRIVER' => 'file', 'RT05_HEARTBEAT_STORE' => 'file',
        'RT05_NEON_BRIDGE' => 'true', 'RT05_NEON_HOST' => NEON_HOST, 'RT05_BRIDGE_PHP' => PHP_CLI,
        // Email nyata menyusul di M16.5 (Resend); sampai itu email ditulis ke log, bukan dikirim.
        'MAIL_MAILER' => 'log', 'MAIL_TIMEOUT' => '15', 'MAIL_FROM_ADDRESS' => '"noreply@'.DOMAIN.'"',
        'RT05_FINANCE_V2' => 'true', 'RT05_PANIC_ENABLED' => 'false', 'RT05_PANIC_AUDIENCE' => 'restricted',
    ]);
    echo write_env("$home/rt05/web", [
        'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_URL' => 'https://'.DOMAIN, 'LOG_LEVEL' => 'warning',
        'RT05_CONTENT_API' => 'https://'.DOMAIN,
        // Indeks Google baru dibuka saat M16.10 (Search Console).
        'RT05_SEARCH_INDEXING' => 'false', 'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'file',
    ]);
    exit("Berikutnya: pemilik mengisi DB_PASSWORD di rt05/api/.env, lalu step=finalize\n");
}

if ($step === 'finalize') {
    if (! function_exists('exec')) {
        exit("exec diblokir PHP web: jalankan finalize lewat cron sekali jalan (php artisan config:cache, route:cache, view:cache, rt05:neon-bridge start, rt05:db-smoke)
");
    }
    $env = (string) @file_get_contents("$home/rt05/api/.env");
    if ($env === '' || str_contains($env, 'ISI_SANDI_RT05_APP')) {
        exit("GAGAL: DB_PASSWORD di rt05/api/.env belum diisi\n");
    }
    foreach (['api', 'web'] as $app) {
        $dir = escapeshellarg("$home/rt05/$app");
        echo "== $app\n".run("cd $dir && ".PHP_CLI.' artisan optimize:clear && '.PHP_CLI.' artisan config:cache && '
            .PHP_CLI.' artisan route:cache && '.PHP_CLI.' artisan view:cache');
    }
    $api = escapeshellarg("$home/rt05/api");
    echo "== jembatan\n".run("cd $api && ".PHP_CLI.' artisan rt05:neon-bridge start');
    echo "== smoke database (role runtime, DDL harus denied)\n".run("cd $api && ".PHP_CLI.' artisan rt05:db-smoke --connection=pgsql');
    echo "== migrasi tertunda (harus kosong; DDL tidak dijalankan dari hosting)\n".run("cd $api && ".PHP_CLI.' artisan about --only=environment');
    exit;
}

http_response_code(400);
echo "step tidak dikenal\n";
