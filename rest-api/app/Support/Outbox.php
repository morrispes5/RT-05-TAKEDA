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
        }

        return $event;
    }
}
