<?php

return [
    'paths' => ['api/mobile/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env(
        'MOBILE_ALLOWED_ORIGINS',
        'capacitor://localhost,https://localhost,http://localhost:5173',
    ))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Idempotency-Key'],
    'exposed_headers' => ['Idempotency-In-Progress', 'Idempotency-Replayed', 'Retry-After', 'X-Correlation-ID'],
    'max_age' => 600,
    'supports_credentials' => false,
];
