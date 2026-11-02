<?php

return [
    'enabled' => env('CONTACT_MAIL_ENABLED', false),
    'transport' => env('CONTACT_MAIL_TRANSPORT', 'smtp'),
    'from' => env('CONTACT_MAIL_FROM', 'info@arevantes.com'),
    'graph' => [
        'tenant_id' => env('MS_GRAPH_TENANT_ID'),
        'client_id' => env('MS_GRAPH_CLIENT_ID'),
        'certificate_path' => env('MS_GRAPH_CERTIFICATE_PATH'),
        'private_key_path' => env('MS_GRAPH_PRIVATE_KEY_PATH'),
        // Secret is read at runtime and never written to Laravel's config cache.
        'private_key_passphrase' => null,
        'sha1_thumbprint' => env('MS_GRAPH_CERTIFICATE_SHA1', '33ABFD30194FD2E890C0D87E8A5A21372CBB3769'),
    ],
    'reply_to' => env('CONTACT_MAIL_REPLY_TO'),
];
