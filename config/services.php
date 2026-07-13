<?php

return [

    'geonames' => [
        'spain_postal_url' => env('GEONAMES_SPAIN_POSTAL_URL', 'https://download.geonames.org/export/zip/ES.zip'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'nominatim' => [
        'url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'ArevantesCRM/1.0 (contact@arevantes.com)'),
        'country_codes' => env('NOMINATIM_COUNTRY_CODES', 'es'),
    ],

    'deye' => [
        'url' => env('DEYE_API_URL', 'https://eu1-developer.deyecloud.com'),
        'app_id' => env('DEYE_APP_ID'),
        'app_secret' => env('DEYE_APP_SECRET'),
        'email' => env('DEYE_EMAIL'),
        'password' => env('DEYE_PASSWORD'),
        'company_id' => env('DEYE_COMPANY_ID'),
    ],

    'huawei' => [
        'url' => env('HUAWEI_FUSIONSOLAR_URL', 'https://eu5.fusionsolar.huawei.com'),
        'username' => env('HUAWEI_FUSIONSOLAR_USERNAME'),
        'system_code' => env('HUAWEI_FUSIONSOLAR_SYSTEM_CODE'),
    ],

    'apns' => [
        'key_id' => env('APNS_KEY_ID'),
        'team_id' => env('APNS_TEAM_ID'),
        'bundle_id' => env('APNS_BUNDLE_ID', 'com.arevantes.mobile'),
        'private_key' => env('APNS_PRIVATE_KEY'),
    ],

];
