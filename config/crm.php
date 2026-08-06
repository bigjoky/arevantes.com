<?php

return [
    'stale_after_days' => (int) env('CRM_STALE_AFTER_DAYS', 14),
    'quote_approval' => [
        'amount_threshold' => (float) env('CRM_QUOTE_APPROVAL_AMOUNT', 50000),
        'discount_threshold' => (float) env('CRM_QUOTE_APPROVAL_DISCOUNT', 15),
        'margin_threshold' => (float) env('CRM_QUOTE_APPROVAL_MARGIN', 20),
    ],

    'scoring' => [
        'customer_contactable' => 20,
        'source_identified' => 10,
        'owner_assigned' => 15,
        'expected_close_set' => 15,
        'next_action_scheduled' => 20,
        'estimated_value_set' => 10,
        'advanced_stage' => 10,
    ],
];
