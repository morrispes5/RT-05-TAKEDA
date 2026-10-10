<?php

namespace App\Support;

use App\Models\OutboxEvent;

/**
 * Transactional outbox: event ditulis dalam transaksi domain; dispatcher (rt05:outbox-dispatch)
 * memprosesnya setelah commit. Payload hanya ID/jenis, tanpa PII atau identitas pelapor anonim.
 */
final class Outbox
{
    public static function record(string $type, string $aggregateType, ?string $aggregateId, array $payload = []): OutboxEvent
    {
        return OutboxEvent::create([
            'type' => $type,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'safe_payload' => $payload,
        ]);
    }
}
