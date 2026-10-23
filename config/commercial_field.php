<?php

return [
    // Operational defaults; AREVANTES must approve these against its retention policy.
    'location_retention_days' => (int) env('COMMERCIAL_FIELD_LOCATION_RETENTION_DAYS', 30),
    'activity_retention_days' => (int) env('COMMERCIAL_FIELD_ACTIVITY_RETENTION_DAYS', 365),
];
