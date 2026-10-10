<?php

use App\Models\Akun;

return [

    'defaults' => [
        'guard' => 'sanctum',
        'passwords' => 'akun',
    ],

    'guards' => [
        // Sesi web CMS (M14). Mobile memakai guard sanctum (bearer per perangkat).
        'web' => [
            'driver' => 'session',
            'provider' => 'akun',
        ],
    ],

    'providers' => [
        'akun' => [
            'driver' => 'eloquent',
            'model' => Akun::class,
        ],
    ],

    'passwords' => [
        'akun' => [
            'provider' => 'akun',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,

];
