<?php

namespace App\Support;

use App\Http\Middleware\AssignRequestId;
use App\Models\Akun;
use App\Models\AuditLog;

/**
 * Jejak audit append-only (audit_logs). Dipanggil di dalam transaksi domain yang sama.
 * safe_diff hanya memuat field ringkas; tidak pernah password, token, atau form keluarga lengkap.
 */
final class Audit
{
    public static function record(?Akun $actor, string $action, string $entityType, ?string $entityId, array $safeDiff = []): void
    {
        AuditLog::create([
            'actor_id' => $actor?->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'safe_diff' => $safeDiff,
            'request_id' => app()->runningInConsole() && ! app()->runningUnitTests() ? null : AssignRequestId::current(),
        ]);
    }
}
