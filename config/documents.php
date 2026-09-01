<?php

declare(strict_types=1);

return [
    'disk' => env('DOCUMENT_DISK', 'documents'),
    'directory' => env('DOCUMENT_DIRECTORY', 'uploads'),
    'max_size_mb' => (int) env('DOCUMENT_MAX_SIZE_MB', 10),
    'retention_hours' => (int) env('DOCUMENT_RETENTION_HOURS', 24),
    'notification_email' => env('DOCUMENT_NOTIFICATION_EMAIL'),
    'expiration_batch_size' => (int) env('DOCUMENT_EXPIRATION_BATCH_SIZE', 100),
];
