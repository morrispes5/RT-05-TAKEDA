<?php

namespace App\Support;

use App\Domain\Operations\OutboxDispatcher;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Throwable;

/**
 * Profil shared hosting. Penanda file hanya petunjuk; kebenaran tetap di jobs/outbox/media.
 * Kerja pengguna jalan setelah response terkirim. Cron adalah jaring, bukan jalur utama.
 */
final class SharedHosting
{
    /** Jaring pemulihan. Tidak menyentuh database bila belum waktunya dan tidak ada penanda. */
    public const SWEEP_SECONDS = 900;

    /**
     * Cron 15 menit boleh terlambat beberapa menit, dan tick menulis heartbeat
     * setelah selesai. Lima menit menutup keterlambatan itu tanpa menunggu satu
     * putaran cron lagi: proses yang macet atau tidak jalan jadi basi.
     */
    public const HEARTBEAT_LATE_GRACE_SECONDS = 300;

    /** Alarm SIGALRM bila pcntl ada. Bukan batas antar-job. */
    public const JOB_TIMEOUT_SECONDS = 40;

    /**
     * Batas operasi yang bisa macet (SMTP, SQL, soket) bila pcntl tidak ada.
     * Ini bukan penghentian paksa proses.
     */
    public const COOPERATIVE_DEADLINE_SECONDS = 20;

    /** @var list<string> */
    public const QUEUES = ['default', 'notifications', 'media', 'exports'];

    public static function enabled(): bool
    {
        return config('rt05.deployment_profile') === 'shared';
    }

    public static function wake(): void
    {
        if (! self::enabled()) {
            return;
        }
        try {
            Cache::store('file')->put('rt05:shared:awake', true, 120);
        } catch (Throwable $e) {
            // Kegagalan petunjuk lokal tidak boleh mengubah mutasi yang sudah commit menjadi 500.
            Log::warning('shared.wake_failed', ['exception' => $e::class]);
        }
    }

    public static function awake(): bool
    {
        return (bool) Cache::store('file')->get('rt05:shared:awake', false);
    }

    /** Jalankan setelah response terkirim (fastcgi_finish_request / litespeed_finish_request). */
    public static function afterResponse(callable $work): void
    {
        if (! self::enabled()) {
            return;
        }
        app()->terminating(function () use ($work): void {
            try {
                $work();
            } catch (Throwable $e) {
                Log::warning('shared.after_response_failed', ['exception' => $e::class]);
                self::wake();
            }
        });
    }

    /** Satu kali per request: kirim outbox yang sudah commit, lalu kerjakan antrean database. */
    public static function drainAfterResponse(): void
    {
        if (! self::enabled() || app()->bound('rt05.shared.drain')) {
            return;
        }
        app()->instance('rt05.shared.drain', true);
        self::afterResponse(function (): void {
            $result = OutboxDispatcher::dispatch(25);
            if ($result['failed'] > 0) {
                self::wake();
            }
            if ($result['enqueued'] < 1 && ! self::backlog()) {
                return;
            }
            $exit = Artisan::call('queue:work', self::workerArguments(20));
            if ($exit !== 0 || self::backlog() || $result['claimed'] === 25) {
                self::wake();
            }
        });
    }

    public static function backlog(): bool
    {
        foreach (self::QUEUES as $queue) {
            if (Queue::connection('database')->size($queue) > 0) {
                return true;
            }
        }

        return false;
    }

    public static function signalsAvailable(): bool
    {
        return function_exists('pcntl_alarm') && function_exists('pcntl_async_signals');
    }

    /**
     * hard_kill hanya true bila worker bisa memasang SIGALRM.
     * max-time Laravel tetap hanya diperiksa di antara job, dengan atau tanpa pcntl.
     *
     * @return array{hard_kill: bool, worker_timeout: int, cooperative_deadline: int|null}
     */
    public static function timeoutPolicy(?bool $signals = null): array
    {
        $signals ??= self::signalsAvailable();
        if ($signals) {
            return [
                'hard_kill' => true,
                'worker_timeout' => self::JOB_TIMEOUT_SECONDS,
                'cooperative_deadline' => null,
            ];
        }

        return [
            'hard_kill' => false,
            'worker_timeout' => 0,
            'cooperative_deadline' => self::COOPERATIVE_DEADLINE_SECONDS,
        ];
    }

    /** @return array<string, mixed> */
    public static function workerArguments(int $maxTime, ?bool $signals = null): array
    {
        return [
            'connection' => 'database',
            '--queue' => implode(',', self::QUEUES),
            '--stop-when-empty' => true,
            '--max-time' => $maxTime,
            '--max-jobs' => 25,
            '--tries' => 5,
            '--timeout' => self::timeoutPolicy($signals)['worker_timeout'],
            '--sleep' => 0,
        ];
    }

    /**
     * Tanpa pcntl, job yang macet tidak bisa dihentikan paksa. Batasi operasi
     * yang menunggu jaringan atau Postgres, dan waktu CPU skrip PHP.
     * GD serta panggilan C yang tidak memeriksa batas ini tetap bisa macet.
     */
    public static function armOperationDeadlines(?bool $signals = null): void
    {
        if (! self::enabled() || self::timeoutPolicy($signals)['hard_kill']) {
            return;
        }
        $seconds = self::COOPERATIVE_DEADLINE_SECONDS;
        ini_set('default_socket_timeout', (string) $seconds);
        set_time_limit($seconds);
        if (config('mail.mailers.smtp.timeout') === null || config('mail.mailers.smtp.timeout') === '') {
            config(['mail.mailers.smtp.timeout' => $seconds]);
        }
        try {
            DB::statement('SET statement_timeout = '.($seconds * 1000));
        } catch (Throwable $e) {
            Log::warning('shared.statement_timeout_failed', ['exception' => $e::class]);
        }
    }
}
