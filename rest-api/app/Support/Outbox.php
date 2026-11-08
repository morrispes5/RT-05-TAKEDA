<?php

namespace App\Support;

use App\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * Transactional outbox: event ditulis dalam transaksi domain; dispatcher (rt05:outbox-dispatch)
 * memprosesnya setelah commit. Payload hanya ID/jenis, tanpa PII atau identitas pelapor anonim.
 */
final class Outbox
{
    public static function record(string $type, string $aggregateType, ?string $aggregateId, array $payload = []): OutboxEvent
    {
        $event = OutboxEvent::create([
            'type' => $type,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'safe_payload' => $payload,
        ]);

        if (SharedHosting::enabled()) {
            DB::afterCommit(function () {
                SharedHosting::wake();
                SharedHosting::drainAfterResponse();
            });
        } else {
            // Profil VPS: sweep outbox tiap menit hanya saat ada event baru (IdleGuard, ADR31).
            DB::afterCommit(fn () => IdleGuard::touch());
            if (self::urgent($type)) {
                DB::afterCommit(fn () => self::dispatchAfterResponse());
            }
        }

        return $event;
    }

    /** Alarm darurat tidak boleh menunggu sweep scheduler tiap menit (jeda 0–60 detik, uji 8 Nov). */
    public static function urgent(string $type): bool
    {
        return str_starts_with($type, 'darurat.') && (bool) config('rt05.outbox.urgent_immediate', true);
    }

    /**
     * Profil VPS: klaim + enqueue segera setelah response terkirim; worker antrean memproses dalam detik.
     * Gagal → event tetap pending dan sweep scheduler tiap menit tetap menjadi jalur cadangan.
     */
    public static function dispatchAfterResponse(): void
    {
        if (app()->bound('rt05.outbox.urgent_drain')) {
            return;
        }
        app()->instance('rt05.outbox.urgent_drain', true);
        app()->terminating(function (): void {
            try {
                OutboxDispatcher::dispatch(25);
            } catch (Throwable $e) {
                // Mutasi sudah commit; kegagalan jalur cepat tidak boleh mengubah response atau membuang event.
                Log::warning('outbox.urgent_dispatch_failed', ['exception' => $e::class]);
            }
        });
    }
}
