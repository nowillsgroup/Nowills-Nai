<?php

return [
    'workspace' => env('NAI_WORKSPACE_NAME', 'Nowills Group'),
    'demo_mode' => (bool) env('NAI_DEMO_MODE', false),

    // Local preview convenience only: open the workspace without the login step.
    'preview_auto_demo' => (bool) env('NAI_PREVIEW_AUTO_DEMO', false),

    // Landing page for the local auto-demo preview: "dashboard" or "arena".
    // This is ignored unless preview_auto_demo, demo_mode, and APP_ENV=local are all enabled.
    'preview_landing' => env('NAI_PREVIEW_LANDING', 'dashboard'),

    /*
    | NAi is an orchestration interface only. These values describe the
    | upstream NAi connection; no model or inference code lives here.
    */
    'monitoring' => [
        'enabled' => (bool) env('NAI_MONITORING_ENABLED', false),
        'timeout' => (int) env('NAI_MONITORING_TIMEOUT', 3),
        'verify_ssl' => (bool) env('NAI_MONITORING_VERIFY_SSL', true),
        'retention_days' => (int) env('NAI_MONITORING_RETENTION_DAYS', 30),
    ],

    'arena' => [
        // Arena AI address framed by the NAi browser view. The frame connects
        // straight from the user's browser; NAi never handles its credentials.
        'portal_url' => env('ARENA_PORTAL_URL', 'https://arena.ai'),
        'base_url' => env('ARENA_BASE_URL', ''),
        'api_key' => env('ARENA_API_KEY', ''),
        'workspace_ref' => env('ARENA_WORKSPACE_REF', ''),
        'chat_path' => env('ARENA_CHAT_PATH', '/v1/chat'),
        'health_path' => env('ARENA_HEALTH_PATH', '/v1/health'),
        'timeout' => (int) env('ARENA_TIMEOUT', 45),
    ],
];
