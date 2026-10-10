<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return [

    // Domain CMS stateful (cookie) diisi saat M14; mobile memakai bearer token.
    'stateful' => array_filter(explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', ''))),

    'guard' => ['web'],

    // Rute /sanctum/csrf-cookie diaktifkan bersama CMS web (M14).
    'routes' => false,

    // Token mobile berlaku 30 hari (docs/SECURITY.md).
    'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION_MINUTES', 60 * 24 * 30),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'rt05_'),

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],

];
