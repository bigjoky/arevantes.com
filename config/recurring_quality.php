<?php

return [
    'review_due_weekdays' => 3,

    'tolerances' => [
        'default' => [
            'duration_percent' => 20,
            'travel_percent' => 25,
            'materials_percent' => 25,
            'reopened_count' => 0,
        ],
        'solar' => [
            'duration_percent' => 20,
            'travel_percent' => 25,
            'materials_percent' => 20,
            'reopened_count' => 0,
        ],
        'security' => [
            'duration_percent' => 15,
            'travel_percent' => 25,
            'materials_percent' => 20,
            'reopened_count' => 0,
        ],
        'electrical' => [
            'duration_percent' => 20,
            'travel_percent' => 25,
            'materials_percent' => 20,
            'reopened_count' => 0,
        ],
    ],
];
