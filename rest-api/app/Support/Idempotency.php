<?php

namespace App\Support;

use App\Exceptions\DomainConflict;
use App\Models\Akun;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Idempotency-Key untuk mutation keuangan (docs/API.md, DATABASE.md):
 * - key sama + payload sama  → respons asal diputar ulang, tidak ada posting kedua;
 * - key sama + payload beda  → 409 IDEMPOTENCY_KEY_REUSED;
 * - key sedang diproses      → 409 IDEMPOTENCY_IN_PROGRESS.
 * Reservasi, mutasi, dan penyimpanan respons terjadi dalam satu transaksi Postgres.
 */
final class Idempotency
{
    /** @param Closure(): JsonResponse $mutation */
    public static function run(Request $request, Akun $actor, string $scope, array $payload, Closure $mutation): JsonResponse
    {
        $key = (string) $request->header('Idempotency-Key', '');
        if (! Str::isUuid($key)) {
            throw ValidationException::withMessages(['idempotency_key' => ['Header Idempotency-Key wajib berupa UUID.']]);
        }

        ksort($payload);
        $hash = hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));

        $existing = DB::table('idempotency_requests')
            ->where(['actor_id' => $actor->id, 'route_scope' => $scope, 'key' => strtolower($key)])
            ->first();
        if ($existing) {
            return self::replay($existing, $hash);
        }

        try {
            return DB::transaction(function () use ($actor, $scope, $key, $hash, $mutation) {
                $id = (string) Str::uuid();
                DB::table('idempotency_requests')->insert([
                    'id' => $id,
                    'actor_id' => $actor->id,
                    'route_scope' => $scope,
                    'key' => strtolower($key),
                    'request_hash' => $hash,
                    'state' => 'processing',
                ]);

                $response = $mutation();

                DB::table('idempotency_requests')->where('id', $id)->update([
                    'state' => 'completed',
                    'response_status' => $response->getStatusCode(),
                    'response_body' => $response->getContent(),
                    'completed_at' => now(),
                ]);

                return $response;
            });
        } catch (UniqueConstraintViolationException $e) {
            // Request kembar bersamaan: yang kalah membaca hasil pemenang setelah commit.
            $existing = DB::table('idempotency_requests')
                ->where(['actor_id' => $actor->id, 'route_scope' => $scope, 'key' => strtolower($key)])
                ->first();
            if (! $existing) {
                throw $e;
            }

            return self::replay($existing, $hash);
        }
    }

    private static function replay(object $existing, string $hash): JsonResponse
    {
        if (! hash_equals($existing->request_hash, $hash)) {
            throw new DomainConflict('IDEMPOTENCY_KEY_REUSED', 'Idempotency-Key sudah dipakai untuk transaksi lain.');
        }
        if ($existing->state !== 'completed') {
            throw new DomainConflict('IDEMPOTENCY_IN_PROGRESS', 'Transaksi dengan kunci ini sedang diproses.');
        }

        return (new JsonResponse(json_decode($existing->response_body, true), $existing->response_status))
            ->header('Idempotent-Replayed', 'true');
    }
}
