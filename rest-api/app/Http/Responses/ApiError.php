<?php

namespace App\Http\Responses;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Bentuk error tunggal API v1 (docs/API.md):
 * {"error": {"code", "message", "fields"?, "request_id"}}
 * Tidak pernah memuat stack trace, nama host, SQL, atau pesan exception internal.
 */
final class ApiError
{
    /** Kode per status untuk HttpException umum; pesan dalam Bahasa Indonesia. */
    private const BY_STATUS = [
        400 => ['BAD_REQUEST', 'Permintaan tidak dapat diproses.'],
        401 => ['UNAUTHENTICATED', 'Silakan masuk terlebih dahulu.'],
        403 => ['FORBIDDEN', 'Akses ditolak.'],
        404 => ['NOT_FOUND', 'Data tidak ditemukan.'],
        405 => ['METHOD_NOT_ALLOWED', 'Metode tidak didukung untuk alamat ini.'],
        409 => ['CONFLICT', 'Data telah berubah. Muat ulang lalu coba lagi.'],
        413 => ['PAYLOAD_TOO_LARGE', 'Berkas terlalu besar.'],
        415 => ['UNSUPPORTED_MEDIA_TYPE', 'Jenis berkas tidak didukung.'],
        419 => ['CSRF_EXPIRED', 'Sesi formulir kedaluwarsa. Muat ulang halaman.'],
        422 => ['VALIDATION_FAILED', 'Periksa isian.'],
        429 => ['TOO_MANY_REQUESTS', 'Terlalu banyak percobaan. Coba lagi nanti.'],
        503 => ['SERVICE_UNAVAILABLE', 'Layanan sementara tidak tersedia.'],
    ];

    public static function make(int $status, ?string $code = null, ?string $message = null, ?array $fields = null, array $headers = []): JsonResponse
    {
        [$defaultCode, $defaultMessage] = self::BY_STATUS[$status] ?? ['INTERNAL_ERROR', 'Terjadi kesalahan pada server.'];

        $error = [
            'code' => $code ?? $defaultCode,
            'message' => $message ?? $defaultMessage,
        ];
        if ($fields !== null) {
            $error['fields'] = $fields;
        }
        $error['request_id'] = AssignRequestId::current();

        return new JsonResponse(['error' => $error], $status, $headers);
    }

    public static function fromThrowable(Throwable $e, Request $request): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => self::make(422, fields: $e->errors()),
            $e instanceof AuthenticationException => self::make(401),
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => self::make(403),
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => self::make(404),
            $e instanceof MethodNotAllowedHttpException => self::make(405, headers: $e->getHeaders()),
            $e instanceof TokenMismatchException => self::make(419),
            $e instanceof ThrottleRequestsException => self::make(429, headers: $e->getHeaders()),
            $e instanceof HttpExceptionInterface => self::make($e->getStatusCode(), headers: $e->getHeaders()),
            default => self::make(500),
        };
    }
}
