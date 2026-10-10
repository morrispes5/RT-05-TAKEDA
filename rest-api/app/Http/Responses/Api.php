<?php

namespace App\Http\Responses;

use App\Http\Middleware\AssignRequestId;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Envelope sukses API v1: {"data": ..., "meta": {"request_id", ...}}. */
final class Api
{
    public static function data(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        return new JsonResponse(['data' => $data, 'meta' => ['request_id' => AssignRequestId::current()] + $meta], $status);
    }

    /** Pagination: default 20, maksimal 100 (docs/API.md). */
    public static function paginate(Request $request, Builder $query, Closure $present): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        /** @var LengthAwarePaginator $page */
        $page = $query->paginate($perPage)->withQueryString();

        return new JsonResponse([
            'data' => $page->getCollection()->map($present)->values(),
            'meta' => [
                'request_id' => AssignRequestId::current(),
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
            'links' => ['next' => $page->nextPageUrl(), 'prev' => $page->previousPageUrl()],
        ]);
    }

    public static function noContent(): JsonResponse
    {
        return new JsonResponse(null, 204);
    }
}
