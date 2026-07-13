<?php

return [
    'legal_name' => env('COMPANY_LEGAL_NAME', 'Arevantes'),
    'tax_id' => env('COMPANY_TAX_ID'),
    'address' => env('COMPANY_ADDRESS'),
    'postal_code' => env('COMPANY_POSTAL_CODE'),
    'city' => env('COMPANY_CITY'),
    'province' => env('COMPANY_PROVINCE'),
    'country' => env('COMPANY_COUNTRY', 'Spain'),
    'email' => env('COMPANY_EMAIL', 'contact@arevantes.com'),
    'phone' => env('COMPANY_PHONE'),
];
