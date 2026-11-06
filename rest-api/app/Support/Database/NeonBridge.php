<?php

namespace App\Support\Database;

use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Log;

/**
 * Jembatan PostgreSQL → Neon lewat WebSocket 443 untuk hosting bersama yang memblokir 5432 (ADR55).
 *
 * Laravel tersambung ke unix socket `<dir>/.s.PGSQL.<port>`; proses `bin/neon-ws-bridge.php` meneruskan byte ke
 * `wss://<RT05_NEON_HOST>/v2` dengan verifikasi sertifikat. Proses dianggap hidup bila memegang flock berkas
 * `bridge.lock` DAN socket ada. Memeriksa tidak membuka koneksi ke Neon, jadi watchdog tiap menit tidak
 * membangunkan compute. Tidak ada kredensial di sini: sandi database tetap di dalam protokol PostgreSQL.
 */
class NeonBridge
{
    public function enabled(): bool
    {
        return (bool) config('rt05.neon_bridge.enabled');
    }

    public function dir(): string
    {
        $dir = (string) (config('rt05.neon_bridge.dir') ?: ($this->connectionConfig()['host'] ?? ''));

        return rtrim($dir, '/');
    }

    public function socketPath(): string
    {
        return $this->dir().'/.s.PGSQL.'.((int) ($this->connectionConfig()['port'] ?? 5432) ?: 5432);
    }

    public function lockPath(): string
    {
        return $this->dir().'/bridge.lock';
    }

    public function logPath(): string
    {
        return $this->dir().'/bridge.log';
    }

    /** Koneksi Postgres yang host-nya folder jembatan (unix socket) dilayani jembatan. */
    public function serves(array $config): bool
    {
        $host = rtrim((string) ($config['host'] ?? ''), '/');

        return $this->enabled() && self::absolute($host) && $host === $this->dir();
    }

    /** Hosting/CI Linux memakai `/…`; huruf drive hanya agar tes unit bisa jalan di PC Windows. */
    private static function absolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path) === 1;
    }

    /** @return list<string> salah konfigurasi tanpa nilai rahasia. */
    public function configErrors(): array
    {
        if (! $this->enabled()) {
            return [];
        }
        $errors = [];
        if (! preg_match('/^[a-z0-9.-]+\.neon\.tech$/', (string) config('rt05.neon_bridge.neon_host'))) {
            $errors[] = 'RT05_NEON_HOST harus host endpoint Neon (*.neon.tech)';
        }
        if (! self::absolute($this->dir())) {
            $errors[] = 'DB_HOST/RT05_BRIDGE_DIR harus path folder absolut untuk unix socket';
        }
        if (! is_file($this->script())) {
            $errors[] = 'skrip jembatan tidak ditemukan';
        }

        return $errors;
    }

    public function running(): bool
    {
        if (! is_file($this->lockPath())) {
            return false;
        }
        $h = @fopen($this->lockPath(), 'r');
        if ($h === false) {
            return false;
        }
        $free = flock($h, LOCK_SH | LOCK_NB);
        if ($free) {
            flock($h, LOCK_UN);
        }
        fclose($h);

        return ! $free && file_exists($this->socketPath());
    }

    public function pid(): ?int
    {
        $pid = (int) trim((string) @file_get_contents($this->lockPath()));

        return $pid > 1 ? $pid : null;
    }

    /** Nyalakan bila belum hidup lalu tunggu socket siap. True = jembatan siap atau tidak dipakai. */
    public function ensureRunning(): bool
    {
        if (! $this->enabled() || $this->running()) {
            return true;
        }
        if ($this->configErrors() !== []) {
            Log::error('neon_bridge.config_invalid', ['errors' => $this->configErrors()]);

            return false;
        }
        if (! $this->spawn()) {
            return false;
        }
        $deadline = microtime(true) + max(100, (int) config('rt05.neon_bridge.start_wait_ms', 3000)) / 1000;
        do {
            usleep(50_000);
            clearstatcache();
            if ($this->running()) {
                Log::info('neon_bridge.started', ['pid' => $this->pid()]);

                return true;
            }
        } while (microtime(true) < $deadline);

        Log::error('neon_bridge.start_timeout');

        return false;
    }

    public function stop(): bool
    {
        $pid = $this->pid();
        if (! $this->running() || $pid === null) {
            return false;
        }
        exec('kill '.$pid.' 2>/dev/null', $out, $code);

        return $code === 0;
    }

    /** @return list<string> baris terakhir log jembatan (isinya hanya status, tanpa data kueri). */
    public function logTail(int $lines = 10): array
    {
        $log = trim((string) @file_get_contents($this->logPath()));

        return $log === '' ? [] : array_slice(explode("\n", $log), -$lines);
    }

    protected function spawn(): bool
    {
        if (! function_exists('exec') || PHP_OS_FAMILY === 'Windows') {
            // Biznet PHP 8.4 memblokir exec: penyala utama di sana adalah cron `rt05:neon-bridge run`.
            Log::warning('neon_bridge.spawn_unsupported');

            return false;
        }
        if (! $this->prepareDir()) {
            return false;
        }
        $env = $this->processEnv();
        $assign = implode(' ', array_map(fn ($k, $v) => $k.'='.escapeshellarg($v), array_keys($env), $env));

        // Proses web (lsphp) mewariskan fd > 2, termasuk socket klien HTTP. Tanpa menutupnya respons tertahan sampai
        // jembatan mati (uji 6 Nov). bash menutup semuanya, lalu setsid melepas jembatan dari sesi/proses induk.
        $inner = 'for f in /proc/$$/fd/*; do n=${f##*/}; [ "$n" -gt 2 ] 2>/dev/null && eval "exec $n>&-"; done; '
            .'exec setsid '.escapeshellarg($this->php()).' '.escapeshellarg($this->script());
        exec('env '.$assign.' nohup bash -c '.escapeshellarg($inner).' </dev/null >/dev/null 2>&1 &', $out, $code);

        if ($code !== 0) {
            Log::error('neon_bridge.spawn_failed', ['code' => $code]);

            return false;
        }

        return true;
    }

    /**
     * Ganti proses CLI ini menjadi jembatan (pcntl_exec, tanpa exec/proc_open yang diblokir hosting). Untuk cron
     * `php artisan rt05:neon-bridge run` tiap menit; bila jembatan sudah hidup kembali segera. Hanya kembali bila gagal.
     */
    public function runInForeground(): bool
    {
        if (! $this->enabled() || $this->running()) {
            return true;
        }
        if ($this->configErrors() !== [] || ! function_exists('pcntl_exec') || ! $this->prepareDir()) {
            return false;
        }
        @pcntl_exec($this->php(), [$this->script()], $this->processEnv() + getenv());

        return false;
    }

    /** @return array<string,string> env proses jembatan (host/path saja, tanpa kredensial). */
    protected function processEnv(): array
    {
        return array_filter([
            'RT05_NEON_HOST' => (string) config('rt05.neon_bridge.neon_host'),
            'RT05_BRIDGE_LISTEN' => 'unix://'.$this->socketPath(),
            'RT05_BRIDGE_LOCK' => $this->lockPath(),
            'RT05_BRIDGE_LOG' => $this->logPath(),
            'RT05_BRIDGE_CAFILE' => (string) config('rt05.neon_bridge.cafile'),
            'RT05_BRIDGE_MAX' => (string) (int) config('rt05.neon_bridge.max_connections', 40),
            'RT05_BRIDGE_MAX_AGE' => (string) (int) config('rt05.neon_bridge.max_age_seconds', 1500),
        ], fn ($v) => $v !== '' && $v !== '0');
    }

    private function prepareDir(): bool
    {
        if (! is_dir($this->dir()) && ! @mkdir($this->dir(), 0700, true) && ! is_dir($this->dir())) {
            Log::error('neon_bridge.dir_failed');

            return false;
        }
        @chmod($this->dir(), 0700);

        return true;
    }

    protected function php(): string
    {
        $php = (string) config('rt05.neon_bridge.php');
        if ($php !== '') {
            return $php;
        }

        // PHP_BINARY di web LiteSpeed adalah lsphp, bukan CLI; isi RT05_BRIDGE_PHP di hosting.
        return PHP_SAPI === 'cli' ? PHP_BINARY : '/usr/local/bin/php';
    }

    protected function script(): string
    {
        return (string) (config('rt05.neon_bridge.script') ?: base_path('bin/neon-ws-bridge.php'));
    }

    private function connectionConfig(): array
    {
        return (new ConfigurationUrlParser)->parseConfiguration((array) config('database.connections.pgsql', []));
    }
}
