<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Local wake-up hint only. Durable truth remains in jobs/outbox/media. */
final class SharedHosting
{
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
            // A local hint failure must never turn an already committed mutation into a 500.
            Log::warning('shared.wake_failed', ['exception' => $e::class]);
        }
    }

    public static function awake(): bool
    {
        return (bool) Cache::store('file')->get('rt05:shared:awake', false);
    }
}
