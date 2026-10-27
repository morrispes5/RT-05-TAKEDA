<?php

/**
 * RT05 TAKEDA — cek kesiapan shared hosting (M16, sebelum deploy apa pun).
 *
 * Cara pakai (docs/RUNBOOK_PRODUCTION.md bagian 1a):
 * 1. Unggah berkas kredensial uji `rt05-cek.ini` ke folder home DI LUAR public_html (contoh /home/USER/rt05-cek.ini).
 * 2. Unggah berkas ini ke public_html dengan nama acak, buka sekali di browser, simpan hasilnya.
 * 3. HAPUS berkas ini dan rt05-cek.ini dari hosting, lalu kabari agent agar role uji Neon dihapus.
 *
 * Tidak ada kata sandi di berkas ini. Hasil hanya menampilkan OK/GAGAL, versi, dan waktu; tanpa isi kredensial.
 */
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$rows = [];
$add = function (string $item, bool $ok, string $note = '') use (&$rows) {
    $rows[] = sprintf('%-6s %-42s %s', $ok ? 'OK' : 'GAGAL', $item, $note);
};

$add('PHP >= 8.3', version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION.' ('.PHP_SAPI.')');
foreach (['pdo_pgsql', 'gd', 'intl', 'mbstring', 'fileinfo', 'openssl', 'curl', 'zip', 'bcmath'] as $ext) {
    $add("ekstensi $ext", extension_loaded($ext));
}
if (function_exists('gd_info')) {
    $gd = gd_info();
    $add('GD JPEG/PNG/WebP', ! empty($gd['JPEG Support']) && ! empty($gd['PNG Support']) && ! empty($gd['WebP Support']));
}
$add('fastcgi/litespeed finish_request', function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request'),
    'kerja setelah respons (push/email tanpa menunggu cron)');
$add('max_execution_time', true, (string) ini_get('max_execution_time').' detik');
$add('memory_limit', true, (string) ini_get('memory_limit'));
$add('upload_max_filesize / post_max_size', true, ini_get('upload_max_filesize').' / '.ini_get('post_max_size'));
$home = dirname($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
$add('folder home di luar public_html dapat ditulis', is_writable($home), 'menyimpan aplikasi/.env di luar web');

$ini = null;
foreach ([$home.'/rt05-cek.ini', dirname($home).'/rt05-cek.ini'] as $path) {
    if (is_readable($path)) {
        $ini = parse_ini_file($path);
        break;
    }
}
if (! is_array($ini) || empty($ini['host'])) {
    $add('berkas rt05-cek.ini', false, 'tidak ditemukan di folder home; uji Neon dilewati');
} else {
    $host = (string) $ini['host'];
    $t = microtime(true);
    $sock = @fsockopen($host, 5432, $errno, $errstr, 8);
    $add('TCP keluar ke Neon port 5432', (bool) $sock, $sock ? round((microtime(true) - $t) * 1000).' ms' : "errno $errno");
    if ($sock) {
        fclose($sock);
    }
    $t = microtime(true);
    $sock = @fsockopen('ssl://fcm.googleapis.com', 443, $errno, $errstr, 8);
    $add('HTTPS keluar ke FCM port 443', (bool) $sock, $sock ? round((microtime(true) - $t) * 1000).' ms' : "errno $errno");
    if ($sock) {
        fclose($sock);
    }
    if (extension_loaded('pdo_pgsql')) {
        $ca = (string) ($ini['sslrootcert'] ?? '');
        $dsn = sprintf('pgsql:host=%s;port=5432;dbname=%s;sslmode=%s%s', $host, $ini['dbname'] ?? 'neondb',
            $ca !== '' && is_readable($ca) ? 'verify-full' : 'require', $ca !== '' && is_readable($ca) ? ';sslrootcert='.$ca : '');
        try {
            $t = microtime(true);
            $pdo = new PDO($dsn, (string) $ini['user'], (string) $ini['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]);
            $ms = round((microtime(true) - $t) * 1000);
            $v = $pdo->query('SHOW server_version')->fetchColumn();
            $ssl = $pdo->query("SELECT ssl FROM pg_stat_ssl WHERE pid = pg_backend_pid()")->fetchColumn();
            $add('PDO PostgreSQL ke Neon (TLS)', true, "PostgreSQL $v, TLS ".($ssl ? 'aktif' : 'tidak').", sambung $ms ms, sslmode ".(str_contains($dsn, 'verify-full') ? 'verify-full' : 'require'));
            $t = microtime(true);
            for ($i = 0; $i < 5; $i++) {
                $pdo->query('SELECT 1')->fetchColumn();
            }
            $add('5 kueri bolak-balik', true, round((microtime(true) - $t) * 1000 / 5).' ms rata-rata');
        } catch (Throwable $e) {
            $add('PDO PostgreSQL ke Neon (TLS)', false, get_class($e).' kode '.$e->getCode());
        }
    }
}

echo "RT05 TAKEDA — cek hosting ".gmdate('Y-m-d H:i:s')." UTC\n\n".implode("\n", $rows)."\n\n";
echo "Hapus berkas ini dan rt05-cek.ini dari hosting setelah hasil disimpan.\n";
