<?php

return [

    // M14: API konten publik RT05 (jaringan internal/same-origin). Kosong = JSON baked (preview statis).
    'rt05_content' => [
        'api' => env('RT05_CONTENT_API'),
        'timeout' => (int) env('RT05_CONTENT_API_TIMEOUT', 3),
        'fresh_seconds' => (int) env('RT05_CONTENT_FRESH_SECONDS', 60),
    ],

    // M16: hanya production yang boleh diindeks mesin pencari; staging/preview noindex. APK_META: path/URL versi.json.
    // APK_PAGE: hanya pratinjau statis (tanpa berkas). Staging/production yang punya versi.json tetap mengunduh langsung.
    'rt05_site' => [
        'indexing' => filter_var(env('RT05_SEARCH_INDEXING', false), FILTER_VALIDATE_BOOL),
        'apk_meta' => env('RT05_APK_META'),
        'apk_page' => env('RT05_APK_PAGE'),
    ],

    // ADR41: menu Air Bersih KSM (aplikasi terpisah, login sendiri) di Area Pengurus, hanya untuk pengurus.
    'air_bersih_ksm' => [
        'url' => env('AIR_BERSIH_KSM_URL', 'https://airbersih-ksm-claude.vercel.app'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
