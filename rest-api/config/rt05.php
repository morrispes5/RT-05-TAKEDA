<?php

/*
| Pengaturan operasional RT05 TAKEDA (docs/ASYNC_JOBS.md). Hanya angka/nama, tanpa credential.
*/

return [

    // Opt-in; VPS existing tidak berubah.
    'deployment_profile' => env('RT05_DEPLOYMENT_PROFILE', 'vps'),

    'outbox' => [
        // null = QUEUE_CONNECTION. Event dikirim sebagai ID saja ke antrean notifications.
        'queue_connection' => env('RT05_OUTBOX_QUEUE_CONNECTION'),
        'queue' => env('RT05_OUTBOX_QUEUE', 'notifications'),
        'batch' => (int) env('RT05_OUTBOX_BATCH', 100),
        // Lease klaim dispatcher (klaim → enqueue). Dispatcher mati di tengah → event diklaim ulang.
        'lease_seconds' => (int) env('RT05_OUTBOX_LEASE_SECONDS', 120),
        // published tanpa completed lebih lama dari ini dianggap hilang dari antrean → replay.
        // Default > jendela retry job (5 percobaan, backoff 10/30/120/300, timeout 60).
        'stale_seconds' => (int) env('RT05_OUTBOX_STALE_SECONDS', 900),
    ],

    // M10 push Android. Tanpa kredensial → inbox saja (push_status dilewati). Kredensial service
    // account FCM hanya dari env runtime: path berkas di luar web root atau JSON base64.
    'push' => [
        'driver' => env('RT05_PUSH_DRIVER', 'fcm'),
        'fcm' => [
            'project_id' => env('FCM_PROJECT_ID'),
            'credentials_path' => env('FCM_CREDENTIALS_PATH'),
            'credentials_base64' => env('FCM_CREDENTIALS_BASE64'),
            'timeout' => (int) env('FCM_TIMEOUT', 10),
            'concurrency' => (int) env('FCM_CONCURRENCY', 20),
        ],
        // Penerima per putaran job; shared hosting memakai batas waktu kooperatif yang sama.
        'batch' => (int) env('RT05_PUSH_BATCH', 50),
        'run_seconds' => (int) env('RT05_PUSH_RUN_SECONDS', 15),
        // Notifikasi lebih tua dari ini (downtime panjang) tidak lagi di-push; inbox tetap.
        'max_age_hours' => (int) env('RT05_PUSH_MAX_AGE_HOURS', 24),
    ],

    // M06 identitas (docs/API.md §Autentikasi, SECURITY.md §Identitas).
    'auth' => [
        // Nama cookie CSRF API; berbeda dari XSRF-TOKEN milik website pada origin yang sama.
        'csrf_cookie' => env('RT05_XSRF_COOKIE', 'rt05_api_xsrf'),
        // Kegagalan token registrasi per IP per jam (selain throttle registrasi 5/10 menit).
        'token_failures_per_hour' => (int) env('RT05_TOKEN_FAILURES_PER_HOUR', 10),
    ],

    'heartbeat' => [
        // Store cache untuk heartbeat; redis → instance queue (noeviction), selain itu store default.
        'store' => env('RT05_HEARTBEAT_STORE', env('CACHE_STORE', 'file') === 'redis' ? 'coordination' : null),
        // null = rumus Heartbeat::maxAge(). Jangan mengunci shared ke 300 detik: cron-nya 15 menit.
        'max_age_seconds' => ($heartbeatMaxAge = env('RT05_HEARTBEAT_MAX_AGE')) !== null && $heartbeatMaxAge !== ''
            ? (int) $heartbeatMaxAge
            : null,
        // Penulisan dari loop worker dibatasi agar tidak menulis Redis setiap iterasi.
        'write_interval_seconds' => (int) env('RT05_HEARTBEAT_WRITE_INTERVAL', 30),
        'components' => env('RT05_DEPLOYMENT_PROFILE', 'vps') === 'shared'
            ? ['shared-tick']
            : ['worker-default', 'worker-media', 'worker-exports', 'scheduler'],
    ],

];
