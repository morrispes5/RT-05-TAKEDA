<?php

return [
    // Release gate: enabling the UI in the database alone cannot enable this deployment.
    'enabled' => env('RT05_PANIC_ENABLED', false),
    'audience' => env('RT05_PANIC_AUDIENCE', 'restricted'),
    'recipient_ids' => array_values(array_filter(explode(',', (string) env('RT05_PANIC_RECIPIENT_IDS', '')))),
    'account_hourly_limit' => 3,
    'global_minute_limit' => 10,
    'alarm_age_minutes' => 10,
];
