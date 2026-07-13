<?php

return [
    'mirror_disk' => env('BACKUP_MIRROR_DISK'),
    'retention' => (int) env('BACKUP_RETENTION_DAYS', 30),
];
