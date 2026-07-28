<?php

return [
    'build' => [
        'version' => env('APP_BUILD_VERSION', 'local'),
        'commit' => env('APP_BUILD_COMMIT', 'unknown'),
    ],
    'health' => [
        'heartbeat_max_age_seconds' => (int) env('PLATFORM_HEARTBEAT_MAX_AGE', 90),
    ],
    'actor_adapter' => env('PLATFORM_ACTOR_ADAPTER', 'laravel-auth'),
    'completion_adapter' => env('PLATFORM_COMPLETION_ADAPTER', 'structured-log'),
];
