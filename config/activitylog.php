<?php

return [
    'default_log_name' => 'web',
    'subject_returns_soft_deleted_models' => true,
    'default_auth_driver' => null,
    'table_name' => 'activity_log',
    'database_connection' => env('DB_CONNECTION', 'mysql'),
    'performed_by' => [
        'enabled' => true,
        'morph_prefix' => 'causer',
    ],
    'activity_model' => \Spatie\Activitylog\Models\Activity::class,
    'subject_morph_prefix' => 'subject',
    'causer_morph_prefix'  => 'causer',
    'default' => [
        'log_ip_address' => true,
        'log_user_agent' => true,
    ],
    // Biar hanya simpan field berubah & auto cleanup:
    'log_only_dirty' => true,
    'submit_empty_logs' => false,
    'cleaning' => [
        'strategy' => \Spatie\Activitylog\CleaningStrategies\OlderThanDays::class,
        'settings' => ['days' => 90], // simpan 6 bulan
    ],
];
