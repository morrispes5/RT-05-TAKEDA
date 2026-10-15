<?php

namespace App\Support;

use App\Domain\Operations\OutboxDispatcher;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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

    /** @return array<string, mixed> */
    public static function workerArguments(int $maxTime): array
    {
        return [
            'connection' => 'database',
            '--queue' => implode(',', self::QUEUES),
            '--stop-when-empty' => true,
            '--max-time' => $maxTime,
            '--max-jobs' => 25,
            '--tries' => 5,
            // Tanpa pcntl, timeout per job diabaikan; max-time tetap memotong antrean di antara job.
            '--timeout' => function_exists('pcntl_alarm') ? 40 : 0,
            '--sleep' => 0,
        ];
    }
}
