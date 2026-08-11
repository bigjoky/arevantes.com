<?php

return [
    'quote_approval' => [
        'amount_threshold' => (float) env('CRM_QUOTE_APPROVAL_AMOUNT', 50000),
        'discount_threshold' => (float) env('CRM_QUOTE_APPROVAL_DISCOUNT', 15),
        'margin_threshold' => (float) env('CRM_QUOTE_APPROVAL_MARGIN', 20),
    ],
];
