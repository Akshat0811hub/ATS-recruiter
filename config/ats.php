<?php

return [
    'currency' => env('APP_CURRENCY', 'INR'),
    'require_super_admin_2fa' => filter_var(env('ATS_REQUIRE_SUPER_ADMIN_2FA', true), FILTER_VALIDATE_BOOLEAN),
    'max_storage_bytes' => (int) env('ATS_MAX_STORAGE_BYTES', 19510000000),
    'storage_warn_70' => (int) env('ATS_STORAGE_WARN_70', 70),
    'storage_warn_85' => (int) env('ATS_STORAGE_WARN_85', 85),
    'storage_warn_95' => (int) env('ATS_STORAGE_WARN_95', 95),
    'max_upload_kb' => (int) env('ATS_MAX_UPLOAD_KB', 10240),
    'backup_enabled' => filter_var(env('ATS_BACKUP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'backup_external_disk' => env('ATS_BACKUP_EXTERNAL_DISK'),
    'backup_retention_days' => (int) env('ATS_BACKUP_RETENTION_DAYS', 30),
    'initial_admin_email' => env('ATS_INITIAL_ADMIN_EMAIL', 'admin@orbitouch-hr.com'),
    'initial_admin_password' => env('ATS_INITIAL_ADMIN_PASSWORD', 'OrbiTouch@2026!'),
];
