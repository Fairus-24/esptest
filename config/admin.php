<?php

return [
    'google_allowed_email' => strtolower(trim((string) env('ADMIN_GOOGLE_ALLOWED_EMAIL', 'mufaza2408@gmail.com'))),
    'session_key' => env('ADMIN_SESSION_KEY', 'admin_config_authenticated'),
    'session_ttl_minutes' => (int) env('ADMIN_SESSION_TTL_MINUTES', 240),
    'platformio_command' => trim((string) env('ADMIN_PLATFORMIO_COMMAND', 'pio')),
    'platformio_workdir' => trim((string) env('ADMIN_PLATFORMIO_WORKDIR', base_path('ESP32_Firmware'))),
    'platformio_env' => trim((string) env('ADMIN_PLATFORMIO_ENV', '')),
    'platformio_timeout_seconds' => (int) env('ADMIN_PLATFORMIO_TIMEOUT_SECONDS', 900),
    'firmware_cli_output_limit' => (int) env('ADMIN_FIRMWARE_CLI_OUTPUT_LIMIT', 60000),

    'board_options' => [
        'esp32doit-devkit-v1',
        'esp32dev',
        'nodemcu-32s',
        'lolin32',
        'esp32-s3-devkitc-1',
    ],

    'dht_models' => [
        'DHT11',
        'DHT22',
        'AM2302',
        'AUTO_DETECT',
    ],
];
