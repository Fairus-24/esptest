<?php
// Publish lebih banyak MQTT test data dengan variasi
require 'vendor/autoload.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

try {
    $connectionSettings = (new ConnectionSettings())
        ->setUseTls(false)
        ->setConnectTimeout(3)
        ->setKeepAliveInterval(60);

    $client = new MqttClient('127.0.0.1', 1883, 'mqtt-publisher-' . time());
    $client->connect($connectionSettings);
    
    echo "✓ Connected to MQTT broker\n";
    echo "Publishing 10 MQTT messages with variation...\n\n";
    
    $baseTime = time();
    $suhuValues = [23.5, 24.1, 24.8, 25.3, 25.9, 26.2, 26.7, 27.1, 27.5, 28.0];
    $dayaValues = [95, 98, 102, 105, 108, 110, 112, 115, 118, 120];
    
    for ($i = 0; $i < 10; $i++) {
        $payload = json_encode([
            'device_id' => 1,
            'suhu' => $suhuValues[$i],
            'timestamp_esp' => $baseTime - ($i * 30),  // 30 detik intervals
            'daya' => $dayaValues[$i],
        ]);
        
        $client->publish('iot/esp32/suhu', $payload);
        printf("[%2d] Suhu: %.1f°C | Daya: %dmW | Payload: %s\n", 
            $i, $suhuValues[$i], $dayaValues[$i], $payload);
        sleep(1);
    }
    
    $client->disconnect();
    echo "\n✓ Successfully published 10 MQTT messages!\n";
    echo "Check dashboard at: http://localhost:8000\n";
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
