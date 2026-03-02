<?php

return [
    'google_allowed_email' => strtolower(trim((string) env('ADMIN_GOOGLE_ALLOWED_EMAIL', 'mufaza2408@gmail.com'))),
    'session_key' => env('ADMIN_SESSION_KEY', 'admin_config_authenticated'),
    'session_ttl_minutes' => (int) env('ADMIN_SESSION_TTL_MINUTES', 240),

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
