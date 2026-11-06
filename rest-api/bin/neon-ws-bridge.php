<?php

declare(strict_types=1);

/*
 * RT05 TAKEDA — jembatan PostgreSQL ke Neon lewat WebSocket (wss port 443).
 *
 * Hosting bersama memblokir TCP keluar 5432, tetapi 443 terbuka. Neon menerima
 * protokol PostgreSQL di dalam WebSocket (wss://<endpoint>/v2). Jembatan ini
 * mendengarkan socket lokal (unix socket di folder akun, izin 0600) dan
 * meneruskan byte apa adanya; ia tidak membaca isi kueri maupun kata sandi.
 *
 * Di production proses ini dinyalakan otomatis oleh Laravel (App\Support\Database\NeonBridge): saat koneksi
 * database pertama bila socket belum ada, dan oleh watchdog `rt05:shared-tick` tiap menit. Manual (uji):
 *         RT05_NEON_HOST=ep-xxx.neon.tech RT05_BRIDGE_LISTEN=unix:///home/u/rt05-pg/.s.PGSQL.5432 \
 *         RT05_BRIDGE_LOCK=/home/u/rt05-pg/bridge.lock php neon-ws-bridge.php
 * Laravel: DB_HOST=/home/u/rt05-pg DB_PORT=5432 DB_SSLMODE=disable (TLS ada di jalur wss).
 */

$neonHost = (string) (getenv('RT05_NEON_HOST') ?: '');
$listen = (string) (getenv('RT05_BRIDGE_LISTEN') ?: '');
$lockFile = (string) (getenv('RT05_BRIDGE_LOCK') ?: '');
$logFile = (string) (getenv('RT05_BRIDGE_LOG') ?: '');
$caFile = (string) (getenv('RT05_BRIDGE_CAFILE') ?: '');
$maxPairs = (int) (getenv('RT05_BRIDGE_MAX') ?: 40);
// Hosting Biznet membunuh setiap proses akun di umur ±30 menit (uji 6 Nov). Sebelum itu jembatan menyerahkan socket
// ke pengganti dan menyelesaikan koneksi yang masih berjalan (maks $drainSeconds), jadi tidak ada koneksi diputus paksa.
$maxAge = max(2, (int) (getenv('RT05_BRIDGE_MAX_AGE') ?: 1500));
$drainSeconds = 60;
$started = time();
$handedOverAt = null;

if ($neonHost === '' || $listen === '' || ! preg_match('/^[a-z0-9.-]+$/', $neonHost)) {
    fwrite(STDERR, "RT05_NEON_HOST dan RT05_BRIDGE_LISTEN wajib diisi\n");
    exit(2);
}

function bridge_log(string $msg): void
{
    global $logFile;
    $line = gmdate('Y-m-d\TH:i:s\Z').' '.$msg."\n";
    if ($logFile !== '') {
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    } else {
        fwrite(STDERR, $line);
    }
}

if ($lockFile !== '') {
    $lock = fopen($lockFile, 'c');
    if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
        exit(0); // instance lain sudah berjalan
    }
    // PID di berkas kunci dibaca `php artisan rt05:neon-bridge status|stop` (kunci tetap dipegang selama hidup).
    ftruncate($lock, 0);
    fwrite($lock, (string) getmypid());
    fflush($lock);
}

set_time_limit(0);
ignore_user_abort(true);

$oldUmask = null;
if (str_starts_with($listen, 'unix://')) {
    $path = substr($listen, 7);
    if (file_exists($path)) {
        @unlink($path); // aman: kunci menjamin hanya satu instance
    }
    $oldUmask = umask(0077);
}
$server = @stream_socket_server($listen, $errno, $errstr);
if ($oldUmask !== null) {
    umask($oldUmask);
}
if ($server === false) {
    bridge_log("listen gagal: $errstr");
    exit(1);
}
stream_set_blocking($server, false);
bridge_log('mulai pid='.getmypid());

function write_all($s, string $data): bool
{
    $deadline = microtime(true) + 15;
    while ($data !== '') {
        $n = @fwrite($s, $data);
        if ($n === false) {
            return false;
        }
        if ($n === 0) {
            if (microtime(true) > $deadline) {
                return false;
            }
            $r = null;
            $w = [$s];
            $e = null;
            @stream_select($r, $w, $e, 1);

            continue;
        }
        $data = (string) substr($data, $n);
    }

    return true;
}

function ws_frame(string $payload, int $opcode = 2): string
{
    $len = strlen($payload);
    $head = chr(0x80 | $opcode);
    if ($len < 126) {
        $head .= chr(0x80 | $len);
    } elseif ($len < 65536) {
        $head .= chr(0x80 | 126).pack('n', $len);
    } else {
        $head .= chr(0x80 | 127).pack('J', $len);
    }
    $mask = random_bytes(4);

    return $head.$mask.($payload ^ str_repeat($mask, intdiv($len, 4) + 1));
}

/** @return list<array{int,string}> */
function ws_parse(string &$buf): array
{
    $out = [];
    while (strlen($buf) >= 2) {
        $b0 = ord($buf[0]);
        $b1 = ord($buf[1]);
        $len = $b1 & 0x7F;
        $off = 2;
        if ($len === 126) {
            if (strlen($buf) < 4) {
                break;
            }
            $len = unpack('n', substr($buf, 2, 2))[1];
            $off = 4;
        } elseif ($len === 127) {
            if (strlen($buf) < 10) {
                break;
            }
            $len = unpack('J', substr($buf, 2, 8))[1];
            $off = 10;
        }
        $mask = '';
        if (($b1 & 0x80) !== 0) {
            if (strlen($buf) < $off + 4) {
                break;
            }
            $mask = substr($buf, $off, 4);
            $off += 4;
        }
        if (strlen($buf) < $off + $len) {
            break;
        }
        $payload = substr($buf, $off, $len);
        if ($mask !== '') {
            $payload ^= str_repeat($mask, intdiv($len, 4) + 1);
        }
        $buf = (string) substr($buf, $off + $len);
        $out[] = [$b0 & 0x0F, $payload];
    }

    return $out;
}

/** @return array{0:resource|null,1:string} */
function ws_open(string $host, string $caFile): array
{
    $ssl = ['peer_name' => $host, 'verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true];
    if ($caFile !== '') {
        $ssl['cafile'] = $caFile;
    }
    $ctx = stream_context_create(['ssl' => $ssl]);
    $s = @stream_socket_client("tls://$host:443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
    if ($s === false) {
        return [null, "tls gagal: $errstr"];
    }
    stream_set_timeout($s, 10);
    $key = base64_encode(random_bytes(16));
    fwrite($s, "GET /v2 HTTP/1.1\r\nHost: $host\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n"
        ."Sec-WebSocket-Key: $key\r\nSec-WebSocket-Version: 13\r\nUser-Agent: rt05-neon-ws-bridge/1\r\n\r\n");
    $resp = '';
    while (! str_contains($resp, "\r\n\r\n")) {
        $d = fread($s, 4096);
        if ($d === false || $d === '') {
            if (feof($s) || stream_get_meta_data($s)['timed_out']) {
                fclose($s);

                return [null, 'handshake terputus'];
            }

            continue;
        }
        $resp .= $d;
        if (strlen($resp) > 16384) {
            fclose($s);

            return [null, 'header terlalu besar'];
        }
    }
    [$head, $rest] = explode("\r\n\r\n", $resp, 2);
    $accept = base64_encode(sha1($key.'258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    if (! preg_match('#^HTTP/1\.1 101#', $head) || stripos($head, $accept) === false) {
        fclose($s);

        return [null, 'upgrade ditolak: '.strtok($head, "\r\n")];
    }
    stream_set_blocking($s, false);

    return [$s, $rest];
}

/** Baca semua byte yang sudah tersedia (termasuk buffer TLS internal). */
function drain($s): array
{
    $data = '';
    while (true) {
        $d = @fread($s, 65536);
        if ($d === false || $d === '') {
            break;
        }
        $data .= $d;
    }

    return [$data, $data === '' && feof($s)];
}

$pairs = [];
$bySock = [];
$nextId = 1;

$closePair = function (int $id, bool $sendClose = true) use (&$pairs, &$bySock): void {
    if (! isset($pairs[$id])) {
        return;
    }
    $p = $pairs[$id];
    if ($sendClose) {
        @fwrite($p['w'], ws_frame('', 8));
    }
    unset($bySock[(int) $p['c']], $bySock[(int) $p['w']], $pairs[$id]);
    @fclose($p['c']);
    @fclose($p['w']);
};

/**
 * Nyalakan instance baru terlepas dari proses ini. Biznet (PHP Selector 8.4) memblokir exec/proc_open untuk web dan
 * CLI, jadi jalur utama pcntl_fork: anak menutup socket koneksi lama (listener dan kunci sudah ditutup sebelum ini),
 * pindah sesi, lalu pcntl_exec menjadi jembatan baru. exec hanya cadangan bila pcntl tidak ada.
 */
function spawn_successor(): bool
{
    global $pairs;
    if (function_exists('pcntl_fork') && function_exists('pcntl_exec') && function_exists('posix_setsid')) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            return false;
        }
        if ($pid === 0) {
            foreach ($pairs as $p) {
                @fclose($p['c']);
                @fclose($p['w']);
            }
            posix_setsid();
            @pcntl_exec(PHP_BINARY, [__FILE__], getenv());
            exit(1); // pcntl_exec gagal: anak tidak boleh ikut menjalankan loop jembatan
        }

        return true;
    }
    if (! function_exists('exec')) {
        return false;
    }
    $inner = 'for f in /proc/$$/fd/*; do n=${f##*/}; [ "$n" -gt 2 ] 2>/dev/null && eval "exec $n>&-"; done; '
        .'exec setsid '.escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__);
    exec('nohup bash -c '.escapeshellarg($inner).' </dev/null >/dev/null 2>&1 &', $out, $code);

    return $code === 0;
}

/** Lepas listener + kunci lalu nyalakan pengganti; koneksi yang sudah ada tetap dilayani proses ini. */
function hand_over(): void
{
    global $server, $listen, $lock, $pairs, $started, $handedOverAt;
    @fclose($server);
    $server = null;
    if (str_starts_with($listen, 'unix://')) {
        @unlink(substr($listen, 7));
    }
    if (isset($lock) && $lock !== false) {
        flock($lock, LOCK_UN);
        fclose($lock);
        $lock = null;
    }
    $ok = spawn_successor();
    $handedOverAt = time();
    bridge_log('serah terima umur='.($handedOverAt - $started).'s aktif='.count($pairs).($ok ? '' : ' pengganti_gagal'));
}

while (true) {
    if ($server !== null && time() - $started >= $maxAge) {
        hand_over();
    }
    if ($handedOverAt !== null && ($pairs === [] || time() - $handedOverAt >= $drainSeconds)) {
        foreach (array_keys($pairs) as $id) {
            $closePair($id);
        }
        bridge_log('keluar setelah serah terima pid='.getmypid());
        exit(0);
    }
    $read = $server !== null ? [$server] : [];
    foreach ($pairs as $p) {
        $read[] = $p['c'];
        $read[] = $p['w'];
    }
    $write = null;
    $except = null;
    $timeout = $server !== null ? max(1, min(60, $started + $maxAge - time())) : 1;
    $n = @stream_select($read, $write, $except, $timeout);
    if ($n === false) {
        usleep(100000);

        continue;
    }
    foreach ($read as $s) {
        if ($s === $server) {
            $c = @stream_socket_accept($server, 0);
            if ($c === false) {
                continue;
            }
            if (count($pairs) >= $maxPairs) {
                bridge_log('tolak: batas koneksi');
                fclose($c);

                continue;
            }
            [$w, $rest] = ws_open($neonHost, $caFile);
            if ($w === null) {
                bridge_log($rest);
                fclose($c);

                continue;
            }
            stream_set_blocking($c, false);
            $id = $nextId++;
            $pairs[$id] = ['c' => $c, 'w' => $w, 'buf' => $rest];
            $bySock[(int) $c] = $id;
            $bySock[(int) $w] = $id;

            continue;
        }
        $id = $bySock[(int) $s] ?? null;
        if ($id === null || ! isset($pairs[$id])) {
            continue;
        }
        $p = &$pairs[$id];
        if ($s === $p['c']) {
            [$data, $eof] = drain($s);
            if ($data !== '' && ! write_all($p['w'], ws_frame($data))) {
                unset($p);
                $closePair($id, false);

                continue;
            }
            if ($eof) {
                unset($p);
                $closePair($id);
            }
        } else {
            [$data, $eof] = drain($s);
            $p['buf'] .= $data;
            $closed = $eof;
            foreach (ws_parse($p['buf']) as [$op, $payload]) {
                if ($op === 2 || $op === 0) {
                    if (! write_all($p['c'], $payload)) {
                        $closed = true;
                        break;
                    }
                } elseif ($op === 9) {
                    write_all($p['w'], ws_frame($payload, 10));
                } elseif ($op === 8) {
                    $closed = true;
                    break;
                }
            }
            if ($closed) {
                unset($p);
                $closePair($id, false);
            }
        }
        unset($p);
    }
}
