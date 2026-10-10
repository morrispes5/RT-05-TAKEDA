<?php

namespace App\Http\Resources;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Basis Resource API v1: {"data": ..., "meta": {"request_id": ...}}.
 * Resource domain mewarisi kelas ini dan menyerialisasi field allowlist secara eksplisit;
 * jangan mengembalikan model mentah (mis. toArray() model) agar kolom privat tidak bocor.
 */
abstract class ApiResource extends JsonResource
{
    public function with(Request $request): array
    {
        return ['meta' => ['request_id' => AssignRequestId::current($request)]];
    }
}
