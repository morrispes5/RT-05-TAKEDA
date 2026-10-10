<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServiceInfoResource;

class ServiceInfoController extends Controller
{
    public function __invoke(): ServiceInfoResource
    {
        return new ServiceInfoResource(['name' => 'RT05 TAKEDA API', 'api_version' => 'v1']);
    }
}
