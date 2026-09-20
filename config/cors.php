<?php

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('ATTENDPRO_CORS_ALLOWED_ORIGINS', 'http://127.0.0.1:8000,http://localhost:8000')),
)));

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],
    'exposed_headers' => ['X-Request-Id'],
    'max_age' => 3600,
    'supports_credentials' => false,
];
