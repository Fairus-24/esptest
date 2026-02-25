<?php

return [
    'host' => env('MQTT_HOST', '127.0.0.1'),
    'fallback_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MQTT_FALLBACK_HOSTS', 'localhost'))
    ))),
    'port' => (int) env('MQTT_PORT', 1883),
    'topic' => env('MQTT_TOPIC', 'iot/esp32/suhu'),
    'client_id' => env('MQTT_CLIENT_ID', 'laravel-mqtt-worker'),
    'username' => env('MQTT_USERNAME', 'esp32'),
    'password' => env('MQTT_PASSWORD', 'esp32'),
    'qos' => (int) env('MQTT_QOS', 0),
    'connect_timeout' => (int) env('MQTT_CONNECT_TIMEOUT', 5),
    'socket_timeout' => (int) env('MQTT_SOCKET_TIMEOUT', 5),
    'keep_alive' => (int) env('MQTT_KEEP_ALIVE', 30),
    'reconnect_delay' => (int) env('MQTT_RECONNECT_DELAY', 3),
    'auto_start' => filter_var(env('MQTT_AUTO_START', true), FILTER_VALIDATE_BOOL),
    'auto_start_cooldown' => (int) env('MQTT_AUTO_START_COOLDOWN', 20),
];
