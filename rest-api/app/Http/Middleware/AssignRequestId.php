<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setiap request mendapat UUID yang muncul di header X-Request-Id, meta/error body, dan log.
 * Nilai dari client hanya dipakai bila berupa UUID valid agar log tidak dapat disisipi teks bebas.
 */
class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->headers->get(self::HEADER, '');
        $id = Str::isUuid($incoming) ? strtolower($incoming) : (string) Str::uuid();

        $request->attributes->set('request_id', $id);
        Context::add('request_id', $id);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }

    public static function current(?Request $request = null): string
    {
        $request ??= request();
        $id = $request->attributes->get('request_id');

        if (! is_string($id)) {
            // Exception sebelum middleware berjalan (mis. route tidak ditemukan pada tahap awal).
            $id = (string) Str::uuid();
            $request->attributes->set('request_id', $id);
            Context::add('request_id', $id);
        }

        return $id;
    }
}
