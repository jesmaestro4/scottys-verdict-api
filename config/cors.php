<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'docs', 'api/documentation', 'openapi.yaml'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_filter([
        env('FRONTEND_URL'),
        'https://www.scottysays.autos',
        'https://scottysays.autos',
        'https://api.scottysays.autos',
        'http://localhost:3000',
        'http://localhost:4173',
        'http://localhost:5173',
        'http://localhost:8080',
        'http://127.0.0.1:4173',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:8080',
        'http://127.0.0.1:8000',
    ]),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
