<?php

return [
    'uploads' => [
        'max_kilobytes' => (int) env('UPLOAD_MAX_KB', 15360),
        'clamav_socket' => env('CLAMAV_SOCKET'),
    ],
    'retention' => [
        'audit_years' => (int) env('AUDIT_RETENTION_YEARS', 7),
        'security_event_years' => (int) env('SECURITY_EVENT_RETENTION_YEARS', 2),
        'health_days' => (int) env('HEALTH_RETENTION_DAYS', 90),
        'mobile_operation_days' => (int) env('MOBILE_OPERATION_RETENTION_DAYS', 30),
    ],
    'access_review_months' => (int) env('ACCESS_REVIEW_MONTHS', 3),
    'mobile' => [
        'token_expiration_minutes' => (int) env('MOBILE_TOKEN_EXPIRATION_MINUTES', 43200),
    ],
];
