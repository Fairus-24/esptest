<?php
// Test script untuk insert MQTT data
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->boot();

use App\Models\Eksperimen;

// Insert 5 test MQTT data
for ($i = 0; $i < 5; $i++) {
    Eksperimen::create([
        'device_id' => 1,
        'protokol' => 'MQTT',
        'suhu' => 25.0 + ($i * 0.5),
        'timestamp_esp' => now()->subMinutes($i),
        'timestamp_server' => now(),
        'latency_ms' => 45 + ($i * 5),
        'daya_mw' => 100 + ($i * 2),
    ]);
}

echo "✓ 5 MQTT test data created!\n";
