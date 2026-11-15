<?php

return [
    // Controlled opt-in only. Do not enable before the documentary preview is ready.
    'grouped_expense_calculation' => env('FISCAL_GROUPED_EXPENSE_CALCULATION', false),
    'historical_invoice_2026' => env('FISCAL_HISTORICAL_INVOICE_2026', false),
];
