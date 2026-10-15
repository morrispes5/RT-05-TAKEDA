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
        }

        return $event;
    }
}
