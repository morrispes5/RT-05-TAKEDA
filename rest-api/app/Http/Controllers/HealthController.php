<?php

namespace App\Http\Controllers;

use App\Support\Database\TlsPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Health minimal untuk proxy/orchestrator (docs/DEPLOYMENT.md):
 * - live: proses HTTP sehat, tidak menyentuh database.
 * - ready: dependency tersedia dengan timeout; body tidak memuat hostname/credential/error mentah.
 * Redis/worker ditambahkan pada M03.
 */
class HealthController extends Controller
{
    private const NO_STORE = ['Cache-Control' => 'no-store'];

    public function live(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok'], 200, self::NO_STORE);
    }

    public function ready(): JsonResponse
    {
        $checks = ['database' => $this->database()];
        $ready = ! in_array(false, array_map(fn ($state) => $state === 'ok', $checks), true);

        return new JsonResponse(
            ['status' => $ready ? 'ready' : 'not_ready', 'checks' => $checks],
            $ready ? 200 : 503,
            self::NO_STORE,
        );
    }

    private function database(): string
    {
        if (TlsPolicy::violations() !== []) {
            Log::critical('health.ready.database_tls_misconfigured', ['violations' => TlsPolicy::violations()]);

            return 'misconfigured';
        }

        try {
            DB::connection('pgsql')->select('select 1');

            return 'ok';
        } catch (Throwable $e) {
            Log::warning('health.ready.database_unavailable', ['exception' => $e::class]);

            return 'unavailable';
        }
    }
}
