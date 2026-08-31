<?php

return [
    'slow_request_ms' => (float) env('PERFORMANCE_SLOW_REQUEST_MS', 750),
    'large_response_bytes' => (int) env('PERFORMANCE_LARGE_RESPONSE_BYTES', 500_000),
    'web_vitals_sample_rate' => min(1, max(0, (float) env('PERFORMANCE_WEB_VITALS_SAMPLE_RATE', 0.1))),
    'web_vitals_retention_days' => max(1, (int) env('PERFORMANCE_WEB_VITALS_RETENTION_DAYS', 90)),
    'release' => env('LARAVEL_CLOUD_DEPLOYMENT_ID', env('APP_VERSION')),
];
