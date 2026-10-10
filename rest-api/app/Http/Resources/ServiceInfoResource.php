<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ServiceInfoResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this['name'],
            'api_version' => $this['api_version'],
        ];
    }
}
