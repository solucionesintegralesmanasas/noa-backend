<?php

return [
    'current_version' => env('API_VERSION', 'v1'),
    'versions' => [
        'v1' => [
            'status' => 'stable',
            'sunset_date' => null,
            'documentation' => env('APP_URL').'/api/documentation',
        ],
    ],
    'rate_limiting' => [
        'api' => env('API_RATE_LIMIT', 60),
    ],
];
