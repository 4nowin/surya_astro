<?php

return [
    'default' => env('GEOCODING_PROVIDER', 'local'),

    'providers' => [
        'local' => [
            'class' => \App\Services\Geo\LocalGazetteerGeocodingProvider::class,
        ],
    ],
];
