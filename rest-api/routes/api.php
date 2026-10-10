<?php

use App\Http\Controllers\ServiceInfoController;
use Illuminate\Support\Facades\Route;

/*
| API v1, prefix /api/v1 (bootstrap/app.php). Setiap endpoint baru wajib tercantum di
| docs/openapi.yaml dengan x-status: implemented; OpenApiContractTest memeriksa kesesuaiannya.
| Domain menambah file route per milestone, mis. routes/api/identity.php pada M06.
*/

Route::get('/', ServiceInfoController::class)->name('api.v1.info');
