<?php

return [
    'mirror_disk' => env('BACKUP_MIRROR_DISK'),
    'retention' => (int) env('BACKUP_RETENTION_DAYS', 30),
    'postgres' => [
        'enabled' => (bool) env('POSTGRES_BACKUP_ENABLED', false),
        'source_connection' => env('POSTGRES_BACKUP_SOURCE_CONNECTION', env('DB_CONNECTION', 'pgsql')),
        'disk' => env('POSTGRES_BACKUP_DISK', env('BACKUP_MIRROR_DISK')),
        'prefix' => trim(env('POSTGRES_BACKUP_PREFIX', 'database-backups/postgres'), '/'),
        'encryption_key' => env('POSTGRES_BACKUP_ENCRYPTION_KEY'),
        'timeout_seconds' => (int) env('POSTGRES_BACKUP_TIMEOUT_SECONDS', 1800),
        'pg_dump_binary' => env('PG_DUMP_BINARY', 'pg_dump'),
        'pg_restore_binary' => env('PG_RESTORE_BINARY', 'pg_restore'),
        'psql_binary' => env('PSQL_BINARY', 'psql'),
        'max_age_hours' => (int) env('POSTGRES_BACKUP_MAX_AGE_HOURS', 36),
        'restore_max_age_days' => (int) env('POSTGRES_RESTORE_MAX_AGE_DAYS', 8),
        'restore_enabled' => (bool) env('POSTGRES_RESTORE_VERIFICATION_ENABLED', false),
        'restore_connection' => env('POSTGRES_RESTORE_CONNECTION', 'backup_restore'),
        'allow_same_host_restore' => (bool) env('POSTGRES_RESTORE_ALLOW_SAME_HOST', false),
        'restore_database_pattern' => env('POSTGRES_RESTORE_DATABASE_PATTERN', '/^[a-z][a-z0-9_]*_restore_verify_test$/D'),
    ],
];
