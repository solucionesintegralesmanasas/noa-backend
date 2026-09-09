<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => explode(',', env('FRONTEND_URLS', 'http://localhost:5173')),
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-API-Version', 'Sunset', 'X-API-Deprecation-Notice'],
    'max_age' => 3600,
    'supports_credentials' => false, // Usar Bearer tokens
];
