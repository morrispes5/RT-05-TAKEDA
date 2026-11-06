<?php

namespace Tests\Unit;

use App\Support\Database\NeonBridge;
use App\Support\Database\TlsPolicy;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/** ADR55: jembatan Neon lewat WebSocket untuk hosting bersama yang memblokir 5432. */
class NeonBridgeTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        // Path pendek: batas panjang path unix socket ±108 byte.
        $this->dir = str_replace('\\', '/', sys_get_temp_dir()).'/nb'.bin2hex(random_bytes(4));
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        $bridge = app(NeonBridge::class);
        if ($bridge->enabled() && $bridge->running()) {
            $bridge->stop();
            $this->waitUntil(fn () => ! $bridge->running());
        }
        foreach (glob($this->dir.'/{,.}*', GLOB_BRACE) ?: [] as $f) {
            if (is_file($f) || is_link($f) || filetype($f) === 'socket') {
                @unlink($f);
            }
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    private function useBridge(string $neonHost = 'ep-test-123-pooler.ap-southeast-1.aws.neon.tech'): NeonBridge
    {
        config([
            'rt05.neon_bridge.enabled' => true,
            'rt05.neon_bridge.neon_host' => $neonHost,
            'rt05.neon_bridge.start_wait_ms' => 5000,
        ]);
        foreach (TlsPolicy::CONNECTIONS as $connection) {
            config([
                "database.connections.$connection.url" => null,
                "database.connections.$connection.host" => $connection === 'pgsql' ? $this->dir : 'ep-x.neon.tech',
                "database.connections.$connection.port" => 5432,
                "database.connections.$connection.sslmode" => $connection === 'pgsql' ? 'disable' : 'verify-full',
                "database.connections.$connection.sslrootcert" => $connection === 'pgsql' ? null : 'system',
            ]);
        }

        return app(NeonBridge::class);
    }

    private function waitUntil(callable $done, float $seconds = 5): bool
    {
        $deadline = microtime(true) + $seconds;
        while (microtime(true) < $deadline) {
            clearstatcache();
            if ($done()) {
                return true;
            }
            usleep(50_000);
        }

        return false;
    }

    public function test_disabled_by_default_and_never_serves_a_connection(): void
    {
        $bridge = app(NeonBridge::class);

        $this->assertFalse($bridge->enabled());
        $this->assertFalse($bridge->serves(['host' => $this->dir]));
        $this->assertTrue($bridge->ensureRunning());
        $this->assertSame([], $bridge->configErrors());
    }

    public function test_serves_only_the_unix_socket_folder_of_the_bridge(): void
    {
        $bridge = $this->useBridge();

        $this->assertSame($this->dir.'/.s.PGSQL.5432', $bridge->socketPath());
        $this->assertTrue($bridge->serves(['host' => $this->dir]));
        $this->assertTrue($bridge->serves(['host' => $this->dir.'/']));
        $this->assertFalse($bridge->serves(['host' => 'ep-x.neon.tech']));
        $this->assertFalse($bridge->serves(['host' => '/tmp/lain']));
    }

    public function test_running_requires_lock_holder_and_socket(): void
    {
        $bridge = $this->useBridge();
        $this->assertFalse($bridge->running());

        $lock = fopen($bridge->lockPath(), 'c');
        $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB));
        fwrite($lock, '4242');
        fflush($lock);
        $this->assertFalse($bridge->running(), 'kunci tanpa socket belum siap');

        touch($bridge->socketPath());
        $this->assertTrue($bridge->running());
        if (PHP_OS_FAMILY !== 'Windows') { // Windows mengunci isi berkas secara wajib; Linux (hosting) advisory.
            $this->assertSame(4242, $bridge->pid());
        }

        flock($lock, LOCK_UN);
        fclose($lock);
        $this->assertFalse($bridge->running(), 'socket basi tanpa pemegang kunci = mati');
    }

    public function test_tls_policy_accepts_bridge_socket_but_not_plain_tcp_without_tls(): void
    {
        $this->useBridge();
        $this->assertSame([], TlsPolicy::violations('production'));
        $this->assertTrue(TlsPolicy::effective('pgsql')['via_bridge']);

        config(['rt05.neon_bridge.neon_host' => 'evil.example.com']);
        $this->assertStringContainsString('RT05_NEON_HOST', implode("\n", TlsPolicy::violations('production')));

        $this->useBridge();
        config(['database.connections.pgsql.host' => 'ep-x.neon.tech']);
        $this->assertStringContainsString('verify-full', implode("\n", TlsPolicy::violations('production')));
    }

    public function test_connector_starts_bridge_before_connecting_only_for_bridge_folder(): void
    {
        $this->useBridge();
        $fake = new class extends NeonBridge
        {
            public int $calls = 0;

            public function ensureRunning(): bool
            {
                $this->calls++;

                return false;
            }
        };
        $this->app->instance(NeonBridge::class, $fake);

        try {
            DB::connection('pgsql')->getPdo();
        } catch (Throwable) {
            // Tidak ada jembatan sungguhan; yang diuji urutan: ensureRunning dipanggil sebelum PDO.
        }
        $this->assertSame(1, $fake->calls);

        DB::purge('pgsql');
        config(['database.connections.pgsql.host' => '127.0.0.1', 'database.connections.pgsql.port' => 1]);
        try {
            DB::connection('pgsql')->getPdo();
        } catch (Throwable) {
        }
        $this->assertSame(1, $fake->calls, 'koneksi TCP biasa tidak menyentuh jembatan');
        DB::purge('pgsql');
    }

    public function test_spawns_real_bridge_once_and_stops_it(): void
    {
        if (PHP_OS_FAMILY === 'Windows' || ! is_executable('/usr/bin/setsid') && ! is_executable('/bin/setsid')) {
            $this->markTestSkipped('butuh bash + setsid (Linux, seperti hosting dan CI)');
        }
        $bridge = $this->useBridge();
        config(['rt05.neon_bridge.php' => PHP_BINARY]);

        $this->assertTrue($bridge->ensureRunning());
        $this->assertTrue($bridge->running());
        $pid = $bridge->pid();
        $this->assertNotNull($pid);
        $this->assertSame('socket', filetype($bridge->socketPath()));
        $this->assertSame('0700', substr(sprintf('%o', fileperms($this->dir)), -4));
        $this->assertStringContainsString("mulai pid=$pid", implode("\n", $bridge->logTail()));

        $this->assertTrue($bridge->ensureRunning());
        $this->assertSame($pid, $bridge->pid(), 'tidak menyalakan instance kedua');

        $this->assertTrue($bridge->stop());
        $this->assertTrue($this->waitUntil(fn () => ! $bridge->running()));

        // Watchdog berikutnya menyalakan ulang dan socket basi diganti.
        $this->assertTrue($bridge->ensureRunning());
        $this->assertNotSame($pid, $bridge->pid());
    }

    public function test_bridge_hands_over_to_successor_before_max_age_without_gap(): void
    {
        if (PHP_OS_FAMILY === 'Windows' || ! is_executable('/usr/bin/setsid') && ! is_executable('/bin/setsid')) {
            $this->markTestSkipped('butuh bash + setsid (Linux, seperti hosting dan CI)');
        }
        $bridge = $this->useBridge();
        config(['rt05.neon_bridge.php' => PHP_BINARY, 'rt05.neon_bridge.max_age_seconds' => 3]);

        $this->assertTrue($bridge->ensureRunning());
        $first = $bridge->pid();
        $this->assertNotNull($first);

        $this->assertTrue($this->waitUntil(fn () => $bridge->running() && $bridge->pid() !== $first, 10), 'pengganti mengambil alih');
        $this->assertTrue($this->waitUntil(fn () => ! file_exists("/proc/$first"), 5), 'proses lama keluar sendiri');
        $log = implode("\n", $bridge->logTail(20));
        $this->assertStringContainsString("keluar setelah serah terima pid=$first", $log);
        $this->assertMatchesRegularExpression('/serah terima umur=\d+s aktif=0\n/', $log."\n");

        // Hentikan rantai serah terima (batas umur 3 dtk); kill tidak memicu pengganti.
        for ($i = 0; $i < 5 && $bridge->running(); $i++) {
            $bridge->stop();
            usleep(300_000);
        }
    }

    public function test_cron_run_command_becomes_the_bridge_without_exec(): void
    {
        if (PHP_OS_FAMILY === 'Windows' || ! function_exists('pcntl_exec')) {
            $this->markTestSkipped('butuh pcntl (Linux CLI, seperti cron hosting dan CI)');
        }
        $bridge = $this->useBridge();
        $env = [
            'RT05_NEON_BRIDGE' => 'true', 'RT05_NEON_HOST' => (string) config('rt05.neon_bridge.neon_host'),
            'RT05_BRIDGE_DIR' => $this->dir, 'DB_HOST' => $this->dir, 'DB_PORT' => '5432', 'DATABASE_URL' => '', 'APP_ENV' => 'testing',
        ] + getenv();
        // Proses terpisah: pcntl_exec mengganti proses pemanggil dengan jembatan.
        $cmd = [PHP_BINARY, base_path('artisan'), 'rt05:neon-bridge', 'run'];
        $out = $this->dir.'/run.out';
        $proc = proc_open($cmd, [['file', '/dev/null', 'r'], ['file', $out, 'a'], ['file', $out, 'a']], $pipes, base_path(), $env);
        $this->assertIsResource($proc);

        $this->assertTrue($this->waitUntil(fn () => $bridge->running(), 10), 'cron run menyalakan jembatan; output='
            .json_encode(@file_get_contents($out)).' isi='.json_encode(scandir($this->dir)));
        $pid = $bridge->pid();
        $this->assertSame($pid, proc_get_status($proc)['pid'], 'proses artisan itu sendiri yang menjadi jembatan');

        // Tick berikutnya saat jembatan hidup: keluar 0 tanpa instance kedua.
        $this->assertSame(0, (int) proc_close(proc_open($cmd, [['file', '/dev/null', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $p2, base_path(), $env)));
        $this->assertSame($pid, $bridge->pid());

        $bridge->stop();
        $this->assertTrue($this->waitUntil(fn () => ! $bridge->running()));
        proc_close($proc);
    }

    public function test_status_command_reports_without_touching_database(): void
    {
        $this->artisan('rt05:neon-bridge')->expectsOutputToContain('jembatan=mati')->assertExitCode(0);

        $this->useBridge();
        $this->artisan('rt05:neon-bridge', ['aksi' => 'status'])->expectsOutputToContain('hidup=tidak')->assertExitCode(1);
    }
}
