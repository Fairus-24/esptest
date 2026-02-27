<?php

return [
    'auto_start' => filter_var(env('LARAVEL_HTTP_AUTO_START', true), FILTER_VALIDATE_BOOL),
    'host' => env('LARAVEL_HTTP_HOST', '0.0.0.0'),
    'port' => (int) env('LARAVEL_HTTP_PORT', 8000),
    'health_host' => env('LARAVEL_HTTP_HEALTH_HOST', '127.0.0.1'),
    'health_path' => env('LARAVEL_HTTP_HEALTH_PATH', '/up'),
    'php_binary' => env('LARAVEL_HTTP_PHP_BINARY', PHP_BINARY),
    'start_cooldown' => (int) env('LARAVEL_HTTP_START_COOLDOWN', 15),
    'wait_seconds' => (int) env('LARAVEL_HTTP_WAIT_SECONDS', 8),
    'ingest_key' => env('HTTP_INGEST_KEY', ''),
    'allow_ingest_without_key' => filter_var(env('HTTP_ALLOW_INGEST_WITHOUT_KEY', false), FILTER_VALIDATE_BOOL),
    'ingest_rate_limit_per_minute' => (int) env('HTTP_INGEST_RATE_LIMIT_PER_MINUTE', 240),
];
