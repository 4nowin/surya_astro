<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Calculation Engine
    |--------------------------------------------------------------------------
    |
    | Uses JPL Moshier Ephemeris via the package-native FFI wrapper.
    | Default mode is moshier as established in Mini-phase 1.
    |
    */
    'engine' => env('PANCHANG_ENGINE', 'jme'),

    'jme_settings' => [
        'mode' => env('PANCHANG_JME_MODE', 'moshier'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Native Ephemeris Configuration
    |--------------------------------------------------------------------------
    */
    'library_path' => env('JME_LIBRARY_PATH', null),
    'ephe_path' => env('PANCHANG_EPHE_PATH', base_path('packages/astrology/panchang-core/data/')),
    'skip_festivals' => true,

    /*
    |--------------------------------------------------------------------------
    | Default Calculation Options
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'locale' => 'en',
        'calendar_type' => 'amanta',
        'measurement_system' => 'indian_metric',
        'date_time_format' => 'indian_12h',
        'time_notation' => '12h',
        'coordinate_format' => 'decimal',
        'angle_unit' => 'degree',
        'duration_format' => 'mixed',
    ],
];
