<?php

declare(strict_types=1);

/*
 * RT05 TAKEDA — cek status jembatan Neon (ADR55). SEMENTARA: unggah ke public_html dengan nama acak, buka di browser,
 * lalu HAPUS. Tidak membaca kredensial dan tidak membuka database.
 */

header('Content-Type: text/plain; charset=utf-8');
$dir = dirname($_SERVER['DOCUMENT_ROOT']).'/rt05-pg';
$log = (string) @file_get_contents("$dir/bridge.log");
$pid = preg_match_all('/mulai pid=(\d+)/', $log, $m) ? (int) end($m[1]) : 0;
$alive = $pid > 0 && file_exists("/proc/$pid");
echo gmdate('c')." pid=$pid hidup=".($alive ? 'ya' : 'tidak').' socket='.(file_exists("$dir/.s.PGSQL.5432") ? 'ya' : 'tidak')."\n";
if ($alive) {
    $st = (string) @file_get_contents("/proc/$pid/status");
    echo 'rss_kb='.(preg_match('/VmRSS:\s+(\d+)/', $st, $x) ? $x[1] : '?')."\n";
}
echo "log:\n".implode("\n", array_slice(explode("\n", trim($log)), -20))."\n";
