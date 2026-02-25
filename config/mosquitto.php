<?php

return [
    'auto_start' => filter_var(env('MOSQUITTO_AUTO_START', true), FILTER_VALIDATE_BOOL),
    'only_for_local_host' => filter_var(env('MOSQUITTO_ONLY_LOCAL', true), FILTER_VALIDATE_BOOL),
    'binary' => env('MOSQUITTO_BINARY', DIRECTORY_SEPARATOR === '\\'
        ? 'C:\\Program Files\\mosquitto\\mosquitto.exe'
        : 'mosquitto'),
    'config_path' => env('MOSQUITTO_CONFIG', DIRECTORY_SEPARATOR === '\\'
        ? 'C:\\Program Files\\mosquitto\\mosquitto.conf'
        : '/etc/mosquitto/mosquitto.conf'),
    'verbose' => filter_var(env('MOSQUITTO_VERBOSE', true), FILTER_VALIDATE_BOOL),
    'start_cooldown' => (int) env('MOSQUITTO_START_COOLDOWN', 20),
    'wait_seconds' => (int) env('MOSQUITTO_WAIT_SECONDS', 8),
];
