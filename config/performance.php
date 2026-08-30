<?php

return [
    'slow_request_ms' => (float) env('PERFORMANCE_SLOW_REQUEST_MS', 750),
    'large_response_bytes' => (int) env('PERFORMANCE_LARGE_RESPONSE_BYTES', 500_000),
];
