<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => explode(',', env('FRONTEND_URLS', 'http://localhost:5173')),
    'allowed_headers' => ['*'],
    'exposed_headers' => ['X-API-Version', 'Sunset', 'X-API-Deprecation-Notice', 'Content-Disposition'],
    'max_age' => 3600,
    // La cookie HTTP-only del refresh token debe viajar entre el frontend y la
    // API (subdominios distintos). allowed_origins es una lista explícita, no '*'.
    'supports_credentials' => true,
];
