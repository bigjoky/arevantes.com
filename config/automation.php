<?php

return [
    // Explicit operator approval is required; this file never activates production.
    'approved' => [
        'push' => (bool) env('OPS_PUSH_APPROVED', false),
        'arevia' => (bool) env('OPS_AREVIA_APPROVED', false),
        'geocode' => (bool) env('OPS_GEOCODE_APPROVED', false),
    ],
    'max_age_seconds' => ['push' => 600, 'arevia' => 900, 'geocode' => 3600],
    'scheduler_profile' => env('OPS_SCHEDULER_PROFILE', 'disabled'),
];
