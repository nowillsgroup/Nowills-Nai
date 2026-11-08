<?php

return [
    'workspace' => env('NAI_WORKSPACE_NAME', 'Nowills Group'),
    'demo_mode' => (bool) env('NAI_DEMO_MODE', false),

    /*
    | NAi is an orchestration interface only. These values describe the
    | upstream Arena AI connection; no model or inference code lives here.
    */
    'monitoring' => [
        'enabled' => (bool) env('NAI_MONITORING_ENABLED', false),
        'timeout' => (int) env('NAI_MONITORING_TIMEOUT', 3),
        'verify_ssl' => (bool) env('NAI_MONITORING_VERIFY_SSL', true),
        'retention_days' => (int) env('NAI_MONITORING_RETENTION_DAYS', 30),
    ],

    'arena' => [
        'base_url' => env('ARENA_BASE_URL', ''),
        'api_key' => env('ARENA_API_KEY', ''),
        'workspace_ref' => env('ARENA_WORKSPACE_REF', ''),
        'chat_path' => env('ARENA_CHAT_PATH', '/v1/chat'),
        'health_path' => env('ARENA_HEALTH_PATH', '/v1/health'),
        'timeout' => (int) env('ARENA_TIMEOUT', 45),
    ],
];
