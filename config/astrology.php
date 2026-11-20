<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Astrology Calculation Driver
    |--------------------------------------------------------------------------
    | Supported: "local", "remote"
    | - "local": Uses local PHP CLI FFI + Moshier native libraries directly.
    | - "remote": Delegates calculations to dedicated VPS calculation microservice over HTTP.
    */
    'driver' => env('ASTRO_CALCULATION_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Remote Calculation Service Configuration
    |--------------------------------------------------------------------------
    */
    'remote' => [
        'url' => env('ASTRO_CALCULATION_REMOTE_URL', env('ASTRO_CALCULATION_SERVICE_URL', 'http://127.0.0.1:8080')),
        'base_url' => env('ASTRO_CALCULATION_REMOTE_URL', env('ASTRO_CALCULATION_SERVICE_URL', 'http://127.0.0.1:8080')),
        'secret' => env('ASTRO_CALCULATION_HMAC_SECRET', ''),
        'connect_timeout' => (float) env('ASTRO_CALCULATION_CONNECT_TIMEOUT', 3.0),
        'timeout' => (float) env('ASTRO_CALCULATION_TIMEOUT', 10.0),
        'fallback_to_local' => (bool) env('ASTRO_CALCULATION_FALLBACK_TO_LOCAL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Practitioner Free Tier Daily Report Limit
    |--------------------------------------------------------------------------
    | Maximum free reports (Kundli, Panchang, Daily Insights) a practitioner
    | can generate per calendar day (IST) without exceeding fair-use quota.
    */
    'free_daily_report_limit' => (int) env('FREE_DAILY_REPORT_LIMIT', 20),
];
