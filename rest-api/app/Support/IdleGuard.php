<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * M15 (ADR31): profil VPS. Sweep scheduler yang menanyai database (outbox tiap menit, foto/push tiap
 * 10 menit) hanya jalan saat ada aktivitas baru atau sekali per jam sebagai pengaman. Tanpa ini compute
 * Neon tidak pernah tidur (suspend setelah 5 menit idle) dan kuota 100 CU-jam/bulan per project habis,
 * termasuk untuk branch production di project yang sama. Penanda ada di cache (Redis), bukan database.
 * Cache gagal → dianggap aktif (perilaku lama, tidak pernah menunda notifikasi).
 */
final class IdleGuard
{
    /** Lama sweep tetap tiap menit setelah event terakhir (mencakup backoff enqueue dan retry job). */
    public const ACTIVE_SECONDS = 600;

    private const ACTIVE = 'rt05:vps:active';

    public static function touch(): void
    {
        try {
            Cache::put(self::ACTIVE, true, self::ACTIVE_SECONDS);
        } catch (Throwable $e) {
            // Mutasi sudah commit; kegagalan penanda tidak boleh mengubahnya menjadi error.
            Log::warning('idle_guard.touch_failed', ['exception' => $e::class]);
        }
    }

    public static function active(): bool
    {
        try {
            return (bool) Cache::get(self::ACTIVE, false);
        } catch (Throwable) {
            return true;
        }
    }

    /** Aktif, atau sweep pengaman `$task` belum jalan dalam `$seconds` terakhir. */
    public static function shouldRun(string $task, int $seconds = 3600): bool
    {
        if (self::active()) {
            return true;
        }
        try {
            return now()->getTimestamp() - (int) Cache::get('rt05:vps:ran:'.$task, 0) >= $seconds - 30;
        } catch (Throwable) {
            return true;
        }
    }

    public static function markRan(string $task): void
    {
        try {
            Cache::put('rt05:vps:ran:'.$task, now()->getTimestamp(), 86400);
        } catch (Throwable) {
        }
    }
}
